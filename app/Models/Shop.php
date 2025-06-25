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
        'account_bank_code',
        'status',
        'free_trial',
        'primary_color',
        'secondary_color',
        'card_background',
        'paystack_recipient_code',
    ];

    protected $appends = ['image_url', 'average_rating', 'ratings_count'];

    protected $casts = [
        'services_rendered' => 'array',
    ];

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function getAverageRatingAttribute()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    public function getRatingsCountAttribute()
    {
        return $this->ratings()->count();
    }

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

    public function menuViews()
    {
        return $this->hasMany(MenuView::class);
    }

    public function paymentHistories()
    {
        return $this->hasMany(PaymentHistory::class);
    }
    
    public function failedPayouts()
    {
        return $this->hasMany(FailedPayout::class, 'shop_id');
    }

    public function getPopularMenuItems()
    {
        return MenuItem::where('shop_id', $this->id)
            ->get()
            ->sortByDesc(function ($menuItem) {
                return $menuItem->totalSold();
            })
            ->take(10)
            ->values()
            ->map(function ($menuItem) {
                return [
                    'name' => $menuItem->name,
                    'image' => $menuItem->image_url,
                    'price' => $menuItem->price,
                    'total_sold' => $menuItem->totalSold(),
                    'category' => $menuItem->category->name
                ];
            });
    }
}
