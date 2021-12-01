<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DateTime;
//Models from MySQL
use App\Models\mysql\construction_product;
use App\Models\mysql\construction_product_projects;
use App\Models\mysql\projects;
use App\Models\mysql\project_technical_detail;
use App\Models\mysql\states;
use App\Models\mysql\project_sectors;
use App\Models\mysql\construction_product_details;
use App\Models\mysql\groups;
use App\Models\mysql\sub_groups;
use App\Models\mysql\brands;
use App\Models\mysql\construction_product_brands;
use App\Models\mysql\Construction_product_attachments;
use App\Models\mysql\technical_detail_tag_relation;
use App\Models\mysql\project_tags;
use App\Models\mysql\project_status;
use App\Models\mysql\project_latest_status;
use App\Models\mysql\brand_companies;
use App\Models\mysql\regions;
use App\Models\mysql\project_type;
use App\Models\mysql\project_type_relation;
use App\Models\mysql\project_attributes;
use App\Models\mysql\project_attribute_relation;
//Models from MongoDB
use App\Models\mongodb\projects_listing;

class APIController extends Controller
{
    private function MongoDate($dateString){
        $date = DateTime::createFromFormat( 'Y-m-d\TH:i:s.uT', $dateString);
        $mDate = new \MongoDB\BSON\UTCDateTime( $date->format('U') * 1000 );
        return $mDate;
    }

    private function MongoDateOnly($dateString){
        $date = DateTime::createFromFormat( 'Y-m-d', $dateString);
        $mDate = new \MongoDB\BSON\UTCDateTime( $date->format('U') * 1000 );
        return $mDate;
    }

    public function listing($plid)
    {
        $plid = (int)$plid;
        $pids = construction_product_projects::select('project_id')->where('construction_product_id',$plid)->get();
        $pids_array = [];
        foreach ($pids as $pid){
            array_push($pids_array,$pid->project_id);
        }
        $pids_available = projects_listing::where('construction_product_id',$plid)->get(['_id']);
        $pids_avail_array = [];
        foreach ($pids_available as $pid){
            array_push($pids_avail_array,$pid->_id);
        }
        $diff1 = array_diff($pids_array,$pids_avail_array);
        $diff2 = array_diff($pids_avail_array,$pids_array);
        foreach ($diff1 as $pid){
            //This means we need to add project in mongoDB Database
            $project = [];
            $project['construction_product_id'] = $plid;
            projects_listing::where('_id',$pid)->update($project,['upsert' => true]);
            $this->project($pid);
        }
        foreach ($diff2 as $pid){
            //This means project is to be removed from mongoDB Database
            projects_listing::where('_id',$pid)->delete();
        }

        $cp = construction_product::select('created_at', 'updated_at', 'deleted_at', 'note', 'note_date')->where('id',$plid)->get()->toArray()[0];
        $detail = [];
        $detail['listing_created_at'] = gettype($cp['created_at']) == 'string' ? $this->MongoDate($cp['created_at']) : $cp['created_at'];
        $detail['listing_updated_at'] = gettype($cp['updated_at']) == 'string' ? $this->MongoDate($cp['updated_at']) : $cp['updated_at'];
        $detail['listing_deleted_at'] = gettype($cp['deleted_at']) == 'string' ? $this->MongoDate($cp['deleted_at']) : $cp['deleted_at'];
        $detail['note'] = $cp['note'];
        $detail['note_date'] = gettype($cp['note_date']) == 'string' ? $this->MongoDateOnly($cp['note_date']) : $cp['note_date'];
        $detail['attachments'] = Construction_product_attachments::select('id','name','link')->where('construction_product_id',$plid)->get()->toArray();
        foreach ($pids_array as $pid){
            projects_listing::where('_id',$pid)->update($detail,['upsert' => true]);
        }
        return ["message"=>"Listing Updated Successfully"];
    }

