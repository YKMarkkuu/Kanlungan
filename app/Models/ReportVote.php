<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportVote extends Model
{
    protected $fillable = ['report_id', 'voter_id', 'choice'];
}
