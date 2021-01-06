<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Models\LogActivitiesPos;
use App\Lib\MyHelper;
use Auth;

class LogActivitiesPOSMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $arrReq = $request->except('_token');
        if(!isset($arrReq['log_save'])){

            if(!isset($arrReq['page']) || (int)$arrReq['page'] <= 1){

                $user = json_encode($request->user());
                $url = $request->url();
                $user = json_decode(json_encode($request->user()), true);
                $st = stristr(json_encode($response),'success');
                $status = 'fail';
                if($st) $status = 'success';
                $reqnya = $request->json()->all();
                $requestnya = json_encode($reqnya);
                $requeste = json_decode($requestnya, true);

                $outletCode = null;
                if(isset($request['store_code'])){
                    $outletCode = $request['store_code'];
                }

                if($requestnya == '[]') $requestnya = null;
                $urlexp = explode('/',$url);

                if(isset($urlexp[6])){
                    $module = $urlexp[6];
                }elseif(isset($urlexp[4])){
                    $module = $urlexp[4];
                }

                if(stristr($url, 'v1/pos')) $module = 'POS';

                $subject = "Unknown";

                //CRUD
                if(stristr($url, 'create') || (stristr($url, 'new') && !stristr($url, 'news') ) ) {
                    if($subject){
                        $subject = 'Create '.$subject;
                    }
                }
                if(stristr($url, 'update')) {
                    if($subject){
                        $subject = 'Update '.$subject;
                    }
                }
                if(stristr($url, 'delete')) {
                    if($subject){
                        $subject = 'Delete '.$subject;
                    }
                }

                if(stristr($url, 'detail')) {
                    if($subject){
                        $subject = $subject.' Detail';
                    }
                }

                if(stristr($url, 'webview')) {
                    if($subject){
                        $subject = 'Webview '.$subject;
                    }
                }

                if(stristr($url, 'list')) {
                    if(stristr($url, 'webview')) {
                        if($subject){
                            $subject = 'Webview '.$subject;
                        }
                    }else{
                        if($subject){
                            $subject = $subject.' List';
                        }
                    }

                }
                if(stristr($url, 'filter')) {
                    if($subject){
                        $subject = $subject.' Filter';
                    }
                }

                if(stristr($url, 'pos/check/member')) { $subject = 'POS Check Member'; }
                elseif(stristr($url, 'pos/check/voucher')) { $subject = 'POS Check Voucher'; }
                elseif(stristr($url, 'pos/voucher/void')) $subject = 'POS Void Voucher';
                elseif(stristr($url, 'pos/menu')) $subject = 'POS Menu Sync Return';
                elseif(stristr($url, 'pos/outlet')) $subject = 'POS Outlet Sync';
                elseif(stristr($url, 'pos/outlet/menu')) $subject = 'POS Menu Outlet Sync';
                elseif(stristr($url, 'pos/menu/sync')) $subject = 'POS Menu Sync';
                elseif(stristr($url, 'pos/transaction/detail')) $subject = 'Fetch Pre Order';
                elseif(stristr($url, 'pos/transaction/refund')) $subject = 'POS Transaction Refund';
                elseif(stristr($url, 'pos/brand')) $subject = 'POS Brand Sync';
                elseif(stristr($url, 'pos/order/accept')) $subject = 'POS Order Ready';
                elseif(stristr($url, 'pos/order/ready')) $subject = 'POS Order Ready';
                elseif(stristr($url, 'pos/order/taken')) $subject = 'POS Order Ready';
                elseif(stristr($url, 'pos/order/reject')) $subject = 'POS Order Ready';

                if(!empty($request->header('ip-address-view'))){
                    $ip = $request->header('ip-address-view');
                }else{
                    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];
                    if(strpos($ip,',') !== false) {
                        $ip = substr($ip,0,strpos($ip,','));
                    }
                }

                $userAgent = $request->header('user-agent');

                $dtUser = null;

                if(!empty($user) && $user != ""){
                    $dtUser = json_encode($request->user());
                }

                $data = [
                    'url' 		=> $url,
                    'subject' 		=> $subject,
                    'outlet_code' 	=> $outletCode,
                    'user' 		=> $dtUser,
                    'request' 		=> $requestnya,
                    'response_status'   => $status,
                    'response' 		=> json_encode($response),
                    'ip' 		=> $ip,
                    'useragent' 	=> $userAgent
                ];

                $log = LogActivitiesPos::create($data);
            }

        }
        return $response;

    }
}
