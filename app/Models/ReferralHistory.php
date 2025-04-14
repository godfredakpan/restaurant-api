<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralHistory extends Model
{
    use HasFactory;

    protected $fillable = ['referrer_id', 'shop_id'];

    public function referrer()
    {
        return $this->belongsTo(Referral::class, 'referrer_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
