<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Collection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Collection::macro('addSerial', function ($start = 1, $pad = 4, $key = 'sr') {
            return $this->values()->map(function ($item, $index) use ($start, $pad, $key) {
                $serial = $start + $index;

                $item->{$key} = str_pad($serial, $pad, '0', STR_PAD_LEFT);

                return $item;
            });
        });
    }
}
