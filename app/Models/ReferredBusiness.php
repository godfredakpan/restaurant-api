<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferredBusiness extends Model
{
    use HasFactory;

     protected $fillable = [
        'place_id',
        'name',
        'address',
        'phone',
        'category',
        'referrer_name',
        'referrer_email',
        'referrer_phone',
        'notes',
        'status'
    ];
}
