<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Transaction;
use App\Http\Models\DealsUser;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\Configs;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentBalance;

use Modules\UserRating\Entities\UserRating;
use Modules\PointInjection\Entities\PointInjection;

use App\Lib\MyHelper;

class ApiHistoryController extends Controller
{
    public function historyAll(Request $request)
    {

        $post = $request->json()->all();
        // return $post;
        $id = $request->user()->id;
        $order = 'new';
        $page = 1;

        if (!isset($post['pickup_order'])) {
            $post['pickup_order'] = null;
        }

        if (!isset($post['delivery_order'])) {
            $post['delivery_order'] = null;
        }

        if (!isset($post['online_order'])) {
            $post['online_order'] = null;
        }

        if (!isset($post['offline_order'])) {
            $post['offline_order'] = null;
        }

        if (!isset($post['pending'])) {
            $post['pending'] = null;
        }

        if (!isset($post['paid'])) {
            $post['paid'] = null;
        }

        if (!isset($post['completed'])) {
            $post['completed'] = null;
        }

        if (!isset($post['cancel'])) {
            $post['cancel'] = null;
        }

        if (!isset($post['brand'])) {
            $post['brand'] = null;
        }

        if (!isset($post['outlet'])) {
            $post['outlet'] = null;
        }

        if (!isset($post['use_point'])) {
            $post['use_point'] = null;
        }
        if (!isset($post['earn_point'])) {
            $post['earn_point'] = null;
        }
        if (!isset($post['offline_order'])) {
            $post['offline_order'] = null;
        }
        if (!isset($post['voucher'])) {
            $post['voucher'] = null;
        }

        $transaction = $this->transaction($post, $id);

        $balance = [];
        $cofigBalance = Configs::where('config_name', 'balance')->first();
        if ($cofigBalance && $cofigBalance->is_active == '1') {
            $balance = $this->balance($post, $id);
        }

        $point = [];
        $cofigPoint = Configs::where('config_name', 'point')->first();
        if ($cofigPoint && $cofigPoint->is_active == '1') {
            $point = $this->point($post, $id);
        }
        // $voucher = [];

        if (!is_null($post['oldest'])) {
            $order = 'old';
        }

        if (!is_null($post['newest'])) {
            $order = 'new';
        }

        if (!is_null($request->get('page'))) {
            $page = $request->get('page');
        }

        $next_page = $page + 1;

        $merge = array_merge($transaction, $balance);
        $merge = array_merge($merge, $point);
        // return $merge;
        $sortTrx = $this->sorting($merge, $order, $page);

        $check = MyHelper::checkGet($sortTrx);
        if (count($merge) > 0) {
            $result['status'] = 'success';
            $result['current_page']  = $page;
            $result['data']          = $sortTrx['data'];
            $result['total']         = count($merge);
            $result['next_page_url'] = null;

            if ($sortTrx['status'] == true) {
                $result['next_page_url'] = ENV('APP_API_URL') . '/api/transaction/history?page=' . $next_page;
            }
        } else {
            $result['status'] = 'fail';
            $result['messages'] = ['empty'];
        }

        return response()->json($result);
    }

    public function historyTrx(Request $request,$mode='group')
    {

        $post = $request->json()->all();
        // return $post;
        $id = $request->user()->id;
        $order = 'new';
        $page = 1;

        if (!isset($post['pickup_order'])) {
            $post['pickup_order'] = null;
        }

        if (!isset($post['delivery_order'])) {
            $post['delivery_order'] = null;
        }

        if (!isset($post['online_order'])) {
            $post['online_order'] = null;
        }

        if (!isset($post['offline_order'])) {
            $post['offline_order'] = null;
        }

        if (!isset($post['pending'])) {
            $post['pending'] = null;
        }

        if (!isset($post['brand'])) {
            $post['brand'] = null;
        }

        if (!isset($post['outlet'])) {
            $post['outlet'] = null;
        }

        if (!isset($post['paid'])) {
            $post['paid'] = null;
        }

        if (!isset($post['completed'])) {
            $post['completed'] = null;
        }

        if (!isset($post['cancel'])) {
            $post['cancel'] = null;
        }

        //for default status, completed
        if($post['pending'] == null && $post['paid'] == null && $post['completed'] == null && $post['cancel'] == null){
            $post['completed'] = '1';
        }

        if (!isset($post['buy_voucher'])) {
            $post['buy_voucher'] = null;
        }

        if (!isset($post['voucher'])) {
            $post['voucher'] = null;
        }

        $transaction = [];
        $voucher = [];

        if($post['online_order'] == 1 || $post['offline_order'] == 1 || $post['delivery_order'] == 1 || $post['pickup_order'] == 1 || ($post['online_order'] == null && $post['offline_order'] == null && $post['delivery_order'] == null && $post['pickup_order'] == null && $post['voucher'] == null)) {
            $transaction = $this->transaction($post, $id);
        }
        if($post['voucher'] == 1 || ($post['online_order'] == null && $post['offline_order'] == null && $post['delivery_order'] == null && $post['pickup_order'] == null && $post['voucher'] == null)){
            $voucher = $this->voucher($post, $id);
        }

        if (!is_null($post['oldest'])) {
            $order = 'old';
        }

        if (!is_null($post['newest'])) {
            $order = 'new';
        }

        if (!is_null($request->get('page'))) {
            $page = $request->get('page');
        }

        $next_page = $page + 1;

        $merge = array_merge($transaction, $voucher);
        if (count($merge) > 0) {
            $sortTrx = $this->sorting($merge, $order, $page);
            if($mode=='group'){
                $sortTrx['data'] = $this->groupIt($sortTrx['data'],'date',function($key,&$val){
                    $explode = explode(' ',$key);
                    $val['time'] = $explode[1];
                    return $explode[0];
                },function($key){
                    return MyHelper::dateFormatInd($key,true,false,false);
                });
            }
            $check = MyHelper::checkGet($sortTrx);

            $result['current_page']  = $page;
            $result['data']          = $sortTrx['data'];
            $result['total']         = count($merge);
            $result['next_page_url'] = null;

            if ($sortTrx['status'] == true) {
                $result['next_page_url'] = ENV('APP_API_URL') . '/api/transaction/history-trx?page=' . $next_page;
            }
            $result = MyHelper::checkGet($result);
        } else {

            if(
                $request->json('date_start') ||
                $request->json('date_end') ||
                $request->json('outlet') ||
                $request->json('brand')
            ){
                $resultMessage = 'Data Not Found';
            }else{
                $resultMessage = "You don't have transaction history";
            }

            $result['status'] = 'fail';
            $result['messages'] = [$resultMessage];
        }

        return response()->json($result);
    }

