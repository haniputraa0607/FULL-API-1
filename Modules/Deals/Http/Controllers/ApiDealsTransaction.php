<?php

namespace Modules\Deals\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;

use App\Http\Models\Outlet;
use App\Http\Models\Deal;
use App\Http\Models\DealsOutlet;
use App\Http\Models\DealsPaymentManual;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsVoucher;
use App\Http\Models\User;
use App\Http\Models\TransactionVoucher;

use DB;

use Modules\Deals\Http\Requests\Deals\Create;
use Modules\Deals\Http\Requests\Deals\Update;
use Modules\Deals\Http\Requests\Deals\Delete;

use Illuminate\Support\Facades\Schema;
use App\Jobs\ExportJob;
use Modules\Report\Entities\ExportQueue;

class ApiDealsTransaction extends Controller
{
    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
    }

    public $saveImage = "img/deals/";

    /* LIST */
    function listTrx(Request $request) {
        $post = $request->json()->all();

        /*
        $trx = DealsUser::select('deals_users.*')
        ->leftJoin('users', 'users.id', '=', 'deals_users.id_user')
        ->leftJoin('deals_vouchers', 'deals_vouchers.id_deals_voucher', '=', 'deals_users.id_deals_voucher')
        ->leftJoin('deals', 'deals_vouchers.id_deals', '=', 'deals.id_deals')
        ->with('user', 'outlet', 'dealVoucher', 'dealVoucher.deal');

        if (isset($post['date_start']) && isset($post['date_end'])) {
            $trx->whereBetween('deals_users.created_at', [$post['date_start'].' 00:00:00', $post['date_end'].' 23:59:59']);
        }

        if (isset($post['claimed_start']) && isset($post['claimed_end'])) {
            $trx->whereBetween('claimed_at', [$post['claimed_start'].' 00:00:00', $post['claimed_end'].' 23:59:59']);
        }

        if (isset($post['redeem_start']) && isset($post['redeem_end'])) {
            $trx->whereBetween('redeemed_at', [$post['redeem_start'].' 00:00:00', $post['redeem_end'].' 23:59:59']);
        }

        if (isset($post['used_start']) && isset($post['used_end'])) {
            $trx->whereBetween('used_at', [$post['used_start'].' 00:00:00', $post['used_end'].' 23:59:59']);
        }

        if (isset($post['id_outlet'])) {
            $trx->where('id_outlet', $post['id_outlet']);
        }

        if (isset($post['paid_status'])) {
            $trx->where('paid_status', $post['paid_status']);
        }

        if (isset($post['id_user'])) {
            $trx->where('id_user', $post['id_user']);
        }

        if (isset($post['phone'])) {
            $trx->where('phone', $post['phone']);
        }

        if (isset($post['id_deals'])) {
            $trx->where('deals.id_deals', $post['id_deals']);
        }

        if (isset($post['id_deals_user'])) {
            $trx->where('id_deals_user', $post['id_deals_user']);
        }
        $trx = $trx->orderBy('claimed_at', 'DESC');
        */

        $trx = $this->listTrxFilter($post);
        // $trx = $trx->get()->toArray();
        $trx = $trx->paginate(10);

        return response()->json(MyHelper::checkGet($trx));
    }

    function listTrxFilter($post) {

        $trx = DealsUser::select('deals_users.*')
        ->leftJoin('users', 'users.id', '=', 'deals_users.id_user')
        ->leftJoin('deals_vouchers', 'deals_vouchers.id_deals_voucher', '=', 'deals_users.id_deals_voucher')
        ->leftJoin('deals', 'deals_vouchers.id_deals', '=', 'deals.id_deals')
        ->with('user', 'outlet', 'dealVoucher', 'dealVoucher.deal');

        if (isset($post['date_start']) && isset($post['date_end'])) {
            $trx->whereBetween('deals_users.created_at', [$post['date_start'].' 00:00:00', $post['date_end'].' 23:59:59']);
        }

        if (isset($post['claimed_start']) && isset($post['claimed_end'])) {
            $trx->whereBetween('claimed_at', [$post['claimed_start'].' 00:00:00', $post['claimed_end'].' 23:59:59']);
        }

        if (isset($post['redeem_start']) && isset($post['redeem_end'])) {
            $trx->whereBetween('redeemed_at', [$post['redeem_start'].' 00:00:00', $post['redeem_end'].' 23:59:59']);
        }

        if (isset($post['used_start']) && isset($post['used_end'])) {
            $trx->whereBetween('used_at', [$post['used_start'].' 00:00:00', $post['used_end'].' 23:59:59']);
        }

        if (isset($post['id_outlet'])) {
            $trx->where('id_outlet', $post['id_outlet']);
        }

        if (isset($post['paid_status'])) {
            $trx->where('paid_status', $post['paid_status']);
        }

        /*if (isset($post['id_user'])) {
            $trx->where('id_user', $post['id_user']);
        }*/

        if (isset($post['phone'])) {
            $trx->where('phone', $post['phone']);
        }

        if (isset($post['id_deals'])) {
            $trx->where('deals.id_deals', $post['id_deals']);
        }

        if (isset($post['id_deals_user'])) {
            $trx->where('id_deals_user', $post['id_deals_user']);
        }
        $trx = $trx->orderBy('claimed_at', 'DESC');
        
        return $trx;
    }

    public function exportExcel($filter){

        $data = $this->listTrxFilter($filter);
        
        foreach ($data->cursor() as $val) {
        	
        	$val->load('user', 'outlet', 'dealVoucher.transaction_voucher.transaction', 'dealVoucher.deal');
        	$val = $val->toArray();

        	yield [
	            'Deals'		=> $val['deal_voucher']['deal']['deals_title'] ?? null,
                'Code'		=> $val['deal_voucher']['voucher_code'] ?? null,
                'User'		=> $val['user']['name'] ?? null,
                'Phone'		=> $val['user']['phone'] ?? null,
                'Claim'		=> (empty($val['claimed_at'])) ? '-' : date('d-M-y', strtotime($val['claimed_at'])),
                'Redeem'	=> (empty($val['redeemed_at'])) ? '-' : date('d-M-y', strtotime($val['redeemed_at'])),
                'Used'		=> (empty($val['used_at'])) ? '-' : date('d-M-y', strtotime($val['used_at'])),
                'Expiry'	=> (empty($val['voucher_expired_at'])) ? '-' : date('d-M-y', strtotime($val['voucher_expired_at'])),
                'Receipt Number'=> $val['deal_voucher']['transaction_voucher']['transaction']['transaction_receipt_number'] ?? null,
                'Outlet Code'	=> $val['outlet']['outlet_code'] ?? null,
                'Outlet Name'	=> $val['outlet']['outlet_name'] ?? null,
                'Grandtotal'	=> $val['deal_voucher']['transaction_voucher']['transaction']['transaction_grandtotal'] ?? null
            ];
        }
    }
}