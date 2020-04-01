<?php

namespace Modules\Deals\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Deal;
use App\Lib\MyHelper;
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

        if($deals['status'] == 'success'){
            $deals['result'][0]['deals_image'] = env('S3_URL_API') . $deals['result'][0]['deals_image'];
            $response = [
                'status' => 'success',
                'result' => 
                    $deals['result'][0]
            ];
            $response['result']['button_text'] = 'BELI';
            $result = [
                'deals_image'           => $deals['result'][0]['deals_image'],
                'deals_end'             => $deals['result'][0]['deals_end'],
                'deals_voucher'         => ($deals['result'][0]['deals_voucher_type'] == 'Unlimited') ? 'Unlimited' : $deals['result'][0]['deals_total_voucher'] - $deals['result'][0]['deals_total_claimed'] . '/' . $deals['result'][0]['deals_total_voucher'],
                'deals_title'           => $deals['result'][0]['deals_title'],
                'deals_second_title'    => $deals['result'][0]['deals_second_title'],
                'deals_description'     => $deals['result'][0]['deals_description'],
                'deals_button'          => 'Claim',
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
                $result['deals_content'][$keyContent]['title'] = $valueContent['title'];
                foreach ($valueContent['deals_content_details'] as $key => $value) {
                    $result['deals_content'][$keyContent]['detail'][$key]['content'] = $value['content'];
                }
                $i++;
            }

            $result['deals_content'][$i]['is_outlet'] = 1;
            $result['deals_content'][$i]['title'] = 'Available at';
            foreach ($deals['result'][0]['outlet_by_city'] as $keyCity => $valueCity) {
                $result['deals_content'][$i]['detail'][$keyCity]['city'] = strtoupper($valueCity['city_name']);
                foreach($valueCity['outlet'] as $keyOutlet => $valueOutlet) {
                    $result['deals_content'][$i]['detail'][$keyCity]['outlet'][$keyOutlet] = strtoupper($valueOutlet['outlet_name']); 
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
        return response()->json($result);
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
