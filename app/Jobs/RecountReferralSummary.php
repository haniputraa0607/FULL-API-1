<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use \Modules\PromoCampaign\Entities\UserReferralCode;

class RecountReferralSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $urcs;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($urcs)
    {
        $this->urcs = $urcs;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->urcs->each(function($urc) {
            $urc->refreshSummary();
        });
    }
}
