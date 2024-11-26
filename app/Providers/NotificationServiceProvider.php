<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Channels\FirebaseChannel;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Register the custom Firebase notification channel
        Notification::extend('firebase', function ($app) {
            return new FirebaseChannel($app->make('Kreait\Firebase\Messaging\Messaging'));
        });
    }
}
