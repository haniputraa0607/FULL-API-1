<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Mail;
use App\Http\Models\Setting;

class SendMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $mail;
    public $tries = 3; // 3x retry max
    public $retryAfter = 60; // retry after 1 minutes

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($mail)
    {
        $this->mail = $mail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $setting_raw = Setting::where('key', 'like', 'mailer_%')->get();
        $settings = [];
        foreach ($setting_raw as $setting) {
            $settings[$setting['key']] = $setting['value'];
        }
        $config = config('mail');
        $config['host'] = $settings['mailer_smtp_host'] ?? $config['host'];
        $config['port'] = $settings['mailer_smtp_port'] ?? $config['port'];
        $config['encryption'] = $settings['mailer_smtp_encryption'] ?? $config['encryption'];
        $config['username'] = $settings['mailer_smtp_username'] ?? $config['username'];
        $config['password'] = $settings['mailer_smtp_password'] ?? $config['password'];

        $transport = app('swift.transport');
        $smtp = $transport->driver('smtp');
        $smtp->setHost($config['host']);
        $smtp->setPort($config['port']);
        $smtp->setUsername($config['username']);
        $smtp->setPassword($config['password']);
        $smtp->setEncryption($config['encryption']);

        Mail::send($this->mail);
    }

    // public function failed(\Exception $e)
    // {
    // }
}