    public function project($pid){
        $pid = (int)$pid;
        $proj_gen = projects::select('name', 'slug', 'state', 'building_use', 'sector',
        'tracked', 'verified', 'active', 'status', 'created_at', 'updated_at', 'average_area',
        'construction_cost', 'region_id', 'latitude', 'longitude')->where('id',$pid)->get()->toArray()[0];
        //return $proj_gen;
        $proj_tech = project_technical_detail::select('id','construction_start',
        'construction_finish', 'max_floor_above_ground', 'max_floor_below_ground', 'apartment_units')
        ->where('project_id',$pid)->get()->toArray()[0];
        $project = array_merge($proj_gen,$proj_tech);
        //to change key name only
        $project['project_created_at'] = gettype($project['created_at']) == "string" ? $this->MongoDate($project['created_at']) : $project['created_at'];
        unset($project['created_at']);
        $project['project_updated_at'] = gettype($project['updated_at']) == "string" ? $this->MongoDate($project['updated_at']) : $project['updated_at'];
        unset($project['updated_at']);
        //to get value instead of id
        $project['state'] = $project['state'] == null ? null : states::select('name')->where('id',$project['state'])->first()['name'];
        $project['sector'] = $project['sector'] == null ? null : project_sectors::select('name')->where('id',$project['sector'])->first()['name'];
        $project['region'] = $project['region_id'] == null ? null : regions::select('name')->where('id',$project['region_id'])->first()['name'];
        unset($project['region_id']);
        $project['latitude'] = $project['latitude'] == null ? null : (float)$project['latitude'];
        $project['longitude'] = $project['longitude'] == null ? null : (float)$project['longitude'];
        $project['apartment_units'] = $project['apartment_units'] == null ? null : (int)$project['apartment_units'];
        $project['tags'] = [];
        $tags = technical_detail_tag_relation::select('tag_id')->where('technical_detail_id', $project['id'])->get();
        unset($project['id']);
        foreach ($tags as $tag){
            $t = ['id'=>$tag->tag_id,'name'=>project_tags::select('name')->where('id',$tag->tag_id)->first()['name']];
            array_push($project['tags'],$t);
        }
        $project['building_use'] = [];
        $uses = project_type_relation::select('type_id')->where('project_id', $pid)->get();
        foreach ($uses as $use){
            $u = ['id'=>$use->type_id,'name'=>project_type::select('type')->where('id',$use->type_id)->first()['type']];
            array_push($project['building_use'],$u);
        }
        $project['category'] = [];
        $categories = project_attribute_relation::select('attribute_id')->where('project_id', $pid)->get();
        foreach ($categories as $cat){
            $c = ['id'=>$cat->attribute_id,'name'=>project_attributes::select('name')->where('id',$cat->attribute_id)->first()['name']];
            array_push($project['category'],$c);
        }
        $project['latest_status'] = [];
        $st = project_latest_status::select('status', 'sub_status')->where('project_id',$pid)->first();
        $project['latest_status']['id'] = $st['status'];
        $project['latest_status']['name'] = project_status::select('name')->where('id',$project['latest_status']['id'])->first()['name'];
        $project['latest_status']['substatus_id'] = $st['sub_status'];
        projects_listing::where('_id',$pid)->update($project,['upsert' => true]);
        return ["message"=>"Project Updated Successfully"];
    }

    public function records($plid){
        $plid = (int)$plid;
        $details = [];
        $details['records'] = [];
        $cpds = construction_product_details::select('id','product_group_id', 'product_subgroup_id', 'equivalent', 'bis_approved')->where('construction_product_id',$plid)->get()->toArray();
        foreach($cpds as $cpd ){
            $record = [];
            $record['group'] = ['id' => $cpd['product_group_id'],'name' => groups::select('name')->where('id',$cpd['product_group_id'])->first()['name']];
            $record['subgroup'] = ['id' => $cpd['product_subgroup_id'],'name' => sub_groups::select('name')->where('id',$cpd['product_subgroup_id'])->first()['name']];
            $record['equivalent'] = $cpd['equivalent'];
            $record['bis_approved'] = $cpd['bis_approved'];
            $record['brand'] = [];
            $brands = construction_product_brands::select('brand_id')->where('const_product_details_id',$cpd['id'])->get();
            foreach($brands as $brand){
                $b = [];
                $b['id'] = $brand['brand_id'];
                $b['name'] = brands::select('title')->where('id',$b['id'])->first()['title'];
                $cid = brand_companies::select('company_id')->where('brand_id',$b['id'])->first();
                $b['company_id'] = $cid == null ? null : $cid->company_id;
                array_push($record['brand'],$b);
            }
            array_push($details['records'],$record);
        }
        $pids = construction_product_projects::select('project_id')->where('construction_product_id',$plid)->get();
        foreach($pids as $pid){
            projects_listing::where('_id',$pid->project_id)->update($details,['upsert' => true]);
        }
        return ["message"=>"Records Updated Successfully"];
    }
}