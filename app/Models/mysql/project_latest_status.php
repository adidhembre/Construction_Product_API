<?php

namespace App\Models\mysql;

use Illuminate\Database\Eloquent\Model;

class project_latest_status extends Model
{
    protected $connection = 'mysql';
    protected $table = 'project_latest_status';
}
