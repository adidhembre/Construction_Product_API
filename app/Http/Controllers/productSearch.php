<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\mongodb\projects_listing;
use MongoDB\BSON\Regex;

class productSearch extends Controller
{
    public function product(Request $request){
        //special filter is 'name' = project name with regular expression
        $avail_filters = ['group','subgroup','brand','listing_created_at','project_created_at','sector',
        'latest_status','latest_sub_status','state','building_use', 'tags',
        'average_area', 'max_floors_above_ground', 'max_floor_below_ground', 'project_value',
        'start_year', 'finish_year', 'tracked', 'verified', 'active'];
        //all filters in $avail_filters other than $filter_with_betwen_operator will be treated as equal operator
        $filter_with_between_operators = ['listing_created_at','project_created_at', 'average_area', 'project_value'];
        //Defining Search path for each filter key
        $serch_path = ['records.group.name','records.subgroup.name','records.brand.name','listing_created_at',
        'project_created_at','sector', 'latest_status.name','latest_status.substatus_id',
        'state', 'building_use', 'tags.name', 'average_area', 'max_floors_above_ground',
        'max_floor_below_ground', 'construction_cost', 'construction_start', 'construction_finish',
        'tracked', 'verified', 'active'];
        //Defining type of input
        $type_of_search = ['string','string','string','date', 'date','string', 'string','int',
        'string', 'string', 'string', 'int', 'int', 'int', 'float', 'int', 'int', 'int', 'int', 'int'];
        $avil_columns = ['listing_created_at','project_created_at','sector', 'latest_status',
        'state','region', 'average_area', 'max_floors_above_ground', 'max_floor_below_ground',
        'project_value', 'start_year', 'finish_year'];
        $columns_path = ['listing_created_at','project_created_at','sector', 'latest_status.name',
        'state','region', 'average_area', 'max_floors_above_ground', 'max_floor_below_ground',
        'project_value', 'start_year', 'finish_year'];
        $columns = ['name',];
        $query = ['active' => 1];
        function getVal($type,$val){
            if ($type=='date'){
                return strtotime($val);
            }
            settype($val, $type);
            return $val;
        }
        if($request->has('filters')){
            $filters = $request->get('filters');
            foreach ($filters as $key => $filter){
                if ($key == 'name'){
                    $query = array_merge($query, ['name' => new Regex('.*'.$filter, 'i')]);
                }
                elseif(in_array($key,$filter_with_between_operators)){
                    $index = array_search($key, $avail_filters);
                    $path = $serch_path[$index];
                    $type = $type_of_search[$index];
                    $vals = explode("-",$filter);
                    $gte = getVal($type,$vals[0]);
                    $lte = getVal($type,$vals[1]);
                    $query = array_merge($query, [$path => ['$gte' => $gte, '$lte' => $lte]]);
                }else{
                    $index = array_search($key, $avail_filters);
                    $path = $serch_path[$index];
                    $type = $type_of_search[$index];
                    $val = getVal($type,$filter);
                    $query = array_merge($query, [$path => $val]);
                }
            }
        }
        if($request->has('columns')){
            foreach($request->columns as $column){
                $index = array_search($column, $avil_columns);
                array_push($columns, $columns_path[$index]);
            }
        }
        $sort = $request->has('sortby') ? $serch_path[array_search($request->sortby, $avail_filters)] : 'listing_created_at';
        $sortorder = ($request->has('sortorder')) ? $request->sortorder : 'desc';
        $limit = $request->has('limit') ? (int)$request->limit : 10;
        $result =  projects_listing::whereRaw($query)->orderBy($sort,$sortorder)->paginate($limit,$columns);
        $filter_count = [];
        $unwind_required = ['group', 'subgroup', 'brand', 'tags'];
        $unwind_paths = ['group' => ['records'],'subgroup' => ['records'],'brand'=>['records','records.brand'],'tags'=>['tags']];
        $avoid = ['listing_created_at','project_created_at','average_area','project_value'];
        foreach($avail_filters as $filter){
            if(in_array($filter,$avoid)){
                //do nothing
            }
            else{
                $index = array_search($filter, $avail_filters);
                $path = $serch_path[$index];
                $advance_filter = [];
                array_push($advance_filter,['$match' => $query]);
                if(in_array($filter,$unwind_required)){
                    foreach($unwind_paths[$filter] as $unwind){
                        array_push($advance_filter,['$unwind' => ['path' => '$'.$unwind]]);
                    }
                    array_push($advance_filter,['$group' => ['_id' => ['$'.$path,'$_id'],'name' => ['$first'=> '$'.$path]]]);
                    array_push($advance_filter,['$group' => ['_id' => ['$name'],'name' => ['$first'=> '$name'], 'count' => ['$sum'=> 1]]]);
                }
                else{
                    array_push($advance_filter,['$group' => ['_id' => '$'.$path,'name' => ['$first'=> '$'.$path],'count'=>['$sum' => 1]]]);
                }
                $get = projects_listing::raw()->aggregate($advance_filter)->toArray();
                $filter_count[$filter] = [];
                foreach($get as $g){
                    array_push($filter_count[$filter],[$g['name']=>$g['count']]);
                }
            }
        }
        return compact('result','filter_count');
    }
}
