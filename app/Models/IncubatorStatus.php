<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncubatorStatus extends Model
{
    protected $table = 'incubator_status';

    protected $fillable = [
        'temperature',
        'humidity',
        'egg_count',
    ];
}
