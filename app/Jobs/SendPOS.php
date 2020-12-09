<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Models\TransactionOnlinePos;
use Throwable;

class SendPOS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 1; // 10x retry max
    public $retryAfter = 300; // 5 minutes
    protected $id_transactions;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id_transactions)
    {
        $this->id_transactions = $id_transactions;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $send = \App\Lib\ConnectPOS::create()->doSendTransaction(...$this->id_transactions);
        if(!$send){
            throw new \Exception("Error send transaction", 1);
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
        foreach ($this->id_transactions as $id_transaction) {
            $top = TransactionOnlinePos::where('id_transaction', $id_transaction)->exists();
            if (!$top) {
                $top = TransactionOnlinePos::create([
                    'request' => '{}',
                    'response' => json_encode([$exception->getMessage()]),
                    'id_transaction' => $id_transaction,
                    'count_retry' => 1
                ]);
            }
        }
    }
}
