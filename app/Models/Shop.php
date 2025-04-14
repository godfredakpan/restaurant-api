<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'shop_name',
        'banner',
        'address',
        'city',
        'state',
        'country',
        'phone_number',
        'email',
        'description',
        'opening_time',
        'closing_time',
        'category',
        'rating',
        'services_rendered',
        'account_number',
        'account_name',
        'account_bank',
        'status',
        'free_trial',
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'services_rendered' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
    
        static::creating(function ($shop) {
            $slug = Str::slug($shop->shop_name);
            $originalSlug = $slug;
            $count = 1;
    
            // Ensure slug uniqueness
            while (Shop::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }
    
            $shop->slug = $slug;
        });
    }

    // Accessor to decode `services_rendered`
    public function getServicesRenderedAttribute($value)
    {
        return json_decode($value, true) ?? [];
    }

    // Accessor for image URL
    public function getImageUrlAttribute()
    {
        return asset($this->banner);
    }

    public function admin()
    {
        return $this->belongsTo(User::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }
}
