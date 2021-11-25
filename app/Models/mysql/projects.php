<?php

namespace App\Models\mysql;

use Illuminate\Database\Eloquent\Model;

class projects extends Model
{
    protected $connection = 'mysql';
    protected $table = 'projects';
}
