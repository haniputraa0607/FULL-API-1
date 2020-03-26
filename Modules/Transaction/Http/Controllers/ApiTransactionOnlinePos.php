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

use App\Http\Models\TransactionOnlinePos;
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
                    ->join('users', 'users.id', 'transactions.id_user')
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
        $trx = TransactionOnlinePos::where('id_transaction_online_pos',$id)->first();
        if(!$trx){
            return MyHelper::checkGet($trx);
        }

        $send = \App\Lib\ConnectPOS::create()->sendTransaction($trx->id_transaction);
        return MyHelper::checkUpdate($send);
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
