<?php

namespace App\Jobs;

use App\Http\Models\AutocrmEmailLog;
use App\Http\Models\AutocrmPushLog;
use App\Http\Models\AutocrmSmsLog;
use App\Http\Models\AutocrmWhatsappLog;
use App\Http\Models\UserInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Modules\Balance\Entities\NotificationExpiryPointSent;
use Modules\Balance\Entities\NotificationExpiryPointSentUser;
use Modules\Users\Http\Controllers\ApiUser;

class NotificationExpiryPointSendJob implements ShouldQueue
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
        $dataSend = $this->data['data_notification'];
        $idNotificationExpiryPointSent = $this->data['id_notification_expiry_point_sent'];

        $dataIdSentUser = [];
        foreach ($dataSend as $data){
            $save = NotificationExpiryPointSentUser::create([
                'id_notification_expiry_point_sent' => $idNotificationExpiryPointSent,
                'id_user' => $data['id_user'],
                'total_expired_point' => $data['total_point'],
                'expired_date' => $data['expired_date']
            ]);

            if($save){
                $dataIdSentUser[] = $save['id_notification_expiry_point_sent_user'];
                app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('Expiry Point', $data['phone'], [
                    'expiry_date' => date('d F Y', strtotime($data['expired_date'])),
                    'total_point_expired' =>  number_format($data['total_point'])
                ], null, false, $save['id_notification_expiry_point_sent_user']);
            }
        }

        if(!empty($dataIdSentUser)){
            $dataUpdate['email_count_sent'] = AutocrmEmailLog::whereIn('id_notification_expiry_point_sent_user', $dataIdSentUser)->count();
            $dataUpdate['sms_count_sent']   = AutocrmSmsLog::whereIn('id_notification_expiry_point_sent_user', $dataIdSentUser)->count();
            $dataUpdate['push_count_sent']  = AutocrmPushLog::whereIn('id_notification_expiry_point_sent_user', $dataIdSentUser)->count();
            $dataUpdate['inbox_count_sent'] = UserInbox::whereIn('id_notification_expiry_point_sent_user', $dataIdSentUser)->count();
            $dataUpdate['whatsapp_count_sent'] = AutocrmWhatsappLog::whereIn('id_notification_expiry_point_sent_user', $dataIdSentUser)->count();

            NotificationExpiryPointSent::where('id_notification_expiry_point_sent', $idNotificationExpiryPointSent)->update($dataUpdate);
        }

        return true;
    }
}
