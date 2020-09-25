<?php

namespace Modules\Deals\Http\Controllers;

use App\Http\Models\DealsUser;
use App\Http\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;

class ApiDealsVoucherWebviewController extends Controller
{
    public function voucherDetail(Request $request, $id_deals_user)
    {
        $bearer = $request->header('Authorization');
        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals_user'] = $id_deals_user;
        $post['used'] = 0;

        $action = MyHelper::postCURLWithBearer('api/voucher/me?log_save=0', $post, $bearer);

        if ($action['status'] != 'success') {
            return [
                'status' => 'fail',
                'messages' => ['Deals is not found']
            ];
        } else {
            $data['voucher'] = $action['result'];
        }

        return view('deals::webview.voucher.voucher_detail_v3', $data);
    }

    public function voucherDetailV2(Request $request, $id_deals_user)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals_user'] = $id_deals_user;
        $post['used'] = 0;

        $action = MyHelper::postCURLWithBearer('api/voucher/me?log_save=0', $post, $bearer);

        if ($action['status'] != 'success') {
            return [
                'status' => 'fail',
                'messages' => ['Deals is not found']
            ];
        } else {
            $data['voucher'] = $action['result'];
        }

        usort($data['voucher']['data'][0]['deal_voucher']['deal']['outlet_by_city'], function($a, $b) {
            if(isset($a['city_name']) && isset($b['city_name'])){
                return $a['city_name'] <=> $b['city_name'];
            }
        });

        for ($i = 0; $i < count($data['voucher']['data'][0]['deal_voucher']['deal']['outlet_by_city']); $i++) {
            usort($data['voucher']['data'][0]['deal_voucher']['deal']['outlet_by_city'][$i]['outlet'] ,function($a, $b) {
                return $a['outlet_name'] <=> $b['outlet_name'];
            });
        }

