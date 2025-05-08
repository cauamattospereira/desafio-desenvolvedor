<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class File extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = [];
}
