<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Queue;
use App\Lib\Midtrans;

use App\Lib\MyHelper;
use App\Lib\PushNotificationHelper;
use App\Lib\classTexterSMS;
use App\Lib\classMaskingJson;
use App\Lib\apiwha;
use Validator;
use Hash;
use DB;
use App\Lib\MailQueue as Mail;

use App\Jobs\CronBalance;

use App\Http\Models\Transaction;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionPickup;
use App\Http\Models\User;
use App\Http\Models\LogBalance;
use App\Http\Models\AutocrmEmailLog;
use App\Http\Models\Autocrm;
use App\Http\Models\Setting;
use App\Http\Models\LogPoint;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use Modules\UserRating\Entities\UserRatingLog;

class ApiCronTrxController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        // ini_set('max_execution_time', 600);
        ini_set('max_execution_time', 0);
        $this->autocrm = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->balance  = "Modules\Balance\Http\Controllers\BalanceController";
        $this->voucher  = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->promo_campaign	= "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->getNotif = "Modules\Transaction\Http\Controllers\ApiNotification";
        $this->trx    = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->nobu_controller    = "Modules\Transaction\Http\Controllers\ApiNobuController";
    }

    public function cron(Request $request)
    {
        $log = MyHelper::logCron('Cancel Transaction');
        try {
            $crossLine = date('Y-m-d H:i:s', strtotime('- 3days'));
            $dateLine  = date('Y-m-d H:i:s', strtotime('- 1days'));
            $now       = date('Y-m-d H:i:s');
            $expired   = date('Y-m-d H:i:s',strtotime('- 5minutes'));

            $getTrx = Transaction::where('transaction_payment_status', 'Pending')
                ->where('transaction_date', '<=', $expired)
                ->where('trasaction_payment_type', '<>', 'Nobu')
                ->where(function ($query) {
                    $query->whereNull('latest_reversal_process')
                        ->orWhere('latest_reversal_process', '<', date('Y-m-d H:i:s', strtotime('- 5 minutes')));
                })
                ->get();

            foreach ($getTrx as $i => $trans) {
                $checkMultiple = TransactionMultiplePayment::where('id_transaction', $trans->id_transaction)->where('type','Nobu')->first();
                if($checkMultiple){
                    unset($getTrx[$i]);
                }
            }   
            
            Transaction::fillLatestReversalProcess($getTrx);

            if (empty($getTrx)) {
                $log->success('empty');
                return response()->json(['empty']);
            }
            $count = 0;
            foreach ($getTrx as $key => $singleTrx) {
                $singleTrx->load('outlet_name');

                $productTrx = TransactionProduct::where('id_transaction', $singleTrx->id_transaction)->get();
                if (empty($productTrx)) {
                    $singleTrx->clearLatestReversalProcess();
                    continue;
                }

                $user = User::where('id', $singleTrx->id_user)->first();
                if (empty($user)) {
                    $singleTrx->clearLatestReversalProcess();
                    continue;
                }

                $checkMultiple = TransactionMultiplePayment::where('id_transaction', $singleTrx->id_transaction)->get()->pluck('type')->toArray();

                if($singleTrx->trasaction_payment_type == 'Midtrans') {
                    $midtransStatus = Midtrans::status($singleTrx->id_transaction);
                    if ((($midtransStatus['status'] ?? false) == 'fail' && ($midtransStatus['messages'][0] ?? false) == 'Midtrans payment not found') || in_array(($midtransStatus['transaction_status'] ?? false), ['deny', 'cancel', 'expire', 'failure']) || ($midtransStatus['status_code'] ?? false) == '404') {
                        $connectMidtrans = Midtrans::expire($singleTrx->transaction_receipt_number);
                    } else {
                        continue;
                    }
                }elseif($singleTrx->trasaction_payment_type == 'Ipay88') {
                    $trx_ipay = TransactionPaymentIpay88::where('id_transaction',$singleTrx->id_transaction)->first();

                    if ($trx_ipay && strtolower($trx_ipay->payment_method) == 'credit card' && $singleTrx->transaction_date > date('Y-m-d H:i:s', strtotime('- 15minutes'))) {
                        $singleTrx->clearLatestReversalProcess();
                        continue;
                    }

                    $update = \Modules\IPay88\Lib\IPay88::create()->update($trx_ipay?:$singleTrx->id_transaction,[
                        'type' =>'trx',
                        'Status' => '0',
                        'requery_response' => 'Cancelled by cron'
                    ],false,false);
                    continue;                
                }elseif($singleTrx->trasaction_payment_type == 'Nobu' || in_array('Nobu',$checkMultiple)) {
                    continue;
                }

                // $detail = $this->getHtml($singleTrx, $productTrx, $user->name, $user->phone, $singleTrx->created_at, $singleTrx->transaction_receipt_number);

                // $autoCrm = app($this->autocrm)->SendAutoCRM('Transaction Online Cancel', $user->phone, ['date' => $singleTrx->created_at, 'status' => $singleTrx->transaction_payment_status, 'name'  => $user->name, 'id' => $singleTrx->transaction_receipt_number, 'receipt' => $detail, 'id_reference' => $singleTrx->transaction_receipt_number]);
                // if (!$autoCrm) {
                //     continue;
                // }

                DB::begintransaction();

                MyHelper::updateFlagTransactionOnline($singleTrx, 'cancel', $user);

                $singleTrx->transaction_payment_status = 'Cancelled';
                $singleTrx->void_date = $now;
                $update = $singleTrx->save();

                if (!$update) {
                    DB::rollBack();
                    $singleTrx->clearLatestReversalProcess();
                    continue;
                }

                //reversal balance
                $logBalance = LogBalance::where('id_reference', $singleTrx->id_transaction)->where('source', 'Transaction')->where('balance', '<', 0)->get();
                foreach($logBalance as $logB){
                    $reversal = app($this->balance)->addLogBalance( $singleTrx->id_user, abs($logB['balance']), $singleTrx->id_transaction, 'Reversal', $singleTrx->transaction_grandtotal);
    	            if (!$reversal) {
    	            	DB::rollBack();
                        $singleTrx->clearLatestReversalProcess();
    	            	continue;
    	            }
                    $usere= User::where('id',$singleTrx->id_user)->first();
                    $send = app($this->autocrm)->SendAutoCRM('Transaction Failed Point Refund', $usere->phone,
                        [
                            "outlet_name"       => $singleTrx->outlet_name->outlet_name,
                            "transaction_date"  => $singleTrx->transaction_date,
                            'id_transaction'    => $singleTrx->id_transaction,
                            'receipt_number'    => $singleTrx->transaction_receipt_number,
                            'received_point'    => (string) abs($logB['balance'])
                        ]
                    );
                }

                // delete promo campaign report
                if ($singleTrx->id_promo_campaign_promo_code) {
                	$update_promo_report = app($this->promo_campaign)->deleteReport($singleTrx->id_transaction, $singleTrx->id_promo_campaign_promo_code);
                	if (!$update_promo_report) {
    	            	DB::rollBack();
    	            	continue;
    	            }
                }

                // return voucher
                $update_voucher = app($this->voucher)->returnVoucher($singleTrx->id_transaction);
                if (!$update_voucher) {
                	DB::rollBack();
                	continue;
                }
                $count++;
                DB::commit();

            }

            $log->success([$count]);
            return response()->json([$count]);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function checkSchedule()
    {
        $log = MyHelper::logCron('Check Schedule');
        try {
            $result = [];

            $data = LogBalance::orderBy('id_log_balance', 'DESC')->whereNotNull('enc')->get()->toArray();

            foreach ($data as $key => $val) {
                $dataHash = [
                    'id_log_balance'                 => $val['id_log_balance'],
                    'id_user'                        => $val['id_user'],
                    'balance'                        => $val['balance'],
                    'balance_before'                 => $val['balance_before'],
                    'balance_after'                  => $val['balance_after'],
                    'id_reference'                   => $val['id_reference'],
                    'source'                         => $val['source'],
                    'grand_total'                    => $val['grand_total'],
                    'ccashback_conversion'           => $val['ccashback_conversion'],
                    'membership_level'               => $val['membership_level'],
                    'membership_cashback_percentage' => $val['membership_cashback_percentage']
                ];


                $encodeCheck = json_encode($dataHash);

                if (MyHelper::decrypt2019($val['enc']) != $encodeCheck) {
                    $result[] = $val;
                }
            }

            if (!empty($result)) {
                $crm = Autocrm::where('autocrm_title','=','Cron Transaction')->with('whatsapp_content')->first();
                if (!empty($crm)) {
                    if(!empty($crm['autocrm_forward_email'])){
                        $exparr = explode(';',str_replace(',',';',$crm['autocrm_forward_email']));
                        foreach($exparr as $email){
                            $n   = explode('@',$email);
                            $name = $n[0];

                            $to      = $email;

                            $content = str_replace('%table_trx%', '', $crm['autocrm_forward_email_content']);

                            $content .= $this->html($result);
                            // return response()->json($this->html($result));
                            // get setting email
                            $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                            $setting = array();
                            foreach ($getSetting as $key => $value) {
                                $setting[$value['key']] = $value['value'];
                            }

                            $subject = $crm['autocrm_forward_email_subject'];

                            $data = array(
                                'customer'     => $name,
                                'html_message' => $content,
                                'setting'      => $setting
                            );

                            Mail::send('emails.test', $data, function($message) use ($to,$subject,$name,$setting)
                            {
                                $message->to($to, $name)->subject($subject);
    							if(env('MAIL_DRIVER') == 'mailgun'){
    								$message->trackClicks(true)
    										->trackOpens(true);
    							}
                                if(!empty($setting['email_from']) && !empty($setting['email_sender'])){
                                    $message->from($setting['email_sender'], $setting['email_from']);
                                }else if(!empty($setting['email_sender'])){
                                    $message->from($setting['email_sender']);
                                }

                                if(!empty($setting['email_reply_to'])){
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                }

                                if(!empty($setting['email_cc']) && !empty($setting['email_cc_name'])){
                                    $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                }

                                if(!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])){
                                    $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                }
                            });

                            // $logData = [];
                            // $logData['id_user'] = 999999999;
                            // $logData['email_log_to'] = $email;
                            // $logData['email_log_subject'] = $subject;
                            // $logData['email_log_message'] = $content;

                            // $logs = AutocrmEmailLog::create($logData);
                        }
                    }
                }
            }

            if (!empty($result)) {
                $log->fail(['data_error' => count($result), 'message' => 'Check your email']);
                return ['status' => 'success', 'data_error' => count($result), 'message' => 'Check your email'];
            } else {
                $log->success(['data_error' => count($result)]);
                return ['status' => 'success', 'data_error' => count($result)];
            }
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function html($data)
    {
        $label = '';
        foreach ($data as $key => $value) {
            // $real = json_decode(MyHelper::decryptkhususnew($value['enc']));
            $real = json_decode(MyHelper::decrypt2019($value['enc']));
            // dd($real->source);
            $user = User::where('id', $value['id_user'])->first();
            if ($value['source'] == 'Transaction' || $value['source'] == 'Rejected Order' || $value['source'] == 'Reverse Point from Rejected Order') {
                $detail = Transaction::with('outlet', 'transaction_pickup')->where('id_transaction', $value['id_reference'])->first();

                $label .= '<tr>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.($key+1).'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Real</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$user['name'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->source.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.date('Y-m-d', strtotime($detail['created_at'])).'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$detail['transaction_receipt_number'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$detail['transaction_pickup']['order_id'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->balance.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->balance_before.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->balance_after.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->grand_total.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->membership_level.'</td>
  </tr>
  <tr>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Change</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$user['name'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['source'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.date('Y-m-d', strtotime($detail['created_at'])).'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$detail['transaction_receipt_number'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$detail['transaction_pickup']['order_id'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['balance'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['balance_before'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['balance_after'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['grand_total'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['membership_level'].'</td>
  </tr>';
            } else {
                $label .= '<tr>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.($key+1).'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Real</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$user['name'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['source'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.date('Y-m-d', strtotime($value['created_at'])).'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->balance.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->balance_before.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->balance_after.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->grand_total.'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$real->membership_level.'</td>
  </tr>
  <tr>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Change</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$user['name'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['source'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.date('Y-m-d', strtotime($value['created_at'])).'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">-</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['balance'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['balance_before'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['balance_after'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['grand_total'].'</td>
    <td style="border: 1px solid #dddddd;text-align: left;padding: 8px;">'.$value['membership_level'].'</td>
  </tr>';
            }
        }
        return '<table style="font-family: arial, sans-serif;border-collapse: collapse;width: 100%;border: 1px solid #dddddd;">
  <tr>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">No</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Ket Data</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Name</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Type</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Date</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Receipt Number</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Order ID</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Get Point</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Point Before</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Point After</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Grand Total</th>
    <th style="border: 1px solid #dddddd;text-align: left;padding: 8px;">Membership Level</th>
  </tr>
  '.$label.'
</table>';
    }

    public function completeTransactionPickup(){
        $log = MyHelper::logCron('Complete Transaction Pickup');
        try {
            $trxs = Transaction::whereDate('transaction_date', '<', date('Y-m-d'))
                ->where('trasaction_type', 'Pickup Order')
                ->join('transaction_pickups','transaction_pickups.id_transaction','=','transactions.id_transaction')
                ->whereNull('taken_at')
                ->whereNull('reject_at')
                ->whereNull('taken_by_system_at')
                ->get();
            $idTrx = [];
            // apply point if ready_at null
            foreach ($trxs as $newTrx) {
                $idTrx[] = $newTrx->id_transaction;
                if(!empty($newTrx->ready_at) || $newTrx->transaction_payment_status != 'Completed'){
                    continue;
                }
                $newTrx->load('user.memberships', 'outlet', 'productTransaction', 'transaction_vouchers','promo_campaign_promo_code','promo_campaign_promo_code.promo_campaign');

                $checkType = TransactionMultiplePayment::where('id_transaction', $newTrx->id_transaction)->get()->toArray();
                $column = array_column($checkType, 'type');

                $use_referral = optional(optional($newTrx->promo_campaign_promo_code)->promo_campaign)->promo_type == 'Referral';

                MyHelper::updateFlagTransactionOnline($newTrx, 'success', $newTrx->user);
                if ((!in_array('Balance', $column) || $use_referral) && $newTrx->user) {

                    $promo_source = null;
                    if ( $newTrx->id_promo_campaign_promo_code || $newTrx->transaction_vouchers || $use_referral) 
                    {
                        if ( $newTrx->id_promo_campaign_promo_code ) {
                            $promo_source = 'promo_code';
                        }
                        elseif ( ($newTrx->transaction_vouchers[0]->status??false) == 'success' )
                        {
                            $promo_source = 'voucher_online';
                        }
                    }

                    if( app($this->trx)->checkPromoGetPoint($promo_source) || $use_referral)
                    {
                        $savePoint = app($this->getNotif)->savePoint($newTrx);
                    }
                }

                /**
                 * taken by system tidak menampilkan popup
                 */
                // // show rate popup
                // if ($newTrx->id_user) {
                //     UserRatingLog::updateOrCreate([
                //         'id_user' => $newTrx->id_user,
                //         'id_transaction' => $newTrx->id_transaction
                //     ],[
                //         'refuse_count' => 0,
                //         'last_popup' => date('Y-m-d H:i:s', time() - MyHelper::setting('popup_min_interval', 'value', 900))
                //     ]);
                // }
            }
            //update taken_by_sistem_at
            $dataTrx = TransactionPickup::whereIn('id_transaction', $idTrx)
                                        ->update(['taken_by_system_at' => date('Y-m-d 00:00:00')]);
            $log->success();
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function cancelTransactionIPay()
    {
        // 15 minutes before
        $max_time = date('Y-m-d H:i:s',time()-900);
        $trxs = Transaction::select('id_transaction')->where([
            'trasaction_payment_type' => 'Ipay88',
            'transaction_payment_status' => 'Pending'
        ])->where('transaction_date','<',$max_time)->take(50)->pluck('id_transaction');
        foreach ($trxs as $id_trx) {
            $trx_ipay = TransactionPaymentIpay88::where('id_transaction',$id_trx)->first();
            $update = \Modules\IPay88\Lib\IPay88::create()->update($trx_ipay?:$id_trx,[
                'type' =>'trx',
                'Status' => '0',
                'requery_response' => 'Cancelled by cron'
            ],false,false);
        }
    }

    /**
     * Cron check status gosend
     */
    public function cronCancelDriverNotFound()
    {
        $log = MyHelper::logCron('Cancel Transaction Driver Not Found');
        $minutes = (int) MyHelper::setting('auto_reject_time','value', 15)*60;
        try {
            $trxs = Transaction::select('transactions.*')
                ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
                ->join('transaction_pickup_go_sends', 'transaction_pickup_go_sends.id_transaction_pickup', 'transaction_pickups.id_transaction_pickup')
                ->whereNull('taken_at')
                ->whereNull('reject_at')
                ->where('latest_status', 'no_driver')
                ->whereDate('transactions.transaction_date', date('Y-m-d'))
                ->where('transaction_pickup_go_sends.stop_booking_at', '<', date('Y-m-d H:i:s', time() - $minutes))
                ->get();
            $errors = [];
            $success = 0;
            foreach ($trxs as $trx) {
                $cancel = $trx->cancelOrder('Auto cancel order by system [no driver]', $errors);
                if ($cancel) {
                    $trx->update(['is_auto_cancel' => 1]);
                    $success++;
                }
            }
            $log->success([
                'total' => $trxs->count(),
                'success' => $success
            ]);
            return response()->json(['success']);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
            return ['status' => 'fail'];
        }
    }
}
