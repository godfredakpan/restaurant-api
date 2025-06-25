<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedPayout extends Model
{
    protected $fillable = [
        'order_id', 'amount', 'reason', 'resolved', 'shop_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }


    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}