        return view('deals::webview.voucher.voucher_detail_v4', $data);
    }


    public function detailVoucher(Request $request)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals_user'] = $request->id_deals_user;
        $post['used'] = 0;

        // $action = MyHelper::postCURLWithBearer('api/voucher/me?log_save=0', $post, $bearer);
        $voucher = DealsUser::with([
        	'outlet', 
        	'deals_voucher', 
        	'deals_voucher.deal', 
        	'deals_voucher.deal.deals_content', 
        	'deals_voucher.deal.deals_content' => function($q){
				$q->where('is_active',1);
			}, 
			'deals_voucher.deal.deals_content.deals_content_details', 
			'deals_voucher.deal.outlets' => function($q) { 
				$q->where('outlet_status', 'Active'); 
			}, 
			'deals_voucher.deal.outlets.city'])
        ->where('id_deals_user', $request->id_deals_user)->get()->toArray()[0];

        if($voucher['deals_voucher']['deal']['is_all_outlet'] == 1){
            $outlets = Outlet::with('city')->where('outlet_status','Active')->get()->toArray();
            $voucher['deals_voucher']['deal']['outlets'] = $outlets;
        }

        if (!empty($voucher['deals_voucher']['deal']['outlets'])) {
            $kota = array_column($voucher['deals_voucher']['deal']['outlets'], 'city');
            $kota = array_values(array_map("unserialize", array_unique(array_map("serialize", $kota))));

            foreach ($kota as $k => $v) {
                if ($v) {
                    $kota[$k]['outlet'] = [];
                    foreach ($voucher['deals_voucher']['deal']['outlets'] as $outlet) {
                        if ($v['id_city'] == $outlet['id_city']) {
                            unset($outlet['pivot']);
                            unset($outlet['city']);

                            array_push($kota[$k]['outlet'], $outlet);
                        }
                    }
                } else {
                    unset($kota[$k]);
                }
            }

            $voucher['deals_voucher']['deal']['outlet_by_city'] = $kota;
        }

        usort($voucher['deals_voucher']['deal']['outlet_by_city'], function($a, $b) {
            if(isset($a['city_name']) && isset($b['city_name'])){
                return $a['city_name'] <=> $b['city_name'];
            }
        });

        for ($i = 0; $i < count($voucher['deals_voucher']['deal']['outlet_by_city']); $i++) {
            usort($voucher['deals_voucher']['deal']['outlet_by_city'][$i]['outlet'] ,function($a, $b) {
                return $a['outlet_name'] <=> $b['outlet_name'];
            });
        }

        $voucher['deals_voucher']['deal']['deals_image'] = env('S3_URL_API') . $voucher['deals_voucher']['deal']['deals_image'];

        //add status used
        $voucher['status'] = 'available';
        $voucher['offline_description'] = '';
        if(!empty($voucher['redeemed_at'])){
            if( !empty($voucher['used_at']) ){
                $voucher['status'] = 'offline used';
        		$voucher['offline_description'] = 'Your QR code has been scanned';
            }
            elseif( $voucher['voucher_expired_at'] < date("Y-m-d H:i:s") ){
                $voucher['status'] = 'offline used';
        		$voucher['offline_description'] = 'Your QR code has been expired';
            }
            else{
                $voucher['status'] = 'offline redeem';
        		$voucher['offline_description'] = 'QR Code below \n must be scanned by our Cashier';
            }
        }else{
            if($voucher['is_used'] == '1'){
                $voucher['status'] = 'online used';
            }
        }
        if($voucher['used_at']){
            // $voucher['status_text']="Sudah digunakan pada \n".MyHelper::dateFormatInd($voucher['used_at'],false);
            $voucher['status_text']="Used on ".date('d M Y', strtotime($voucher['used_at']));
            $voucher['btn_disable'] = 1;
        }elseif($voucher['voucher_expired_at']<date('Y-m-d H:i:s')){
            // $voucher['status_text']="Telah berakhir pada \n".MyHelper::dateFormatInd($voucher['voucher_expired_at'],false);
            $voucher['status_text']="Expired on ".date('d M Y', strtotime($voucher['voucher_expired_at']));
            $voucher['btn_disable'] = 1;
        }else{
            // $voucher['status_text']="Berlaku hingga \n".MyHelper::dateFormatInd($voucher['voucher_expired_at'],false);
            $voucher['status_text']="Valid until ".date('d M Y', strtotime($voucher['voucher_expired_at']));
            $voucher['btn_disable'] = 0;
        }
        $data = $voucher;


        $result = [
            'deals_image'           => $data['deals_voucher']['deal']['deals_image'],
            'deals_title'           => $data['deals_voucher']['deal']['deals_title'],
            'deals_description'     => $data['deals_voucher']['deal']['deals_description'],
            'id_deals_voucher'      => $data['id_deals_voucher'],
            'voucher_hash'          => $data['voucher_hash'],
            'voucher_hash_code'     => $data['voucher_hash_code'],
            'id_deals_user'         => $data['id_deals_user'],
            'voucher_expired'       => date('d M Y', strtotime($data['voucher_expired_at'])),
            'is_used'               => $data['is_used'],
            'status'                => $data['status'],
            'btn_used'              => 'Use Later',
            'is_online'             => $data['deals_voucher']['deal']['is_online'],
            'btn_online'            => 'Use Voucher',
            'is_offline'            => $data['deals_voucher']['deal']['is_offline'],
            'btn_offline'           => 'Redeem to Cashier',
            'btn_disable'           => $data['btn_disable'],
            'header_online_voucher' => 'Online Transaction',
            'title_online_voucher'  => 'Apply promo on this app',
            'header_offline_voucher'=> 'Offline Transaction',
            'title_offline_voucher' => 'Redeem directly at Cashier',
            'status_text'           => $data['status_text'],
            'button_text'           => 'Redeem',
            'text_before_scan'      => 'QR Code below<br>must be scanned by our Cashier',
            'custom_outlet_text'	=> $data['deals_voucher']['deal']['custom_outlet_text'],
            'offline_description'	=> $data['offline_description'],
            'popup_message'         => [
                $data['deals_voucher']['deal']['deals_title'],
                'will be used on the next transaction'
            ],
            'custom_voucher_expired' 	  => date('d M Y', strtotime($data['voucher_expired_at'])),
            'custom_time_voucher_expired' => date('H:i', strtotime($data['voucher_expired_at'])),
            'custom_full_voucher_expired' => date('d M Y', strtotime($data['voucher_expired_at'])).' at '.date('H:i', strtotime($data['voucher_expired_at']))
        ];

        if ($data['outlet']['outlet_name']) {

            $result['text_after_scan'] = 'Voucher has been used<br>in '.$data['outlet']['outlet_name'];
        }

        $i = 0;
        foreach ($data['deals_voucher']['deal']['deals_content'] as $keyContent => $valueContent) {
            if (!empty($valueContent['deals_content_details'])) {
                $result['deals_content'][$keyContent]['title'] = $valueContent['title'];
                foreach ($valueContent['deals_content_details'] as $key => $value) {
                    $result['deals_content'][$keyContent]['detail'][0]['data'][$key] = $value['content'];
                    // $content[$key] = '<li>'.$value['content'].'</li>';
                }
                // $result['deals_content'][$keyContent]['detail'] = '<ul style="color:#707070;">'.implode('', $content).'</ul>';
                $i++;
            }
        }

        $result['deals_content'][$i]['is_outlet'] = 1;
        $result['deals_content'][$i]['title'] = 'Available at';

        if($data['deals_voucher']['deal']['custom_outlet_text'] != null){
            $result['deals_content'][$i]['detail'][0]['data'][0] = $data['deals_voucher']['deal']['custom_outlet_text'];
        }else{
            foreach ($data['deals_voucher']['deal']['outlet_by_city'] as $keyCity => $valueCity) {
                if (isset($valueCity['city_name'])) {
                    $result['deals_content'][$i]['detail'][$keyCity]['city'] = $valueCity['city_name'];
                    foreach ($valueCity['outlet'] as $keyOutlet => $valueOutlet) {
                        $result['deals_content'][$i]['detail'][$keyCity]['outlet'][$keyOutlet] = $valueOutlet['outlet_name'];
                        // $valTheOutlet[$keyOutlet] = '<li style="line-height: 12px;">' . $valueOutlet['outlet_name'] . '</li>';
                    }
                    // $city[$keyCity] = strtoupper($valueCity['city_name']) . '<br><ul style="color:#707070;">' .implode('', $valTheOutlet).'</ul>';
                    // $result['deals_content'][$i]['detail'] = implode('', $city);
                }
            }
        }

        return response()->json(MyHelper::checkGet($result));
    }

    // display detail voucher after used
    public function voucherUsed(Request $request, $id_deals_user)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals_user'] = $id_deals_user;
        $post['used'] = 1;

        $action = MyHelper::postCURLWithBearer('api/voucher/me?log_save=0', $post, $bearer);

        if ($action['status'] != 'success') {
            return [
                'status' => 'fail',
                'messages' => ['Deals is not found']
            ];
        } else {
            $data['voucher'] = $action['result'];
        }

        return view('deals::webview.voucher.voucher_detail', $data);
    }
}
