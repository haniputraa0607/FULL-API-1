<?php

namespace App\Jobs;

use App\Http\Models\PromotionContent;
use App\Http\Models\Setting;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $mail;
    protected $callbackData;
    public $tries      = 3; // 3x retry max
    public $retryAfter = 60; // retry after 1 minutes

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($mail, $callbackData)
    {
        $this->mail         = $mail;
        $this->callbackData = $callbackData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $setting_raw = Setting::where('key', 'like', 'mailer_%')->get();
        $settings    = [];
        foreach ($setting_raw as $setting) {
            $settings[$setting['key']] = $setting['value'];
        }
        $config               = config('mail');
        $config['host']       = $settings['mailer_smtp_host'] ?? $config['host'];
        $config['port']       = $settings['mailer_smtp_port'] ?? $config['port'];
        $config['encryption'] = $settings['mailer_smtp_encryption'] ?? $config['encryption'];
        $config['username']   = $settings['mailer_smtp_username'] ?? $config['username'];
        $config['password']   = $settings['mailer_smtp_password'] ?? $config['password'];

        $transport = app('swift.transport');
        $smtp      = $transport->driver('smtp');
        $smtp->setHost($config['host']);
        $smtp->setPort($config['port']);
        $smtp->setUsername($config['username']);
        $smtp->setPassword($config['password']);
        $smtp->setEncryption($config['encryption']);

        Mail::send($this->mail);
        if (is_array($this->callbackData)) {
            $this->callback();
        }
    }

    public function callback()
    {
        $callbackData = $this->callbackData;
        switch ($callbackData['type'] ?? '') {
            case 'send_campaign':
                DB::table('campaigns')
                    ->where('id_campaign', $callbackData['data']['id_campaign'] ?? '')
                    ->update([
                        'campaign_email_count_sent' => DB::raw('campaign_email_count_sent + 1'),
                    ]);
                break;

            case 'send_promotion':
                PromotionContent::where('id_promotion_content', $callbackData['data']['id_promotion_content'])->update(['promotion_count_email_sent' => DB::raw('promotion_count_email_sent + 1')]);
                break;
        }
    }
    // public function failed(\Exception $e)
    // {
    // }
}
