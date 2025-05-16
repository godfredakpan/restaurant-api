<?php

namespace App\Http\Middleware;

use Closure;
use Jenssegers\Agent\Agent;

class CaptureAnalytics
{
    public function handle($request, Closure $next)
    {
        if ($request->is('api/*')) {
            $agent = new Agent();
            
            $request->merge([
                'analytics' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'referrer' => $request->header('referer'),
                    'device' => $this->getDeviceType($agent),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ]
            ]);
        }
        
        return $next($request);
    }
    
    protected function getDeviceType($agent)
    {
        if ($agent->isMobile()) return 'mobile';
        if ($agent->isTablet()) return 'tablet';
        return 'desktop';
    }
}