<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

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
            // General API rate limit (per user)
            RateLimiter::for('api', function (Request $request) {
                return $request->user()
                    ? Limit::perMinute(60)->by($request->user()->id)
                    : Limit::perMinute(20)->by($request->ip());
            });

            // Strict rate limit for auth endpoints
            RateLimiter::for('auth', function (Request $request) {
                return Limit::perMinute(5)->by($request->ip());
            });

            // Login attempts (prevent brute force)
            RateLimiter::for('login', function (Request $request) {
                return Limit::perHour(5)->by($request->ip() . '|' . $request->input('email'));
            });

            // Payment endpoints (critical)
            RateLimiter::for('payment', function (Request $request) {
                return $request->user()
                    ? Limit::perMinute(10)->by($request->user()->id)
                    : Limit::perMinute(5)->by($request->ip());
            });

            // Webhook (no limit but log suspicious activity)
            RateLimiter::for('webhook', function (Request $request) {
                return Limit::perMinute(100)->by($request->ip());
            });

            // Admin routes (less strict for admins)
            RateLimiter::for('admin', function (Request $request) {
                return $request->user() && $request->user()->hasRole('admin')
                    ? Limit::perMinute(120)->by($request->user()->id)
                    : Limit::perMinute(30)->by($request->ip());
            });
        }
}
