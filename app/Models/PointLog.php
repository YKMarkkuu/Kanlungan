<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointLog extends Model
{
    protected $fillable = ['user_id', 'amount', 'reason', 'source_type', 'source_id'];
}
