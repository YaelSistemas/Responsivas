<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcCounter extends Model
{
    protected $table = 'oc_counters';
    protected $fillable = ['tenant_id', 'last_seq'];
}
