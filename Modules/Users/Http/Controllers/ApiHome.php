<?php

namespace Modules\Users\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\User;
use App\Http\Models\UserFeature;
use App\Http\Models\UserDevice;
use App\Http\Models\Level;
use App\Http\Models\LogRequest;
use App\Http\Models\UserInbox;
use App\Http\Models\Setting;
use App\Http\Models\Greeting;
use App\Http\Models\HomeBackground;
use App\Http\Models\UsersMembership;
use App\Http\Models\Transaction;
use App\Http\Models\Banner;

use DB;
use App\Lib\MyHelper;

use Modules\Users\Http\Requests\Home;
use Modules\Queue\Http\Controllers\ApiQueue;

class ApiHome extends Controller
{
    public $getMyVoucher;
    public $endPoint;
    
    public function __construct() {
        date_default_timezone_set('Asia/Jakarta');
		$this->balance  = "Modules\Balance\Http\Controllers\BalanceController";
		$this->point  = "Modules\Deals\Http\Controllers\ApiDealsClaim";
		$this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
		$this->endPoint  = env('APP_API_URL');
    }
	
	public function homeNotLoggedIn(Request $request) {
		
		if ($request->json('device_id') && $request->json('device_token') && $request->json('device_type')) {
           $this->updateDeviceUserGuest($request->json('device_id'), $request->json('device_token'), $request->json('device_type'));
		}
		$key = array_pluck(Setting::where('key', 'LIKE', '%default_home%')->get()->toArray(), 'key');
		$value = array_pluck(Setting::where('key', 'LIKE', '%default_home%')->get()->toArray(), 'value');
		$defaultHome = array_combine($key, $value);

		if(isset($defaultHome['default_home_image'])){
			$defaultHome['default_home_image_url'] = $this->endPoint.$defaultHome['default_home_image']; 
		}
		
		if(isset($defaultHome['default_home_splash_screen'])){
			$defaultHome['splash_screen_url'] = $this->endPoint.$defaultHome['default_home_splash_screen']."?"; 
		}

        // banner
        $banners = $this->getBanner();
        $defaultHome['banners'] = $banners;
	   
       return response()->json(MyHelper::checkGet($defaultHome));
	}

    public function getBanner()
    {
        // banner
        $banners = Banner::orderBy('position')->get();
        // add full url to collection
        $banners = $banners->map(function ($banner, $key) {
            $item['image_url'] = url($banner->image);
            $item['id_news']   = $banner->id_news;
            $item['url']       = $banner->url;
            // $item['position']  = $banner->position;
            return $item;
        });
        $banners->all();

        return $banners;
    }
	
    public function refreshPointBalance(Request $request) {
		$user = $request->user();
		if($user){
			$point      = app($this->point)->getPoint($user->id);
			$balance      = app($this->balance)->balanceNow($user->id);
			
			 /* QR CODE */
            $expired = Setting::where('key', 'qrcode_expired')->first();
            if(!$expired || ($expired && $expired->value == null)){
                $expired = '10';
            }else{
                $expired = $expired->value;
            }

            $timestamp = strtotime('+'.$expired.' minutes');
        
            $qr = MyHelper::createQR($timestamp, $user->phone);

            $qrCode = 'https://chart.googleapis.com/chart?chl='.$qr.'&chs=250x250&cht=qr&chld=H%7C0';
            $qrCode = html_entity_decode($qrCode);
				
			$result = [
					'status' => 'success',
					'result' => [
						// 'total_point'   => (int) $point,
						'total_kopi_point' => (int) $balance,
						'qr_code'        => $qrCode
					]
				]; 
		}else {
			$result = [
                'status' => 'fail'
            ];
		}
		return response()->json($result);
	}
	
