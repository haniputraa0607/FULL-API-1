<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Lib\classJatisSMS;

class CheckSmsStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $logModel;
    protected $result;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($logModel, $result)
    {
        $this->logModel = $logModel;
        $this->result = $result;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $messageId = $this->result['MessageId'];
        $status = classJatisSms::deliveryReport($messageId);
        $report = $status['response']['Reports'][0]??[];
        $deliveryStatus = ($report['DeliveryStatus']??false) ? (classJatisSms::$deliveryStatus[trim($report['DeliveryStatus'])] ?? false) : null;

        $this->logModel->update(['status' => $deliveryStatus, 'status_response' => $status['response_raw']]);
    }
}
