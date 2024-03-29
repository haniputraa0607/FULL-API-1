<?php

namespace Modules\Deals\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Lib\MyHelper;

use App\Http\Models\Deal;
use App\Http\Models\DealsOutlet;
use App\Http\Models\DealsPaymentManual;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsVoucher;
use App\Http\Models\Outlet;
use App\Http\Models\TransactionVoucher;
use App\Http\Models\Transaction;

use Modules\Deals\Http\Requests\Deals\Voucher;
use Modules\Deals\Http\Requests\Deals\UseVoucher;
use Modules\Deals\Http\Requests\Deals\MyVoucherStatus;
use DB;

class ApiDealsVoucher extends Controller
{
    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
        $this->deals        = "Modules\Deals\Http\Controllers\ApiDeals";
    }

    /* CREATE VOUCHER */
    function create($post) {

        DB::beginTransaction();
        if (is_array($post['voucher_code'])) {
            $data = [];
			$check_voucher = $this->checkVoucher($post['voucher_code']);

            if ($check_voucher['status']) {

	            foreach ($check_voucher['unique_code'] as $value) {
	                array_push($data, [
	                    'id_deals'             => $post['id_deals'],
	                    'voucher_code'         => strtoupper($value),
	                    'deals_voucher_status' => 'Available',
	                    'created_at'           => date('Y-m-d H:i:s'),
	                    'updated_at'           => date('Y-m-d H:i:s')
	                ]);
	            }

            	$save = DealsVoucher::insert($data);

                if ($save) {
                    // UPDATE VOUCHER TOTAL DEALS TABLE
                    $updateDealsTable = $this->updateTotalVoucher($post);

                    if ($updateDealsTable) {
                        DB::commit();
                        $save = true;
                    }
                    else {
                        DB::rollBack();
                        $save = false;
                    }
                }
            }
            else {

            	if (($post['add_type']??false) != 'add'){
            	    $save = true;
            	}
            	else{
	                DB::rollBack();
	                $save = false;
                }
            }

            $result = MyHelper::checkUpdate($save);

            foreach ($check_voucher['same_code'] as $value) {
        		$warnings[] = 'Voucher '.$value.' already exists';
        		$result['warnings'] = $warnings;
        	}

            return $result;
        }
        else {
            $save = DealsVoucher::create([
                'id_deals'             => $post['id_deals'],
                'voucher_code'         => strtoupper($post['voucher_code']),
                'deals_voucher_status' => 'Available'
            ]);

            return MyHelper::checkCreate($save);
        }
    }

    /* UPDATE TOTAL VOUCHER DEALS TABLE */
    function updateTotalVoucher($post) {
        $jumlahVoucher = DealsVoucher::where('id_deals', $post['id_deals'])->count();

        if ($jumlahVoucher) {
            // UPDATE DATA DEALS

            $save = Deal::where('id_deals', $post['id_deals'])->update([
                'deals_total_voucher' => $jumlahVoucher
            ]);

            if ($save) {
                return true;
            }
        }

        return false;
    }

    /* DELETE VOUCHER */
    function deleteReq(Request $request) {
        if (is_array($request->json('id_deals_voucher'))) {
            $delete = DealsVoucher::whereIn('id_deals_voucher', $request->json('id_deals_voucher'))->where('deals_voucher_status', '=', 'Available')->delete();
        }
        else {
            $delete = DealsVoucher::where('id_deals_voucher', $request->json('id_deals_voucher'))->where('deals_voucher_status', '=', 'Available')->delete();
        }

        if ($request->json('id_deals')) {
            $delete = DealsVoucher::where('id_deals')->where('deals_voucher_status', '=', 'Available')->delete();
        }

        return response()->json(MyHelper::checkDelete($delete));
    }

    /* CREATE VOUCHER REQUEST */
    function createReq(Voucher $request) {

        if ($request->json('type') == "generate") {
            $save = $this->generateVoucher($request->json('id_deals'), $request->json('total'));
            return response()->json(MyHelper::checkUpdate($save));
        }
        else {
            $save = $this->create($request->json()->all());

            return response()->json($save);
        }
    }

    /* GENERATE VOUCHER */
    function generateVoucher($id_deals, $total, $status=0) {
        $data = [];
        // pengecekan database
        $voucherDB = $this->voucherDB($id_deals);
        
        if ($total > 1) {
            for ($i=0; $i < $total; $i++) {
                // generate code
                $code = $this->generateCode($id_deals);

                // unique code in 1 deals
                while (in_array($code, $voucherDB)) {
                    $code = $this->generateCode($id_deals);
                }

                // push for voucher DB, to get unique code
                array_push($voucherDB, $code);

                // push for save db
                array_push($data, [
                    'id_deals'             => $id_deals,
                    'voucher_code'         => strtoupper($code),
                    'deals_voucher_status' => 'Available',
                    'created_at'           => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s')
                ]);
            }

            $save = DealsVoucher::insert($data);
        }
        else {
            // generate code
            $code = $this->generateCode($id_deals);
            
            // unique code in 1 deals
            while (in_array($code, $voucherDB)) {
                $code = $this->generateCode($id_deals);
            }

            $data = [
                'id_deals'             => $id_deals,
                'voucher_code'         => strtoupper($code),
            ];

            if ($status != 0) {
                $data['deals_voucher_status'] = "Sent";
            }
            else {
                $data['deals_voucher_status'] = "Available";
            }

            $save = DealsVoucher::create($data);
        }

        return $save;
    }

    /* CHECK VOUCHER DATABASE */
    function voucherDB($id_deals) {
        $dbVoucher = DealsVoucher::where('id_deals', $id_deals)->get()->toArray();

        if (!empty($dbVoucher)) {
            $dbVoucher = array_pluck($dbVoucher, 'voucher_code');
        }

        return $dbVoucher;
    }

    /* GENERATE CODE */
    function generateCode($id_deals) {
        $deals = Deal::where('id_deals',$id_deals)->first();
        if($deals['deals_type'] != 'SecondDeals'){
            $code = sprintf('%03d', $id_deals).MyHelper::createRandomPIN(5);
        }else{
            $code = $this->generateSecondVoucherCode($deals['prefix'],$deals['digit_random']);
        }

        return $code;
    }

    /* GENERATE CODE SECOND VOUCHER*/
    function generateSecondVoucherCode($prefix, $digit){
        $code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),0,$digit);
        $code = $prefix.$code;
        $check = DealsVoucher::where('voucher_code', $code)->first();
        if($check){
            $this->generateSecondVoucherCode($prefix, $digit);
        }
        return $code;
    }

    /* UPDATE VOUCHER */
    function update($id_deals_voucher, $post) {
        $update = DealsVoucher::where('id_deals_voucher', $id_deals_voucher)->updateWithUserstamps($post);

        return $update;
    }

    /* CREATE VOUCHER USER */
    function createVoucherUser($post) {
        $create = DealsUser::create($post);

        if ($create) {
            $create = DealsUser::with(['userMid', 'dealVoucher'])->where('id_deals_user', $create->id_deals_user)->first();

            // add notif mobile
            $addNotif = MyHelper::addUserNotification($create->id_user,'voucher');
        }

        return $create;
    }

    /* UPDATE VOUCHER USER */
    function updateVoucherUser($id_deals_user, $post) {
        $update = DealsVoucher::where('id_deals_user', $id_deals_user)->updateWithUserstamps($post);

        return $update;
    }

    /* MY VOUCHER */
    function myVoucher(Request $request) {
        $post = $request->json()->all();
        $outlet_total = Outlet::get()->count();

        $voucher = DealsUser::where('id_user', $request->user()->id)
                            ->whereIn('paid_status', ['Free', 'Completed'])
                            ->with(['dealVoucher', 'dealVoucher.deal', 'dealVoucher.deal.deals_content', 'dealVoucher.deal.deals_content.deals_content_details', 'dealVoucher.deal.outlets.city', 'dealVoucher.deal.outlets.city']);
        $voucher->select('deals_users.id_deals','voucher_expired_at','deals_users.id_deals_voucher','id_deals_user','id_outlet','voucher_hash','redeemed_at','used_at','is_used');
        if (isset($post['id_deals_user'])) {
            $voucher->addselect('deals_users.redeemed_at', 'deals_users.used_at');
            $voucher->where('id_deals_user', $post['id_deals_user']);
        }

        $voucher->where(function ($query) use ($post) {

            if (isset($post['used']) && ($post['used'] == 1 || $post['used'] == '1'))  {
                $query->orWhere(function ($amp) use ($post) {
                        $amp->whereNotNull('used_at');
                    });
            }
            if (isset($post['expired']) && ($post['expired'] == 1 || $post['expired'] == '1'))  {
                $query->orWhere(function ($amp) use ($post) {
                        $amp->where('voucher_expired_at', '<=', date('Y-m-d H:i:s'));
                        $amp->whereNull('used_at');
                    });
            }
            if (isset($post['available']) && ($post['available'] == 1 || $post['available'] == '1')) {
                 $query->orWhere(function ($amp) use ($post) {
                        $amp->whereNull('used_at')->where('voucher_expired_at', '>', date('Y-m-d H:i:s'));
                    });
            }
        });


        if (isset($post['expired_start'])) {
            $voucher->whereDate('voucher_expired_at', '>=',date('Y-m-d', strtotime($post['expired_start'])));
        }

        if (isset($post['expired_end'])) {
            $voucher->whereDate('voucher_expired_at', '<=',date('Y-m-d', strtotime($post['expired_end'])));
        }

         //search by outlet
        if(isset($post['id_outlet']) && is_numeric($post['id_outlet'])){

            $voucher->join('deals_vouchers', 'deals_users.id_deals_voucher', 'deals_vouchers.id_deals_voucher')
                    ->join('deals', 'deals.id_deals', 'deals_vouchers.id_deals')
                    ->leftJoin('deals_outlets', 'deals.id_deals', 'deals_outlets.id_deals')
                    ->where(function ($query) use ($post) {
                        $query->where('deals_users.id_outlet', $post['id_outlet'])
                                ->orWhere('deals_outlets.id_outlet', $post['id_outlet'])
                                ->orWhere('deals.is_all_outlet','=',1);
                    })
                    ->select('deals_users.*')->distinct();
        }

        if(!MyHelper::isJoined($voucher,'deals_vouchers')){
            $voucher->leftJoin('deals_vouchers', 'deals_users.id_deals_voucher', 'deals_vouchers.id_deals_voucher');
        }
    	if(!MyHelper::isJoined($voucher,'deals')){
            $voucher->leftJoin('deals', 'deals.id_deals', 'deals_vouchers.id_deals');
        }

    	$voucher->addselect('deals.is_online', 'deals.is_offline');

        if ( isset($post['online']) ) {
        	if(!MyHelper::isJoined($voucher,'deals_vouchers')){
                $voucher->leftJoin('deals_vouchers', 'deals_users.id_deals_voucher', 'deals_vouchers.id_deals_voucher');
            }
        	if(!MyHelper::isJoined($voucher,'deals')){
                $voucher->leftJoin('deals', 'deals.id_deals', 'deals_vouchers.id_deals');
            }

            $voucher->where(function ($query) {
                                    $query->where('deals.is_online', '=', 1)
                                    		->whereNull('deals_users.redeemed_at');
                                });
        }

        if(isset($post['key_free']) && $post['key_free'] != null){
            if(!MyHelper::isJoined($voucher,'deals_vouchers')){
                $voucher->leftJoin('deals_vouchers', 'deals_users.id_deals_voucher', 'deals_vouchers.id_deals_voucher');
            }
            if(!MyHelper::isJoined($voucher,'deals')){
                $voucher->leftJoin('deals', 'deals.id_deals', 'deals_vouchers.id_deals');
            }
            $voucher->where(function ($query) use ($post) {
                                    $query->where('deals.deals_title', 'LIKE', '%'.$post['key_free'].'%')
                                            ->orWhere('deals.deals_second_title', 'LIKE', '%'.$post['key_free'].'%');
                                });
            }
         //search by brand
        if(isset($post['id_brand']) && is_numeric($post['id_brand'])){
            if(!MyHelper::isJoined($voucher,'deals_vouchers')){
                $voucher->leftJoin('deals_vouchers', 'deals_users.id_deals_voucher', 'deals_vouchers.id_deals_voucher');
            }
            if(!MyHelper::isJoined($voucher,'deals')){
                $voucher->leftJoin('deals', 'deals.id_deals', 'deals_vouchers.id_deals');
            }
            $voucher->where('deals.id_brand',$post['id_brand']);
        }

        // filter promo type
        if( isset($post['promo_type']) ){
        	
            if(!MyHelper::isJoined($voucher,'deals_vouchers')){
                $voucher->leftJoin('deals_vouchers', 'deals_users.id_deals_voucher', 'deals_vouchers.id_deals_voucher');
            }

            if(!MyHelper::isJoined($voucher,'deals')){
                $voucher->leftJoin('deals', 'deals.id_deals', 'deals_vouchers.id_deals');
            }

        	if ($post['promo_type'] == 'delivery') {
	            $voucher->where('deals.promo_type','Discount delivery');
        	}
        	elseif( $post['promo_type'] == 'discount' ) {
        		$voucher->where('deals.promo_type', '!=', 'Discount delivery');
        	}
        }

        // $voucher->orderBy('voucher_expired_at', 'asc');
        if (isset($post['oldest']) && ($post['oldest'] == 1 || $post['oldest'] == '1')) {
                $voucher = $voucher->orderBy('deals_users.claimed_at', 'asc');
        }
        elseif (isset($post['newest_expired']) && ($post['newest_expired'] == 1 || $post['newest_expired'] == '1')) {
            $voucher = $voucher->orderBy('voucher_expired_at', 'asc');
        }
        else{
            $voucher = $voucher->orderBy('deals_users.claimed_at', 'desc');
        }

        // if voucher detail, no need pagination
        if (isset($post['id_deals_user']) && $post['id_deals_user'] != "") {
            $vcr=$voucher->first();
            if(($post['no_qr']??false)&&!$vcr->used_at){
                $vcr->redeemed_at=null;
                $vcr->save();
            }
            $voucher = $voucher->get()->toArray();
        }
        else {
            if (isset($post['used']) && ($post['used'] == 1 || $post['used'] == '1'))  {
                // if voucher used, return max 100 vouchers with pagination
                $collection = $voucher->take(100)->get();
                $perPage = 10;
                $currentPage = LengthAwarePaginator::resolveCurrentPage();
                if ($currentPage == 1) {
                    $start = 0;
                }
                else {
                    $start = ($currentPage - 1) * $perPage;
                }
                $currentPageCollection = $collection->slice($start, $perPage)->all();

                $paginatedLast100 = new LengthAwarePaginator($currentPageCollection, count($collection), $perPage);

                $paginatedLast100->setPath(LengthAwarePaginator::resolveCurrentPath());
                $voucher = $paginatedLast100;
            }
            else{
                $voucher = $voucher->paginate(10);
            }

            // get pagination attributes
            $current_page = $voucher->currentPage();
            $next_page_url = $voucher->nextPageUrl();
            $per_page = $voucher->perPage();
            $prev_page_url = $voucher->previousPageUrl();
            $total = $voucher->count();

            $voucher_temp = [];
            // convert paginate collection to array data of vouchers
            foreach ($voucher as $key => $value) {
                $voucher_temp[] = $value->toArray();
            }
            $voucher = $voucher_temp;
        }

        //add outlet name
        foreach($voucher as $index => $datavoucher){
            $check = count($datavoucher['deal_voucher']['deal']['outlets']);
            if ($check == $outlet_total) {
                $voucher[$index]['deal_voucher']['deal']['label_outlet'] = 'All';
            } else {
                $voucher[$index]['deal_voucher']['deal']['label_outlet'] = 'Some';
            }
           if($datavoucher['used_at']){
                $voucher[$index]['label']='Used';
                // $voucher[$index]['status_text']="Sudah digunakan pada \n".MyHelper::dateFormatInd($voucher[$index]['used_at'],false);
                $voucher[$index]['status_text']="Used on ".date('d M Y', strtotime($datavoucher['used_at']));
                $voucher[$index]['voucher_status_text']=["Used on",date('d M Y', strtotime($datavoucher['used_at']))];
            }elseif($datavoucher['voucher_expired_at']<date('Y-m-d H:i:s')){
                $voucher[$index]['label']='Expired';
                // $voucher[$index]['status_text']="Telah berakhir pada \n".MyHelper::dateFormatInd($voucher[$index]['voucher_expired_at'],false);
                $voucher[$index]['status_text']="Expired on ".date('d M Y', strtotime($voucher[$index]['voucher_expired_at']));
                $voucher[$index]['voucher_status_text']=["Expired on", date('d M Y', strtotime($voucher[$index]['voucher_expired_at']))];
            }else{
                $voucher[$index]['label']='Available';
                // $voucher[$index]['status_text']="Berlaku hingga \n".MyHelper::dateFormatInd($voucher[$index]['voucher_expired_at'],false);
                $voucher[$index]['status_text']="Valid until ".date('d M Y', strtotime($voucher[$index]['voucher_expired_at']));
                $voucher[$index]['voucher_status_text']=["Valid until", date('d M Y', strtotime($voucher[$index]['voucher_expired_at']))];
            }
            $outlet = null;
            if($datavoucher['deal_voucher'] == null){
                unset($voucher[$index]);
            }else{
                // if(count($datavoucher['deal_voucher']['deal']['outlets_active']) <= 1){
                //     unset($voucher[$index]);
                // }else{
                    // $voucher[$index]['deal_voucher']['deal']['outlets'] = $datavoucher['deal_voucher']['deal']['outlets_active'];
                    // unset($voucher[$index]['deal_voucher']['deal']['outlets_active']);
                    $outlet = null;
                    if($datavoucher['id_outlet']){
                        $getOutlet = Outlet::find($datavoucher['id_outlet']);
                        if($getOutlet){
                            $outlet = $getOutlet['outlet_name'];
                        }
                    }

                    $voucher[$index] = array_slice($voucher[$index], 0, 4, true) +
                    array("outlet_name" => $outlet) +
                    array_slice($voucher[$index], 4, count($voucher[$index]) - 1, true) ;

                    // get new voucher code
                    // beetwen "https://chart.googleapis.com/chart?chl="
                    // and "&chs=250x250&cht=qr&chld=H%7C0"
                    // preg_match("/api.qrserver.com\/v1\/create-qr-code\/?size=250x250&data=(.*)&chs=250x250/", $datavoucher['voucher_hash'], $matches);
                    preg_match("/chart.googleapis.com\/chart\?chl=(.*)&chs=250x250/", $datavoucher['voucher_hash'], $matches);

                    // replace voucher_code with code from voucher_hash
                    if (isset($matches[1])) {
                        $voucher[$index]['deal_voucher']['voucher_code'] = $matches[1];
                    }
                    else {
                        $voucherHash = $datavoucher['voucher_hash'];
                        $voucher[$index]['deal_voucher']['voucher_code'] = str_replace("https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=",'',  $voucherHash);
                    }

                    $useragent = $_SERVER['HTTP_USER_AGENT'];
                    // if(stristr($useragent,'okhttp')){
                    //     $voucher[$index]['voucher_expired_at'] = date('d/m/Y H:i',strtotime($voucher[$index]['voucher_expired_at']));
                    // }
                // }
                // else{
                //     unset($voucher[$index]);
                //     continue;
                // }

            }

            $voucher = $this->kotacuks($voucher);
        }
        // add webview url & btn text
        /*if (isset($post['used'])) {
            if ($post['used'] == 0) {
                foreach($voucher as $index => $dataVou){
                    $voucher[$index]['webview_url'] = env('APP_URL') ."webview/voucher/". $dataVou['id_deals_user'];
                    $voucher[$index]['button_text'] = 'INVALIDATE';
                }
            }
            elseif ($post['used'] == 1) {
                foreach($voucher as $index => $dataVou){
                    $voucher[$index]['webview_url'] = env('APP_URL') ."webview/voucher/used/". $dataVou['id_deals_user'];
                }
            }
        }*/
        if (!($post['used']??false)) {

                foreach($voucher as $index => $dataVou){
                    $voucher[$index]['webview_url_v2'] = env('API_URL') ."api/webview/voucher/v2/". $dataVou['id_deals_user'];
                    $voucher[$index]['button_text'] = 'Redeem';
                }

        }

        // if voucher detail, no need pagination
        if (isset($post['id_deals_user']) && $post['id_deals_user'] != "") {
            $voucher[0]['deals_title'] = $voucher[0]['deal_voucher']['deal']['deals_title'];
            $voucher[0]['is_offline'] = $voucher[0]['deal_voucher']['deal']['is_offline'];
            $voucher[0]['is_online'] = $voucher[0]['deal_voucher']['deal']['is_online'];
            $voucher[0]['popup_message'][] = $voucher[0]['deal_voucher']['deal']['deals_title'];
            $voucher[0]['popup_message'][] = 'will be used on the next transaction';
            $result['data'] = $voucher;
        }
        else {
            // add pagination attributes
            // $result['data'] = $voucher;
            $result['data'] = array_map(function($var){
            	if ($var['is_online'] == 1 && $var['is_offline'] == 1) {
            		$redeem_info = "App & Outlet";
            	}elseif($var['is_online'] == 1){
            		$redeem_info = "App only";
            	}else{
            		$redeem_info = "Outlet only";
            	}

            	if ($var['voucher_expired_at'] < date('Y-m-d H:i:s') || !empty($var['used_at'])) {
            		$var['is_used'] = 0;
            	}

                return [
                    'id_deals'=> $var['deal_voucher']['id_deals']??null,
                    'voucher_expired_at'=> $var['voucher_expired_at'],
                    'id_deals_voucher'=> $var['id_deals_voucher'],
                    'id_deals_user'=> $var['id_deals_user'],
                    'deals_title'=>$var['deal_voucher']['deal']['deals_title']??'',
                    'deals_second_title'=>$var['deal_voucher']['deal']['deals_second_title']??'',
                    'webview_url_v2'=>$var['webview_url_v2']??'',
                    'url_deals_image'=>$var['deal_voucher']['deal']['url_deals_image'],
                    'status_redeem'=>($var['redeemed_at']??false)?1:0,
                    'label'=>$var['label'],
                    'status_text'=>$var['status_text'],
                    'voucher_status_text'=>$var['voucher_status_text'],
                    'is_used'=>$var['is_used'],
                    'is_online'=>$var['is_online'],
                    'is_offline'=>$var['is_offline'],
                    'redeem_info'=>$redeem_info,
                    'custom_voucher_expired'=> date('d M Y', strtotime($var['voucher_expired_at'])),
                	'custom_time_voucher_expired'=> date('H:i', strtotime($var['voucher_expired_at'])),
                    'custom_full_voucher_expired'=> date('d M Y', strtotime($var['voucher_expired_at'])).' at '.date('H:i', strtotime($var['voucher_expired_at']))
                ];
            },$voucher);
            $result['current_page'] = $current_page;
            $result['next_page_url'] = $next_page_url;
            $result['prev_page_url'] = $prev_page_url;
            $result['per_page'] = $per_page;
            $result['total'] = $total;
            if(!$result['total']){
                $result=[];
            }
        }

        $isPromo = 0;
        if($request->json('is_use_promo')){
            $isPromo = $request->json('is_use_promo');
        }
        if($isPromo == 1){
            $resultMessage = 'Try again later';
        }else{
            if(
                $request->json('id_outlet') ||
                $request->json('id_brand') ||
                $request->json('expired_start') ||
                $request->json('expired_end') ||
                $request->json('key_free')
            ){
                $resultMessage = 'The Voucher You Are Looking For Is Not Available';
            }else{
                $resultMessage = "You don't have any voucher";
            }
        }

        return response()->json(MyHelper::checkGet($result, $resultMessage));
    }

    function kotacuks($deals)
    {
        $timeNow = date('Y-m-d H:i:s');

        // print_r($deals); exit();

        foreach ($deals as $key => $value) {
            $markerCity = 0;

            $deals[$key]['deal_voucher']['deal']['outlet_by_city'] = [];

            // set time
            $deals[$key]['deal_voucher']['deal']['time_server'] = $timeNow;

            if (!empty($deals[$key]['deal_voucher']['deal']['outlets'])) {
                // ambil kotanya dulu

                // print_r($value['deal_voucher']['deal']); exit();
                $kota = array_column($value['deal_voucher']['deal']['outlets'], 'city');
                $kota = array_values(array_map("unserialize", array_unique(array_map("serialize", $kota))));


                // jika ada pencarian kota
                if (!empty($city)) {
                    $cariKota = array_search($city, array_column($kota, 'id_city'));

                    if (is_integer($cariKota)) {
                        $markerCity = 1;
                    }
                }

                foreach ($kota as $k => $v) {
                    $kota[$k]['outlet'] = [];

                    foreach ($value['deal_voucher']['deal']['outlets'] as $outlet) {
                        if ($outlet['id_city'] != null) {
                            if ($v['id_city'] == $outlet['id_city']) {
                                unset($outlet['pivot']);
                                unset($outlet['city']);

                                array_push($kota[$k]['outlet'], $outlet);
                            }
                        }

                    }
                }

                $deals[$key]['deal_voucher']['deal']['outlet_by_city'] = $kota;
            }

            // unset($deals[$key]['outlets']);
            // jika ada pencarian kota
            if (!empty($city)) {
                if ($markerCity == 0) {
                    unset($deals[$key]);
                }
            }

            // kalkulasi point
            $calc = $value['deal_voucher']['deal']['deals_total_voucher'] - $value['deal_voucher']['deal']['deals_total_claimed'];

            if ($value['deal_voucher']['deal']['deals_voucher_type'] == "Unlimited") {
                $calc = '*';
            }

            $deals[$key]['deal_voucher']['deal']['available_voucher'] = $calc;

            // print_r($deals[$key]['available_voucher']);
        }

        // print_r($deals); exit();
        $deals = array_values($deals);

        return $deals;
    }

    function voucherUser(Request $request){
        $post = $request->json()->all();

        $voucher = DealsUser::join('users', 'deals_users.id_user', 'users.id')->where('phone', $post['phone'])
                                ->with(['deals_voucher.deals', 'outlet'])
                                ->get();

        return response()->json(MyHelper::checkGet($voucher));
    }

    public function useVoucher($id_deals_user, $use_later=null)
    {
    	$user = auth()->user();

		DB::beginTransaction();
		// change is used flag to 0
		$deals_user = DealsUser::where('id_user','=',$user->id)->where('is_used','=',1)->update(['is_used' => 0]);
		if (empty($use_later)) {
			// change specific deals user is used to 1
			$deals_user = DealsUser::where('id_deals_user','=',$id_deals_user)->update(['is_used' => 1]);
		}

		if (is_int($deals_user) || $deals_user) {
			DB::commit();
			$deals_user = 1;
		}else{
			DB::rollBack();
		}
		$deals_user = MyHelper::checkUpdate($deals_user);
		$deals_user['webview_url_v2'] = env('API_URL') ."api/webview/voucher/v2/". $id_deals_user;
		return $deals_user;

    }

    public function unuseVoucher(Request $request)
    {
    	$post = $request->json()->all();
    	$unuse = $this->useVoucher($post['id_deals_user'], 1);
    	if ($unuse) {
    		return response()->json($unuse);
    	}else{
    		return response()->json([
    			'status' => 'fail',
    			'messages' => 'Failed to update voucher'
    		]);
    	}
    }

    public function returnVoucher($id_transaction)
    {
    	$getVouchers = TransactionVoucher::where('id_transaction','=',$id_transaction)->with('deals_voucher.deals')->get();
    	// if ($getVoucher)
    	foreach ($getVouchers ?? [] as $key => $getVoucher) {

    		$update = [
    			'used_at' 		=> null,
    			'id_outlet' 	=> null,
    			'redeemed_at' 	=> null
    		];
	    	$update = DealsUser::where('id_deals_voucher', '=', $getVoucher['id_deals_voucher'])->update($update);

	    	if ($update)
	    	{
	    		$update = TransactionVoucher::where('id_deals_voucher', '=', $getVoucher['id_deals_voucher'])->update(['status' => 'failed']);

	    		if ($update)
	    		{
	    			// $update = Deal::where('id_deals','=',$getVoucher['deals_voucher']['deals']['id_deals'])->update(['deals_total_used' => $getVoucher['deals_voucher']['deals']['deals_total_used']-1]);

	    			if ($update)
		    		{
		    			// return true;
		    			continue;
		    		}
		    		else
		    		{
		    			return false;
		    		}
	    		}
	    	}
	    	else
	    	{
	    		return false;
	    	}
        }

        return true;
    }

    public function checkStatus(MyVoucherStatus $request)
    {
    	$post = $request->json()->all();
    	$getData = DealsUser::where('id_deals_user', '=', $post['id_deals_user'])->first();

		if (!$getData) {
			return response()->json(['status' => 'fail']);
		}
    	$result['payment_status'] = $getData['paid_status']??'';
    	if ($result['payment_status'] == 'Free') {
    		$result['payment_status'] = 'Completed';
    	}
    	$result['webview_url'] = env('API_URL').'api/webview/mydeals/'.$post['id_deals_user'];

		return response()->json(MyHelper::checkGet($result));
    }

    public function checkVoucher($list_voucher)
    {
    	// array_push($list_voucher, 'aaaaaa');
    	$list_voucher = array_flip($list_voucher);
        $list_voucher = array_flip($list_voucher);

    	$result = [
    		'status' 		=> true,
    		'unique_code' 	=> $list_voucher,
    		'same_code' 	=> []
    	];

		$check_voucher 	= DealsVoucher::whereIn('voucher_code',$list_voucher)->pluck('voucher_code')->toArray();

		if ($check_voucher) {
			$diff_code 	= array_udiff($list_voucher, $check_voucher, 'strcasecmp');
			$same_code 	= array_uintersect($list_voucher, $check_voucher, 'strcasecmp');
			$result['unique_code'] 	= $diff_code;
			$result['same_code'] 	= $same_code;

			if (empty($diff_code)) {
				$result['status'] = false;
			}
		}

		return $result;    	
    }
}
