<?php

namespace App\Providers;

use App\Services\BlueskyService;
use App\Services\FakeBlueskyService;
use App\Support\LocalDev;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ローカル開発用: 本物の Bluesky につながない偽物に差し替える
        if (LocalDev::fakeBlueskyEnabled()) {
            $this->app->bind(BlueskyService::class, FakeBlueskyService::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
