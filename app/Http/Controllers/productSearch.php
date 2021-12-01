<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\mongodb\projects_listing;
use MongoDB\BSON\Regex;

class productSearch extends Controller
{
  private function MongoDateOnly($dateString){
    $date = DateTime::createFromFormat( 'Y-m-d', $dateString);
    $mDate = new \MongoDB\BSON\UTCDateTime( $date->format('U') * 1000 );
    return $mDate;
  }

  private function getConstants(){
    //special filter is 'name' = project name with regular expression
    $this->avail_filters = ['group','subgroup','brand','listing_created_at', 'listing_updated_at',
    'project_created_at', 'project_updated_at', 'sector', 'region', 'building_use', 'category',
    'latest_status','latest_sub_status','state','building_use', 'tags',
    'average_area', 'max_floors_above_ground', 'max_floor_below_ground', 'project_value',
    'start_year', 'finish_year', 'tracked', 'verified', 'active'];
    //all filters in $avail_filters other than $filter_with_betwen_operator will be treated as equal operator
    $this->filter_with_between_operators = ['listing_created_at','listing_updated_at',
    'project_created_at', 'project_updated_at', 'average_area', 'project_value'];
    //Defining Search path for each filter key
    $this->search_path = ['records.group.name','records.subgroup.name','records.brand.name','listing_created_at', 'listing_updated_at' ,
    'project_created_at', 'project_updated_at', 'sector', 'region', 'building_use.name', 'category.name',
    'latest_status.name','latest_status.substatus_id',
    'state', 'building_use', 'tags.name', 'average_area', 'max_floors_above_ground',
    'max_floor_below_ground', 'construction_cost', 'construction_start', 'construction_finish',
    'tracked', 'verified', 'active'];
    //Defining type of input
    $this->type_of_search = ['string','string','string','date', 'date' ,'date', 'date', 'string',
    'string', 'string', 'string', 'string','int',
    'string', 'string', 'string', 'int', 'int', 'int', 'float', 'int', 'int', 'int', 'int', 'int'];
    $this->avil_columns = ['listing_created_at','listing_updated_at','project_created_at', 'project_updated_at',
    'sector', 'latest_status', 'state','region', 'average_area', 'max_floors_above_ground', 'max_floor_below_ground',
    'project_value', 'start_year', 'finish_year','attachments','construction_product_id','slug', 'region', 'building_use',
    'category'];
    $this->columns_path = ['listing_created_at','listing_updated_at','project_created_at', 'project_updated_at',
    'sector', 'latest_status.name', 'state','region', 'average_area', 'max_floors_above_ground', 'max_floor_below_ground',
    'project_value', 'start_year', 'finish_year','attachments','construction_product_id', 'slug', 'region', 'building_use.name',
    'category.name'];
  }

  private function generateQuery($filters){
    $this->getConstants();

    function getVal($type,$val){
      if ($type=='date'){
          return $this->MongoDateOnly($val);
      }
      settype($val, $type);
      return $val;
    }

    $query = ['active' => 1];
    foreach ($filters as $key => $filter){
      if ($key == 'name'){
          $query = array_merge($query, ['name' => new Regex('.*'.$filter, 'i')]);
      }
      elseif(in_array($key,$this->filter_with_between_operators)){
          $index = array_search($key, $this->avail_filters);
          $path = $this->search_path[$index];
          $type = $this->type_of_search[$index];
          $vals = explode("-",$filter);
          $gte = getVal($type,$vals[0]);
          $lte = getVal($type,$vals[1]);
          $query = array_merge($query, [$path => ['$gte' => $gte, '$lte' => $lte]]);
      }
      else{
          $index = array_search($key, $this->avail_filters);
          $path = $this->search_path[$index];
          $type = $this->type_of_search[$index];
          $val = getVal($type,$filter);
          $query = array_merge($query, [$path => $val]);
      }
    }
    return $query;
  }

  public function product(Request $request){
    $this->getConstants();
    $columns = ['name','slug','listing_updated_at','attachments','construction_product_id'];
    $query = ['active' => 1];
    if($request->has('filters')){
        $filters = $request->get('filters');
        $query = $this->generateQuery($filters);
    }
    if($request->has('columns')){
      $columns = ['name'];
      foreach($request->columns as $column){
        $index = array_search($column, $this->avil_columns);
        array_push($columns, $this->columns_path[$index]);
      }
    }
    $sort = $request->has('sortby') ? $serch_path[array_search($request->sortby, $this->avail_filters)] : 'listing_created_at';
    $sortorder = ($request->has('sortorder')) ? $request->sortorder : 'desc';
    $limit = $request->has('limit') ? (int)$request->limit : 50;
    $result =  projects_listing::whereRaw($query)->orderBy($sort,$sortorder)->paginate($limit,$columns);
    return $result;
  }

  public function filterCount(Request $request, $filter){
    $this->getConstants();
    $unwind_required = ['group', 'subgroup', 'brand', 'tags', 'building_use', 'category'];
    $unwind_paths = ['group' => ['records'],'subgroup' => ['records'],'brand'=>['records','records.brand'],'tags'=>['tags'],'building_use'=>['building_use'], 'category' => ['category']];
    $query = ['active' => 1];
    $filter_count = [];
    if($request->has('filters')){
        $filters = $request->get('filters');
        $query = $this->generateQuery($filters);
    }
    $count = 1;
    if($request->has('count')){
      $index = array_search($request->count, $this->avail_filters);
      $count = '$'.$this->search_path[$index];
    }
    $index = array_search($filter, $this->avail_filters);
    $path = $this->search_path[$index];
    $advance_filter = [];
    array_push($advance_filter,['$match' => $query]);
    if(in_array($filter,$unwind_required)){
      foreach($unwind_paths[$filter] as $unwind){
          array_push($advance_filter,['$unwind' => ['path' => '$'.$unwind]]);
      }
      array_push($advance_filter,['$group' => ['_id' => ['$'.$path,'$_id'],'name' => ['$first'=> '$'.$path], 'count' => ['$first' => $count]]]);
      array_push($advance_filter,['$group' => ['_id' => ['$name'],'name' => ['$first'=> '$name'], 'count' => ['$sum'=> '$count']]]);
    }
    else{
        array_push($advance_filter,['$group' => ['_id' => '$'.$path,'name' => ['$first'=> '$'.$path],'count'=>['$sum' => $count]]]);
    }
    array_push($advance_filter,['$sort' => ['name' => 1]]);
    $get = projects_listing::raw()->aggregate($advance_filter)->toArray();
    foreach($get as $g){
      array_push($filter_count,[$g['name']=>$g['count']]);
    }
    return $filter_count;
  }

  public function summary(Request $request){
    $query = ['active' => 1];
    if($request->has('filters')){
      $filters = $request->get('filters');
      $query = $this->generateQuery($filters);
    }
    $filter = [];
    array_push($filter,['$match' => $query]);
    $group = ['_id' => 'Summary'];
    $group['projectst'] = ['$sum' => 1];
    $group['construction_area'] = ['$sum' => '$average_area'];
    $group['construction_cost'] = ['$sum' => '$construction_cost'];
    $group['apartment_units'] = ['$sum' => '$apartment_units'];
    array_push($filter,['$group' => $group]);
    $summary = projects_listing::raw()->aggregate($filter)->toArray()[0];
    unset($summary['_id']);
    $query = array_merge($query, ['latitude' => ['$ne'=>null]], ['longitude' => ['$ne' => null]]);
    $loc = projects_listing::whereRaw($query)->count();
    $summary['locations'] = $loc;
    return $summary;
  }
}
