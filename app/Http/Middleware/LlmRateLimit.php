<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LlmRateLimit
{
    private const DAILY_LIMIT = 50;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $today = now()->toDateString();
        $cacheKey = "llm_daily:{$user->id}:{$today}";

        $count = (int) Cache::get($cacheKey, 0);

        if ($count >= self::DAILY_LIMIT) {
            return response()->json([
                'error'       => 'Daily limit reached. Maximum ' . self::DAILY_LIMIT . ' requests are permitted.',
                'used'        => $count,
                'limit'       => self::DAILY_LIMIT,
                'retry_after' => now()->endOfDay()->diffInSeconds(),
            ], 429);
        }

        $ttl = now()->secondsUntilEndOfDay();
        Cache::put($cacheKey, $count + 1, $ttl);

        return $next($request);
    }
}
