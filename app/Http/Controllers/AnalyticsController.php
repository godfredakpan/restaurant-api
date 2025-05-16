<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function getShopAnalytics(Shop $shop)
    {
        // total views
        $totalViews = $shop->menuViews()->count();

        // views last month
        $viewsLastMonth = $shop->menuViews()
            ->where('created_at', '>', now()->subMonth())
            ->count();

        // views last 24h
        $viewsLast24h = $shop->menuViews()
            ->where('created_at', '>', now()->subDay())
            ->count();

        $uniqueVisitors = $shop->menuViews()
            ->select('ip_address')
            ->where('created_at', '>', now()->subDay())
            ->groupBy('ip_address')
            ->get()
            ->count();

        $viewsLast7Days = $shop->menuViews()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>', now()->subDays(7))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(function ($item) {
                return [
                    'day' => $item->day,
                    'count' => (int) $item->count,
                ];
            })
            ->toArray();

        $deviceTypes = $shop->menuViews()
            ->select('device_type', \DB::raw('count(*) as count'))
            ->groupBy('device_type')
            ->get()
            ->pluck('count', 'device_type');

        return response()->json([
            'total_views' => $totalViews,
            'views_last_month' => $viewsLastMonth,
            'views_last_24h' => $viewsLast24h,
            'unique_visitors' => $uniqueVisitors,
            'views_trend' => $viewsLast7Days,
            'device_distribution' => $deviceTypes,
            'popular_items' => $shop->getPopularMenuItems(),
        ]);
    }
}