    public function historyTrxOnGoing(Request $request,$mode='group')
    {

        $post = $request->json()->all();
        // return $post;
        $id = $request->user()->id;
        $order = 'new';
        $page = 1;

        $transaction = $this->transactionOnGoingPickup($post, $id);

        if (!is_null($post['oldest'])) {
            $order = 'old';
        }

        if (!is_null($post['newest'])) {
            $order = 'new';
        }

        if (!is_null($request->get('page'))) {
            $page = $request->get('page');
        }

        $next_page = $page + 1;

        if (count($transaction) > 0) {
            $sortTrx = $this->sorting($transaction, $order, $page);
            if($mode=='group'){
                $sortTrx['data'] = $this->groupIt($sortTrx['data'],'date',function($key,&$val){
                    $explode = explode(' ',$key);
                    $val['time'] = substr($explode[1],0,5);
                    return $explode[0];
                },function($key){
                    return MyHelper::dateFormatInd($key,true,false,false);
                });
            }
            $check = MyHelper::checkGet($sortTrx);
            $result['current_page']  = $page;
            $result['data']          = $sortTrx['data'];
            $result['total']         = count($transaction);
            $result['next_page_url'] = null;

            if ($sortTrx['status'] == true) {
                $result['next_page_url'] = ENV('APP_API_URL') . '/api/transaction/history-ongoing?page=' . $next_page;
            }
            $result = MyHelper::checkGet($result);
        } else {
            $result['status'] = 'fail';
            $result['messages'] = ["You don't have on going transaction history"];
        }

        return response()->json($result);
    }

    public function historyPoint(Request $request)
    {
        $post = $request->json()->all();
        $id = $request->user()->id;
        $order = 'new';
        $page = 1;

        if (!isset($post['use_point'])) {
            $post['use_point'] = null;
        }
        if (!isset($post['earn_point'])) {
            $post['earn_point'] = null;
        }
        if (!isset($post['offline_order'])) {
            $post['offline_order'] = null;
        }
        if (!isset($post['voucher'])) {
            $post['voucher'] = null;
        }

        if (!isset($post['buy_voucher'])) {
            $post['buy_voucher'] = null;
        }

        if (!is_null($post['oldest'])) {
            $order = 'old';
        }

        if (!is_null($post['newest'])) {
            $order = 'new';
        }

        if (!is_null($request->get('page'))) {
            $page = $request->get('page');
        }

        $next_page = $page + 1;

        $point = $this->point($post, $id);

        $sortPoint = $this->sorting($point, $order, $page);

        $check = MyHelper::checkGet($sortPoint);
        if (count($point) > 0) {
            $result['status'] = 'success';
            $result['current_page']  = $page;
            $result['data']          = $sortPoint['data'];
            $result['total']         = count($point);
            $result['next_page_url'] = null;

            if ($sortPoint['status'] == true) {
                $result['next_page_url'] = ENV('APP_API_URL') . '/api/transaction/history-point?page=' . $next_page;
            }
        } else {
            $result['status'] = 'fail';
            $result['messages'] = ["You don't have point history"];
        }

        return response()->json($result);
    }

    public function historyBalance(Request $request,$mode='group')
    {
        $post = $request->json()->all();
        $id = $request->user()->id;
        $order = 'new';
        $post['order'] = 'newest';
        $page = 0;

        if (!isset($post['use_point'])) {
            $post['use_point'] = null;
        }
        if (!isset($post['earn_point'])) {
            $post['earn_point'] = null;
        }
        if (!isset($post['offline_order'])) {
            $post['offline_order'] = null;
        }
        if (!isset($post['online_order'])) {
            $post['online_order'] = null;
        }
        if (!isset($post['pickup_order'])) {
            $post['pickup_order'] = null;
        }
        if (!isset($post['delivery_order'])) {
            $post['delivery_order'] = null;
        }
        if (!isset($post['voucher'])) {
            $post['voucher'] = null;
        }
        if (!isset($post['oldest'])) {
            $post['oldest'] = null;
        }
        if (!isset($post['newest'])) {
            $post['newest'] = null;
        }

        if (!is_null($post['oldest'])) {
            $order = null;
            $post['order'] = 'oldest';
        }

        if (!is_null($post['newest'])) {
            $order = null;
            $post['order'] = 'newest';
        }

        if (!is_null($request->get('page'))) {
            $page = $request->get('page');
        }

        $next_page = $page + 1;

        $balance = $this->balance($post, $id);

        if (count($balance) > 0) {
            $sortBalance = $this->sorting($balance, $order, $page);
            if($mode=='group'){
                $sortBalance['data'] = $this->groupIt($sortBalance['data'],'date',function($key,&$val){
                    $explode = explode(' ',$key);
                    $val['time'] = substr($explode[1],0,5);
                    return $explode[0];
                },function($key){
                    return MyHelper::dateFormatInd($key,true,false,false);
                });
            }
            $check = MyHelper::checkGet($sortBalance);
            $result['current_page']  = $page;
            $result['data']          = $sortBalance['data'];
            $result['total']         = count($balance);
            $result['next_page_url'] = null;

            if ($sortBalance['status'] == true) {
                $result['next_page_url'] = ENV('APP_API_URL') . '/api/transaction/history-balance?page=' . $next_page;
            }
            $result = MyHelper::checkGet($result);
        } else {
            if(
                $request->json('date_start') ||
                $request->json('date_end') ||
                $request->json('outlet') ||
                $request->json('brand') ||
                $request->json('use_point') ||
                $request->json('earn_point')
            ){
                $resultMessage = 'Data not found';
            }else{
                $resultMessage = "You don't have point history";
            }

            $result['status'] = 'fail';
            $result['messages'] = [$resultMessage];
        }

        return response()->json($result);
    }

