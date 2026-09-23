<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer(['layouts.*', 'certificates.pdf'], function ($view) {
            $settings = Setting::whereIn('key', ['app_name', 'logo_path', 'contact_email'])->pluck('value', 'key');
            $view->with(['brandName' => $settings['app_name'] ?? 'ALDEF LMS', 'brandLogo' => $settings['logo_path'] ?? 'assets/logo/aldef-landscape02.png', 'contactEmail' => $settings['contact_email'] ?? 'hello@aldeftech.com']);
        });
    }
}
