<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'description', 'price', 'image_path', 'shop_id', 'processing_time', 'status'];

    protected $appends = ['image_url'];


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageUrlAttribute() {
        return asset($this->image_path);
    }

    // total sold
    public function totalSold() {
        return $this->hasMany(OrderItem::class)->sum('quantity');
    }
}