    public function home(Home $request) {
        try {
            $user = $request->user();

            /**
             * update device token 
             */

            if ($request->json('device_id') && $request->json('device_token') && $request->json('device_type')) {
                $this->updateDeviceUser($user, $request->json('device_id'), $request->json('device_token'), $request->json('device_type'));
            }

            if ($request->json('time')) {
                $time = $request->json('time');
            }
            else {
                $time = date('H:i:s');
            }

            $time = strtotime($time);

            // ambil dari DB
            $timeDB = Setting::select('key', 'value')->whereIn('key', ['greetings_morning', 'greetings_afternoon', 'greetings_evening', 'greetings_latenight'])->get()->toArray();

            if (empty($timeDB)) {
                $greetings = "Hello";
                $background = "";
            }
            else {
                $dbTime = [];

                /**
                 * replace key supaya gamapang dibaca
                 */
                foreach ($timeDB as $key => $value) {
                    $dbTime[str_replace("greetings_", "", $value['key'])] = $value['value'];
                }
                
                /**
                 * search greetings from DB
                 */
                if($time >= strtotime($dbTime['afternoon']) && $time < strtotime($dbTime['evening'])){
                    // salamnya dari DB
                    $greetings  = Greeting::where('when', '=', 'afternoon')->get()->toArray();
                    $background  = HomeBackground::where('when', '=', 'afternoon')->get()->toArray();
                }
                elseif($time >= strtotime($dbTime['evening']) && $time <= strtotime($dbTime['latenight'])){
                    $greetings  = Greeting::where('when', '=', 'evening')->get()->toArray();
                    $background  = HomeBackground::where('when', '=', 'evening')->get()->toArray();
                }
                elseif($time >= strtotime($dbTime['latenight'])){
                    $greetings  = Greeting::where('when', '=', 'latenight')->get()->toArray();
                    $background  = HomeBackground::where('when', '=', 'latenight')->get()->toArray();
                }
                elseif($time <= strtotime("04:00:00")){
                    $greetings  = Greeting::where('when', '=', 'latenight')->get()->toArray();
                    $background  = HomeBackground::where('when', '=', 'latenight')->get()->toArray();
                }
                else{
                    $greetings  = Greeting::where('when', '=', 'morning')->get()->toArray();
                    $background  = HomeBackground::where('when', '=', 'morning')->get()->toArray();
                }
				
                /**
                 * kesimpulannya
                 */
                if (empty($greetings)) {
                    $greetingss = "Hello";
                    $greetingss2 = "Nice to meet You";
                    $background = "";
                }
                else {
                    $greetingKey   = array_rand($greetings, 1);
					// return $greetings[$greetingKey]['greeting2'];
                    $greetingss     = app($this->autocrm)->TextReplace($greetings[$greetingKey]['greeting'], $user['phone']);
                    $greetingss2     = app($this->autocrm)->TextReplace($greetings[$greetingKey]['greeting2'], $user['phone']);
                    if (!empty($background)) {
						$backgroundKey = array_rand($background, 1);
						$background    = env('API_URL').'/'.$background[$backgroundKey]['picture'];
					}
                }
            }
            
            $expired = Setting::where('key', 'qrcode_expired')->first();
            if(!$expired || ($expired && $expired->value == null)){
                $expired = '10';
            }else{
                $expired = $expired->value;
            }

            $timestamp = strtotime('+'.$expired.' minutes');
            $qr = MyHelper::createQR($timestamp, $user->phone);
            
            $qrCode = 'https://chart.googleapis.com/chart?chl='.$qr.'&chs=250x250&cht=qr&chld=H%7C0';
            $qrCode = html_entity_decode($qrCode);
			
			$point      = app($this->point)->getPoint($user->id);
			$balance      = app($this->balance)->balanceNow($user->id);
			
			$membership = UsersMembership::select('memberships.*')
										->Join('memberships','memberships.id_membership','=','users_memberships.id_membership')
										->where('id_user','=',$user->id)
										->orderBy('id_log_membership','desc')
										->first();
			if(isset($membership) && $membership != ""){
				if(isset($membership['membership_image']))
					$membership['membership_image'] = env('APP_API_URL').$membership['membership_image'];
			} else {
				$membership = null;
			}
			
			$splash = Setting::where('key', '=', 'default_home_splash_screen')->first();

			if(!empty($splash)){
				$splash = $this->endPoint.$splash['value']; 
			} else {
				$splash = null;
            }
            
            $countUnread = UserInbox::where('id_user','=',$user['id'])->where('read', '0')->count();
            $transactionPending = Transaction::where('id_user','=',$user['id'])->where('transaction_payment_status', 'Pending')->count();

            // banner
            $banners = $this->getBanner();

            // webview: user profile form
            $webview_url = "";
            $webview_link = env('APP_URL') . '/webview/complete-profile/' . $user->phone;

            // check user profile completeness (if there is null data)
            if ($user->id_city=="" || $user->gender=="" || $user->birthday=="") {
                $user_profile = true;
            }

                // get setting user profile value
                $complete_profile_interval = 0;
                $complete_profile_count = 0;
                $setting_profile_point = Setting::where('key', 'complete_profile_interval')->first();
                $setting_profile_cashback = Setting::where('key', 'complete_profile_count')->first();
                if (isset($setting_profile_point->value)) {
                    $complete_profile_interval = $setting_profile_point->value;
                }
                if (isset($setting_profile_cashback->value)) {
                    $complete_profile_count = $setting_profile_cashback->value;
                }

                // check interval and counter
                // if $webview_url == "", app won't pop up the form
                if ($user->last_complete_profile != null) {
                    $now = date('Y-m-d H:i:s');
                    // count date difference (in minutes)
                    $date_start = strtotime($user->last_complete_profile);
                    $date_end   = strtotime($now);
                    $date_diff  = $date_end - $date_start;
                    $minutes_diff = $date_diff / 60;

                    if ($user->count_complete_profile < $complete_profile_count && $complete_profile_interval < $minutes_diff ) {
                        $webview_url = $webview_link;
                    }
                }
                else {  // never pop up before
                    $webview_url = $webview_link;
                }


            $result = [
                'status' => 'success',
                'result' => [
                    // 'greetings'     => $greetingss,
                    // 'greetings2'    => $greetingss2,
                    // 'background'    => $background,
                    'banners'       => $banners,
                    'splash_screen_url' => $splash."?",
                    // 'total_point'   => (int) $point,
                    'total_kopi_point' => (int) $balance,
                    // 'notification'  =>[
                    //     'total' => $countUnread + $transactionPending,
                    //     'count_unread_inbox' => $countUnread,
                    //     'count_transaction_pending' => $transactionPending,
                    // ],
                    'user_info'     => [
                        'name'  => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'birthday' => date("d F Y", strtotime($user->birthday)),
                        'gender' => $user->gender,
                        'city'  => $user->city,
                        'membership'  => $membership,
                    ],
                    'qr_code'       => $qrCode,
                    'uid'           => $qr,
                    'webview_complete_profile_url'   => $webview_url
                ]
            ]; 
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(MyHelper::throwError($e));
        }        
    }

