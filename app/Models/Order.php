<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
        'order_number',
        'order_status',
        'order_type',
        'order_total',
        'additional_notes',
        'address',
        'user_phone',
        'user_name',
        'order_items',
        'table_number',
        'hotel_room',
        'payment_status',
        'tracking_number',
        'commission',
        'net_amount',
        'commission_processed'
    ];


    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }


    public function orders() {
        return $this->hasMany(Order::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

}
