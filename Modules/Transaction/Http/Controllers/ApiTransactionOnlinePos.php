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
        $result = TransactionOnlinePOS::join('transactions', 'transactions.id_transaction', 'transactions_online_pos.id_transaction')
                    ->join('users', 'users.id', 'transactions.id_user')
                    ->select('transactions_online_pos.*', 'transactions.transaction_receipt_number', 'users.name', 'users.phone');

        $countTotal = null;

        if ($keyword = ($request->search['value']??false)) {
            $countTotal = $result->count();
            $result->where(function ($query) use ($keyword) {
                $query->where('transactions.transaction_receipt_number', 'like', '%'.$keyword.'%');
            });
        }

        if($request->rule){
            $this->filterList($result,$request->rule,$request->operator?:'and');
        }

        if (is_array($orders = $request->order)) {
            $columns = [
                null,
                null,
                'transaction_receipt_number',
                'users.name',
                'users.phone',
                null,
                null,
                'count_retry'
            ];
            foreach ($orders as $column) {
                if ($colname = ($columns[$column['column']]??false)) {
                    $result->orderBy($colname, $column['dir']);
                }
            }
        }

        $result->orderBy('transaction_date','desc');

        if ($request->page) {
            $result = $result->paginate($request->length?:15)->toArray();
            if (is_null($countTotal)) {
                $countTotal = $result['total'];
            }
            // needed for datatables
            $result['recordsTotal'] = $countTotal;
        } else {
            $result = $result->get();
        }
        return MyHelper::checkGet($result);
    }

    public function filterList($model,$rule,$operator='and'){
        $newRule=[];
        $where=$operator=='and'?'where':'orWhere';
        foreach ($rule as $var) {
            $var1=['operator'=>$var['operator']??'=','parameter'=>$var['parameter']??null];
            if($var1['operator']=='like'){
                $var1['parameter']='%'.$var1['parameter'].'%';
            }
            $newRule[$var['subject']][]=$var1;
        }
        $inner=['transaction_receipt_number', 'transaction_date', 'success_retry_status', 'id_outlet', 'name', 'phone'];
        foreach ($inner as $col_name) {
            if($rules=$newRule[$col_name]??false){
                foreach ($rules as $rul) {
                    $model->$where($col_name,$rul['operator'],$rul['parameter']);
                }
            }
        }
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
