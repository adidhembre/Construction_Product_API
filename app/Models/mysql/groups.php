<?php

namespace App\Models\mysql;

use Illuminate\Database\Eloquent\Model;

class groups extends Model
{
    protected $connection = 'mysql';
    protected $table = 'biltrax_database.groups';
}
