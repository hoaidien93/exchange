<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (session()->has('locale')) {
            app()->setLocale(session()->get('locale'));
        }

        // Set default currency
        $location = geoip($request->ip())->toArray();

        if ($currency = Arr::get($location, 'currency')) {
            currency()->setUserCurrency($currency);
        }

        return $next($request);
    }
}
