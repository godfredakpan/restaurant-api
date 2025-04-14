<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Shop;

class CheckShopStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $shop = Shop::where('id', $user->shop_id)->first();

        if (!$shop || $shop->status !== 'active') {
            return response()->json([
                'error' => 'Your account is inactive. Please activate your shop to continue.'
            ], 403);
        }

        return $next($request);
    }
}
