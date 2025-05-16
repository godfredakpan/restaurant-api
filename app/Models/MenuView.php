<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jenssegers\Agent\Agent;

class MenuView extends Model
{
    protected $fillable = [
        'shop_id',
        'user_id',
        'ip_address',
        'user_agent',
        'referrer',
        'device_type',
        'session_id'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $agent = new Agent();
            
            $model->user_agent = request()->userAgent();
            $model->referrer = request()->header('referer');
            $model->device_type = MenuView::getDeviceType($agent);
            $model->session_id = request()->hasSession() ? session()->getId() : null;
        });
    }

    public static function getDeviceType(Agent $agent)
    {
        if ($agent->isMobile()) {
            return 'mobile';
        } elseif ($agent->isTablet()) {
            return 'tablet';
        }
        return 'desktop';
    }
}