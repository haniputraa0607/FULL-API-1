<?php

namespace Modules\Outlet\Http\Controllers;

use App\Http\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Outlet\Http\Requests\outlet\Filter;

use App\Lib\MyHelper;

class ApiOutletWebview extends Controller
{
    function __construct() {
		$this->outlet  = "Modules\Outlet\Http\Controllers\ApiOutletController";
    }
    public function detailWebview(Request $request, $id)
    {
    	$bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }
        
        $list = MyHelper::postCURLWithBearer('api/outlet/list?log_save=0', ['id_outlet' => $id], $bearer);
        // return $list;
        return view('outlet::webview.list', ['data' => $list['result']]);
    }

    public function detailOutlet(Request $request)
    {
    	$bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }
        
        // $list = MyHelper::postCURLWithBearer('api/outlet/list?log_save=0', [
        //     'id_outlet' => $request->id_outlet,
        //     'latitude' => $request->latitude,
        //     'longitude' => $request->longitude
        // ], $bearer);

        
        $outlet = Outlet::with(['today', 'outlet_schedules'])
        ->where('id_outlet', $request->id_outlet)->get()->toArray()[0];

        $outlet['distance']=number_format((float)$this->distance($request->latitude, $request->longitude, $outlet['outlet_latitude'], $outlet['outlet_longitude'], "K"), 2, '.', '').' km';
        
        foreach ($outlet['outlet_schedules'] as $key => $value) {
            $value = app($this->outlet)->setTimezone($value);
            if (date('l') == $value['day']) {
                $outlet['outlet_schedules'][$key] = [
                    'is_today'  => 1,
                    'day'       => substr($value['day'], 0, 3),
                    'time'      => $value['open'] . ' - ' . $value['close'],
                    'is_closed' => $value['is_closed']
                ];
            } else {
                $outlet['outlet_schedules'][$key] = [
                    'day'       => substr($value['day'], 0, 3),
                    'time'      => $value['open'] . ' - ' . $value['close'],
                    'is_closed' => $value['is_closed']
                ];
            }
        }
        $outlet['is_closed'] = $outlet['today']['is_closed'];
        unset($outlet['today']);
        unset($outlet['url']);
        unset($outlet['detail']);
        unset($outlet['created_at']);
        unset($outlet['updated_at']);

        return response()->json(MyHelper::checkGet($outlet));
    }

    function distance($lat1, $lon1, $lat2, $lon2, $unit) {
        $theta = $lon1 - $lon2;
        $lat1=floatval($lat1);
        $lat2=floatval($lat2);
        $lon1=floatval($lon1);
        $lon2=floatval($lon2);
        $dist  = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist  = acos($dist);
        $dist  = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit  = strtoupper($unit);

        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }

    public function listOutletGofood(Request $request, $type='gofood')
    {
    	$bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }

    	$post = $request->all();
    	// $post['latitude'] = '-7.803761';
        // $post['longitude'] = '110.383058';
        $post['sort'] = 'Nearest';
        $post['type'] = 'transaction';
        $post['search'] = '';
    	$post[$type] = 1;
        // $list = MyHelper::post('outlet/filter', $post);

        $filter = New Filter;
        $filter->merge($post);
        $list = json_decode(json_encode(app($this->outlet)->filter($filter)), true);
        $list = $list['original'];
        // $list = MyHelper::postCURLWithBearer('api/outlet/filter/gofood', $post, $bearer);

        if (stristr($_SERVER['HTTP_USER_AGENT'], 'okhttp')){ $useragent = 'Android';}
        else {$useragent = 'iOS';}

        if (isset($list['status']) && $list['status'] == 'success') {
            return view('outlet::webview.outlet_gofood_v2', ['outlet' => $list['result'], 'useragent'=> $useragent]);
        } elseif (isset($list['status']) && $list['status'] == 'fail') {
            return view('outlet::webview.outlet_gofood_v2', ['outlet' => [], 'msg' => $list['messages'][0], 'useragent'=> $useragent]);
            // return view('error', ['msg' => 'Data failed']);
        } else {
            return view('error', ['msg' => 'Something went wrong, try again']);
        }
    }
}
