<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\Autocrm;
use App\Http\Models\LogActivitiesPosTransactionsOnline;
use App\Http\Models\Setting;
use Illuminate\Pagination\Paginator;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request as RequestGuzzle;
use Guzzle\Http\Message\Response as ResponseGuzzle;
use Guzzle\Http\Exception\ServerErrorResponseException;

use App\Http\Models\TransactionOnlinePOS;
use App\Lib\MyHelper;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use DB;
use Mailgun;


class ApiTransactionOnlinePOS extends Controller
{
    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm       = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->url_oauth  = env('URL_OUTLET_OAUTH');
        $this->url_kirim  = env('URL_OUTLET_ORDER');
        $this->oauth_id  = env('OUTLET_OAUTH_ID');
        $this->oauth_secret  = env('OUTLET_OAUTH_SECRET');
    }

    public function listTransaction(Request $request) {
        $post = $request->json()->all();

        if(isset($post['start'])){
            $start = $post['start'];
            $length = $post['length'];
        }

        $getData = TransactionOnlinePOS::join('transactions', 'transactions.id_transaction', 'transactions_online_pos.id_transaction')
                    ->join('users', 'users.id', 'transactions.id_transaction')
                    ->select('transactions_online_pos.*', 'transactions.transaction_receipt_number', 'users.name', 'users.phone');

        $total = $getData->count();
        $dataReport = $getData->select(DB::raw("transactions_online_pos.id_transaction_online_pos as '0', transactions_online_pos.success_retry_status as '1', transactions.transaction_receipt_number as '2', users.name as '3', users.phone as '4', transactions_online_pos.request as '5', 
                        transactions_online_pos.response as '6', transactions_online_pos.count_retry as '7'"))
            ->skip($start)->take($length)->get()->toArray();

        $result = [
            'status' => 'success',
            'result' => $dataReport,
            'total' => $total
        ];

        return response()->json($result);
    }

    public function resendTransaction(Request $request){
        $post = $request->json()->all();
        $id = $post['id_transaction_online_pos'];

        $getData = TransactionOnlinePOS::where('id_transaction_online_pos', $id)
            ->join('transactions', 'transactions.id_transaction', 'transactions_online_pos.id_transaction')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->select('outlets.outlet_code', 'transactions_online_pos.*')
            ->first();

        if($getData){
            $client = new Client;
            $params = array();
            $params['client_id'] = $this->oauth_id;
            $params['client_secret'] = $this->oauth_secret;

            $content = array(
                'headers' => [
                    'Content-Type'  => 'application/x-www-form-urlencoded'
                ],
                'form_params' => $params
            );

            $dataLog = [
                'url' 		        => $this->url_oauth,
                'subject' 		    => 'POS Get Bearer',
                'outlet_code' 	    => $getData['outlet_code'],
                'user' 		        => null,
                'request' 		    => json_encode($content),
                'response_status'   => null,
                'ip' 		        => $request->ip(),
                'useragent' 	    => $request->header('user-agent')
            ];

            try {
                $response =  $client->request('POST', $this->url_oauth, $content);

                $res = json_decode($response->getBody(), true);

                //insert log
                $dataLog['response'] = json_encode($res);
                $log = LogActivitiesPosTransactionsOnline::create($dataLog);
            }
            catch (\GuzzleHttp\Exception\RequestException $e) {
                try{

                    if($e->getResponse()){
                        $response = $e->getResponse()->getBody()->getContents();

                        $error = json_decode($response, true);

                        //insert log
                        $dataLog['response'] = json_encode($error);
                        $log = LogActivitiesPosTransactionsOnline::create($dataLog);
                        if(!$error) {
                            return $e->getResponse()->getBody();
                        } else {
                            return $error;
                        }
                    } else{
                        //insert log
                        $dataLog['response_status'] = 'fail';
                        $dataLog['response'] = 'Check your internet connection.';
                        $log = LogActivitiesPosTransactionsOnline::create($dataLog);

                        return ['status' => 'fail', 'messages' => 'Check your internet connection.'];
                    }

                } catch(Exception $e) {
                    //insert log
                    $dataLog['response_status'] = 'fail';
                    $dataLog['response'] = 'Check your internet connection.';
                    $log = LogActivitiesPosTransactionsOnline::create($dataLog);
                    return ['status' => 'fail', 'messages' => 'Check your internet connection.'];
                }
            }

            if($res){
                $client = new Client;
                $request = json_decode($getData['request']);
                $requestformat = json_encode($request['json']);

                $content = array(
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization'  => $res['type'].' '.$res['token'],
                    ],
                    'json' => $requestformat
                );

                $dataLog = [
                    'url' 		        => $this->url_kirim,
                    'subject' 		    => 'POS Send Data',
                    'outlet_code' 	    => $getData['outlet_code'],
                    'user' 		        => null,
                    'request' 		    => json_encode($content),
                    'response_status'   => null,
                    'ip' 		        => $request->ip(),
                    'useragent' 	    => $request->header('user-agent')
                ];

                try {
                    $response =  $client->request('POST', $this->url_kirim, $content);
                    $res = json_decode($response->getBody(), true);

                    //insert log
                    $dataLog['response'] = json_encode($res);
                    $log = LogActivitiesPosTransactionsOnline::create($dataLog);
                    if(isset($res['status']) && $res['status'] === true){
                        $count = $getData['count_retry'] + 1;
                        TransactionOnlinePOS::where('id_transaction_online_pos',$id)->update(['count_retry' => $count, 'success_retry_status' => 1]);
                    }else{
                        $count = $getData['count_retry'] + 1;
                        TransactionOnlinePOS::where('id_transaction_online_pos',$id)->update(['count_retry' => $count]);
                    }
                    return $res;
                }
                catch (\GuzzleHttp\Exception\RequestException $e) {
                    try{

                        if($e->getResponse()){
                            $response = $e->getResponse()->getBody()->getContents();

                            $error = json_decode($response, true);

                            //insert log
                            $dataLog['response'] = json_encode($error);
                            $log = LogActivitiesPosTransactionsOnline::create($dataLog);
                            if(!$error) {
                                return $e->getResponse()->getBody();
                            } else {
                                return $error;
                            }
                        } else{
                            //insert log
                            $dataLog['response_status'] = 'fail';
                            $dataLog['response'] = 'Check your internet connection.';
                            $log = LogActivitiesPosTransactionsOnline::create($dataLog);
                            return ['status' => 'fail', 'messages' => 'Check your internet connection.'];
                        }

                    } catch(Exception $e) {
                        //insert log
                        $dataLog['response_status'] = 'fail';
                        $dataLog['response'] = 'Check your internet connection.';
                        $log = LogActivitiesPosTransactionsOnline::create($dataLog);
                        return ['status' => 'fail', 'messages' => 'Check your internet connection.'];
                    }
                }
            }
        }else{
            return ['status' => 'fail', 'messages' => 'Data not found'];
        }
    }

    public function sendNotification(Request $request){
        $getData = TransactionOnlinePOS::join('transactions', 'transactions.id_transaction', 'transactions_online_pos.id_transaction')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->join('users', 'users.id', 'transactions.id_user')
            ->where('send_email_status', 0)
            ->select('transactions_online_pos.id_transaction_online_pos', 'transactions.*', 'users.phone', 'outlets.outlet_name')
            ->get()->toArray();

        $getAutocrm = Autocrm::where('autocrm_title', 'Transaction Online Failed Pos')->first();
        if($getAutocrm['autocrm_email_toogle'] == 1 && !is_null($getAutocrm['autocrm_forward_email'])){
            foreach ($getData as $dt){
                $recipient_email = explode(',', str_replace(' ', ',', str_replace(';', ',', $getAutocrm['autocrm_forward_email'])));
                foreach($recipient_email as $key => $recipient){
                    if($recipient != ' ' && $recipient != ""){
                        $to		 = $recipient;
                        $subject = app($this->autocrm)->TextReplace($getAutocrm['email_subject'], $dt['phone'], ['receipt_number' => $dt['transaction_receipt_number'], 'outlet_name' => $dt['outlet_name'], 'transaction_date' => date('d F Y H:i', strtotime($dt['transaction_date']))]);
                        $content = app($this->autocrm)->TextReplace($getAutocrm['email_content'], $dt['phone'], ['receipt_number' => $dt['transaction_receipt_number'], 'outlet_name' => $dt['outlet_name'], 'transaction_date' => date('d F Y H:i', strtotime($dt['transaction_date']))]);

                        //get setting email
                        $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                        $setting = array();
                        foreach ($getSetting as $key => $value) {
                            $setting[$value['key']] = $value['value'];
                        }

                        $em_arr = explode('@',$recipient);
                        $name = ucwords(str_replace("_"," ", str_replace("-"," ", str_replace("."," ", $em_arr[0]))));

                        $data = array(
                            'customer' => $name,
                            'html_message' => $content,
                            'setting' => $setting
                        );

                        try{
                            $send = Mailgun::send('emails.test', $data, function($message) use ($to,$subject,$name,$setting)
                            {
                                $message->to($to, $name)->subject($subject)
                                    ->trackClicks(true)
                                    ->trackOpens(true);
                                if(!empty($setting['email_from']) && !empty($setting['email_sender'])){
                                    $message->from($setting['email_from'], $setting['email_sender']);
                                }else if(!empty($setting['email_from'])){
                                    $message->from($setting['email_from']);
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

                            if(isset($send->status) && $send->status == 200){
                                TransactionOnlinePOS::where('id_transaction_online_pos',$dt['id_transaction_online_pos'])->update(['send_email_status' => 1]);
                            }
                        }catch(\Exception $e){

                        }
                    }
                }
            }
        }

        return 'success';
    }

    public function autoresponse(Request $request){
        $post = $request->json()->all();

        if($post){
            $upate = Autocrm::where('autocrm_title', 'Transaction Online Failed Pos')->update($post);
            return response()->json(MyHelper::checkUpdate($upate));
        }else{
            $getData = Autocrm::where('autocrm_title', 'Transaction Online Failed Pos')->first();

            return response()->json(MyHelper::checkGet($getData));
        }
    }
}
