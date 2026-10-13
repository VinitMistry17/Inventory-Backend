<?php

namespace App\Providers;

use App\Contracts\MediaUploader;
use App\Services\CloudinaryMediaUploader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MediaUploader::class, CloudinaryMediaUploader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
