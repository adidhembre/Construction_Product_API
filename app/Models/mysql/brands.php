<?php

namespace App\Models\mysql;

use Illuminate\Database\Eloquent\Model;

class brands extends Model
{
    protected $connection = 'mysql';
    protected $table = 'brands';
}
