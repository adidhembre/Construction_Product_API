<?php

namespace App\Models\mongodb;

use Jenssegers\Mongodb\Eloquent\Model;

class projects_listing extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'construction_product_projects';
    //this way to define only fields which are fillable
    //protected $fillable = ['_id','construction_product_id'];

    //this way we can disallow fields to fill
    protected $guarded = [];
    //keeping this empty means all fields are allowed
}
