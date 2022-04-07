<?php

namespace Modules\Balance\Http\Controllers;

use App\Http\Models\AutocrmEmailLog;
use App\Http\Models\AutocrmPushLog;
use App\Http\Models\AutocrmSmsLog;
use App\Http\Models\AutocrmWhatsappLog;
use App\Http\Models\UserInbox;
use App\Jobs\AdjustmentPointUserJob;
use App\Jobs\NotificationExpiryPointSendJob;
use App\Lib\MyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\Outlet;

use DB;
use Modules\Balance\Http\Controllers\BalanceController;
use Modules\Balance\Entities\AdjustmentPointUser;
use Modules\Balance\Entities\NotificationExpiryPoint;
use Modules\Balance\Entities\NotificationExpiryPointSent;
use App\Http\Models\User;

class ApiExpiryPoint extends Controller
{
    function __construct() {
        ini_set('max_execution_time', 0);
        date_default_timezone_set('Asia/Jakarta');
        $this->setting_trx   = "Modules\Transaction\Http\Controllers\ApiSettingTransactionV2";
    }

    function settingExpiryPoint(Request $request){
        $post = $request->json()->all();

        if(empty($post)){
            $notif = Setting::where('key', 'date_send_notification_expiry_point')->first()['value_text']??[];
            $notif = (array)json_decode($notif);

            if(empty($notif)){
                $notif = ["date" => "", "time" => ""];
            }

            $adjustment = Setting::where('key', 'date_adjustment_point_user')->first()['value_text']??[];
            $adjustment = (array)json_decode($adjustment);

            if(empty($adjustment)){
                $adjustment = ["date" => "", "time" => ""];
            }

            $res = [
                'date_send_notification_expiry_point' => $notif,
                'date_adjustment_point_user' => $adjustment
            ];
            return response()->json(MyHelper::checkGet($res));
        }else{
            $notif = ["date" => $post['notification_date'], "time" => date('H:i', strtotime($post['notification_time']))];
            $save = Setting::updateOrCreate(['key' => 'date_send_notification_expiry_point'], ['value_text' => json_encode($notif)]);

            $adjustment = ["date" => $post['adjustment_date'], "time" => date('H:i', strtotime($post['adjustment_time']))];
            $save = Setting::updateOrCreate(['key' => 'date_adjustment_point_user'], ['value_text' => json_encode($adjustment)]);

            return response()->json(MyHelper::checkUpdate($save));
        }
    }

