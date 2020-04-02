<?php

namespace Modules\Deals\Http\Controllers;

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

    
    public function detailVoucher(Request $request, $id_deals_user)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals_user'] = $id_deals_user;
        $post['used'] = 0;

        $action = MyHelper::postCURLWithBearer('api/voucher/me?log_save=0', $post, $bearer);
        
        if ($action['status'] != 'success') {
            return response()->json([
                'status' => 'fail',
                'messages' => ['Deals is not found']
            ]);
        } else {
            usort($action['result']['data'][0]['deal_voucher']['deal']['outlet_by_city'], function($a, $b) {
                if(isset($a['city_name']) && isset($b['city_name'])){
                    return $a['city_name'] <=> $b['city_name'];
                }
            });
    
            for ($i = 0; $i < count($action['result']['data'][0]['deal_voucher']['deal']['outlet_by_city']); $i++) {
                usort($action['result']['data'][0]['deal_voucher']['deal']['outlet_by_city'][$i]['outlet'] ,function($a, $b) {
                    return $a['outlet_name'] <=> $b['outlet_name'];
                });
            }

            $action['result']['data'][0]['deal_voucher']['deal']['deals_image'] = env('S3_URL_API') . $action['result']['data'][0]['deal_voucher']['deal']['deals_image'];

            $data = $action['result']['data'][0];
            
            $result = [
                'deals_image'           => $data['deal_voucher']['deal']['deals_image'],
                'deals_title'           => $data['deal_voucher']['deal']['deals_title'],
                'id_deals_voucher'      => $data['id_deals_voucher'],
                'id_deals_user'         => $data['id_deals_user'],
                'voucher_expired'       => date('d F Y', strtotime($data['voucher_expired_at'])),
                'is_used'               => $data['is_used'],
                'is_online'             => $data['is_online'],
                'is_offline'            => $data['is_offline'],
            ];

            $i = 0;
            foreach ($data['deal_voucher']['deal']['deals_content'] as $keyContent => $valueContent) {
                $result['deals_content'][$keyContent]['title'] = $valueContent['title'];
                foreach ($valueContent['deals_content_details'] as $key => $value) {
                    $result['deals_content'][$keyContent]['detail'][$key]['content'] = $value['content'];
                }
                $i++;
            }

            $result['deals_content'][$i]['is_outlet'] = 1;
            $result['deals_content'][$i]['title'] = 'Available at';
            foreach ($data['deal_voucher']['deal']['outlet_by_city'] as $keyCity => $valueCity) {
                if (isset($valueCity['city_name'])) {
                    $result['deals_content'][$i]['detail'][$keyCity]['city'] = strtoupper($valueCity['city_name']);
                    foreach ($valueCity['outlet'] as $keyOutlet => $valueOutlet) {
                        $result['deals_content'][$i]['detail'][$keyCity]['outlet'][$keyOutlet] = strtoupper($valueOutlet['outlet_name']);
                    }
                }
            }

            return response()->json(MyHelper::checkGet($result));
        }
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
