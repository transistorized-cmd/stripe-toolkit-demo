<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\DemoServiceCheck;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->shareDemoServiceBanner();
    }

    /**
     * Push a service-warning banner into every view as `$globalBanner`
     * when the queue worker or Stripe CLI listener isn't running.
     * Layouts (both demo and the kit's debug inspector) render
     * `{!! $globalBanner ?? '' !!}` near the top.
     */
    protected function shareDemoServiceBanner(): void
    {
        if ($this->app->environment('production')) {
            return;
        }

        // Scope to the two layouts that render `{!! $globalBanner !!}`,
        // not every view in the app. Wildcard '*' would fire for every
        // partial including the warning partial itself (recursion risk)
        // and every Blade fragment Laravel renders, including from the
        // kit. Two explicit names is faster and clearer.
        View::composer(
            ['checkout.layout', 'stripe-webhooks::debug.layout'],
            function ($view): void {
                $missing = $this->app->make(DemoServiceCheck::class)->missingServices();

                if ($missing === []) {
                    $view->with('globalBanner', '');

                    return;
                }

                $banner = view('partials.service-warning', ['missing' => $missing])->render();
                $view->with('globalBanner', $banner);
            }
        );
    }
}
