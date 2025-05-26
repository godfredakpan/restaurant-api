<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoUse extends Model
{
    use HasFactory;

    protected $fillable = [
        'promo_campaign_id',
        'order_id',
        'user_id',
        'shop_id',
    ];


    public function promoCampaign()
    {
        return $this->belongsTo(PromoCampaign::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }


}
