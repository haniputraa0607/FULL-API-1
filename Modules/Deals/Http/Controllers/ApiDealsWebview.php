<?php

namespace Modules\Deals\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Deal;
use App\Http\Models\User;
use App\Lib\MyHelper;
use Illuminate\Support\Facades\Auth;
use Route;

use Modules\Deals\Http\Requests\Deals\ListDeal;

class ApiDealsWebview extends Controller
{
    // deals detail webview
    public function dealsDetail(Request $request)
    {
        // return url webview and button text for mobile (native button)
        
        $post['id_deals'] = $request->get('id_deals');
        $post['publish'] = 1;
        $post['deals_type'] = "Deals";
        $post['web'] = 1;
        
        $deals = MyHelper::postCURLWithBearer('api/deals/list', $post, $request->header('Authorization'));
        $point = Auth::user()->balance;
        // dd($user);
        // $point = User::where

        if($deals['status'] == 'success'){
            $deals['result'][0]['deals_image'] = env('S3_URL_API') . $deals['result'][0]['deals_image'];
            $response = [
                'status' => 'success',
                'result' => 
                    $deals['result'][0]
            ];
            $response['result']['button_text'] = 'BELI';
            
            $result = [
                'id_deals'                      => $deals['result'][0]['id_deals'],
                'deals_type'                    => $deals['result'][0]['deals_type'],
                'deals_status'                  => $deals['result'][0]['deals_status'],
                'deals_voucher_type'            => $deals['result'][0]['deals_voucher_price_type'],
                'deals_voucher_use_point'       => (($deals['result'][0]['deals_voucher_price_cash'] - $point) <= 0) ? MyHelper::requestNumber(0,'_POINT') : MyHelper::requestNumber($deals['result'][0]['deals_voucher_price_cash'] - $point,'_POINT'),
                'deals_voucher_point_now'       => MyHelper::requestNumber($point,'_POINT'),
                'deals_voucher_avaliable_point' => (($point - $deals['result'][0]['deals_voucher_price_cash']) <= 0) ? MyHelper::requestNumber(0,'_POINT') : MyHelper::requestNumber($point - $deals['result'][0]['deals_voucher_price_cash'],'_POINT'),
                'deals_voucher_point_success'   => (($deals['result'][0]['deals_voucher_price_cash'] - $point) <= 0) ? 'enable' : 'disable',
                'deals_image'                   => $deals['result'][0]['deals_image'],
                'deals_start'                   => $deals['result'][0]['deals_start'],
                'deals_end'                     => $deals['result'][0]['deals_end'],
                'deals_voucher'                 => ($deals['result'][0]['deals_voucher_type'] == 'Unlimited') ? 'Unlimited' : $deals['result'][0]['deals_total_voucher'] - $deals['result'][0]['deals_total_claimed'] . '/' . $deals['result'][0]['deals_total_voucher'],
                'deals_title'                   => $deals['result'][0]['deals_title'],
                'deals_second_title'            => $deals['result'][0]['deals_second_title'],
                'deals_description'             => $deals['result'][0]['deals_description'],
                'deals_button'                  => 'Claim',
                'time_server'                   => $deals['result'][0]['time_server'],
                'time_to_end'                   => strtotime($deals['result'][0]['deals_end']) - time(),
                'button_text'                   => $deals['result'][0]['button_text'],
                'payment_message'               => $deals['result'][0]['payment_message'],
                'payment_success_message'       => $deals['result'][0]['payment_success_message']
            ];
            if ($deals['result'][0]['deals_voucher_price_cash'] != "") {
                $result['deals_price'] = MyHelper::requestNumber($deals['result'][0]['deals_voucher_price_cash'], '_CURRENCY');
            } elseif ($deals['result'][0]['deals_voucher_price_point']) {
                $result['deals_price'] = MyHelper::requestNumber($deals['result'][0]['deals_voucher_price_point'],'_POINT') . " points";
            } else {
                $result['deals_price'] = "Free";
            }
            
            $i = 0;
            foreach ($deals['result'][0]['deals_content'] as $keyContent => $valueContent) {
                if (!empty($valueContent['deals_content_details'])) {
                    $result['deals_content'][$keyContent]['title'] = $valueContent['title'];
                    foreach ($valueContent['deals_content_details'] as $key => $value) {
                        $content[$key] = '<ol style="line-height: 10px;">' . $value['content'] . '</ol>';
                    }
                    $result['deals_content'][$keyContent]['detail'] = '<ul>'.implode('', $content).'</ul>';
                    $i++;
                }
            }

            $result['deals_content'][$i]['title'] = 'Available at';
            foreach ($deals['result'][0]['outlet_by_city'] as $keyCity => $valueCity) {
                if (isset($valueCity['city_name'])) {
                    foreach ($valueCity['outlet'] as $keyOutlet => $valueOutlet) {
                        $outlet[$keyOutlet] = '<li style="line-height: 12px;">' . $valueOutlet['outlet_name'] . '</li>';
                    }
                    $city[$keyCity] = strtoupper($valueCity['city_name']) . '<br>' . implode('', $outlet);
                    $result['deals_content'][$i]['detail'] = '<ul>'.implode('', $city).'</ul>';
                }
            }
        }else{
            $result = [
                'status' => 'fail',
                'messages' => [
                    'Deals Not Found'
                ]
            ];
        }

        return response()->json(MyHelper::checkGet($result));
    }

