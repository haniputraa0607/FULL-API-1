<?php

namespace App\Jobs;

use App\Http\Models\AutocrmEmailLog;
use App\Http\Models\AutocrmPushLog;
use App\Http\Models\AutocrmSmsLog;
use App\Http\Models\AutocrmWhatsappLog;
use App\Http\Models\User;
use App\Http\Models\UserInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Modules\Balance\Entities\AdjustmentPointUser;
use Modules\Balance\Entities\NotificationExpiryPointSent;
use Modules\Balance\Entities\NotificationExpiryPointSentUser;
use Modules\Balance\Http\Controllers\BalanceController;
use Modules\Users\Http\Controllers\ApiUser;

class AdjustmentPointUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $data,$camp;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data=$data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $datas = $this->data['data'];

        foreach ($datas as $data){
            \Log::info($data);
            if($data['point_adjust'] < 0){
                $checkCurrentBalance = User::where('id', $data['id_user'])->first()['balance']??0;

                if($checkCurrentBalance == 0){
                    continue;
                }elseif(abs($data['point_adjust']) > $checkCurrentBalance){
                    $data['point_adjust'] = -$checkCurrentBalance;
                }
            }
            $balanceController = new BalanceController();
            $balanceController->addLogBalance($data['id_user'], $data['point_adjust'], $data['id_adjustment_point_user'], $data['reason']);
        }

        return true;
    }
}
