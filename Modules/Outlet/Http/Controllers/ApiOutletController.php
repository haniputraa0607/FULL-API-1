<?php

namespace Modules\Outlet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Outlet;
use App\Http\Models\OutletDoctor;
use App\Http\Models\OutletDoctorSchedule;
use App\Http\Models\OutletHoliday;
use App\Http\Models\Holiday;
use App\Http\Models\DateHoliday;
use App\Http\Models\OutletPhoto;
use App\Http\Models\City;
use App\Http\Models\User;
use App\Http\Models\UserOutlet;
use App\Http\Models\Configs;
use App\Http\Models\OutletSchedule;
use App\Http\Models\Setting;
use App\Http\Models\OauthAccessToken;

use App\Imports\ExcelImport;
use App\Imports\FirstSheetOnlyImport;

use App\Lib\MyHelper;
use Modules\Outlet\Http\Requests\Outlet\OutletListOrderNow;
use Validator;
use Hash;
use DB;
use App\Lib\MailQueue as Mail;
use Excel;
use Storage;

use Modules\Brand\Entities\BrandOutlet;
use Modules\Brand\Entities\Brand;
use Modules\Outlet\Entities\OutletOvo;
use Modules\Outlet\Http\Requests\outlet\Upload;
use Modules\Outlet\Http\Requests\outlet\Update;
use Modules\Outlet\Http\Requests\outlet\UpdateStatus;
use Modules\Outlet\Http\Requests\outlet\UpdatePhoto;
use Modules\Outlet\Http\Requests\outlet\UploadPhoto;
use Modules\Outlet\Http\Requests\outlet\Create;
use Modules\Outlet\Http\Requests\outlet\Delete;
use Modules\Outlet\Http\Requests\outlet\DeletePhoto;
use Modules\Outlet\Http\Requests\outlet\Nearme;
use Modules\Outlet\Http\Requests\outlet\Filter;
use Modules\Outlet\Http\Requests\outlet\OutletList;

use Modules\Outlet\Http\Requests\UserOutlet\Create as CreateUserOutlet;
use Modules\Outlet\Http\Requests\UserOutlet\Update as UpdateUserOutlet;

use Modules\Outlet\Http\Requests\Holiday\HolidayStore;
use Modules\Outlet\Http\Requests\Holiday\HolidayEdit;
use Modules\Outlet\Http\Requests\Holiday\HolidayUpdate;
use Modules\Outlet\Http\Requests\Holiday\HolidayDelete;

use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Illuminate\Support\Facades\Auth;

