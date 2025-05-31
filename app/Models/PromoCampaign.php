<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PromoCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'title',
        'type',
        'discount_value',
        'description',
        'valid_days',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'usage_limit',
        'promo_code',
        'show_on_menu',
        'show_as_banner',
        'is_active'
    ];

    protected $casts = [
        'valid_days' => 'array',
        'show_on_menu' => 'boolean',
        'show_as_banner' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function isActive()
    {
        $now = now();
        
        if (!$this->is_active) {
            return false;
        }

        // Day of week check (0=Sunday, 6=Saturday)
        $isValidDay = empty($this->valid_days) || in_array($now->dayOfWeek, $this->valid_days);

        // Date range check (start_date and end_date)
        $isValidDate = (!$this->start_date || $now->gte($this->start_date)) &&
                    (!$this->end_date || $now->lte($this->end_date));

        $startTime = $this->start_time 
            ? Carbon::createFromTimeString($this->start_time)->setDateFrom($now)
            : null;
        
        $endTime = $this->end_time 
            ? Carbon::createFromTimeString($this->end_time)->setDateFrom($now)
            : null;

        $isValidTime = (!$startTime || $now->gte($startTime)) &&
                    (!$endTime || $now->lte($endTime));

        return $isValidDay && $isValidTime && $isValidDate;
    }

    public function uses()
    {
        return $this->hasMany(PromoUse::class);
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, PromoUse::class, 'promo_campaign_id', 'id', 'id', 'order_id');
    }
}