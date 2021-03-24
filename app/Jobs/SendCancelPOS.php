<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\POS\Entities\TransactionOnlinePosCancel;

class SendCancelPOS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 1; // 10x retry max
    public $retryAfter = 300; // 5 minutes
    protected $transaction;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $send = \App\Lib\ConnectPOS::create()->doSendCancelOrder($this->transaction);
        if(!$send){
            throw new \Exception("Error send cancel transaction", 1);
        }
        
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        $id_transaction = $this->transaction;
        if (!is_numeric($id_transaction)) {
            $id_transaction = $transaction->id_transaction;
        }
        $top = TransactionOnlinePosCancel::where('id_transaction', $id_transaction)->exists();
        if (!$top) {
            $top = TransactionOnlinePosCancel::create([
                'request' => '{}',
                'response' => json_encode([$exception->getMessage()]),
                'id_transaction' => $id_transaction,
                'count_retry' => 1
            ]);
        }
    }
}