class ApiOutletController extends Controller
{
    public $saveImage = "img/outlet/";

    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->autocrm              = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
    }

    function checkInputOutlet($post=[]) {
        $data = [];

        if (isset($post['outlet_code'])) {
            $data['outlet_code'] = strtoupper($post['outlet_code']);
        }
        if (isset($post['outlet_name'])) {
            $data['outlet_name'] = $post['outlet_name'];
        }
        if (isset($post['outlet_address'])) {
            $data['outlet_address'] = $post['outlet_address'];
        }
        if (isset($post['id_city'])) {
            $data['id_city'] = $post['id_city'];
        }
        if (isset($post['outlet_postal_code'])) {
            $data['outlet_postal_code'] = $post['outlet_postal_code'];
        }
        if (isset($post['outlet_phone'])) {
            $data['outlet_phone'] = $post['outlet_phone'];
        }
        if (isset($post['outlet_fax'])) {
            $data['outlet_fax'] = $post['outlet_fax'];
        }
        if (isset($post['outlet_email'])) {
            $data['outlet_email'] = $post['outlet_email'];
        }
        if (isset($post['outlet_latitude'])) {
            $data['outlet_latitude'] = $post['outlet_latitude'];
        }
        if (isset($post['outlet_longitude'])) {
            $data['outlet_longitude'] = $post['outlet_longitude'];
        }
        if (isset($post['outlet_open_hours'])) {
            $data['outlet_open_hours'] =  date('Y-m-d H:i:s', strtotime($post['outlet_open_hours']));
        }
        if (isset($post['outlet_close_hours'])) {
            $data['outlet_close_hours'] = date('Y-m-d H:i:s', strtotime( $post['outlet_close_hours']));
        }
        if (isset($post['outlet_pin'])) {
            $data['outlet_pin'] = bcrypt($post['outlet_pin']);
        }
        if (isset($post['outlet_status'])) {
            $data['outlet_status'] = $post['outlet_status'];
        }
        if (isset($post['outlet_brands'])) {
            $data['outlet_brands'] = $post['outlet_brands'];
        }
        if (isset($post['deep_link_gojek'])) {
            $data['deep_link_gojek'] = $post['deep_link_gojek'];
        }
        if (isset($post['deep_link_grab'])) {
            $data['deep_link_grab'] = $post['deep_link_grab'];
        }
        if (isset($post['delivery_order']) || isset($post['delivery_order_default'])) {
            $data['delivery_order'] = $post['delivery_order'] ?? $post['delivery_order_default'];
            if ($data['delivery_order']) {
                if (isset($post['available_delivery'])) {
                    $data['available_delivery'] = implode(',', $post['available_delivery']);
                } else {
                    $data['available_delivery'] = null;
                }
            } else {
                $data['available_delivery'] = null;
            }
            if (!$data['available_delivery']) {
                $data['delivery_order'] = 0;
            }
        }

        return $data;
    }

    /* Pengecekan code unique */
    function cekUnique($id, $code) {
        $cek = Outlet::where('outlet_code', strtoupper($code))->first();

        if (empty($cek)) {
            return true;
        }
        else {
            if ($cek->id_product == $id) {
                return true;
            }
            else {
                return false;
            }
        }
    }


    /**
     * create
     */
    function create(Create $request) {
        $post = $this->checkInputOutlet($request->json()->all());
        if(!isset($post['outlet_code'])){
            do{
                $post['outlet_code'] = MyHelper::createRandomPIN(3);
                $code = Outlet::where('outlet_code', strtoupper($post['outlet_code']))->first();
            }while($code != null);
        }

        DB::beginTransaction();
        $save = Outlet::create($post);
        if (!$save) {
            DB::rollBack();
        }

        if(is_array($brands=$post['outlet_brands']??false)){
            if(in_array('*', $post['outlet_brands'])){
                $brands=Brand::select('id_brand')->get()->toArray();
                $brands=array_column($brands, 'id_brand');
            }
            foreach ($brands as $id_brand) {
                BrandOutlet::create([
                    'id_outlet'=>$save['id_outlet'],
                    'id_brand'=>$id_brand
                ]);
            }
        }
        //schedule
        if($request->json('day') && $request->json('open') && $request->json('close')){
            $days = $request->json('day');
            $opens = $request->json('open');
            $closes = $request->json('close');
            $is_closed = $request->json('is_closed');
            foreach($days as $key => $value){
                $data['open'] = $opens[$key];
                $data['close'] = $closes[$key];
                $data['is_close'] = $is_closed[$key];
                $saveSchedule = OutletSchedule::updateOrCreate(['id_outlet' => $save['id_outlet'], 'day' => $value], $data);
                if (!$saveSchedule) {
                    DB::rollBack();
                    return response()->json(['status' => 'fail']);
                }
            }
        }

        DB::commit();
        return response()->json(MyHelper::checkCreate($save));
    }

    /**
     * update
     */
    function update(Update $request) {
        $post = $this->checkInputOutlet($request->json()->all());

        DB::beginTransaction();
        if(is_array($brands=$post['outlet_brands']??false)){
            if(in_array('*', $post['outlet_brands'])){
                $brands=Brand::select('id_brand')->get()->toArray();
                $brands=array_column($brands, 'id_brand');
            }
            BrandOutlet::where('id_outlet',$request->json('id_outlet'))->delete();
            foreach ($brands as $id_brand) {
                BrandOutlet::create([
                    'id_outlet'=>$request->json('id_outlet'),
                    'id_brand'=>$id_brand
                ]);
            }
        }

        unset($post['outlet_brands']);
        $save = Outlet::where('id_outlet', $request->json('id_outlet'))->updateWithUserstamps($post);
        // return Outlet::where('id_outlet', $request->json('id_outlet'))->first();
        if($save){
            DB::commit();
        }else{
            DB::rollBack();
        }
        return response()->json(MyHelper::checkUpdate($save));
    }

    function updateStatus(UpdateStatus $request) {
        //check data
        $outlet = Outlet::where('id_outlet', $request->json('id_outlet'))->first();
        if($request->json('outlet_status') == 'Active' &&
            ($outlet['id_city'] == null || $outlet['outlet_latitude'] == null || $outlet['outlet_longitude'] == null)){
            return response()->json([
                'status' => 'fail', 'messages' => ['data outlet not complete']
            ]);
        }
        $post = $this->checkInputOutlet($request->json()->all());
        $save = Outlet::where('id_outlet', $request->json('id_outlet'))->updateWithUserstamps($post);
        // return Outlet::where('id_outlet', $request->json('id_outlet'))->first();

        return response()->json(MyHelper::checkUpdate($save));
    }

    /**
     * delete
     */
    function delete(Request $request) {

        $check = $this->checkDeleteOutlet($request->json('id_outlet'));

        if ($check) {
            // delete holiday
            $deleteHoliday = $this->deleteHolidayOutlet($request->json('id_outlet'));
            // delete photo
            $deletePhoto = $this->deleteFotoStore($request->json('id_outlet'));

            $delete = Outlet::where('id_outlet', $request->json('id_outlet'))->delete();
            return response()->json(MyHelper::checkDelete($delete));
        }
        else {
            return response()->json([
                    'status' => 'fail',
                    'messages' => ['outlet has been used.']
                ]);
        }
    }

    /**
     * delete foto by store
     */
    function deleteFotoStore($id) {
        // info photo
        $dataPhoto = OutletPhoto::where('id_outlet')->get()->toArray();

        if (!empty($dataPhoto)) {
            foreach ($dataPhoto as $key => $value) {
                MyHelper::deletePhoto($value['outlet_photo']);
            }
        }

        $delete = OutletPhoto::where('id_outlet', $id)->delete();

        return $delete;
    }

    function deleteHolidayOutlet($id) {
        $delete = OutletHoliday::where('id_outlet', $id)->delete();
        $deleteholiday = Holiday::whereDoesntHave('outlets')->delete();
        return $deleteholiday;
    }

    /**
     * cek delete outlet
     */
    function checkDeleteOutlet($id) {

        $table = [
            'deals_outlets',
            'enquiries',
            'product_prices',
            'user_outlets'
        ];

        for ($i=0; $i < count($table); $i++) {

            $check = DB::table($table[$i])->where('id_outlet', $id)->count();

            if ($check > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * function upload
     */
    function upload(UploadPhoto $request) {
        $post = $request->json()->all();

        $data = [];

        if (isset($post['photo'])) {

            $upload = MyHelper::uploadPhotoStrict($post['photo'], $this->saveImage, 600, 300);

            if (isset($upload['status']) && $upload['status'] == "success") {
                $data['outlet_photo'] = $upload['path'];
            }
            else {
                $result = [
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ];

                return response()->json($result);
            }
        }

        if (empty($data)) {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['fail save to database']
            ]);
        }
        else {
            $data['id_outlet']          = $post['id_outlet'];
            $data['outlet_photo_order'] = $this->cekLastUrutan($post['id_outlet']);
            $save                       = OutletPhoto::create($data);

            return response()->json(MyHelper::checkCreate($save));
        }
    }

    /*
    cari urutan
    */
    function cekLastUrutan($id) {
        $last = OutletPhoto::where('id_outlet', $id)->orderBy('outlet_photo_order', 'DESC')->first();

        if (!empty($last)) {
            $last = $last->outlet_photo_order + 1;
        }
        else {
            $last = 1;
        }

        return $last;
    }

    /**
     * delete upload
     */
    function deleteUpload(DeletePhoto $request) {
        // info
        $dataPhoto = OutletPhoto::where('id_outlet_photo')->get()->toArray();

        if (!empty($dataPhoto)) {
            MyHelper::deletePhoto($dataPhoto[0]['outlet_photo']);
        }

        $delete = OutletPhoto::where('id_outlet_photo', $request->json('id_outlet_photo'))->delete();

        return response()->json(MyHelper::checkDelete($delete));
    }

    /**
    * update foto product
    */
    function updatePhoto(Request $request) {
        $update =   OutletPhoto::where('id_outlet_photo', $request->json('id_outlet_photo'))->updateWithUserstamps([
            'outlet_photo_order' => $request->json('outlet_photo_order')
        ]);

        return response()->json(MyHelper::checkUpdate($update));
    }

    /**
    * update pin outlet
    */
    function updatePin(Request $request) {
        $post = $request->json()->all();
        $outlet = Outlet::find($post['id_outlet']);

        if(!$outlet){
            return response()->json([
                'status'    => 'fail',
                'messages'      => [
                    'Data outlet not found.'
                ]
            ]);
        }

        $pin = bcrypt($post['outlet_pin']);
        $outlet->outlet_pin = $pin;
        $outlet->save();

        //delete token
        $del = OauthAccessToken::join('oauth_access_token_providers', 'oauth_access_tokens.id', 'oauth_access_token_providers.oauth_access_token_id')
                                    ->where('oauth_access_tokens.user_id', $post['id_outlet'])->where('oauth_access_token_providers.provider', 'outlet-app')->delete();


        return response()->json(MyHelper::checkUpdate($outlet));
    }
    /**
     * list
     */
    function listOutlet(OutletList $request) {
        $post = $request->json()->all();

        if (isset($post['webview'])) {
            $outlet = Outlet::with(['today', 'brands']);
        }elseif(isset($post['admin']) && isset($post['type']) && $post['type'] == 'export'){
            $outlet = Outlet::with(['user_outlets','city','today','product_prices','product_prices.product'])->select('*');
        }elseif(isset($post['admin'])){
            $outlet = Outlet::with(['user_outlets','city','today', 'outlet_schedules', 'outlet_photos'])->select('*');
            if(isset($post['id_product'])){
                $outlet = $outlet->with(['product_prices'=> function($q) use ($post){
                    $q->where('id_product', $post['id_product']);
                }]);
            }else{
                $outlet = $outlet->with(['product_prices']);
            }
        }
        elseif($post['simple_result']??false) {
            $outlet = Outlet::select('outlets.id_outlet','outlets.outlet_name');
        }else{
            $outlet = Outlet::with(['city', 'outlet_photos', 'outlet_schedules', 'today', 'user_outlets','brands']);
            if(!($post['id_outlet']??false)||!($post['id_outlet']??false)){
                $outlet->select('outlets.id_outlet','outlets.outlet_name','outlets.outlet_code','outlets.outlet_status','outlets.outlet_address','outlets.id_city','outlet_latitude','outlet_longitude','outlet_phone','outlet_email');
            }
        }
        if($post['rule']??false){
            $this->filterList($outlet,$post['rule'],$post['operator']??'and');
        }
        if(($post['order_field']??false)&&($post['order_method']??false)){
            $outlet->orderBy($post['order_field'],$post['order_method']);
        }
        if($post['simple_result']??false){
            $outlet->select('outlets.id_outlet','outlets.outlet_name');
        }
        if(is_array($post['id_brand']??false)&&$post['id_brand']){
            $outlet->leftJoin('brand_outlet','outlets.id_outlet','brand_outlet.id_outlet');
            $id_brands=$post['id_brand'];
            $outlet->where(function($query) use ($id_brands){
                foreach ($id_brands as $id_brand) {
                    $query->orWhere('brand_outlet.id_brand',$id_brand);
                }
            });
        }

        if($post['key_free']??false){
            $outlet->where('outlets.outlet_name','LIKE','%'.$post['key_free'].'%');
        }

        if (isset($post['outlet_code'])) {
            $outlet->with(['holidays', 'holidays.date_holidays','brands'])->where('outlet_code', $post['outlet_code']);
        }

        if (isset($post['id_outlet'])) {
            if(!isset($post['webview'])){
                $outlet->with(['holidays', 'holidays.date_holidays', 'product_prices.product']);
            }
            $outlet->where('id_outlet', $post['id_outlet']);
        }

        if (isset($post['id_city'])) {
            $outlet->where('id_city',$post['id_city']);
        }


        if(isset($post['all_outlet']) && $post['all_outlet'] == 0){
            $outlet = $outlet->where('outlet_status', 'Active')->whereNotNull('id_city');
            $outlet->whereHas('brands',function($query){
                $query->where('brand_active','1');
            });
        }

        // qrcode
        if (isset($post['qrcode'])){
            if(isset($post['qrcode_paginate'])){
                $outlet = $outlet->orderBy('outlet_name')->paginate(5)->toArray();
                foreach ($outlet['data'] as $key => $value) {
                    $qr      = $value['outlet_code'];

                    $qrCode = 'https://chart.googleapis.com/chart?chl='.$qr.'&chs=250x250&cht=qr&chld=H%7C0';
                    $qrCode = html_entity_decode($qrCode);

                    $outlet['data'][$key]['qrcode'] = $qrCode;
                }
                $loopdata=&$outlet['data'];
            }else{
                $outlet = $outlet->orderBy('outlet_name')->get()->toArray();
                foreach ($outlet as $key => $value) {
                    $qr      = $value['outlet_code'];

                    $qrCode = 'https://chart.googleapis.com/chart?chl='.$qr.'&chs=250x250&cht=qr&chld=H%7C0';
                    $qrCode = html_entity_decode($qrCode);

                    $outlet[$key]['qrcode'] = $qrCode;
                }
                $loopdata=&$outlet;
            }
            $request['page'] = 0;
        }else{
            $useragent = $_SERVER['HTTP_USER_AGENT'];
            if(stristr($_SERVER['HTTP_USER_AGENT'],'iOS')) $useragent = 'iOS';
            if(stristr($_SERVER['HTTP_USER_AGENT'],'okhttp')) $useragent = 'Android';
            if($useragent == 'Android' || $useragent == 'iOS'){
                $outlet = $outlet->orderBy('outlet_name')->get()->toArray();
                foreach ($outlet as $keyOutlet => $valueOutlet) {
                    $countBrandNotActive = 0;
                    foreach ($valueOutlet['brands'] as $keyBrand => $valueBrand) {
                        if ($valueBrand['brand_active'] == 0) {
                            $countBrandNotActive++;
                        }
                    }
                    if (count($valueOutlet['brands']) == $countBrandNotActive) {
                        $outlet[$keyOutlet]['outlet_status'] = 'Inactive';
                    }
                }
            } else {
                $outlet = $outlet->orderBy('outlet_name')->get()->toArray();
            }
            $loopdata=&$outlet;
        }

        $loopdata = array_map(function($var) use ($post){
            $var['url']=env('API_URL').'api/outlet/webview/'.$var['id_outlet'];
            if(isset($var['outlet_schedules'])){
                foreach($var['outlet_schedules'] as $index => $sch){
                    $var['outlet_schedules'][$index] = $this->setTimezone($var['outlet_schedules'][$index]);
                }
            }
            if(isset($var['today']['time_zone'])){
                $var['today'] = $this->setTimezone($var['today']);
            }
            if(($post['latitude']??false)&&($post['longitude']??false)){
                $var['distance']=number_format((float)$this->distance($post['latitude'], $post['longitude'], $var['outlet_latitude'], $var['outlet_longitude'], "K"), 2, '.', '').' km';
            }
            return $var;
        }, $loopdata);
        if (isset($post['webview'])) {
            if(isset($outlet[0])){
                $latitude  = $post['latitude'];
                $longitude = $post['longitude'];
                $jaraknya = number_format((float)$this->distance($latitude, $longitude, $outlet[0]['outlet_latitude'], $outlet[0]['outlet_longitude'], "K"), 2, '.', '');
                $outlet[0]['distance'] = $jaraknya." km";

                $outlet[0]['url'] = env('API_URL').'api/outlet/webview/'.$post['id_outlet'];

                if(isset($outlet[0]['holidays'])) unset($outlet[0]['holidays']);
            }
        }
        if($post['simple_result']??false){
            $outlet=array_map(function($var){
                return [
                    'id_outlet'=>$var['id_outlet'],
                    'outlet_name'=>$var['outlet_name']
                ];
            },$outlet);
        }
        if($outlet&&($post['id_outlet']??false)){
            $var=&$outlet[0];
            $var['deep_link_gojek']=$var['deep_link_gojek']??'';
            $var['deep_link_grab']=$var['deep_link_grab']??'';
        }
        if(isset($request['page']) && $request['page'] > 0){
            $page = $request['page'];
            $next_page = $page + 1;

            $dataOutlet = $outlet;
            $outlet = [];
            $pagingOutlet=$this->pagingOutlet($dataOutlet, $page,$post['take']??15);
            if (isset($pagingOutlet['data']) && count($pagingOutlet['data']) > 0) {
                $outlet['current_page']  = $page;
                $outlet['data']          = $pagingOutlet['data'];
                $outlet['total']         = count($dataOutlet);
                $outlet['next_page_url'] = null;

                if ($pagingOutlet['status'] == true) {
                    $outlet['next_page_url'] = ENV('APP_API_URL').'api/outlet/list?page='.$next_page;
                }
            } else {
                $outlet = [];
            }
        }

        return response()->json(MyHelper::checkGet($outlet));

    }

    function listOutletNameID(Request $request)
    {
        $post = $request->json()->all();

        if (isset($post['all_outlet']) && $post['all_outlet'] == 1) {
            $outlet['data'] = Outlet::select('id_outlet', 'outlet_name')->where('outlet_status', 'Active')->orderBy('outlet_name')->get()->toArray();
        } else {
            $outlet = Outlet::select('id_outlet', 'outlet_name')->where('outlet_status', 'Active')->orderBy('outlet_name')->paginate(10)->toArray();
        }
        foreach ($outlet['data'] as $key => $value) {
            unset($outlet['data'][$key]['call']);
            unset($outlet['data'][$key]['url']);
            unset($outlet['data'][$key]['detail']);
        }

        return response()->json(MyHelper::checkGet($outlet));
    }

    function listOutletOvo(Request $request)
    {
        $post = $request->json()->all();

        if (!$post) {
            $outlet = Outlet::with(['outlet_ovo'])->get()->toArray();
        } else {
            $check = OutletOvo::where('id_outlet', $post['id_outlet'])->first();
            if(!$check){
                $update = OutletOvo::create($post);
            }else{
                $update = OutletOvo::where('id_outlet', $post['id_outlet'])->updateWithUserstamps($post);
            }

            if ($update) {
                $outlet['updated'] = 1;
            }
            $outlet = Outlet::with(['outlet_ovo'])->get()->toArray();
        }

        return response()->json(MyHelper::checkGet($outlet));
    }

    /* City Outlet */
    function cityOutlet(Request $request) {
        $outlet = Outlet::join('cities', 'cities.id_city', '=', 'outlets.id_city')->where('outlet_status', 'Active')->select('outlets.id_city', 'city_name')->orderBy('city_name', 'ASC')->distinct()->get()->toArray();

        // if (!empty($outlet)) {
        //     $outlet = array_pluck($outlet, 'city_name');
        // }
        return response()->json(MyHelper::checkGet($outlet));
    }

    /* Near Me*/
    function nearMe(Nearme $request) {
        $latitude  = $request->json('latitude');
        $longitude = $request->json('longitude');

        if(!$latitude || !$longitude){
            return response()->json(['status' => 'fail', 'messages' => ["Make sure your phone's location settings are connected"]]);
        }

        // outlet
        $outlet = Outlet::with(['today'])->select('outlets.id_outlet','outlets.outlet_name','outlets.outlet_phone','outlets.outlet_code','outlets.outlet_status','outlets.outlet_address','outlets.id_city','outlet_latitude','outlet_longitude')->where('outlet_status', 'Active')->whereNotNull('id_city')->orderBy('outlet_name','asc');
        if($request->json('search') && $request->json('search') != ""){
            $outlet = $outlet->where('outlet_name', 'LIKE', '%'.$request->json('search').'%');
        }
        $outlet->whereHas('brands',function($query){
            $query->where('brand_active','1');
        });
        $outlet = $outlet->get()->toArray();

        if (!empty($outlet)) {
            $processing = '0';
            $settingTime = Setting::where('key', 'processing_time')->first();
            if($settingTime && $settingTime->value){
                $processing = $settingTime->value;
            }
            foreach ($outlet as $key => $value) {
                $jaraknya =   number_format((float)$this->distance($latitude, $longitude, $value['outlet_latitude'], $value['outlet_longitude'], "K"), 2, '.', '');
                settype($jaraknya, "float");

                $outlet[$key]['distance'] = number_format($jaraknya, 2, '.', ',')." km";
                $outlet[$key]['dist']     = (float) $jaraknya;

            }
            usort($outlet, function($a, $b) {
                return $a['dist'] <=> $b['dist'];
            });

        }else{
            return response()->json(['status' => 'fail', 'messages' => ['There is no open store','at this moment']]);
        }

        if(isset($request['page']) && $request['page'] > 0){
            $page = $request['page'];
            $next_page = $page + 1;

            $dataOutlet = $outlet;
            $outlet = [];

            $pagingOutlet = $this->pagingOutlet($dataOutlet, $page);

        	$check_holiday = $this->checkOutletHoliday();
            foreach ($pagingOutlet['data'] as $key => $value) {
	            if ($check_holiday['status'] && in_array($pagingOutlet['data'][$key]['id_outlet'], $check_holiday['list_outlet'])) {
	            	$pagingOutlet['data'][$key]['today']['is_closed'] = 1;
	            }
	            $pagingOutlet['data'][$key] = $this->setAvailableOutlet($pagingOutlet['data'][$key], $processing);
	            if(isset($pagingOutlet['data'][$key]['today']['time_zone'])){
                    $pagingOutlet['data'][$key]['today'] = $this->setTimezone($pagingOutlet['data'][$key]['today']);
                }
            }

            if (isset($pagingOutlet['data']) && count($pagingOutlet['data']) > 0) {
                $outlet['current_page']  = $page;
                $outlet['data']          = $pagingOutlet['data'];
                $outlet['total']         = count($dataOutlet);
                $outlet['next_page_url'] = null;

                if ($pagingOutlet['status'] == true) {
                    $outlet['next_page_url'] = ENV('APP_API_URL').'api/outlet/nearme?page='.$next_page;
                }
            } else {
                return response()->json(['status' => 'fail', 'messages' => ['There is no open store','at this moment']]);
            }
        }

        if(!$outlet){
            return response()->json(['status' => 'fail', 'messages' => ['There is no open store','at this moment']]);
        }

        return response()->json(MyHelper::checkGet($outlet));
    }

    /* Near Me Geolocation, return geojson */
    function nearMeGeolocation(Nearme $request) {
        $latitude  = $request->json('latitude');
        $longitude = $request->json('longitude');

        if(!$latitude || !$longitude){
            return response()->json(['status' => 'success', 'messages' => ["Make sure your phone's location settings are connected"]]);
        }

        // outlet
        $outlet = Outlet::with(['today', 'city', 'outlet_photos'])->orderBy('outlet_name','asc')->where('outlet_status', 'Active')->whereNotNull('id_city');
        if($request->json('search') && $request->json('search') != ""){
            $outlet = $outlet->where('outlet_name', 'LIKE', '%'.$request->json('search').'%');
        }
        $outlet = $outlet->get()->toArray();

        if (!empty($outlet)) {
            $processing = '0';
            $settingTime = Setting::where('key', 'processing_time')->first();
            if($settingTime && $settingTime->value){
                $processing = $settingTime->value;
            }

            foreach ($outlet as $key => $value) {
                $jaraknya =   number_format((float)$this->distance($latitude, $longitude, $value['outlet_latitude'], $value['outlet_longitude'], "K"), 2, '.', '');
                settype($jaraknya, "float");

                $outlet[$key]['distance'] = number_format($jaraknya, 2, '.', ',')." km";
                $outlet[$key]['dist']     = (float) $jaraknya;

            }
            usort($outlet, function($a, $b) {
                return $a['dist'] <=> $b['dist'];
            });

        }

        if(isset($request['page']) && $request['page'] > 0){
            $page = $request['page'];
            $next_page = $page + 1;

            $dataOutlet = $outlet;
            $outlet = [];

            $pagingOutlet = $this->pagingOutlet($dataOutlet, $page);

        	$check_holiday = $this->checkOutletHoliday();
            foreach ($pagingOutlet['data'] as $key => $value) {
	            if ($check_holiday['status'] && in_array($pagingOutlet['data'][$key]['id_outlet'], $check_holiday['list_outlet'])) {
	            	$pagingOutlet['data'][$key]['today']['is_closed'] = 1;
	            }
	            $pagingOutlet['data'][$key] = $this->setAvailableOutlet($pagingOutlet['data'][$key], $processing);
            }

            // format outlet data into geojson
            $pagingOutlet['data'] = $this->geoJson($pagingOutlet['data']);

            if (count($pagingOutlet) > 0) {
                $outlet['status'] = 'success';
                $outlet['current_page']  = $page;
                $outlet['data']          = $pagingOutlet['data'];
                $outlet['total']         = count($dataOutlet);
                $outlet['next_page_url'] = null;

                if ($pagingOutlet['status'] == true) {
                    $outlet['next_page_url'] = ENV('APP_API_URL').'api/outlet/nearme?page='.$next_page;
                }
            } else {
                $outlet['status'] = 'fail';
                $outlet['messages'] = ['empty'];

            }
        }
        else {
            // format result into geojson
            $outlet = $this->geoJson($outlet);
        }

        return response()->json(MyHelper::checkGet($outlet));
    }

    public function pagingOutlet($data, $page,$paginate=10) {
        $next = false;

        if ($page > 0) {
            $resultData = [];
            $start      = $paginate * ($page - 1);
            $all        = $paginate * $page;
            $end        = $all;
            $next       = true;

            if ($all >= count($data)) {
                $end = count($data);
                $next = false;
            }

            for ($i=$start; $i < $end; $i++) {
                array_push($resultData, $data[$i]);
            }

            return ['data' => $resultData, 'status' => $next];
        }


        return ['data' => $data, 'status' => $next];
    }

    // create geojson format
    private function geoJson ($locales)
    {
        $original_data = $locales;
        $features = array();

        foreach($original_data as $key => $value) {
            $features[] = array(
                    'type' => 'Feature',
                    'geometry' => array(
                        'type' => 'Point',
                        'coordinates' => array(
                            (float) $value['outlet_longitude'],
                            (float) $value['outlet_latitude']
                        )
                    ),
                    'properties' => array(
                        'title' => $value['outlet_name'],
                        'id_outlet' => $value['id_outlet'],
                        'url' => $value['url'],
                        'today' => $value['today'],
                        'distance' => $value['distance'],
                        'dist' => $value['dist']
                    ),
                );
            };

        $allfeatures = array('type' => 'FeatureCollection', 'features' => $features);

        // write into file
        Storage::disk('s3')->put('stations.geojson', json_encode($allfeatures));
        // Storage::disk('public_custom')->put('stations.geojson', json_encode($allfeatures));

        return $allfeatures;
    }

	/* Filter*/
    function filter(Filter $request) {
        $post=$request->except('_token');
        $latitude  = $request->json('latitude');
        $longitude = $request->json('longitude');

        if(!isset($post['latitude']) || !isset($post['longitude'])){
            return response()->json([
                'status' => 'fail',
                'messages' => ["Make sure your phone's location settings are connected"]
            ]);
        }

        $latitude  = $post['latitude'];
        $longitude = $post['longitude'];

        $distance = $post['distance']??"";
        $id_city = $post['id_city']??"";
        $sort = $post['sort']??"";
        $gofood = $post['gofood']??"";
        $grabfood = $post['grabfood']??"";

        // outlet
        $outlet = Outlet::with(['today'])->select('outlets.id_outlet','outlets.outlet_name','outlets.outlet_phone','outlets.outlet_code','outlets.outlet_status','outlets.outlet_address','outlets.id_city','outlet_latitude','outlet_longitude', 'delivery_order')->where('outlet_status', 'Active')->whereNotNull('id_city')->orderBy('outlet_name','asc');

        $outlet->whereHas('brands',function($query){
            $query->where('brand_active','1');
        });

        $countAll=$outlet->count();

        if(is_array($post['id_brand']??false)&&$post['id_brand']){
            $outlet->leftJoin('brand_outlet','outlets.id_outlet','brand_outlet.id_outlet');
            $id_brands=$post['id_brand'];
            $outlet->where(function($query) use ($id_brands){
                foreach ($id_brands as $id_brand) {
                    $query->orWhere('brand_outlet.id_brand',$id_brand);
                }
            });
        }

        if(isset($post['search']) && $post['search'] && $post['search'] != ""){
            $outlet = $outlet->where('outlet_name', 'LIKE', '%'.$post['search'].'%');
        }

        if ($gofood) {
            $outlet = $outlet->whereNotNull('deep_link_gojek')->addSelect('deep_link_gojek');
        }

        if ($grabfood) {
            $outlet = $outlet->whereNotNull('deep_link_grab')->addSelect('deep_link_grab');
        }

        $outlet = $outlet->get()->toArray();

        if (!empty($outlet)) {
            $processing = '0';
            $settingTime = Setting::where('key', 'processing_time')->first();
            if($settingTime && $settingTime->value){
                $processing = $settingTime->value;
            }

            $activeDelivery = json_decode(MyHelper::setting('active_delivery_methods', 'value_text', '[]'), true) ?? [];
            $active = array_sum(array_column($activeDelivery ?? [], 'status'));
            foreach ($outlet as $key => $value) {
				$outlet[$key]['is_promo'] = 0;
                if (!$active) {
                    $outlet[$key]['delivery_order'] = 0;
                }
			}
			
			$promo_data = $this->applyPromo($post, $outlet, $promo_error);

	        if ($promo_data) {
	        	$outlet = $promo_data;
	        }

            foreach ($outlet as $key => $value) {
                $jaraknya =   number_format((float)$this->distance($latitude, $longitude, $value['outlet_latitude'], $value['outlet_longitude'], "K"), 2, '.', '');
                settype($jaraknya, "float");

                $outlet[$key]['distance'] = number_format($jaraknya, 2, '.', ',')." km";
                $outlet[$key]['dist']     = (float) $jaraknya;

				if($distance == "0-2km"){
					if((float) $jaraknya < 0.01 || (float) $jaraknya > 2.00)
                        unset($outlet[$key]);
                        continue;
				}

				if($distance == "2-5km"){
					if((float) $jaraknya < 2.00 || (float) $jaraknya > 5.00)
                        unset($outlet[$key]);
                        continue;
				}

				if($distance == ">5km"){
					if((float) $jaraknya < 5.00)
                        unset($outlet[$key]);
                        continue;
				}

				if($id_city != "" && $id_city != $value['id_city']){
                    unset($outlet[$key]);
                    continue;
                }

                if (isset($post['is_map']) && $post['is_map'] == 0) {
                    unset($outlet[$key]['outlet_latitude']);
                    unset($outlet[$key]['outlet_longitude']);
                }
            }
			if($sort != 'Alphabetical'){
				usort($outlet, function($a, $b) {
					return $a['dist'] <=>  $b['dist'];
				});
			}
			$urutan = array();
			if($outlet){
				foreach($outlet as $o){
					array_push($urutan, $o);
				}
            }
        } else {
            if($countAll){
                if($request->json('search')){
                    return response()->json(['status' => 'fail', 'messages' => ['Outlet not found']]);
                }
                return response()->json(['status' => 'fail', 'messages' => ['Outlet is Empty']]);
            }else{
                return response()->json(['status' => 'fail', 'messages' => ['There is no open store','at this moment']]);
            }
        }

        // if (!isset($request['page'])) {
        //     $request['page'] = 1;
        // }

        if(isset($post['page']) && $post['page'] > 0){
            $page = $post['page'];
            $next_page = $page + 1;

            $dataOutlet = $urutan;
            $urutan = [];

            $pagingOutlet = $this->pagingOutlet($dataOutlet, $page);

        	$check_holiday = $this->checkOutletHoliday();
            foreach ($pagingOutlet['data'] as $key => $value) {
	            if ($check_holiday['status'] && in_array($pagingOutlet['data'][$key]['id_outlet'], $check_holiday['list_outlet'])) {
	            	$pagingOutlet['data'][$key]['today']['is_closed'] = 1;
	            }
	            $pagingOutlet['data'][$key] = $this->setAvailableOutlet($pagingOutlet['data'][$key], $processing);
	            if(isset($pagingOutlet['data'][$key]['today']['time_zone'])){
                    $pagingOutlet['data'][$key]['today'] = $this->setTimezone($pagingOutlet['data'][$key]['today']);
                }
	            unset($outlet[$key]['today']['id_outlet']);
                // unset($outlet[$key]['today']['is_closed']);
                unset($outlet[$key]['today']['time_zone_id']);
                unset($outlet[$key]['today']['time_zone']);

            }

            if (isset($pagingOutlet['data']) && count($pagingOutlet['data']) > 0) {
                $urutan['current_page']  = $page;
                $urutan['data']          = $pagingOutlet['data'];
                $urutan['total']         = count($dataOutlet);
                $urutan['next_page_url'] = null;

                if ($pagingOutlet['status'] == true) {
                    $urutan['next_page_url'] = ENV('APP_API_URL').'api/outlet/filter?page='.$next_page;
                }
                $urutan['promo_error']   = $promo_error;
            } else {
                if($countAll){
                    return response()->json(['status' => 'fail', 'messages' => ['Outlet is Empty']]);
                }else{
                    return response()->json(['status' => 'fail', 'messages' => ['There is no open store','at this moment']]);
                }
            }
        }else{
            $check_holiday = $this->checkOutletHoliday();
            foreach ($urutan as $key => $value) {
	            if ($check_holiday['status'] && in_array($urutan[$key]['id_outlet'], $check_holiday['list_outlet'])) {
	            	$urutan[$key]['today']['is_closed'] = 1;
	            }
	            $urutan[$key] = $this->setAvailableOutlet($urutan[$key], $processing);
	            if(isset($urutan[$key]['today']['time_zone'])){
                    $urutan[$key]['today'] = $this->setTimezone($urutan[$key]['today']);
                }
            }
        }
        if(!$urutan){
            if($countAll){
                return response()->json(['status' => 'fail', 'messages' => ['Outlet is Empty']]);
            }else{
                return response()->json(['status' => 'fail', 'messages' => ['There is no open store','at this moment']]);
            }
        }
        return response()->json(MyHelper::checkGet($urutan));
    }

    /* Filter Geolocation, return geojson */
    function filterGeolocation(Filter $request) {
        $latitude  = $request->json('latitude');
        $longitude = $request->json('longitude');

        if(!isset($latitude) || !isset($longitude)){
            return response()->json([
                'status' => 'success',
                'messages' => ["Make sure your phone's location settings are connected"]
            ]);
        }

        $distance = $request->json('distance');
        $id_city = $request->json('id_city');
        $sort = $request->json('sort');

        // outlet
        $outlet = Outlet::with(['today', 'city', 'outlet_photos'])->where('outlet_status', 'Active')->whereNotNull('id_city')->orderBy('outlet_name','asc');
        if($request->json('search') && $request->json('search') != ""){
            $outlet = $outlet->where('outlet_name', 'LIKE', '%'.$request->json('search').'%');
        }
        $outlet = $outlet->get()->toArray();

        if (!empty($outlet)) {
            $processing = '0';
            $settingTime = Setting::where('key', 'processing_time')->first();
            if($settingTime && $settingTime->value){
                $processing = $settingTime->value;
            }

            foreach ($outlet as $key => $value) {
                $jaraknya =   number_format((float)$this->distance($latitude, $longitude, $value['outlet_latitude'], $value['outlet_longitude'], "K"), 2, '.', '');
                settype($jaraknya, "float");

                $outlet[$key]['distance'] = number_format($jaraknya, 2, '.', ',')." km";
                $outlet[$key]['dist']     = (float) $jaraknya;

                if($distance == "0-2km"){
                    if((float) $jaraknya < 0.01 || (float) $jaraknya > 2.00)
                        unset($outlet[$key]);
                }

                if($distance == "2-5km"){
                    if((float) $jaraknya < 2.00 || (float) $jaraknya > 5.00)
                        unset($outlet[$key]);
                }

                if($distance == ">5km"){
                    if((float) $jaraknya < 5.00)
                        unset($outlet[$key]);
                }

                if($id_city != "" && $id_city != $value['id_city']){
                    unset($outlet[$key]);
                }
            }
            if($sort != 'Alphabetical'){
                usort($outlet, function($a, $b) {
                    return $a['dist'] <=>  $b['dist'];
                });
            }
            $urutan = array();
            if($outlet){
                foreach($outlet as $o){
                    array_push($urutan, $o);
                }
            }

        } else {
            return response()->json(MyHelper::checkGet($outlet));
        }

        if(isset($request['page']) && $request['page'] > 0){
            $page = $request['page'];
            $next_page = $page + 1;

            $dataOutlet = $urutan;
            $urutan = [];

            $pagingOutlet = $this->pagingOutlet($dataOutlet, $page);

        	$check_holiday = $this->checkOutletHoliday();
            foreach ($pagingOutlet['data'] as $key => $value) {
	            if ($check_holiday['status'] && in_array($pagingOutlet['data'][$key]['id_outlet'], $check_holiday['list_outlet'])) {
	            	$pagingOutlet['data'][$key]['today']['is_closed'] = 1;
	            }
	            $pagingOutlet['data'][$key] = $this->setAvailableOutlet($pagingOutlet['data'][$key], $processing);
            }

            // format outlet data into geojson
            $pagingOutlet['data'] = $this->geoJson($pagingOutlet['data']);

            if (count($pagingOutlet) > 0) {
                $urutan['status'] = 'success';
                $urutan['current_page']  = $page;
                $urutan['data']          = $pagingOutlet['data'];
                $urutan['total']         = count($dataOutlet);
                $urutan['next_page_url'] = null;

                if ($pagingOutlet['status'] == true) {
                    $urutan['next_page_url'] = ENV('APP_API_URL').'api/outlet/filter?page='.$next_page;
                }
            } else {
                $urutan['status'] = 'fail';
                $urutan['messages'] = ['empty'];

            }
        }
        else{
            // format result into geojson
            $urutan = $this->geoJson($urutan);
        }

        $geojson_file_url = env('API_URL') . 'files/stations.geojson' . '?';

        if($urutan && !empty($urutan)) return ['status' => 'success', 'result' => $urutan, 'url'=>$geojson_file_url];
        else if(empty($urutan)) return ['status' => 'fail', 'messages' => ['empty']];
        else return ['status' => 'fail', 'messages' => ['failed to retrieve data']];

        // return response()->json(MyHelper::checkGet($urutan));
    }

    // unset outlet yang tutup dan libur
    function setAvailableOutlet($outlet, $processing){
        $outlet['today']['status'] = 'open';
        $outlet['today']['status_detail'] = '';
        $soon = env('OUTLET_OPEN_CLOSE_SOON_TIME', null);
        $now = date('H:i');

        $yesterday_schedule = $this->getYesterdaySchedule($outlet['id_outlet']);
        if (date('H:i', strtotime($yesterday_schedule['close'])) < date('H:i', strtotime($yesterday_schedule['open']))
        	&& $now < date('H:i', strtotime($yesterday_schedule['close']))
    	) {
    		$today_schedule = $outlet['today'];
    		$outlet['today'] = $yesterday_schedule;
        }

        if( !isset($outlet['today']['open']) || !isset($outlet['today']['close']) ){
            $outlet['today']['status'] = 'closed';
        }
        else
        {
	    	$close = $this->setTimezone($outlet['today'])['close'];
	    	$open = $this->setTimezone($outlet['today'])['open'];
        	$outlet['today']['status_detail'] = 'Today until '.$close;

            if($outlet['today']['is_closed'] == '1'){
	        	$schedule = OutletSchedule::where('id_outlet', $outlet['id_outlet'])->get()->toArray();
	            $new_days = $this->reorderDays($schedule, $outlet['today']['day']);
	            $i = 0;
	            $found = 0;
	            foreach ($new_days as $key => $value) {
	            	if ($value['is_closed'] != 1) {
	            		$outlet['today']['day'] 	= $value['day'];
	            		$outlet['today']['open'] 	= $value['open'];
	            		$outlet['today']['close'] 	= $value['close'];
	            		$found = 1;
	            		break;
	            	}
	            	$i++;
	            }
                $outlet['today']['status'] = 'closed';
                $open = $this->setTimezone($outlet['today'])['open'];
                $close = $this->setTimezone($outlet['today'])['close'];
                if ($i===0) {
                	$outlet['today']['status_detail'] = 'Tomorrow open at '.$open;
                }elseif ($found===0) {
                	$outlet['today']['status_detail'] = 'Temporarily closed';
                }else{
                	$outlet['today']['status_detail'] = 'Open '.$outlet['today']['day'].' at '.$open;
                }
            }
            else{
            	if (date('H:i', strtotime($outlet['today']['close'])) > date('H:i', strtotime($outlet['today']['open']))) {
	            	if ( $soon 
	            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['open'])) )
	            		&& ( date('H:i:01') > date('H:i', strtotime($outlet['today']['open']." -".$soon." minutes")) )
	            	) {
	            		$outlet['today']['status'] = 'opening soon';
	            		$outlet['today']['status_detail'] = 'Today open at '.$open;
	            	}
	            	elseif($outlet['today']['open'] && date('H:i:01') < date('H:i', strtotime($outlet['today']['open']))){
	                    $outlet['today']['status'] = 'closed';
	            		$outlet['today']['status_detail'] = 'Today open at '.$outlet['today']['open'];
	                }
	            	elseif ( $soon 
	            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['close'])) )
	            		&& ( date('H:i:01') > date('H:i', strtotime($outlet['today']['close']." -".$soon." minutes")) )
	            	) {
	            		$outlet['today']['status'] = 'closing soon';
	        			$outlet['today']['status_detail'] = 'Today until '.$close;
	            	}
	                elseif($outlet['today']['close'] && date('H:i') > date('H:i', strtotime('-'.$processing.' minutes', strtotime($outlet['today']['close'])))){
	                	$schedule = OutletSchedule::where('id_outlet', $outlet['id_outlet'])->get()->toArray();
			            $new_days = $this->reorderDays($schedule, $outlet['today']['day']);
			            $i = 0;
			            foreach ($new_days as $key => $value) {
			            	if ($value['is_closed'] != 1) {
			            		$outlet['today']['day'] 	= $value['day'];
			            		$outlet['today']['open'] 	= $value['open'];
			            		$outlet['today']['close'] 	= $value['close'];
			            		break;
			            	}
			            	$i++;
			            }
		                $outlet['today']['status'] = 'closed';
		                $open = $this->setTimezone($outlet['today'])['open'];
                		$close = $this->setTimezone($outlet['today'])['close'];
		                if ($i===0) {
	                		$outlet['today']['status_detail'] = 'Tomorrow open at '.$open;
		                }
		                else{
		                	$outlet['today']['status_detail'] = 'Open '.$outlet['today']['day'].' at '.$open;
		                }
		            }
            	}else{
            		if ($outlet['today']['day'] != $yesterday_schedule['day']) {
            			if ( $soon 
		            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['open'])) )
		            		&& ( date('H:i:01') > date('H:i', strtotime($outlet['today']['open']." -".$soon." minutes")) )
		            	) {
		            		$outlet['today']['status'] = 'opening soon';
		            		$outlet['today']['status_detail'] = 'Today open at '.$open;
		            	}
		            	elseif(
		            		$outlet['today']['open'] 
		            		&& date('H:i:01') < date('H:i', strtotime($outlet['today']['open']))
		            	){
		                    $outlet['today']['status'] = 'closed';
		            		$outlet['today']['status_detail'] = 'Today open at '.$open;
		                }
		            	else{
		        			$outlet['today']['status_detail'] = 'Tomorrow until '.$close;
		            	}
            		}
            		else{
            			if ( $soon 
		            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['close'])) )
		            		&& ( date('H:i:01') > date('H:i', strtotime($outlet['today']['close']." -".$soon." minutes")) )
		            	) {
		            		$outlet['today']['status'] = 'closing soon';
		        			$outlet['today']['status_detail'] = 'Today until '.$close;
		            	}
		                elseif(
		                	$outlet['today']['close'] 
		            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['open'])) )
		                	&& date('H:i') > date('H:i', strtotime('-'.$processing.' minutes', strtotime($outlet['today']['close'])))
		                ){
		                	$schedule = OutletSchedule::where('id_outlet', $outlet['id_outlet'])->get()->toArray();
				            $new_days = $this->reorderDays($schedule, $outlet['today']['day']);
				            $i = 0;
				            foreach ($new_days as $key => $value) {
				            	if ($value['is_closed'] != 1) {
				            		$outlet['today']['day'] 	= $value['day'];
				            		$outlet['today']['open'] 	= $value['open'];
				            		$outlet['today']['close'] 	= $value['close'];
				            		break;
				            	}
				            	$i++;
				            }
			                $outlet['today']['status'] = 'closed';
			                $open = $this->setTimezone($outlet['today'])['open'];
	                		$close = $this->setTimezone($outlet['today'])['close'];
			                if ($i===0) {
		                		$outlet['today']['status_detail'] = 'Today open at '.$open;
			                }
			                else{
			                	$outlet['today']['status_detail'] = 'Open '.$outlet['today']['day'].' at '.$open;
			                }
			            }	
            		}
            		/*
            		if ( $soon 
	            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['open'])) )
	            		&& ( date('H:i:01') > date('H:i', strtotime($outlet['today']['open']." -".$soon." minutes")) )
	            	) {
	            		$outlet['today']['status'] = 'opening soon';
	            		$outlet['today']['status_detail'] = 'Today open at '.$open;
	            	}
	            	elseif(
	            		$outlet['today']['open'] 
	            		&& date('H:i:01') < date('H:i', strtotime($outlet['today']['open']))
	            		&& date('H:i:01') > date('H:i', strtotime($outlet['today']['close']))
	            	){
	                    $outlet['today']['status'] = 'closed';
	            		$outlet['today']['status_detail'] = 'Today open at '.$open;
	                }
	            	elseif ( $soon 
	            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['close'])) )
	            		&& ( date('H:i:01') > date('H:i', strtotime($outlet['today']['close']." -".$soon." minutes")) )
	            	) {
	            		$outlet['today']['status'] = 'closing soon';
	        			if ($outlet['today']['day'] == $yesterday_schedule['day']) {
	        				$outlet['today']['status_detail'] = 'Today until '.$close;
	        			}
	            	}
	                elseif(
	                	$outlet['today']['close'] 
	            		&& ( date('H:i:01') < date('H:i', strtotime($outlet['today']['open'])) )
	                	&& date('H:i') > date('H:i', strtotime('-'.$processing.' minutes', strtotime($outlet['today']['close'])))
	                ){
	                	$schedule = OutletSchedule::where('id_outlet', $outlet['id_outlet'])->get()->toArray();
			            $new_days = $this->reorderDays($schedule, $outlet['today']['day']);
			            $i = 0;
			            foreach ($new_days as $key => $value) {
			            	if ($value['is_closed'] != 1) {
			            		$outlet['today']['day'] 	= $value['day'];
			            		$outlet['today']['open'] 	= $value['open'];
			            		$outlet['today']['close'] 	= $value['close'];
			            		break;
			            	}
			            	$i++;
			            }
		                $outlet['today']['status'] = 'closed';
		                $open = $this->setTimezone($outlet['today'])['open'];
                		$close = $this->setTimezone($outlet['today'])['close'];
		                if ($i===0) {
	                		$outlet['today']['status_detail'] = 'Tomorrow open at '.$open;
		                }
		                else{
		                	$outlet['today']['status_detail'] = 'Open '.$outlet['today']['day'].' at '.$open;
		                }
		            }
		            else{
		            	if ($outlet['today']['day'] == $yesterday_schedule['day']) {
	        				$outlet['today']['status_detail'] = 'Today until '.$close;
	        			}
		            }*/
            	}
            }
        }

        return $outlet;
    }

    /**
     * Cek outlet buka atau tutup
     * @param  Array $dataOutlet outlet
     * @return string 'open'/'closed'
     */
    public function checkOutletStatus($dataOutlet){
        if($dataOutlet['today']['open'] == null || $dataOutlet['today']['close'] == null){
            return 'closed';
        }else{
            $processing = '0';
            $settingTime = Setting::where('key', 'processing_time')->first();
            if($settingTime && $settingTime->value){
                $processing = $settingTime->value;
            }
            if($dataOutlet['today']['is_closed'] == '1'){
                return 'closed';
            }else{
                if($dataOutlet['today']['open'] != "00:00" && $dataOutlet['today']['close'] != "00:00"){
                	$soon = env('OUTLET_OPEN_CLOSE_SOON_TIME', null);
	            	if ( $soon 
	            		&& ( date('H:i:01') < date('H:i', strtotime($dataOutlet['today']['open'])) )
	            		&& ( date('H:i:01') > date('H:i', strtotime($dataOutlet['today']['open']." -".$soon." minutes")) )
	            	) {
	            		return 'opening soon';
	            	}
	            	elseif($dataOutlet['today']['open'] && date('H:i:01') < date('H:i', strtotime($dataOutlet['today']['open']))){
                        return 'closed';
                    }
                    elseif ( $soon 
	            		&& ( date('H:i:01') < date('H:i', strtotime($dataOutlet['today']['close'])) )
	            		&& ( date('H:i:01') > date('H:i', strtotime($dataOutlet['today']['close']." -".$soon." minutes")) )
	            	) {
	            		return 'closing soon';
	            	}
	            	elseif($dataOutlet['today']['close'] && date('H:i') > date('H:i', strtotime('-'.$processing.' minutes', strtotime($dataOutlet['today']['close'])))){
                        return 'closed';
                    }
                    else{
                        $holiday = Holiday::join('outlet_holidays', 'holidays.id_holiday', 'outlet_holidays.id_holiday')->join('date_holidays', 'holidays.id_holiday', 'date_holidays.id_holiday')
                        ->where('id_outlet', $dataOutlet['id_outlet'])->whereDay('date_holidays.date', date('d'))->whereMonth('date_holidays.date', date('m'))->get();
                        if(count($holiday) > 0){
                            foreach($holiday as $i => $holi){
                                if($holi['yearly'] == '0'){
                                    if($holi['date'] == date('Y-m-d')){
                                            return 'closed';
                                        break;
                                    }
                                }else{
                                        return 'closed';
                                    break;
                                }
                            }

                        }
                    }
                }
            }
        }
        return 'open';
    }

    /* Penghitung jarak */
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

    function listHoliday(Request $request) {
        $post = $request->json()->all();

        $holiday = Holiday::with(['outlets', 'date_holidays']);
        if (isset($post['id_holiday'])) {
            $holiday->where('id_holiday', $post['id_holiday']);
        }

        if (isset($post['id_outlet'])) {
            $holiday->where('id_outlet', $post['id_outlet']);
        }

        $holiday = $holiday->get()->toArray();

        return response()->json(MyHelper::checkGet($holiday));

    }

    function deleteHoliday(HolidayDelete $request) {

        $data = Holiday::where('id_holiday', $request->json('id_holiday'))->first();

        if ($data) {
            $data->date_holidays()->delete();
            $delete = Holiday::where('id_holiday', $request->json('id_holiday'))->delete();
            return response()->json(MyHelper::checkDelete($delete));
        }
        else {
            return response()->json([
                    'status' => 'fail',
                    'messages' => ['data outlet holiday not found.']
                ]);
        }
    }

    function createHoliday(HolidayStore $request) {
        $post = $request->json()->all();

        $yearly = 0;
        if(isset($post['yearly'])){
            $yearly = 1;
        }

        $holiday = [
            'holiday_name'  => $post['holiday_name'],
            'yearly'        => $yearly
        ];

        DB::beginTransaction();
        $insertHoliday = Holiday::create($holiday);

        if ($insertHoliday) {
            $dateHoliday = [];
            $date = $post['date_holiday'];

            foreach ($date as $value) {
                $dataDate = [
                    'id_holiday'    => $insertHoliday['id_holiday'],
                    'date'          => date('Y-m-d', strtotime($value)),
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'created_by'    => Auth::id(),
            		'updated_by'    => Auth::id()
                ];

                array_push($dateHoliday, $dataDate);
            }

            $insertDateHoliday = DateHoliday::insert($dateHoliday);

            if ($insertDateHoliday) {
                $outletHoliday = [];
                $outlet = $post['id_outlet'];

                foreach ($outlet as $ou) {
                    $dataOutlet = [
                        'id_holiday'    => $insertHoliday['id_holiday'],
                        'id_outlet'     => $ou,
                        'created_at'    => date('Y-m-d H:i:s'),
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'created_by'    => Auth::id(),
                		'updated_by'    => Auth::id()
                    ];

                    array_push($outletHoliday, $dataOutlet);
                }

                $insertOutletHoliday = OutletHoliday::insert($outletHoliday);

                if ($insertOutletHoliday) {
                    DB::commit();
                    return response()->json(MyHelper::checkCreate($insertOutletHoliday));

                } else {
                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'      => [
                            'Data is invalid !!!'
                        ]
                    ]);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'      => [
                        'Data is invalid !!!'
                    ]
                ]);
            }

        } else {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'      => [
                    'Data is invalid !!!'
                ]
            ]);
        }
    }

    public function updateHoliday(HolidayUpdate $request) {
        $post = $request->json()->all();

        $yearly = 0;
        if(isset($post['yearly'])){
            $yearly = 1;
        }
        $holiday = [
            'holiday_name'  => $post['holiday_name'],
            'yearly'        => $yearly
        ];

        DB::beginTransaction();
        $updateHoliday = Holiday::where('id_holiday', $post['id_holiday'])->updateWithUserstamps($holiday);

        if ($updateHoliday) {
            $delete = DateHoliday::where('id_holiday', $post['id_holiday'])->delete();

            if ($delete) {
                $dateHoliday = [];
                $date = $post['date_holiday'];

                foreach ($date as $value) {
                    $dataDate = [
                        'id_holiday'    => $post['id_holiday'],
                        'date'          => date('Y-m-d', strtotime($value)),
                        'created_at'    => date('Y-m-d H:i:s'),
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'created_by'    => Auth::id(),
                		'updated_by'    => Auth::id()
                ];

                    array_push($dateHoliday, $dataDate);
                }

                $updateDateHoliday = DateHoliday::insert($dateHoliday);

                if ($updateDateHoliday) {
                    $deleteOutletHoliday = OutletHoliday::where('id_holiday', $post['id_holiday'])->delete();

                    if ($deleteOutletHoliday) {
                        $outletHoliday = [];
                        $outlet = $post['id_outlet'];

                        foreach ($outlet as $ou) {
                            $dataOutlet = [
                                'id_holiday'    => $post['id_holiday'],
                                'id_outlet'     => $ou,
                                'created_at'    => date('Y-m-d H:i:s'),
                                'updated_at'    => date('Y-m-d H:i:s'),
		                        'created_by'    => Auth::id(),
		                		'updated_by'    => Auth::id()
                            ];

                            array_push($outletHoliday, $dataOutlet);
                        }

                        $insertOutletHoliday = OutletHoliday::insert($outletHoliday);

                        if ($insertOutletHoliday) {
                            DB::commit();
                            return response()->json(MyHelper::checkCreate($insertOutletHoliday));

                        } else {
                            DB::rollBack();
                            return response()->json([
                                'status'    => 'fail',
                                'messages'      => [
                                    'Data is invalid !!!'
                                ]
                            ]);
                        }
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'      => [
                                'Data is invalid !!!'
                            ]
                        ]);
                    }

                } else {
                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'      => [
                            'Data is invalid !!!'
                        ]
                    ]);
                }
            }
        }

        return response()->json(MyHelper::checkUpdate($updateHoliday));
    }

    function exportCity(Request $request) {
        $cities = City::select('city_name as City')->groupBy('city_name')->get()->toArray();
        return response()->json(MyHelper::checkGet($cities));
    }

    function export(Request $request) {
        $brands=$request->json('brands');
        $combo=$request->json('outlet_type')=='combo';
        $all=$request->json('outlet_type')=='all';
        $return=[];
        foreach ($brands??[[]] as $brand) {
            $outlets = Outlet::select('id_outlet','outlets.outlet_code as code',
                'outlets.outlet_name as name',
                'outlets.outlet_address as address',
                'cities.city_name as city',
                'outlets.outlet_phone as phone',
                'outlets.outlet_email as email',
                'outlets.outlet_latitude as latitude',
                'outlets.outlet_longitude as longitude',
                'outlets.deep_link_gojek as deep_link_gojek',
                'outlets.deep_link_grab as deep_link_grab',
                'outlets.available_delivery'
            )->with('brands')->join('cities', 'outlets.id_city', '=', 'cities.id_city');

            foreach ($brand as $bran) {
                $outlets->whereHas('brands',function($query) use ($brand){
                    $query->where('brands.id_brand',$brand);
                });
            }

            $outlets = $outlets->get();
            $count=0;
            foreach ($outlets as $outlet) {
                if($all){
                    $name='All Type';
                }else{
                    if(count($outlet->brands)!=count($brand)){
                        continue;
                    }
                    $continue=false;
                    foreach ($outlet->brands as $outlet_brand) {
                        if(!in_array($outlet_brand->id_brand, $brand)){
                            $continue=true;
                            continue;
                        }
                    }
                    if($continue){
                        continue;
                    }
                    if($combo){
                        $name=$outlet->brands[0]->name_brand.','.$outlet->brands[1]->name_brand;
                    }else{
                        $name=$outlet->brands[0]->name_brand;
                    }
                }
                $outlet_array=$outlet->toArray();
                unset($outlet_array['call']);
                unset($outlet_array['url']);
                unset($outlet_array['brands']);
                unset($outlet_array['id_outlet']);
                unset($outlet_array['detail']);
                $outlet_array['available_delivery'] = implode(',',$outlet_array['available_delivery']);
                $return[$name][]=$outlet_array;
                $count++;
            }
            // if no outlet found
            if(!$count){
                //get name brand
                $brand_name=Brand::select('name_brand')->whereIn('id_brand',$brand)->get()->pluck('name_brand')->toArray();
                //return empty
                $return[implode(',', $brand_name)][]=[
                    'code'=>'',
                    'name'=>'',
                    'address'=>'',
                    'city'=>'',
                    'phone'=>'',
                    'email'=>'',
                    'latitude'=>'',
                    'longitude'=>''
                ];
            }

        }
        return response()->json(MyHelper::checkGet($return));
    }

    function import(Request $request)
    {
        $post = $request->json()->all();
        $dataimport = $post['data_import'];

        if(!empty($dataimport) && count($dataimport)){
            $city = City::get();
            $id_city = array_pluck($city, 'id_city');
            $city_name = array_pluck($city, 'city_name');
            $city_name = array_map('strtolower', $city_name);

            DB::beginTransaction();
            $countImport = 0;
            $failedImport = [];

            foreach ($dataimport as $key => $value) {
                if(
                    empty($value['code']) &&
                    empty($value['name']) &&
                    empty($value['address']) &&
                    empty($value['city']) &&
                    empty($value['phone']) &&
                    empty($value['latitude']) &&
                    empty($value['longitude']) &&
                    empty($value['open_hours']) &&
                    empty($value['close_hours'])
                ){}else{
                    $search = array_search(strtolower($value['city']), $city_name);
                    if(!empty($search) && $key < count($dataimport)){
                        if(!empty($value['open_hours'])){
                            $value['open_hours'] = date('H:i:s', strtotime($value['open_hours']));
                        }
                        if(!empty($value['close_hours'])){
                            $value['close_hours'] = date('H:i:s', strtotime($value['close_hours']));
                        }
                        if(empty($value['code'])){
                            do{
                                $value['code'] = MyHelper::createRandomPIN(3);
                                $code = Outlet::where('outlet_code', $value['code'])->first();
                            }while($code != null);
                        }
                        $code = ['outlet_code' => $value['code']];
                        $insert = [
                            'outlet_code' => $value['code']??'',
                            'outlet_name' => $value['name']??'',
                            'outlet_address' => $value['address']??'',
                            'outlet_postal_code' => $value['postal_code']??'',
                            'outlet_phone' => $value['phone']??'',
                            'outlet_email' => $value['email']??'',
                            'outlet_latitude' => $value['latitude']??'',
                            'outlet_longitude' => $value['longitude']??'',
                            'deep_link_gojek' => $value['deep_link_gojek']??'',
                            'deep_link_grab' => $value['deep_link_grab']??'',
                            'available_delivery' => $value['available_delivery']??'',
                            'delivery_order' => ($value['available_delivery']??'') ? 1 : 0,
                            'id_city' => $id_city[$search]??null
                        ];
                        if(!empty($insert['outlet_name'])){
                            $save = Outlet::updateOrCreate($code, $insert);

                            if(empty($save)){
                                DB::rollBack();
                                return response()->json([
                                    'status'    => 'fail',
                                    'messages'      => [
                                        'Data city not found.'
                                    ]
                                ]);
                            } else {
                                $day = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

                                foreach ($day as $val){
                                    $data = [
                                        'day'       => $val,
                                        'open'      => '10:00:00',
                                        'close'     => '21:30:00',
                                        'is_closed' => 0,
                                        'id_outlet' => $save['id_outlet']
                                    ];
                                    $check = OutletSchedule::where('id_outlet', $save['id_outlet'])->where('day', $val)->select('day')->first();
                                    if(!$check){
                                        $save = OutletSchedule::updateOrCreate(['id_outlet' => $save['id_outlet'], 'day' => $val], $data);
                                        if (!$save) {
                                            DB::rollBack();
                                            return response()->json([
                                                'status'    => 'fail',
                                                'messages'      => [
                                                    'Add shedule failed.'
                                                ]
                                            ]);
                                        }
                                    }
                                }
                                $countImport++;
                            }
                        }else{
                            $failedImport[] = $value['code'];
                        }
                    }else{
                        $failedImport[] = $value['code'];
                    }
                }
            }

            DB::commit();

            if($save??false) return ['status' => 'success', 'message' => $countImport.' data successfully imported. Failed save outlet : '.implode(",",$failedImport)];
            else return ['status' => 'fail','messages' => ['failed to update data' , 'Failed save outlet : '.implode(",",$failedImport)]];
        }else{
            return response()->json([
                'status'    => 'fail',
                'messages'      => [
                    'File is empty.'
                ]
            ]);
        }
    }

    function importBrand(Request $request)
    {
        $post = $request->json()->all();
        $dataimport = $post['data_import'];

        if(!empty($dataimport) && count($dataimport)){
            DB::beginTransaction();
            $countImport = 0;

            $outlets = Outlet::get();
            $id_outlet = array_pluck($outlets, 'id_outlet');
            $outlet_code = array_pluck($outlets, 'outlet_code');
            $outlet_code = array_map('strtolower', $outlet_code);

            $brands = Brand::get();
            $id_brand = array_pluck($brands, 'id_brand');
            $name_brand = array_pluck($brands, 'name_brand');
            $name_brand = array_map('strtolower', $name_brand);

            $countDataImport = count($dataimport);
            for($i=0;$i<$countDataImport;$i++){
                $countDetail = count($dataimport[$i]);
                if(isset($dataimport[$i]['code_outlet'])){
                    $search_outlet = array_search(strtolower($dataimport[$i]['code_outlet']), $outlet_code);
                    if(!empty($search_outlet)){
                        BrandOutlet ::where('id_outlet',$id_outlet[$search_outlet])->delete();

                        foreach ($dataimport[$i] as $key => $val) {
                            if ($key == 'code_outlet') continue;

                            if(strtoupper($val) == 'YES'){
                                $search_brand = array_search(strtolower($key), $name_brand);
                                $insertBrandOutlet = [
                                    'id_brand' => $id_brand[$search_brand]??null,
                                    'id_outlet' => $id_outlet[$search_outlet]??null,
			                        'created_by' => Auth::id(),
			                		'updated_by' => Auth::id()
                                ];

                                $saveBrandOutlet = BrandOutlet::insert($insertBrandOutlet);

                                if (!$saveBrandOutlet) {
                                    DB::rollBack();
                                    return response()->json([
                                        'status'    => 'fail',
                                        'messages'      => [
                                            'Save brand outlet failed.'
                                        ]
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();

            if($saveBrandOutlet??false) return ['status' => 'success', 'message' => 'Data successfully imported.'];
            else return ['status' => 'fail','messages' => ['failed to import data']];
        }else{
            return response()->json([
                'status'    => 'fail',
                'messages'      => [
                    'File is empty.'
                ]
            ]);
        }
    }

    function createAdminOutlet(CreateUserOutlet $request){
        $post = $request->json()->all();

        $outlet = Outlet::where('outlet_code', $post['outlet_code'])->first();
        unset($post['outlet_code']);
        if($outlet){
            $check1 = UserOutlet::where('id_outlet', $outlet->id_outlet)->where('phone', $post['phone'])->first();
            $check2 = UserOutlet::where('id_outlet', $outlet->id_outlet)->where('email', $post['email'])->first();
            if($check1){
                $msg[] = "The phone has already been taken.";
            }
            if($check2){
                $msg[] = "The email has already been taken.";
            }
            if(isset($msg)){
                return response()->json([
                    'status'    => 'fail',
                    'messages'      => $msg
                ]);
            }
            if(isset($post['id_user'])){
                unset($post['id_user']);

            }
            $post['id_outlet'] = $outlet->id_outlet;
            foreach($post['type'] as $value){
                $post[$value] = 1;
            }
            unset($post['type']);
            $save = UserOutlet::create($post);
            return response()->json(MyHelper::checkCreate($save));
        }else{
            return response()->json([
                'status'    => 'fail',
                'messages'      => [
                    'Data outlet not found.'
                ]
            ]);
        }
    }

    function detailAdminOutlet(Request $request){
        $post = $request->json()->all();
        if($post['id_user_outlet']){
            $userOutlet = UserOutlet::find($post['id_user_outlet']);
            return response()->json(MyHelper::checkGet($userOutlet));
        }
    }

    function updateAdminOutlet(UpdateUserOutlet $request){
        $post = $request->json()->all();

            foreach($post['type'] as $value){
                $post[$value] = 1;
            }
            unset($post['type']);
            $userOutlet = UserOutlet::where('id_user_outlet', $post['id_user_outlet'])->first();
            $check1 = UserOutlet::whereNotIn('id_user_outlet', [$post['id_user_outlet']])->where('id_outlet', $userOutlet->id_outlet)->where('phone', $post['phone'])->first();
            $check2 = UserOutlet::whereNotIn('id_user_outlet', [$post['id_user_outlet']])->where('id_outlet', $userOutlet->id_outlet)->where('email', $post['email'])->first();
            if($check1){
                $msg[] = "The phone has already been taken.";
            }
            if($check2){
                $msg[] = "The email has already been taken.";
            }
            if(isset($msg)){
                return response()->json([
                    'status'    => 'fail',
                    'messages'      => $msg
                ]);
            }
            $save = $userOutlet->update($post);
            return response()->json(MyHelper::checkUpdate($save));
    }

    function deleteAdminOutlet(Request $request){
        $post = $request->json()->all();
        $delete = UserOutlet::where('id_user_outlet', $post['id_user_outlet'])->delete();
        return response()->json(MyHelper::checkDelete($delete));
    }

    public function scheduleSave(Request $request)
    {
        $post = $request->json()->all();
        DB::beginTransaction();
        foreach ($post['day'] as $key => $value) {
            $data = [
                'day'       => $value,
                'open'      => $post['open'][$key],
                'close'     => $post['close'][$key],
                'is_closed' => $post['is_closed'][$key],
                'id_outlet' => $post['id_outlet']
            ];

            $schedule_outlet = OutletSchedule::where('id_outlet', $post['id_outlet'])->where('day', $value)->first();
            if($schedule_outlet){
                switch ($schedule_outlet['time_zone']) {
                    case 'Asia/Makassar':
                    case 'Asia/Singapore':
                        $data['open'] = date('H:i', strtotime('-1 hour',strtotime($data['open'])));
                        $data['close'] = date('H:i', strtotime('-1 hour', strtotime($data['close'])));
                        break;
                    case 'Asia/Jayapura':
                        $data['open'] = date('H:i', strtotime('-2 hours', strtotime($data['open'])));
                        $data['close'] = date('H:i', strtotime('-2 hours', strtotime($data['close'])));
                        break;
                }
                $save = OutletSchedule::updateOrCreate(['id_outlet' => $post['id_outlet'], 'day' => $value], $data);
                if (!$save) {
                    DB::rollBack();
                    return response()->json(['status' => 'fail']);
                }
            }else{
                $create = OutletSchedule::create($data);
                if (!$create) {
                    DB::rollBack();
                    return response()->json(['status' => 'fail']);
                }
            }
        }

        DB::commit();
        return response()->json(['status' => 'success']);
    }

    public function filterList($model,$rule,$operator='and'){
        $newRule=[];
        $where=$operator=='and'?'where':'orWhere';
        foreach ($rule as $var) {
            $var1=['operator'=>$var['operator']??'=','parameter'=>$var['parameter']??null];
            if($var1['operator']=='like'){
                $var1['parameter']='%'.$var1['parameter'].'%';
            }
            $newRule[$var['subject']][]=$var1;
        }
        if($newRule['all_empty']??false){
                $model->$where(function($query){
                    $all=['id_city','outlet_latitude','outlet_longitude'];
                    foreach ($all as $field) {
                        $query->where(function($query) use($field){
                            $query->where($field,'=','')->orWhereNull($field);
                        });
                    }
                });
        }
        if($newRule['empty']??false){
            $all=array_column($newRule['empty'],'parameter');
            foreach ($all as $field) {
                $model->$where(function($query) use ($field){
                    $query->where($field,'')->orWhereNull($field);
                });
            }
        }
        if($rules=$newRule['id_brand']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('brands',function($query) use ($rul){
                    $query->where('brands.id_brand',$rul['operator'],$rul['parameter']);
                });
            }
        }
        $inner=['outlet_code'];
        foreach ($inner as $col_name) {
            if($rules=$newRule[$col_name]??false){
                foreach ($rules as $rul) {
                    $model->$where('outlets.'.$col_name,$rul['operator'],$rul['parameter']);
                }
            }
        }
    }

    public function batchUpdate(Request $request){
        $posts=$request->json()->all();
        DB::beginTransaction();
        $save=1;
        foreach ($posts['outlets']??[] as $id_outlet=>$data) {
            $post = $this->checkInputOutlet($data);
            $save_t = Outlet::where('id_outlet', $id_outlet)->updateWithUserstamps($post);
            // return Outlet::where('id_outlet', $request->json('id_outlet'))->first();
            if(!$save_t){
                $save=0;
                break;
            }
        }
        if($save){
            DB::commit();
            return ['status'=>'success'];
        }
        DB::rollBack();
        return ['status'=>'fail'];
    }

    public function ajaxHandler(Request $request){
        $post=$request->except('_token');
        $q=(new Outlet)->newQuery();
        if($post['select']??false){
            $q->select($post['select']);
        }
        if($condition=$post['condition']??false){
            $this->filterList($q,$condition['rules']??'',$condition['operator']??'and');
        }
        return MyHelper::checkGet($q->get());
    }

    public function detailTransaction(Request $request) {
        $outlet = Outlet::with(['today','brands'=>function($query){
                    $query->where([['brand_active',1],['brand_visibility',1]]);
                    $query->select('brands.id_brand','name_brand');
                }])->select('id_outlet','outlet_code','outlet_name','outlet_address','outlet_latitude','outlet_longitude','outlet_phone','outlet_status','delivery_order')->find($request->json('id_outlet'));
        if(!$outlet){
            return MyHelper::checkGet([]);
        }
        $outlet = $outlet->toArray();
        $processing = '0';
        $settingTime = Setting::where('key', 'processing_time')->first();
        if($settingTime && $settingTime->value){
            $processing = $settingTime->value;
        }
        if(($latitude = $request->json('latitude'))&&($longitude = $request->json('longitude'))){
            $jaraknya =   number_format((float)$this->distance($latitude, $longitude, $outlet['outlet_latitude'], $outlet['outlet_longitude'], "K"), 2, '.', '');
            settype($jaraknya, "float");

            $outlet['distance'] = number_format($jaraknya, 2, '.', ',')." km";
            $outlet['dist']     = (float) $jaraknya;
        }else{
            $outlet['distance'] = '';
        }

    	$check_holiday = $this->checkOutletHoliday();
        
        if ($check_holiday['status'] && in_array($outlet['id_outlet'], $check_holiday['list_outlet'])) {
        	$outlet['today']['is_closed'] = 1;
        }

        $outlet = $this->setAvailableOutlet($outlet, $processing);
        $outlet['today'] = $this->setTimezone($outlet['today']);
        return MyHelper::checkGet($outlet);
    }

    public function listOutletOrderNow(OutletListOrderNow $request){
        $post = $request->json()->all();
        $user = $request->user();

        if (!$request->latitude && !$request->longitude) {
            return [
                'status' => 'fail',
                'messages' => ['Make sure your phone\'s location settings are connected']
            ];
        }

        try{
            $title = Setting::where('key', 'order_now_title')->first()->value;
            $subTitleSuccess = Setting::where('key', 'order_now_sub_title_success')->first()->value;
            $subTitleFail = Setting::where('key', 'order_now_sub_title_fail')->first()->value;
            $processingTime = Setting::where('key', 'processing_time')->first();

            if($processingTime){
                $processingTime = (int)$processingTime['value'] * 100;
            }else{
                $processingTime = 15 * 100; //processing time in minutes convert to second - set default
            }

            $data = [
                'current_date' => date('Y-m-d'),
                'current_day' => date('l'),
                'current_hour' => date('H:i:s'),
                'processing_time' => $processingTime
            ];

            $outlet = Outlet::join('cities', 'cities.id_city', 'outlets.id_city')
                ->selectRaw('outlets.id_outlet, outlets.outlet_name, outlets.outlet_code,outlets.outlet_status,outlets.outlet_address,outlets.id_city, outlets.outlet_latitude, outlets.outlet_longitude,
                    (111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(outlets.outlet_latitude))
                         * COS(RADIANS('.$post['latitude'].'))
                         * COS(RADIANS(outlets.outlet_longitude - '.$post['longitude'].'))
                         + SIN(RADIANS(outlets.outlet_latitude))
                         * SIN(RADIANS('.$post['latitude'].')))))) AS distance_in_km' )
                ->with(['user_outlets','city','today', 'outlet_schedules', 'brands'])
                ->where('outlets.outlet_status', 'Active')
                ->whereNotNull('outlets.outlet_latitude')
                ->whereNotNull('outlets.outlet_longitude')
                ->whereHas('brands',function($query){
                    $query->where('brand_active','1');
                })
                ->whereIn('id_outlet',function($query) use ($data){
                    $query->select('id_outlet')
                        ->from('outlet_schedules')
                        ->where('day', $data['current_day'])
                        ->where('is_closed', 0)
                        ->whereRaw('TIME_TO_SEC("'.$data['current_hour'].'") >= TIME_TO_SEC(open) AND TIME_TO_SEC("'.$data['current_hour'].'") <= TIME_TO_SEC(SUBTIME(close, "'.$data['processing_time'].'")) ');
                })->whereNotIn('id_outlet',function($query) use ($data){
                    $query->select('id_outlet')
                        ->from('outlet_holidays')
                        ->join('date_holidays', 'date_holidays.id_holiday', 'outlet_holidays.id_holiday')
                        ->where('date', $data['current_date']);
                })
                ->orderBy('distance_in_km', 'asc')
                ->limit(5)
                ->get()->toArray();

            if(count($outlet) > 0){
                $loopdata=&$outlet;
                $loopdata = array_map(function($var) use ($post){
                    $var['url']=env('API_URL').'api/outlet/webview/'.$var['id_outlet'];
                    if(($post['latitude']??false)&&($post['longitude']??false)){
                        $var['distance']=number_format((float)$this->distance($post['latitude'], $post['longitude'], $var['outlet_latitude'], $var['outlet_longitude'], "K"), 2, '.', '').' km';
                    }
                    return $var;
                }, $loopdata);

                $result = [
                    'status' => 'success',
                    'messages' => [],
                    'result' => [
                        'title' => $title,
                        'sub_title' => $subTitleSuccess,
                        'data' => $outlet
                    ]
                ];
            }else{
                $result = [
                    'status' => 'fail',
                    'messages' => [$subTitleFail],
                    'result' => [
                        'title' => $title,
                        'sub_title' => $subTitleFail,
                        'data' => null
                    ]
                ];
            }

        }catch (Exception $e) {
            $result = [
                'status' => 'fail',
                'messages' => ['something went wrong'],
                'result' => [
                    'data' => null
                ]
            ];
        }
        return response()->json($result);
    }

    public function getAllCodeOutlet(Request $request){
        $outlet = Outlet::get()->pluck('outlet_code');

        if($outlet){
            return response()->json(['status' => 'success', 'result' => $outlet]);
        }else{
            return response()->json(['status' => 'fail', 'message' => 'empty']);
        }
    }

    public function applyPromo($promo_post, $data_outlet, &$promo_error)
    {
    	// check promo
    	$post = $promo_post;
    	$outlet = $data_outlet;

    	// give all product flag is_promo = 0
        foreach ($outlet as $key => $value) {
			$outlet[$key]['is_promo'] = 0;
		}

		$promo_error = null;
		if ( (!empty($post['promo_code']) && empty($post['id_deals_user'])) || (empty($post['promo_code']) && !empty($post['id_deals_user'])) ) {
        // if (isset($post['promo_code'])) {
        	if (!empty($post['promo_code']))
        	{
        		$code = app($this->promo_campaign)->checkPromoCode($post['promo_code'], 1);
        		$source = 'promo_campaign';
        	}else{
        		$code = app($this->promo_campaign)->checkVoucher($post['id_deals_user'], 1);
        		$source = 'deals';
        	}

	        if(!$code){
	        	$promo_error = 'Promo not valid';
	        	return false;
	        }else{

	        	if ( ($code['promo_campaign']['date_end']??$code['voucher_expired_at']) < date('Y-m-d H:i:s') ) {
	        		$promo_error = 'Promo is ended';
	        		return false;
	        	}

	        	// if valid give flag is_promo = 1
	        	$code = $code->toArray();
        		if ($code['promo_campaign']['is_all_outlet']??$code['deal_voucher']['deals']['is_all_outlet']??false) {
        			foreach ($outlet as $key => $value) {
    					$outlet[$key]['is_promo'] = 1;
    				}
        		}else{
		        	foreach ( ($code['promo_campaign']['promo_campaign_outlets']??$code['deal_voucher']['deals']['outlets_active']) as $key => $value) {
	        			foreach ($outlet as $key2 => $value2) {
	        				if ( $value2['id_outlet'] == $value['id_outlet'] ) {
	    						$outlet[$key2]['is_promo'] = 1;
	    						break;
	    					}
	        			}
		        	}
        		}
	        }
        }elseif( !empty($post['promo_code']) && !empty($post['id_deals_user']) ) {
        	$promo_error = 'Can only use either promo code or voucher';
        }

        return $outlet;
        // end check promo
    }
    /**
     * Get list different outlet
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function differentPrice(Request $request) {
        $data = Outlet::select('id_outlet','outlet_code','outlet_name','outlet_different_price');
        if($keyword = $request->json('keyword')){
            $data->where('outlet_code','like',"%$keyword%")
                ->orWhere('outlet_name','like',"%$keyword%");
        }
        if($request->page){
            return MyHelper::checkGet($data->paginate(20));
        }else{
            return MyHelper::checkGet($data->get());
        }
    }
    public function updateDifferentPrice(Request $request) {
        $post = $request->json()->all();
        $update = Outlet::whereIn('id_outlet',$post['id_outlet']??'')->updateWithUserstamps(['outlet_different_price'=>$post['status']??0]);
        if($update){
            return [
                'status'=>'success',
                'result'=>$post['status']??0
            ];
        }
        return ['status'=>'fail'];
    }

    public function setTimezone($data){
        $data['time_zone_id'] = 'WIB';
        switch ($data['time_zone']) {
            case 'Asia/Makassar':
                $data['open'] = date('H:i', strtotime('+1 hour',strtotime($data['open'])));
                $data['close'] = date('H:i', strtotime('+1 hour', strtotime($data['close'])));
                $data['time_zone_id'] = 'WITA';
            break;
            case 'Asia/Jayapura':
                $data['open'] = date('H:i', strtotime('+2 hours', strtotime($data['open'])));
                $data['close'] = date('H:i', strtotime('+2 hours', strtotime($data['close'])));
                $data['time_zone_id'] = 'WIT';
            break;
            case 'Asia/Singapore':
                $data['open'] = date('H:i', strtotime('+1 hour',strtotime($data['open'])));
                $data['close'] = date('H:i', strtotime('+1 hour', strtotime($data['close'])));
                $data['time_zone_id'] = '';
            break;
        }
        return $data;
    }

    function reorderDays($days, $now)
    {	
    	$temp_days 	= [];
    	$new_days	= [];
		foreach ($days as $key => $value) {
			$temp_days[] = $value;
			if ($value['day'] == $now) {
				$new_days = array_slice($days, $key+1);
				break;
			}
		}
		if (!empty($new_days)) {
			$days = array_merge($new_days, $temp_days);
		}

		return $days;
    }

    function checkOutletHoliday()
    {
    	$result = [
			'status' 		=> false,
			'list_outlet' 	=> []
		];

    	$holiday 	= Holiday::join('outlet_holidays', 'holidays.id_holiday', 'outlet_holidays.id_holiday')
					->join('date_holidays', 'holidays.id_holiday', 'date_holidays.id_holiday')
	                ->select('outlet_holidays.id_outlet', 'holidays.id_holiday', 'holidays.yearly', 'date_holidays.date')
	                ->whereDay('date_holidays.date', date('d'))
	                ->whereMonth('date_holidays.date', date('m'))
	                ->get()
	                ->toArray();

		if ($holiday) {
			$list_outlet = array_column($holiday, 'id_outlet');
            foreach($holiday as $i => $holi){
                if($holi['yearly'] == '0'){
                    if($holi['date'] == date('Y-m-d')){
                        $result = [
							'status' 		=> true,
							'list_outlet' 	=> $list_outlet
						];
                    }
                }
                else
                {
                    $result = [
						'status' 		=> true,
						'list_outlet' 	=> $list_outlet
					];
                }
            }
		}

		return $result;
    }

    function getYesterday(){
    	$day = date ("D");

		switch($day){
			case 'Sun':
				$yesterday = "Saturday";
			break;

			case 'Mon':
				$yesterday = "Sunday";
			break;

			case 'Tue':
				$yesterday = "Monday";
			break;

			case 'Wed':
				$yesterday = "Tuesday";
			break;

			case 'Thu':
				$yesterday = "Wednesday";
			break;

			case 'Fri':
				$yesterday = "Thursday";
			break;

			default:
				$yesterday = "Friday";
			break;
		}

		return $yesterday;
    }

    function getYesterdaySchedule($id_outlet)
    {
    	$yesterday = $this->getYesterday();
	    $outlet_schedule = OutletSchedule::where('id_outlet', $id_outlet)->where('day', $yesterday)->select('id_outlet', 'day', 'open', 'close', 'is_closed', 'time_zone')->first();

	    return $outlet_schedule;
    }

    public function sendNotifIncompleteOutlet(...$id_outlets)
    {
        if(!$id_outlets){
            // find incomplete outlet
            $outlets = Outlet::where(function($q) {
                $q->whereNull('outlet_latitude')
                    ->orWhereNull('outlet_longitude')
                    ->orWhereNull('outlet_phone')
                    ->orWhereNull('outlet_address');
            })->get();
        }else{
            $outlets = Outlet::whereIn('id_outlet',$id_outlets)->where('notify_admin',0)->get();
        }
        $phone = User::select('phone')->pluck('phone')->first();
        $complete = [0,0];
        foreach ($outlets as $outlet) {
            $variable = [];
            foreach ($outlet->toArray() as $key => $value) {
                $variable[str_replace('outlet_','',$key)] = $value;
            }
            $incomplete = [];
            if(!$outlet['outlet_latitude']){
                $incomplete[] = 'Outlet Latitude';
            }
            if(!$outlet['outlet_longitude']){
                $incomplete[] = 'Outlet Longitude';
            }
            if(!$outlet['outlet_phone']){
                $incomplete[] = 'Outlet Phone';
            }
            if(!$outlet['outlet_address']){
                $incomplete[] = 'Outlet Address';
            }
            $variable['incomplete_data'] = implode(', ', $incomplete);
            $send = app($this->autocrm)->SendAutoCRM('Incomplete Outlet Data', $phone, $variable, null, true);
            $complete[1]++;
            if(!$send){
                \Log::warning('Failed send forward email Incomplete Outlet Data for outlet '.$outlet->code.' - '.$outlet->name);
            }else{
                $complete[0]++;
            }
        }
        return ['status'=>'success','result' => ['incomplete'=>$complete[1],'send'=>$complete[0]]];
    }

    public function resetNotify()
    {
        $log = MyHelper::logCron('Reset Notify Flag');
        try {
            Outlet::where('notify_admin',1)->update(['notify_admin'=>0]);
            $log->success();
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }
}
