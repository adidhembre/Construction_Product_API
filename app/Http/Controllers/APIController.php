<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
//Models from MongoDB
use App\Models\mongodb\projects_listing;

class APIController extends Controller
{
    public function listing(Request $request, $plid)
    {
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
        $diff = array_diff($pids_array,$pids_avail_array);
        foreach ($diff as $pid){
            if (in_array($pid,$pids_array)){
                //This means we need to add project in mongoDB Database
                $project = [];
                $project['construction_product_id'] = $plid;
                projects_listing::where('_id',$pid)->update($project,['upsert' => true]);
                $this->project($pid);
            }
            else{
                //This means project is to be removed from mongoDB Database
                projects_listing::where('_id',$pid)->delete();
            }
        }

        $cp = construction_product::select('created_at', 'updated_at', 'deleted_at')->where('id',$plid)->get()->toArray()[0];
        $detail = [];
        $detail['listing_created_at'] = gettype($cp['created_at']) == 'string' ? strtotime($cp['created_at']) : $cp['created_at'];
        $detail['listing_updated_at'] = gettype($cp['updated_at']) == 'string' ? strtotime($cp['updated_at']) : $cp['updated_at'];
        $detail['listing_deleted_at'] = gettype($cp['deleted_at']) == 'string' ? strtotime($cp['deleted_at']) : $cp['deleted_at'];
        foreach ($pids_array as $pid){
            projects_listing::where('_id',$pid)->update($detail,['upsert' => true]);
        }
        return ["message"=>"Listing Updated Successfully"];
    }

    public function project($pid){
        $proj_gen = projects::select('name', 'state', 'building_use', 'sector',
        'tracked', 'verified', 'active', 'status', 'created_at', 'average_area',
        'construction_cost')->where('id',$pid)->get()->toArray()[0];
        $proj_tech = project_technical_detail::select('construction_start',
        'construction_finish', 'max_floor_above_ground', 'max_floor_below_ground')
        ->where('id',$pid)->get()->toArray()[0];
        $project = array_merge($proj_gen,$proj_tech);
        //to change key name only
        $project['project_created_at'] = gettype($project['created_at']) == "string" ? strtotime($project['created_at']) : $project['created_at'];
        unset($project['created_at']);
        //to get value instead of id
        $project['state'] = $project['state'] == null ? null : states::select('name')->where('id',$project['state'])->first()['name'];
        $project['sector'] = $project['sector'] == null ? null : project_sectors::select('name')->where('id',$project['sector'])->first()['name'];
        projects_listing::where('_id',$pid)->update($project,['upsert' => true]);
        return ["message"=>"Project Updated Successfully"];
    }

    public function records($plid){
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
                $b = ['id'=>$brand['brand_id'],'name'=> brands::select('title')->where('id',$brand['brand_id'])->first()['title']];
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
