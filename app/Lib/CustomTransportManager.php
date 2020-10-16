<?php
namespace App\Lib;

use Illuminate\Mail\TransportManager;
use App\Http\Models\Setting; //my models are located in app\models

class CustomTransportManager extends TransportManager {

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
        $setting_raw = Setting::where('key', 'like', 'mailer_%')->get();
        $settings = [];
        foreach ($setting_raw as $setting) {
            $settings[$setting['key']] = $setting['value'];
        }
        $config = config('mail');
        $config['driver'] = 'smtp';
        $config['host'] = $settings['mailer_smtp_host'] ?? $config['host'];
        $config['port'] = $settings['mailer_smtp_port'] ?? $config['port'];
        $config['encryption'] = $settings['mailer_smtp_encryption'] ?? $config['encryption'];
        $config['username'] = $settings['mailer_smtp_username'] ?? $config['username'];
        $config['password'] = $settings['mailer_smtp_password'] ?? $config['password'];
        $this->app['config']['mail'] = $config;
    }
}