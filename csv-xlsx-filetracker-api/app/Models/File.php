<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class File extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'filetracker-api-service';
    protected $connection = 'mongodb';
    protected $guarded = [];
}
