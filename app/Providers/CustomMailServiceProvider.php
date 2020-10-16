<?php

namespace App\Providers;

use Illuminate\Mail\MailServiceProvider as ServiceProvider;
use App\Lib\CustomTransportManager;

class CustomMailServiceProvider extends ServiceProvider{

    protected function registerSwiftTransport(){
        $this->app->singleton('swift.transport', function () {
            return new CustomTransportManager($this->app);
        });
    }
}