    public function sorting($data, $order, $page)
    {
        $date = [];
        foreach ($data as $key => $row) {
            $date[$key] = strtotime($row['date']);
        }
        if ($order == 'new') {
            array_multisort($date, SORT_DESC, $data);
        }elseif ($order == 'old') {
            array_multisort($date, SORT_ASC, $data);
        }

        $next = false;

        if ($page > 0) {
            $resultData = [];
            $paginate   = 10;
            $start      = $paginate * ($page - 1);
            $all        = $paginate * $page;
            $end        = $all;
            $next       = true;

            if ($all > count($data)) {
                $end = count($data);
                $next = false;
            }
            $data = array_slice($data, $start, $paginate);

            return ['data' => $data, 'status' => $next];
        }


        return ['data' => $data, 'status' => $next];
    }

    public function transaction($post, $id)
    {
        $transaction = Transaction::select(\DB::raw('*,sum(transaction_products.transaction_product_qty) as sum_qty'))->distinct('transactions.*')
            ->join('outlets', 'transactions.id_outlet', '=', 'outlets.id_outlet')
            ->join('transaction_pickups', 'transactions.id_transaction', '=', 'transaction_pickups.id_transaction')
            ->join('brand_outlet', 'outlets.id_outlet', '=', 'brand_outlet.id_outlet')
            ->leftJoin('transaction_products', 'transactions.id_transaction', '=', 'transaction_products.id_transaction')
            ->where('transactions.id_user', $id)
            ->with('outlet', 'logTopup')
            ->orderBy('transaction_date', 'DESC')
            ->groupBy('transactions.id_transaction');

        if (isset($post['outlet']) || isset($post['brand'])) {
            if (isset($post['outlet']) && !isset($post['brand'])) {
                $transaction->where('transactions.id_outlet', $post['outlet']);
            } elseif (!isset($post['outlet']) && isset($post['brand'])) {
                $transaction->where('brand_outlet.id_brand', $post['brand']);
            } else {
                $transaction->where('transactions.id_outlet', $post['outlet']);
                $transaction->orWhere('brand_outlet.id_brand', $post['brand']);
            }
        }

        if (!is_null($post['date_start']) && !is_null($post['date_end'])) {
            $date_start = date('Y-m-d', strtotime($post['date_start'])) . " 00.00.00";
            $date_end = date('Y-m-d', strtotime($post['date_end'])) . " 23.59.59";

            $transaction->whereBetween('transactions.transaction_date', [$date_start, $date_end]);
        }

        $transaction->where(function ($query) use ($post) {
            if (!is_null($post['pickup_order'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->where('transactions.trasaction_type', 'Pickup Order')->whereNull('transaction_shipping_method');
                });
            }

            if (!is_null($post['delivery_order'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->where('transactions.trasaction_type', 'Pickup Order')->whereNotNull('transaction_shipping_method');
                });
            }
            // if (!is_null($post['delivery_order'])) {
            //     $query->orWhere(function ($amp) use ($post) {
            //         $amp->where('transactions.trasaction_type', 'Delivery');
            //     });
            // }

            if (!is_null($post['offline_order'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->where('transactions.trasaction_type', 'Offline');
                });
            }

            if (!is_null($post['online_order'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->whereNotIn('transactions.trasaction_type', ['Offline']);
                });
            }
        });

        $transaction->where(function ($query) use ($post) {
            if (!is_null($post['pending'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->where('transactions.transaction_payment_status', 'Pending');
                });
            }

            if (!is_null($post['paid'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->where('transactions.transaction_payment_status', 'Paid');
                });
            }

            if (!is_null($post['completed'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->where('transactions.transaction_payment_status', 'Completed');
                });
            }

            if (!is_null($post['cancel'])) {
                $query->orWhere(function ($amp) use ($post) {
                    $amp->where('transactions.transaction_payment_status', 'Cancelled');
                });
            }
        });

        $transaction = $transaction->get();

        $listTransaction = [];

        $lastStatusText = [
            'payment_pending' => [
                'text' => 'Payment Pending',
                'code' => 2,
            ],
            'pending' => [
                'text' => 'Your order is pending',
                'code' => 1,
            ],
            'received' => [
                'text' => 'On Process',
                'code' => 2,
            ],
            'on_delivery_find_driver' => [
                'text' => 'Looking For a Driver',
                'code' => 2,
            ],
            'ready' => [
                'text' => 'Order Ready',
                'code' => 3,
            ],
            'completed' => [
                'text' => 'Completed',
                'code' => 4,
            ],
            'cancelled' => [
                'text' => 'Payment Canceled',
                'code' => 0,
            ],
            'rejected' => [
                'text' => 'Order Canceled',
                'code' => 0,
            ],
            'on_delivery_no_driver' => [
                'text' => 'Driver not Found',
                'code' => 0,
            ],
            'on_delivery_out_for_pickup' => [
                'text' => 'Driver on the way to Outlet',
                'code' => 3,
            ],
            'on_delivery_on_hold' => [
                'text' => 'Delivery On Hold',
                'code' => 3,
            ],
            'on_delivery_out_for_delivery' => [
                'text' => 'Delivering by Driver',
                'code' => 3,
            ],
            'on_delivery_rejected' => [
                'text' => 'Delivery Rejected',
                'code' => 0,
            ],
            'on_delivery_internal' => [
                'text' => 'Delivering by Maxx Crew',
                'code' => 3,
            ],
        ];

        foreach ($transaction as $key => $value) {
            if ($value['transaction_payment_status'] == 'Cancelled') {
                $last_status = $lastStatusText['cancelled'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'rejected') {
                $last_status = $lastStatusText['on_delivery_rejected'];
            } elseif ($value['reject_at']) {
                $last_status = $lastStatusText['rejected'];
            } elseif ($value['taken_by_system_at'] || $value['taken_at']) {
                $last_status = $lastStatusText['completed'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'out_for_delivery') {
                $last_status = $lastStatusText['on_delivery_out_for_delivery'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'out_for_pickup') {
                $last_status = $lastStatusText['on_delivery_out_for_pickup'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'no_driver') {
                $last_status = $lastStatusText['on_delivery_no_driver'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'on_hold') {
                $last_status = $lastStatusText['on_delivery_on_hold'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'cancelled') {
                $last_status = $lastStatusText['rejected'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false)) {
                $last_status = $lastStatusText['on_delivery_find_driver'];
            } elseif ($value['ready_at']) {
                $last_status = $lastStatusText['ready'];
            } elseif ($value['receive_at']) {
                $last_status = $lastStatusText['received'];
            } elseif ($value['transaction_payment_status'] == 'Completed') {
                $last_status = $lastStatusText['pending'];
            } else {
                $last_status = $lastStatusText['payment_pending'];
            }

            $dataList['type'] = 'trx';
            $dataList['id'] = $value['id_transaction'];
            $dataList['date']    = date('d M Y H:i', strtotime($value['transaction_date']));
            $dataList['id_outlet'] = $value['outlet']['id_outlet'];
            $dataList['outlet_code'] = $value['outlet']['outlet_code'];
            $dataList['outlet'] = $value['outlet']['outlet_name'];
            $dataList['pickup_by'] = $value['pickup_by'];
            $dataList['transaction_type'] = $value['pickup_by'] == 'Customer' ? 'Pickup Order' : 'Delivery';
            $dataList['amount'] = MyHelper::requestNumber($value['transaction_grandtotal'], '_CURRENCY');
            $dataList['last_status'] = $last_status['text'];
            $dataList['last_status_code'] = $last_status['code'];

            $dataList['cashback'] = MyHelper::requestNumber($value['transaction_cashback_earned'],'_POINT');
            $dataList['subtitle'] = $value['sum_qty'].($value['sum_qty']>1?' items':' item');
            $dataList['item_total'] = (int) $value['sum_qty'];
            if ($dataList['cashback'] >= 0) {
                $dataList['status_point'] = 1;
            } else {
                $dataList['status_point'] = 0;
            }
            $dataList['rate_status'] = UserRating::where('id_transaction',$value['id_transaction'])->exists()?1:0;
            $dataList['payment_status'] = strtoupper($value['transaction_payment_status']);
            if($dataList['payment_status'] == 'CANCELLED'){
                $dataList['payment_status'] = 'CANCELED';
            }

            $listTransaction[] = $dataList;
        }

        return $listTransaction;
    }

    public function transactionOnGoingPickup($post, $id)
    {
        $transaction = Transaction::select(\DB::raw('*,sum(transaction_products.transaction_product_qty) as sum_qty'))->join('transaction_pickups', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
            ->leftJoin('transaction_products', 'transactions.id_transaction', '=', 'transaction_products.id_transaction')
            ->with('outlet', 'transaction_pickup_go_send')
            ->where('transaction_payment_status', 'Completed')
            ->whereDate('transaction_date', date('Y-m-d'))
            ->whereNull('taken_at')
            ->whereNull('reject_at')
            ->where('transactions.id_user', $id)
            ->orderBy('transaction_date', 'DESC')
            ->groupBy('transactions.id_transaction')
            ->get()->toArray();

        $listTransaction = [];

        $lastStatusText = [
            'payment_pending' => [
                'text' => 'Payment Pending',
                'code' => 2,
            ],
            'pending' => [
                'text' => 'Your order is pending',
                'code' => 1,
            ],
            'received' => [
                'text' => 'On Process',
                'code' => 2,
            ],
            'on_delivery_find_driver' => [
                'text' => 'Looking For a Driver',
                'code' => 2,
            ],
            'ready' => [
                'text' => 'Order Ready',
                'code' => 3,
            ],
            'completed' => [
                'text' => 'Completed',
                'code' => 4,
            ],
            'cancelled' => [
                'text' => 'Payment Canceled',
                'code' => 0,
            ],
            'rejected' => [
                'text' => 'Order Canceled',
                'code' => 0,
            ],
            'on_delivery_no_driver' => [
                'text' => 'Driver not Found',
                'code' => 0,
            ],
            'on_delivery_out_for_pickup' => [
                'text' => 'Driver on the way to Outlet',
                'code' => 3,
            ],
            'on_delivery_out_for_delivery' => [
                'text' => 'Delivering by Driver',
                'code' => 3,
            ],
            'on_delivery_on_hold' => [
                'text' => 'Delivery On Hold',
                'code' => 3,
            ],
            'on_delivery_internal' => [
                'text' => 'Delivering by Maxx Crew',
                'code' => 3,
            ],
        ];

        foreach ($transaction as $key => $value) {
            if ($value['reject_at']) {
                $last_status = $lastStatusText['rejected'];
            } elseif ($value['taken_by_system_at'] || $value['taken_at']) {
                $last_status = $lastStatusText['completed'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'out_for_delivery') {
                $last_status = $lastStatusText['on_delivery_out_for_delivery'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'out_for_pickup') {
                $last_status = $lastStatusText['on_delivery_out_for_pickup'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'no_driver') {
                $last_status = $lastStatusText['on_delivery_no_driver'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'on_hold') {
                $last_status = $lastStatusText['on_delivery_on_hold'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'cancelled' || ($value['transaction_pickup_go_send']['latest_status'] ?? false) == 'rejected') {
                $last_status = $lastStatusText['rejected'];
            } elseif (($value['transaction_pickup_go_send']['latest_status'] ?? false)) {
                $last_status = $lastStatusText['on_delivery_find_driver'];
            } elseif ($value['ready_at']) {
                $last_status = $lastStatusText['ready'];
            } elseif ($value['receive_at']) {
                $last_status = $lastStatusText['received'];
            } elseif ($value['transaction_payment_status'] == 'Completed') {
                $last_status = $lastStatusText['pending'];
            } elseif ($value['transaction_payment_status'] == 'Cancelled') {
                $last_status = $lastStatusText['cancelled'];
            } else {
                $last_status = $lastStatusText['payment_pending'];
            }

            $dataList['type'] = 'trx';
            $dataList['id'] = $value['id_transaction'] ;
            $dataList['date']    = date('d M Y H:i', strtotime($value['transaction_date']));
            $dataList['outlet'] = $value['outlet']['outlet_name'];
            $dataList['outlet_code'] = $value['outlet']['outlet_code'];
            $dataList['pickup_by'] = $value['pickup_by'];
            $dataList['transaction_type'] = $value['pickup_by'] == 'Customer' ? 'Pickup Order' : 'Delivery';
            $dataList['amount'] = MyHelper::requestNumber($value['transaction_grandtotal'],'_CURRENCY');
            $dataList['last_status'] = $last_status['text'];
            $dataList['last_status_code'] = $last_status['code'];

            if ($value['ready_at'] != null) {
                $dataList['status'] = "Pesanan Sudah Siap";
            } elseif ($value['receive_at'] != null) {
                $dataList['status'] = "Pesanan Sudah Diterima";
            } else {
                $dataList['status'] = "Pesanan Menunggu Konfirmasi";
            }
            $dataList['subtitle'] = $value['sum_qty'].($value['sum_qty']>1?' items':' item');
            $dataList['item_total'] = (int) $value['sum_qty'];

            $listTransaction[] = $dataList;
        }

        return $listTransaction;
    }

    public function voucher($post, $id)
    {
        $voucher = DealsUser::distinct('id_deals_users')->with('outlet')->orderBy('claimed_at', 'DESC');

        if (!is_null($post['date_start']) && !is_null($post['date_end'])) {
            $date_start = date('Y-m-d', strtotime($post['date_start'])) . " 00.00.00";
            $date_end = date('Y-m-d', strtotime($post['date_end'])) . " 23.59.59";

            $voucher = $voucher->whereBetween('claimed_at', [$date_start, $date_end]);
        }

        if(is_null($post['pending']) && is_null($post['paid'])
            && is_null($post['completed']) && is_null($post['cancel'])){
            $voucher = $voucher->where('paid_status', 'Completed');
        }else{
            $voucher = $voucher->where(function ($query) use ($post) {
                if (!is_null($post['pending'])) {
                    $query->orWhere(function ($amp) use ($post) {
                        $amp->where('paid_status', 'Pending');
                    });
                }

                if (!is_null($post['paid'])) {
                    $query->orWhere(function ($amp) use ($post) {
                        $amp->where('paid_status', 'Paid');
                    });
                }

                if (!is_null($post['completed'])) {
                    $query->orWhere(function ($amp) use ($post) {
                        $amp->where('paid_status', 'Completed');
                    });
                }

                if (!is_null($post['cancel'])) {
                    $query->orWhere(function ($amp) use ($post) {
                        $amp->where('paid_status', 'Cancelled');
                    });
                }
            });
        }

        $voucher = $voucher->whereNotNull('voucher_price_cash')->where('id_user', $id)
                    ->where(function ($query) {
                        $query->whereColumn('balance_nominal', '<', 'voucher_price_cash')
                            ->orWhereNull('balance_nominal');
                    });

        $voucher = $voucher->get()->toArray();
        $dataVoucher = [];
        foreach ($voucher as $key => $value) {
            $dataVoucher[$key]['type'] = 'voucher';
            $dataVoucher[$key]['id'] = $value['id_deals_user'];
            $dataVoucher[$key]['date'] = date('d M Y H:i', strtotime($value['claimed_at']));
            $dataVoucher[$key]['outlet'] = 'Buy Voucher';

            if($value['paid_status'] == 'Free'){
                $value['paid_status'] = 'Completed';
            }elseif($value['paid_status'] == 'Cancelled'){
                $value['paid_status'] = 'Canceled';
            }
            $dataVoucher[$key]['payment_status'] = strtoupper( $value['paid_status']);
            $dataVoucher[$key]['amount'] = MyHelper::requestNumber($value['voucher_price_cash'] - $value['balance_nominal'], '_CURRENCY');
        }

        return $dataVoucher;
    }

    public function point($post, $id)
    {
        $log = LogPoint::where('id_user', $id)->get();

        $listPoint = [];

        foreach ($log as $key => $value) {
            if ($value['source'] == 'Transaction') {
                $trx = Transaction::with('outlet')->where('id_transaction', $value['id_reference'])->first();

                $dataList['type']    = 'point';
                $dataList['detail_type']    = 'trx';
                $dataList['id']      = $value['id_log_point'];
                $dataList['date']    = date('d M Y H:i', strtotime($trx['transaction_date']));
                $dataList['outlet']  = $trx['outlet']['outlet_name'];
                $dataList['amount'] = MyHelper::requestNumber($value['point'],'_POINT');

                $listPoint[$key] = $dataList;

                if ($trx['trasaction_type'] == 'Offline') {
                    $log[$key]['online'] = 0;
                } else {
                    $log[$key]['online'] = 1;
                }
            } else {
                $vou = DealsUser::with('dealVoucher.deal')->where('id_deals_user', $value['id_reference'])->first();

                $dataList['type']        = 'point';
                $dataList['detail_type'] = 'voucher';
                $dataList['id']          = $value['id_log_point'];
                $dataList['date']        = date('d M Y H:i', strtotime($vou['claimed_at']));
                $dataList['outlet']      = $trx['outlet']['outlet_name'];
                $dataList['amount']     = MyHelper::requestNumber($value['point'],'_POINT');
                $log[$key]['online']     = 1;

                $listPoint[$key] = $dataList;
            }

            if (!is_null($post['date_start']) && !is_null($post['date_end'])) {
                $date_start = date('Y-m-d', strtotime($post['date_start'])) . " 00.00.00";
                $date_end = date('Y-m-d', strtotime($post['date_end'])) . " 23.59.59";

                if ($listPoint[$key]['date'] < $date_start || $listPoint[$key]['date'] > $date_end) {
                    unset($listPoint[$key]);
                    continue;
                }
            }

            if (!is_null($post['use_point']) && !is_null($post['earn_point']) && !is_null($post['online_order']) && !is_null($post['offline_order']) && !is_null($post['voucher'])) { }

            if (!is_null($post['use_point']) && !is_null($post['earn_point'])) { } elseif (is_null($post['use_point']) && is_null($post['earn_point'])) { } else {
                if (!is_null($post['use_point'])) {
                    if ($value['source'] == 'Transaction') {
                        unset($listPoint[$key]);
                        continue;
                    }
                }

                if (!is_null($post['earn_point'])) {
                    if ($value['source'] != 'Transaction') {
                        unset($listPoint[$key]);
                        continue;
                    }
                }
            }


            if (!is_null($post['online_order']) && !is_null($post['offline_order']) && !is_null($post['voucher'])) { } elseif (is_null($post['online_order']) && is_null($post['offline_order']) && is_null($post['voucher'])) { } else {
                if (!is_null($post['online_order'])) {
                    if (is_null($post['voucher'])) {
                        if ($listPoint[$key]['type'] == 'voucher') {
                            unset($listPoint[$key]);
                            continue;
                        }
                    }

                    if ($listPoint[$key]['online'] == 0) {
                        unset($listPoint[$key]);
                        continue;
                    }
                }

                if (!is_null($post['offline_order'])) {
                    if ($listPoint[$key]['online'] != 0) {
                        unset($listPoint[$key]);
                        continue;
                    }
                }

                if (!is_null($post['voucher'])) {
                    if ($listPoint[$key]['type'] != 'voucher') {
                        unset($listPoint[$key]);
                        continue;
                    }
                }
            }
        }

        return $listPoint;
    }

    function pointTest($post)
    {
        $log = DB::table('log_points')->paginate();
    }

    public function balance($post, $id)
    {
        $log = LogBalance::where('log_balances.id_user', $id);

        if (isset($post['outlet']) || isset($post['brand'])) {
            $log->where(function ($query) use ($post) {
                $query->whereIn(
                    'log_balances.id_log_balance',
                    function ($query) use ($post) {
                        $query->select('id_log_balance')
                            ->from('log_balances')
                            ->join('transactions', 'log_balances.id_reference', '=', 'transactions.id_transaction')
                            ->join('outlets', 'transactions.id_outlet', '=', 'outlets.id_outlet')
                            ->join('brand_outlet', 'outlets.id_outlet', '=', 'brand_outlet.id_outlet')
                            ->where('log_balances.source', 'Transaction');
                        if (isset($post['outlet']) && !isset($post['brand'])) {
                            $query->where('outlets.id_outlet', $post['outlet']);
                        } elseif (!isset($post['outlet']) && isset($post['brand'])) {
                            $query->where('brand_outlet.id_brand', $post['brand']);
                        } else {
                            $query->where('outlets.id_outlet', $post['outlet']);
                            $query->orWhere('brand_outlet.id_brand', $post['brand']);
                        }
                    }
                );
                $query->orWhereIn(
                    'log_balances.id_log_balance',
                    function ($query) use ($post) {
                        $query->select('id_log_balance')
                            ->from('log_balances')
                            ->join('deals_users', 'log_balances.id_reference', '=', 'deals_users.id_deals_user')
                            ->join('deals_vouchers', 'deals_users.id_deals_voucher', '=', 'deals_vouchers.id_deals_voucher')
                            ->join('deals', 'deals_vouchers.id_deals', '=', 'deals.id_deals')
                            ->where('log_balances.source', 'Deals Balance');
                        if (isset($post['outlet']) && !isset($post['brand'])) {
                            $query->where('deals_users.id_outlet', $post['outlet']);
                        } elseif (!isset($post['outlet']) && isset($post['brand'])) {
                            $query->where('deals.id_brand', $post['brand']);
                        } else {
                            $query->where(function ($query) use ($post) {
                                $query->where('deals_users.id_outlet', $post['outlet'])
                                    ->orWhere('deals.id_brand', $post['brand']);
                            });
                        }
                    }
                );
            });
        }
        switch ($post['order']) {
            case 'newest':
                $log->orderBy('log_balances.id_log_balance','desc');
                break;

            case 'oldest':
                $log->orderBy('log_balances.id_log_balance','asc');
                break;
        }
        $log->where(function ($query) use ($post) {
            if (!is_null($post['use_point'])) {
                $query->orWhere(function ($queryLog) {
                    $queryLog->where('balance', '<', 0);
                });
            }
            if (!is_null($post['earn_point'])) {
                $query->orWhere(function ($queryLog) {
                    $queryLog->where('balance', '>', 0);
                });
            }
        });

         if (!is_null($post['online_order']) || !is_null($post['offline_order']) || !is_null($post['pickup_order']) || !is_null($post['delivery_order'])) {
             $log->leftJoin('transactions', 'transactions.id_transaction', 'log_balances.id_reference')
                 ->where(function ($query) use ($post) {
                     if (!is_null($post['online_order'])) {
                         $query->orWhere(function ($queryLog) {
                             $queryLog->whereIn('source', ['Transaction', 'Transaction Failed', 'Rejected Order', 'Rejected Order Midtrans', 'Rejected Order Point', 'Rejected Order Ovo', 'Reversal'])
                                 ->where('trasaction_type', '!=', 'Offline');
                         });
                     }
                     if (!is_null($post['offline_order'])) {
                         $query->orWhere(function ($queryLog) {
                             $queryLog->where('source', 'Transaction')
                                 ->where('trasaction_type', '=', 'Offline');
                         });
                     }
                     if (!is_null($post['pickup_order'])) {
                        $query->orWhere(function ($queryLog) use ($post) {
                            $queryLog->where('transactions.trasaction_type', 'Pickup Order')->whereNull('transaction_shipping_method');
                        });
                    }
        
                    if (!is_null($post['delivery_order'])) {
                        $query->orWhere(function ($queryLog) use ($post) {
                            $queryLog->where('transactions.trasaction_type', 'Pickup Order')->whereNotNull('transaction_shipping_method');
                        });
                    }
                 });
         }

        if($post['voucher'] == 1 && $post['online_order'] == null && $post['offline_order'] == null){
            $log->where('source', 'Deals Balance');
        }elseif(!is_null($post['voucher'])){
            $log->orWhere(function ($queryLog) {
                $queryLog->where('source', 'Deals Balance');
            });
        }

        if (isset($post['date_start']) && !is_null($post['date_start'])){
            $log->whereDate('created_at', '>=', date('Y-m-d', strtotime($post['date_start'])));
        }

        if (isset($post['date_end']) && !is_null($post['date_end'])){
            $log->whereDate('created_at', '<=', date('Y-m-d', strtotime($post['date_end'])));
        }

        $log = $log->get();
        $listBalance = [];

        foreach ($log as $key => $value) {
            if ($value['source'] == 'Transaction' || $value['source'] == 'Rejected Order'  || $value['source'] == 'Rejected Order Point' || $value['source'] == 'Rejected Order Midtrans' || $value['source'] == 'Rejected Order Ovo' || $value['source'] == 'Reversal' || $value['source'] == 'Transaction Failed') {
                $trx = Transaction::with('outlet')->where('id_transaction', $value['id_reference'])->first();

                // return $trx;
                // $log[$key]['detail'] = $trx;
                // $log[$key]['type']   = 'trx';
                // $log[$key]['date']   = date('Y-m-d H:i:s', strtotime($trx['transaction_date']));
                // $log[$key]['outlet'] = $trx['outlet']['outlet_name'];
                // if ($trx['trasaction_type'] == 'Offline') {
                //     $log[$key]['online'] = 0;
                // } else {
                //     $log[$key]['online'] = 1;
                // }

                if (empty($trx)) {
                    continue;
                }

                if ($trx['transaction_payment_status'] != 'Cancelled') {
                    $dataList['type']    = 'balance';
                    $dataList['id']      = $value['id_log_balance'];
                    $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                    $dataList['outlet']  = $trx['outlet']['outlet_name'];
                    if ($value['balance'] < 0) {
                        $dataList['amount'] = '- ' . ltrim(MyHelper::requestNumber($value['balance'], '_POINT'), '-');
                    } else {
                        $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');
                    }

                    $listBalance[$key] = $dataList;
                } else {
                    if ($value['balance'] < 0) {
                        $dataList['type']    = 'balance';
                        $dataList['id']      = $value['id_log_balance'];
                        $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                        $dataList['outlet']  = $trx['outlet']['outlet_name'];
                        if ($value['balance'] < 0) {
                            $dataList['amount'] = '- ' . ltrim(MyHelper::requestNumber($value['balance'], '_POINT'), '-');
                        } else {
                            $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'],'_POINT');
                        }

                        $listBalance[$key] = $dataList;
                    } else {
                        $dataList['type']    = 'profile';
                        $dataList['id']      = $value['id_log_balance'];
                        $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                        $dataList['outlet']  = 'Reversal';
                        if ($value['balance'] < 0) {
                            $dataList['amount'] = '- ' . ltrim(MyHelper::requestNumber($value['balance'], '_POINT'), '-');
                        } else {
                            $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');
                        }

                        $listBalance[$key] = $dataList;
                    }
                }
            } elseif ($value['source'] == 'Voucher' || $value['source'] == 'Deals Balance') {
                $vou = DealsUser::with('dealVoucher.deal')->where('id_deals_user', $value['id_reference'])->first();
                // $log[$key]['detail'] = $vou;
                $dataList['type']   = 'voucher';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']   = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet'] = 'Buy Voucher';
                $dataList['amount'] = '- ' . ltrim(MyHelper::requestNumber($value['balance'], '_POINT'), '-');
                // $dataList['amount'] = number_format($value['balance'], 0, ',', '.');
                // $dataList['online'] = 1;

                $listBalance[$key] = $dataList;
            } elseif($value['source'] == 'Deals Reversal' || $value['source'] == 'Claim Deals Failed') {
                $dataList['type']   = 'profile';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet'] = 'Reversal';
                if ($value['balance'] < 0) {
                    $dataList['amount'] = '- ' . ltrim(MyHelper::requestNumber($value['balance'], '_POINT'), '-');
                } else {
                    $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');
                }

                $listBalance[$key] = $dataList;
            } elseif ($value['source'] == 'Reversal Duplicate') {
                continue;
            } elseif ($value['source'] == 'Point Injection') {
                $getPointInjection = PointInjection::find($value['id_reference']);
                if ($getPointInjection) {
                    $dataList['outlet'] = $getPointInjection->title;
                } else {
                    $dataList['outlet'] = 'Free Point';
                }

                $dataList['type']   = 'profile';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');

                $listBalance[$key] = $dataList;
            } elseif($value['source'] == 'Balance Reset') {
                $dataList['type']   = 'profile';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet'] = 'Point Expired';
                $dataList['amount'] = MyHelper::requestNumber($value['balance'], '_POINT');

                $listBalance[$key] = $dataList;
            } elseif($value['source'] == 'Referral Bonus') {
                $dataList['type']   = 'profile';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet'] = 'Referral Bonus';
                $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');

                $listBalance[$key] = $dataList;
            } elseif (strpos($value['source'], 'Rejected Order') !== false) {
                $dataList['type']   = 'balance';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet'] = 'Reversal';
                if ($value['balance'] < 0) {
                    $dataList['amount'] = '- ' . ltrim(MyHelper::requestNumber($value['balance'], '_POINT'), '-');
                } else {
                    $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');
                }

                $listBalance[$key] = $dataList;
            } elseif(strtolower($value['source']) == 'write off') {
                $dataList['type']   = 'profile';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet']  = 'Point Expiry';
                if ($value['balance'] < 0) {
                    $dataList['amount'] = '- ' . ltrim(MyHelper::requestNumber($value['balance'], '_POINT'), '-');
                } else {
                    $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');
                }

                $listBalance[$key] = $dataList;
            }elseif($value['source'] == 'Welcome Point') {
                $dataList['type']   = 'profile';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet'] = 'Welcome Point';
                $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');

                $listBalance[$key] = $dataList;
            } else {
                // return 'a';
                // $dataList['type']   = $value['source'];
                $dataList['type']   = 'profile';
                $dataList['id']      = $value['id_log_balance'];
                $dataList['date']    = date('d M Y H:i', strtotime($value['created_at']));
                $dataList['outlet'] = ucfirst($value['source']);
                $dataList['amount'] = '+ ' . MyHelper::requestNumber($value['balance'], '_POINT');

                $listBalance[$key] = $dataList;
            }

//            if (isset($post['date_start']) && !is_null($post['date_start']) && isset($post['date_end']) && !is_null($post['date_end'])) {
//                $date_start = date('Y-m-d', strtotime($post['date_start'])) . " 00.00.00";
//                $date_end = date('Y-m-d', strtotime($post['date_end'])) . " 23.59.59";
//
//                if ($listBalance[$key]['date'] < $date_start || $listBalance[$key]['date'] > $date_end) {
//                    unset($listBalance[$key]);
//                    continue;
//                }
//            }

            // if (!is_null($post['online_order']) && !is_null($post['offline_order']) && !is_null($post['voucher'])) {

            // } elseif (is_null($post['online_order']) && is_null($post['offline_order']) && is_null($post['voucher'])) {

            // } else {
            //     if (!is_null($post['online_order'])) {
            //         if (is_null($post['voucher'])) {
            //             if ($listBalance[$key]['type'] == 'voucher') {
            //                 unset($listBalance[$key]);
            //                 continue;
            //             }
            //         }

            //         if ($listBalance[$key]['online'] == 0) {
            //             unset($listBalance[$key]);
            //             continue;
            //         }
            //     }

            //     if (!is_null($post['offline_order'])) {
            //         if ($log[$listBalance]['online'] != 0) {
            //             unset($listBalance[$key]);
            //             continue;
            //         }
            //     }

            //     if (!is_null($post['voucher'])) {
            //         if ($listBalance[$key]['type'] != 'voucher') {
            //             unset($listBalance[$key]);
            //             continue;
            //         }
            //     }
            // }

        }


        return array_values($listBalance);
    }
    /**
     * Group some array based on a column
     * @param  array        $array        data
     * @param  string       $col          column as key for grouping
     * @param  function     $modifier     function to modify key value
     * @return array                      grouped array
     */
    public function groupIt($array,$col,$col_modifier=null,$key_modifier=null) {
        $newArray=[];
        foreach ($array as $value) {
            if($col_modifier!==null){
                $key = $col_modifier($value[$col],$value);
            }else{
                $key = $value[$col];
            }
            $newArray[$key][]=$value;
        }
        if($key_modifier!==null){
            foreach ($newArray as $key => $value) {
                $new_key=$key_modifier($key,$value);
                $newArray[$new_key]=$value;
                unset($newArray[$key]);
            }
        }
        return $newArray;
    }
}