    // webview deals detail
    public function webviewDealsDetail(Request $request, $id_deals, $deals_type)
    {
        $bearer = $request->header('Authorization');
        
        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals'] = $id_deals;
        $post['publish'] = 1;
        $post['deals_type'] = "Deals";
        $post['web'] = 1;
        
        $action = MyHelper::postCURLWithBearer('api/deals/list', $post, $bearer);

        if ($action['status'] != 'success') {
            return [
                'status' => 'fail',
                'messages' => ['Deals is not found']
            ];
        } else {
            $data['deals'] = $action['result'];
        }
        
        usort($data['deals'][0]['outlet_by_city'], function($a, $b) {
            return $a['city_name'] <=> $b['city_name'];
        });
        
        for ($i = 0; $i < count($data['deals'][0]['outlet_by_city']); $i++) {
            usort($data['deals'][0]['outlet_by_city'][$i]['outlet'] ,function($a, $b) {
                return $a['outlet_name'] <=> $b['outlet_name'];
            });
        }
        
        return view('deals::webview.deals.deals_detail', $data);
    }

    public function dealsClaim(Request $request, $id_deals_user)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals_user'] = $id_deals_user;

        $action = MyHelper::postCURLWithBearer('api/deals/me', $post, $bearer);

        if ($action['status'] != 'success') {
            return [
                'status' => 'fail',
                'messages' => ['Deals is not found']
            ];
        } else {
            $data['deals'] = $action['result'];
        }

        return view('deals::webview.deals.deals_claim', $data);
    }

    public function dealsDetailLater(Request $request)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return abort(404);
        }

        $post['id_deals_user'] = $request->id_deals_user;

        $action = MyHelper::postCURLWithBearer('api/deals/me', $post, $bearer);

        if ($action['status'] != 'success') {
            return [
                'status' => 'fail',
                'messages' => ['Deals is not found']
            ];
        } else {
            $data['deals'] = $action['result'];
        }

        $result = [
            'id_deals_user'             => $data['deals']['id_deals_user'],
            'header_title'              => 'Horayy!',
            'header_sub_title'          => 'Thank you for claiming',
            'deals_title'               => $data['deals']['deals_voucher']['deal']['deals_title'],
            'deals_image'               => env('S3_URL_API') . $data['deals']['deals_voucher']['deal']['deals_image'],
            'voucher_expired_at'        => 'Valid until ' . date('d F Y', strtotime($data['deals']['voucher_expired_at'])),
            'claimed_at'                => date('d M Y H:i', strtotime($data['deals']['claimed_at'])),
            'transaction_id'            => strtotime($data['deals']['claimed_at']).$data['deals']['id_deals_user'],
        ];

        if ($data['deals']['voucher_price_point'] != null) {
            $result['payment'] = number_format($data['deals']['voucher_price_point'],0,",",".").' points';
        } elseif ($data['deals']['voucher_price_cash'] != null) {
            $result['payment'] = number_format($data['deals']['voucher_price_cash'],0,",",".");
        } else {
            $result['payment'] = 'Free';
        }

        return response()->json(MyHelper::checkGet($result));
    }
    
    // voucher detail webview
    /*public function voucherDetail($id_deals_user)
    {
        // return url webview and button text for mobile (native button)
        $response = [
            'status' => 'success',
            'result' => [
                'webview_url' => env('APP_URL') ."webview/voucher/". $id_deals_user,
                'button_text' => 'INVALIDATE'
            ]
        ];
        return response()->json($response);
    }*/
    
}