    public function sendNotificationExpiryPoint($processingNow = 0){
        $log = MyHelper::logCron('Notification Expiry Point');
        try {
            $status = DB::table('notification_expiry_point_sent_jobs')->count();
            if($processingNow == 1 && $status > 0){
                $log->success([0]);
                return ['status' => 'fail', 'message' => 'Sending expiry point still on progress'];
            }

            $currentDate = date('d');
            $currentTime = date('H:i');
            $settingDateTime = Setting::where('key', 'date_send_notification_expiry_point')->first()['value_text']??[];
            $settingDateTime = (array)json_decode($settingDateTime);
            if($processingNow == 1 || ($processingNow == 0 && !empty($settingDateTime['date']) && !empty($settingDateTime['time']) &&
                $currentDate == $settingDateTime['date'] && strtotime($currentTime) > strtotime($settingDateTime['time']))){

                $datas = NotificationExpiryPoint::join('users', 'users.id', 'notification_expiry_points.id_user')->get()->toArray();
                if(!empty($datas)){
                    $create = NotificationExpiryPointSent::create([
                        'total_customer' => count($datas),
                        'notification_expiry_point_date_sent' => date('Y-m-d H:i:s')
                    ]);
                    if($create){
                        $chunk = array_chunk($datas, 200);
                        foreach ($chunk as $data){
                            NotificationExpiryPointSendJob::dispatch(['data_notification' => $data, 'id_notification_expiry_point_sent' => $create['id_notification_expiry_point_sent']])->allOnConnection('notification_expiry_point_sent_queue');
                            NotificationExpiryPoint::whereIn('id_notification_expiry_point', array_column($data, 'id_notification_expiry_point'))->delete();
                        }
                    }
                }else{
                    $log->success([0]);
                    return ['status' => 'fail', 'message' => 'Data not available'];
                }
            }

            $log->success([count($datas??[])]);
            return ['status' => 'success'];
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function adjustmentPointUser($processingNow = 0){
        $log = MyHelper::logCron('Adjustment Point User');
        try {
            $status = DB::table('notification_expiry_point_sent_jobs')->count();
            if($processingNow == 1 && $status > 0){
                $log->success([0]);
                return ['status' => 'fail', 'message' => 'Processing adjustment point still on progress'];
            }

            $currentDate = date('d');
            $currentTime = date('H:i');
            $settingDateTime = Setting::where('key', 'date_adjustment_point_user')->first()['value_text']??[];
            $settingDateTime = (array)json_decode($settingDateTime);
            if($processingNow == 1 || ($processingNow == 0 && !empty($settingDateTime['date']) && !empty($settingDateTime['time']) &&
                $currentDate == $settingDateTime['date'] && strtotime($currentTime) > strtotime($settingDateTime['time']))){

                $datas = AdjustmentPointUser::join('users', 'users.id', 'adjustment_point_users.id_user')->get()->toArray();
                if(!empty($datas)){
                    $chunk = array_chunk($datas, 200);
                    foreach ($chunk as $data){
                        AdjustmentPointUserJob::dispatch(['data' => $data])->allOnConnection('notification_expiry_point_sent_queue');
                        AdjustmentPointUser::whereIn('id_adjustment_point_user', array_column($data, 'id_adjustment_point_user'))->delete();
                    }
                }else{
                    $log->success([0]);
                    return ['status' => 'fail', 'message' => 'Data not available'];
                }
            }

            $log->success([count($datas??[])]);
            return ['status' => 'success'];
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function reportExpiryPoint(Request $request){
        $post = $request->json()->all();

        $list = NotificationExpiryPointSent::orderBy('created_at', 'desc');

        if(isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])){
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $list->whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date);
        }

        if(isset($post['conditions']) && !empty($post['conditions'])){
            $rule = 'and';
            if(isset($post['rule'])){
                $rule = $post['rule'];
            }

            if($rule == 'and'){
                foreach ($post['conditions'] as $row){
                    if(isset($row['subject'])){
                        $list->where($row['subject'], $row['operator'], $row['parameter']);
                    }
                }
            }else{
                $list->where(function ($subquery) use ($post){
                    foreach ($post['conditions'] as $row){
                        if(isset($row['subject'])){
                            $subquery->orWhere($row['subject'], $row['operator'], $row['parameter']);
                        }
                    }
                });
            }
        }

        $list = $list->paginate(25);
        return response()->json(MyHelper::checkGet($list));
    }

    public function reportExpiryPointOutbox(Request $request){
        $post = $request->json()->all();

        $type = $post['type'];
        $id = $post['id_notification_expiry_point_sent_user'];

        switch ($type) {
            case 'email-outbox':
                $data = AutocrmEmailLog::join('users', 'users.id', 'autocrm_email_logs.id_user')
                    ->join('notification_expiry_point_sent_users', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent_user', 'autocrm_email_logs.id_notification_expiry_point_sent_user')
                    ->join('notification_expiry_point_sents', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent', 'notification_expiry_point_sents.id_notification_expiry_point_sent')
                    ->where('notification_expiry_point_sents.id_notification_expiry_point_sent', $id)
                    ->paginate(25);
                break;
            case 'sms-outbox':
                $data = AutocrmSmsLog::join('users', 'users.id', 'autocrm_sms_logs.id_user')
                    ->join('notification_expiry_point_sent_users', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent_user', 'autocrm_sms_logs.id_notification_expiry_point_sent_user')
                    ->join('notification_expiry_point_sents', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent', 'notification_expiry_point_sents.id_notification_expiry_point_sent')
                    ->where('notification_expiry_point_sents.id_notification_expiry_point_sent', $id)
                    ->paginate(25);
                break;
            case 'push-outbox':
                $data = AutocrmPushLog::join('users', 'users.id', 'autocrm_push_logs.id_user')
                    ->join('notification_expiry_point_sent_users', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent_user', 'autocrm_push_logs.id_notification_expiry_point_sent_user')
                    ->join('notification_expiry_point_sents', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent', 'notification_expiry_point_sents.id_notification_expiry_point_sent')
                    ->where('notification_expiry_point_sents.id_notification_expiry_point_sent', $id)
                    ->paginate(25);
                break;
            case 'inbox-outbox':
                $data = UserInbox::join('users', 'users.id', 'user_inboxes.id_user')
                    ->join('notification_expiry_point_sent_users', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent_user', 'user_inboxes.id_notification_expiry_point_sent_user')
                    ->join('notification_expiry_point_sents', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent', 'notification_expiry_point_sents.id_notification_expiry_point_sent')
                    ->where('notification_expiry_point_sents.id_notification_expiry_point_sent', $id)
                    ->paginate(25);
                break;
            case 'whatsapp-outbox':
                $data = AutocrmWhatsappLog::join('users', 'users.id', 'autocrm_whatsapp_logs.id_user')
                    ->join('notification_expiry_point_sent_users', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent_user', 'autocrm_whatsapp_logs.id_notification_expiry_point_sent_user')
                    ->join('notification_expiry_point_sents', 'notification_expiry_point_sent_users.id_notification_expiry_point_sent', 'notification_expiry_point_sents.id_notification_expiry_point_sent')
                    ->where('notification_expiry_point_sents.id_notification_expiry_point_sent', $id)
                    ->paginate(25);
                break;
        }

        return response()->json(MyHelper::checkGet($data));
    }

    public function processingNowNotificationExpiryPoint(){
        $processing = $this->sendNotificationExpiryPoint(1);
        return $processing;
    }

    public function processingNowAdjustmentPoint(){
        $processing = $this->adjustmentPointUser(1);
        return $processing;
    }
}
