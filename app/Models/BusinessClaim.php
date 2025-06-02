<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessClaim extends Model
{
    protected $fillable = [
        'place_id',
        'business_name',
        'business_email',
        'business_phone',
        'business_address',
        'claimed_by_name',
        'claimed_by_phone',
        'claimed_by_email',
        'business_logo',
        'status',
        'approved_at',
    ];
    
    protected $casts = [
        'approved_at' => 'datetime',
    ];
}