    public function background(Request $request) {
        try {

            if ($request->json('time')) {
                $time = $request->json('time');
            }
            else {
                $time = date('H:i:s');
            }

            $time = strtotime($time);

            // ambil dari DB
            $timeDB = Setting::select('key', 'value')->whereIn('key', ['greetings_morning', 'greetings_afternoon', 'greetings_evening', 'greetings_late_night'])->get()->toArray();

            // print_r($timeDB); exit();

            if (empty($timeDB)) {
                $background = "";
            }
            else {
                $dbTime = [];

                /**
                 * replace key supaya gamapang dibaca
                 */
                foreach ($timeDB as $key => $value) {
                    $dbTime[str_replace("greetings_", "", $value['key'])] = $value['value'];
                }

                /**
                 * search greetings from DB
                 */
                if($time >= strtotime($dbTime['afternoon']) && $time < strtotime($dbTime['evening'])){
                    // salamnya dari DB
                    $background  = HomeBackground::where('when', '=', 'afternoon')->get()->toArray();
                }
                elseif($time >= strtotime($dbTime['evening']) && $time <= strtotime($dbTime['late_night'])){
                    $background  = HomeBackground::where('when', '=', 'evening')->get()->toArray();
                }
                elseif($time >= strtotime($dbTime['late_night'])){
                    $background  = HomeBackground::where('when', '=', 'late_night')->get()->toArray();
                }
                elseif($time <= strtotime("04:00:00")){
                    $background  = HomeBackground::where('when', '=', 'late_night')->get()->toArray();
                }
                else{
                    $background  = HomeBackground::where('when', '=', 'morning')->get()->toArray();
                }

                /**
                 * kesimpulannya
                 */
                if (empty($background)) {
                    $background = "";
                }
                else {
                    $backgroundKey = array_rand($background, 1);
                    $background    = env('APP_API_URL').$background[$backgroundKey]['picture'];
                }
            }

            $result = [
                'status' => 'success',
                'result' => [
                    'background'     => $background,
                ]
            ]; 
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(MyHelper::throwError($e));
        } 
    }

	public function updateDeviceUserGuest($device_id, $device_token, $device_type) {
        $dataUpdate = [
            'device_id'    => $device_id,
            'device_token' => $device_token,
            'device_type' => $device_type
        ];

        $checkDevice = UserDevice::where('device_id', $device_id)
								->where('device_token', $device_token)
								->where('device_type', $device_type)
								->count();
        if ($checkDevice == 0) {
            $update                = UserDevice::create($dataUpdate);
            $result = [
                'status' => 'updated'
            ];
        }
        else {
            $result = [
                'status' => 'success'
            ];
        }

        return $result;
    }
	
    public function updateDeviceUser($user, $device_id, $device_token, $device_type) {
        $dataUpdate = [
            'device_id'    => $device_id,
            'device_token' => $device_token,
            'device_type' => $device_type
        ];

        $checkDevice = UserDevice::where('id_user', $user->id)
								->where('device_id', $device_id)
								->where('device_type', $device_type)
								->count();

        if ($checkDevice == 0) {
            $dataUpdate['id_user'] = $user->id;
            $update                = UserDevice::create($dataUpdate);
        }
        else {
            $update = UserDevice::where('id_user','=',$user->id)->update($dataUpdate);
        }

        if ($update) {
			if($device_type == 'Android')
            $query                 = User::where('id','=',$user->id)->update(['android_device' => $device_id]);
		
			if($device_type == 'IOS')
            $query                 = User::where('id','=',$user->id)->update(['ios_device' => $device_id]);
		
            $result = [
                'status' => 'updated'
            ];
        }
        else {
            $result = [
                'status' => 'fail'
            ];
        }

        return $result;
    }
}