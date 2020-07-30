<?php

namespace Modules\Deals\Http\Controllers;

use App\Http\Models\Configs;
use App\Http\Models\DealTotal;
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
use App\Http\Models\SpinTheWheel;
use App\Http\Models\Setting;
use Modules\Brand\Entities\Brand;
use App\Http\Models\DealsPromotionTemplate;
use Modules\ProductVariant\Entities\ProductGroup;
use App\Http\Models\Product;

use Modules\Deals\Entities\DealsProductDiscount;
use Modules\Deals\Entities\DealsProductDiscountRule;
use Modules\Deals\Entities\DealsTierDiscountProduct;
use Modules\Deals\Entities\DealsTierDiscountRule;
use Modules\Deals\Entities\DealsBuyxgetyProductRequirement;
use Modules\Deals\Entities\DealsBuyxgetyRule;
use Modules\Deals\Entities\DealsUserLimit;
use Modules\Deals\Entities\DealsContent;
use Modules\Deals\Entities\DealsContentDetail;

use DB;

use Modules\Deals\Http\Requests\Deals\Create;
use Modules\Deals\Http\Requests\Deals\Update;
use Modules\Deals\Http\Requests\Deals\Delete;
use Modules\Deals\Http\Requests\Deals\ListDeal;
use Modules\Deals\Http\Requests\Deals\DetailDealsRequest;
use Modules\Deals\Http\Requests\Deals\UpdateContentRequest;
use Modules\Deals\Http\Requests\Deals\ImportDealsRequest;
use Modules\Deals\Http\Requests\Deals\UpdateComplete;

use Illuminate\Support\Facades\Schema;

use Image;

class ApiDeals extends Controller
{

    function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->user     = "Modules\Users\Http\Controllers\ApiUser";
        $this->hidden_deals     = "Modules\Deals\Http\Controllers\ApiHiddenDeals";
        $this->autocrm = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->subscription = "Modules\Subscription\Http\Controllers\ApiSubscription";
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->promotion_deals      = "Modules\Promotion\Http\Controllers\ApiPromotionDeals";
        $this->promo_export_import  = "Modules\PromoCampaign\Http\Controllers\ApiPromoExportImport";
        $this->deals_claim    = "Modules\Deals\Http\Controllers\ApiDealsClaim";
    }

    public $saveImage = "img/deals/";


    function rangePoint()
    {
        $start = Setting::where('key', 'point_range_start')->get()->first();
        $end = Setting::where('key', 'point_range_end')->get()->first();

        if (!$start) {
            $start['value'] = 0;
        }

        if (!$end) {
            $end['value'] = 1000000;
        }

        return response()->json([
            'status'    => 'success',
            'result'    => [
                'point_range_start' => $start['value'],
                'point_range_end'   => $end['value'],
            ]
        ]);
    }

    /* CHECK INPUTAN */
    function checkInputan($post)
    {

        $data = [];

        if (isset($post['deals_promo_id_type'])) {
            $data['deals_promo_id_type'] = $post['deals_promo_id_type'];
        }
        if (isset($post['deals_type'])) {
            $data['deals_type'] = $post['deals_type'];
        }
        if (isset($post['deals_voucher_type'])) {
            $data['deals_voucher_type'] = $post['deals_voucher_type'];
            if ($data['deals_voucher_type'] == 'Unlimited') {
            	$data['deals_total_voucher'] = 0;
            }

            if ($post['deals_type'] == 'Promotion')
            {
	            if($post['deals_voucher_type'] == 'List Vouchers'){
					$data['deals_list_voucher'] = str_replace("\r\n", ',', $post['voucher_code']);
				}else{
					$data['deals_list_voucher'] = null;
				}
            }
        }
        if (isset($post['deals_promo_id'])) {
            $data['deals_promo_id'] = $post['deals_promo_id'];
        }
        if (isset($post['deals_title'])) {
            $data['deals_title'] = $post['deals_title'];
        }
        if (isset($post['deals_second_title'])) {
            $data['deals_second_title'] = $post['deals_second_title'];
        }
        if (isset($post['deals_description'])) {
            $data['deals_description'] = $post['deals_description'];
        }
        if (isset($post['product_type'])) {
            $data['product_type'] = $post['product_type'];
        }
        if (isset($post['deals_tos'])) {
            $data['deals_tos'] = $post['deals_tos'];
        }
        if (isset($post['deals_short_description'])) {
            $data['deals_short_description'] = $post['deals_short_description'];
        }
        if (isset($post['deals_image'])) {

            if ($post['deals_type'] == 'Promotion')
            {
            	$promotionPath = 'img/promotion/deals/';
            }
            if (!file_exists($promotionPath??$this->saveImage)) {
                mkdir($promotionPath??$this->saveImage, 0777, true);
            }

            $upload = MyHelper::uploadPhotoStrict($post['deals_image'], ($promotionPath??$this->saveImage), 500, 500);

            if (isset($upload['status']) && $upload['status'] == "success") {
                $data['deals_image'] = $upload['path'];
            } else {
                $result = [
                    'error'    => 1,
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ];

                return $result;
            }
        }
        // if (isset($post['deals_video'])) {
        //     $data['deals_video'] = $post['deals_video'];
        // }
        if (isset($post['id_product'])) {
            $data['id_product'] = $post['id_product'];
        }
        if (isset($post['id_brand'])) {
            $data['id_brand'] = $post['id_brand'];
        }
        if (isset($post['deals_start'])) {
            $data['deals_start'] = date('Y-m-d H:i:s', strtotime($post['deals_start']));
        }
        if (isset($post['deals_end'])) {
            $data['deals_end'] = date('Y-m-d H:i:s', strtotime($post['deals_end']));
        }
        if (isset($post['deals_publish_start'])) {
            $data['deals_publish_start'] = date('Y-m-d H:i:s', strtotime($post['deals_publish_start']));
        }
        if (isset($post['deals_publish_end'])) {
            $data['deals_publish_end'] = date('Y-m-d H:i:s', strtotime($post['deals_publish_end']));
        }

        // ---------------------------- DURATION
        if (isset($post['deals_voucher_duration'])) {
            $data['deals_voucher_duration'] = $post['deals_voucher_duration'];
        }
        if (empty($post['deals_voucher_duration']) || is_null($post['deals_voucher_duration'])) {
            $data['deals_voucher_duration'] = null;
        }

        // ---------------------------- EXPIRED
        if (isset($post['deals_voucher_expired'])) {
            $data['deals_voucher_expired'] = $post['deals_voucher_expired'];
        }
        if (empty($post['deals_voucher_expired']) || is_null($post['deals_voucher_expired'])) {
            $data['deals_voucher_expired'] = null;
        }
        // ---------------------------- VOUCHER START
        $data['deals_voucher_start']=$post['deals_voucher_start']??null;
        // ---------------------------- POINT
        if (isset($post['deals_voucher_price_point'])) {
            $data['deals_voucher_price_point'] = $post['deals_voucher_price_point'];
        }

        if (empty($post['deals_voucher_price_point']) || is_null($post['deals_voucher_price_point'])) {
            $data['deals_voucher_price_point'] = null;
        }

        // ---------------------------- CASH
        if (isset($post['deals_voucher_price_cash'])) {
            $data['deals_voucher_price_cash'] = $post['deals_voucher_price_cash'];
        }
        if (empty($post['deals_voucher_price_cash']) || is_null($post['deals_voucher_price_cash'])) {
            $data['deals_voucher_price_cash'] = null;
        }

        if (isset($post['deals_total_voucher'])) {
            $data['deals_total_voucher'] = $post['deals_total_voucher'];
        }
        if (isset($post['deals_total_claimed'])) {
            $data['deals_total_claimed'] = $post['deals_total_claimed'];
        }
        if (isset($post['deals_total_redeemed'])) {
            $data['deals_total_redeemed'] = $post['deals_total_redeemed'];
        }
        if (isset($post['deals_total_used'])) {
            $data['deals_total_used'] = $post['deals_total_used'];
        }
        if (isset($post['id_outlet'])) {
        	if ($post['deals_type'] == 'Promotion') {
        		$data['deals_list_outlet'] = implode(',', $post['id_outlet']);
				unset($data['id_outlet']);
        	}else{
        	    $data['id_outlet'] = $post['id_outlet'];
        	}
            if (in_array("all", $post['id_outlet'])){
                $data['is_all_outlet'] = 1;
                $data['id_outlet'] = [];
            }else{
                $data['is_all_outlet'] = 0;
            }
        }
        if (isset($post['user_limit'])) {
            $data['user_limit'] = $post['user_limit'];
        } else {
            $data['user_limit'] = 0;
        }

        if (isset($post['is_online'])) {
            $data['is_online'] = 1;
        } else {
            $data['is_online'] = 0;
        }

        if (isset($post['is_offline'])) {
            $data['is_offline'] = 1;
        } else {
            $data['is_offline'] = 0;
            $data['deals_promo_id_type'] = null;
            $data['deals_promo_id'] = null;
        }

        if (isset($post['custom_outlet_text'])) {
        	$data['custom_outlet_text'] = $post['custom_outlet_text'];
        }

        return $data;
    }

    /* CREATE */
    function create($data)
    {
        $data = $this->checkInputan($data);
        $data['created_by'] = auth()->user()->id;
        // error
        if (isset($data['error'])) {
            unset($data['error']);
            return response()->json($data);
        }

        //for 1 brand
        if(!isset($data['id_brand'])){
            $configBrand = Configs::where('config_name', 'use brand')->select('is_active')->first();
            if(isset($configBrand['is_active']) && $configBrand['is_active'] != '1'){
                $brand = Brand::select('id_brand')->first();
                if(isset($brand['id_brand'])){
                    $data['id_brand'] = $brand['id_brand'];
                }
            }
        }

        if ($data['deals_type'] == 'Promotion') {
        	$save = DealsPromotionTemplate::create($data);
        }else{
        	$save = Deal::create($data);
        }

        if ($save) {
            if (isset($data['id_outlet']) && $data['is_all_outlet'] == 0) {
                $saveOutlet = $this->saveOutlet($save, $data['id_outlet']);

                if (!$saveOutlet) {
                    return false;
                }
            }
        }
        return $save;
    }

    /* CREATE REQUEST */
    function createReq(Create $request)
    {
        DB::beginTransaction();
        $save = $this->create($request->json()->all());

        if ($save) {
            DB::commit();
            $dt = '';
            switch ($save->deals_type){
                case 'Deals':
                    $dt = 'Deals';
                    break;
                case 'Hidden':
                    $dt = 'Inject Voucher';
                    break;
                case 'WelcomeVoucher':
                    $dt = 'Welcome Voucher';
                    break;
            }
            $deals = $save->toArray();
            $send = app($this->autocrm)->SendAutoCRM('Create '.$dt, $request->user()->phone, [
                'voucher_type' => $deals['deals_voucher_type']??'',
                'promo_id_type' => $deals['deals_promo_id_type']??'',
                'promo_id' => $deals['deals_promo_id']??'',
                'detail' => view('deals::emails.detail',['detail'=>$deals])->render()
            ]+$deals,null,true);
        } else {
            DB::rollBack();
        }

        return response()->json(MyHelper::checkCreate($save));
    }

    /* LIST */
    function listDeal(ListDeal $request) {
        if($request->json('forSelect2'))
        {
            $deals = Deal::select('id_deals','deals_title')
            		->where('deals_type','Deals')
            		->whereDoesntHave('featured_deals');

            if ($request->json('featured')) {
            	$deals = $deals->where('deals_end', '>', date('Y-m-d H:i:s'))
            			->where('deals_publish_end', '>', date('Y-m-d H:i:s'))
            			->where('step_complete', '=', 1);
            }

            return MyHelper::checkGet($deals->get());
        }

        $deals = (new Deal)->newQuery();
        $user = $request->user();
        $curBalance = (int) $user->balance??0;
        if($request->json('admin')){
            $deals->addSelect('id_brand');
            $deals->with('brand');
        }else{
            if($request->json('deals_type') != 'WelcomeVoucher' && !$request->json('web')){
                $deals->where('deals_end', '>', date('Y-m-d H:i:s'));
            }
        }
        if ($request->json('id_outlet') && is_integer($request->json('id_outlet'))) {
            $deals = $deals->join('deals_outlets', 'deals.id_deals', 'deals_outlets.id_deals')
                ->where('id_outlet', $request->json('id_outlet'))
                ->addSelect('deals.*')->distinct();
        }

        // brand
        if ($request->json('id_brand')) {
            $deals->where('id_brand',$request->json('id_brand'));
        }
        // deals subscription
        if ($request->json('deals_type') == "Subscription") {
            $deals->with('deals_subscriptions');
        }

        if ($request->json('id_deals')) {
            $deals->with([
                'deals_vouchers',
                // 'deals_vouchers.deals_voucher_user',
                // 'deals_vouchers.deals_user.user'
            ])->where('id_deals', $request->json('id_deals'))->with(['deals_content', 'deals_content.deals_content_details', 'outlets', 'outlets.city', 'product','brand']);
        }else{
            $deals->addSelect('id_deals','deals_title','deals_second_title','deals_voucher_price_point','deals_voucher_price_cash','deals_total_voucher','deals_total_claimed','deals_voucher_type','deals_image','deals_start','deals_end','deals_type','is_offline','is_online','product_type','step_complete','deals_total_used','promo_type','deals_promo_id_type','deals_promo_id');
            if(strpos($request->user()->level,'Admin')>=0){
                $deals->addSelect('deals_promo_id','deals_publish_start','deals_publish_end','created_at');
            }
        }
        if ($request->json('rule')){
             $this->filterList($deals,$request->json('rule'),$request->json('operator')??'and');
        }
        if ($request->json('publish')) {
            $deals->where( function($q) {
            	$q->where('deals_publish_start', '<=', date('Y-m-d H:i:s'))
            		->where('deals_publish_end', '>=', date('Y-m-d H:i:s'));
            });

            $deals->where( function($q) {
	        	$q->where('deals_voucher_type','Unlimited')
	        		->orWhereRaw('(deals.deals_total_voucher - deals.deals_total_claimed) > 0 ');
	        });
            $deals->where('step_complete', '=', 1);

            $deals->whereDoesntHave('deals_user_limits', function($q) use ($user){
            	$q->where('id_user',$user->id);
            });
        }

        if ($request->json('deals_type')) {
            // get > 1 deals types
            if (is_array($request->json('deals_type'))) {
                $deals->whereIn('deals_type', $request->json('deals_type'));
            } else {
                $deals->where('deals_type', $request->json('deals_type'));
            }
        }

		if ($request->json('deals_type_array')) {
            // get > 1 deals types
            $deals->whereIn('deals_type', $request->json('deals_type_array'));
        }        

        if ($request->json('deals_promo_id')) {
            $deals->where('deals_promo_id', $request->json('deals_promo_id'));
        }

        if ($request->json('key_free')) {
            $deals->where(function($query) use ($request){
                $query->where('deals_title', 'LIKE', '%' . $request->json('key_free') . '%')
                    ->orWhere('deals_second_title', 'LIKE', '%' . $request->json('key_free') . '%');
            });
        }


        /* ========================= TYPE ========================= */
        $deals->where(function ($query) use ($request) {
            // cash
            if ($request->json('voucher_type_paid')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNotNull('deals_voucher_price_cash');
                    if(is_numeric($val=$request->json('price_range_start'))){
                        $amp->where('deals_voucher_price_cash','>=',$val);
                    }
                    if(is_numeric($val=$request->json('price_range_end'))){
                        $amp->where('deals_voucher_price_cash','<=',$val);
                    }
                });
                // print_r('voucher_type_paid');
                // print_r($query->get()->toArray());die();
            }

            if ($request->json('voucher_type_point')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNotNull('deals_voucher_price_point');
                    if(is_numeric($val=$request->json('point_range_start'))){
                        $amp->where('deals_voucher_price_point','>=',$val);
                    }
                    if(is_numeric($val=$request->json('point_range_end'))){
                        $amp->where('deals_voucher_price_point','<=',$val);
                    }
                });
                // print_r('voucher_type_point');
                // print_r($query->get()->toArray());die();
            }

            if ($request->json('voucher_type_free')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNull('deals_voucher_price_point')->whereNull('deals_voucher_price_cash');
                });
                // print_r('voucher_type_free');
                // print_r($query->get()->toArray());die();
            }
        });

        // print_r($deals->get()->toArray());
        // $deals = $deals->orderBy('deals_start', 'ASC');

        if ($request->json('lowest_point')) {
            $deals->orderBy('deals_voucher_price_point', 'ASC');
        }

        if ($request->json('highest_point')) {
            $deals->orderBy('deals_voucher_price_point', 'DESC');
        }

        if ($request->json('alphabetical')) {
            $deals->orderBy('deals_title', 'ASC');
        } else if ($request->json('newest')) {
            $deals->orderBy('deals_publish_start', 'DESC');
        } else if ($request->json('oldest')) {
            $deals->orderBy('deals_publish_start', 'ASC');
        } else if ($request->json('updated_at')) {
            $deals->orderBy('updated_at', 'DESC');
        } else {
            $deals->orderBy('deals_end', 'ASC');
        }
        if ($request->json('id_city')) {
            $deals->with('outlets','outlets.city');
        }

        if ($request->json('paginate') && $request->json('admin')) {
        	return $this->dealsPaginate($deals, $request);
        }

        $deals = $deals->get()->toArray();
        // print_r($deals); exit();

        if (!empty($deals)) {
            $city = "";

            // jika ada id city yg faq
            if ($request->json('id_city')) {
                $city = $request->json('id_city');
            }

            $deals = $this->kotacuks($deals, $city,$request->json('admin'));
        }

        if ($request->json('highest_available_voucher')) {
            $tempDeals = [];
            $dealsUnlimited = $this->unlimited($deals);

            if (!empty($dealsUnlimited)) {
                foreach ($dealsUnlimited as $key => $value) {
                    array_push($tempDeals, $deals[$key]);
                }
            }

            $limited = $this->limited($deals);

            if (!empty($limited)) {
                $tempTempDeals = [];
                foreach ($limited as $key => $value) {
                    array_push($tempTempDeals, $deals[$key]);
                }

                $tempTempDeals = $this->highestAvailableVoucher($tempTempDeals);

                $tempDeals =  array_merge($tempDeals, $tempTempDeals);
            }

            $deals = $tempDeals;
        }

        if ($request->json('lowest_available_voucher')) {
            $tempDeals = [];

            $limited = $this->limited($deals);

            if (!empty($limited)) {
                foreach ($limited as $key => $value) {
                    array_push($tempDeals, $deals[$key]);
                }

                $tempDeals = $this->lowestAvailableVoucher($tempDeals);
            }

            $dealsUnlimited = $this->unlimited($deals);

            if (!empty($dealsUnlimited)) {
                foreach ($dealsUnlimited as $key => $value) {
                    array_push($tempDeals, $deals[$key]);
                }
            }

            $deals = $tempDeals;
        }



        // if deals detail, add webview url & btn text
        if ($request->json('id_deals') && !empty($deals)) {
            //url webview
            $deals[0]['webview_url'] = env('APP_URL') . "webview/deals/" . $deals[0]['id_deals'] . "/" . $deals[0]['deals_type'];
            // text tombol beli
            $deals[0]['button_status'] = 0;
            // price cash - user point
            $price_cash_point = $deals[0]['deals_voucher_price_cash'] - auth()->user()->balance;
            if ( $price_cash_point <= 0 ) {
            	$deals[0]['price_cash_point'] = 0;
            }else{
            	$deals[0]['price_cash_point'] = $price_cash_point;
            }
            $deals[0]['price_cash_point_pretty'] = MyHelper::requestNumber($deals[0]['price_cash_point'],'_CURRENCY');
            //text konfirmasi pembelian
            if($deals[0]['deals_voucher_price_type']=='free'){
                //voucher free
                $deals[0]['button_text'] = 'Get';
                $payment_message = Setting::where('key', 'payment_messages')->pluck('value_text')->first()??'Are you sure you want to take this voucher?';
                $payment_message = MyHelper::simpleReplace($payment_message,['deals_title'=>$deals[0]['deals_title']]);
            }
            elseif($deals[0]['deals_voucher_price_type']=='point')
            {
                $deals[0]['button_text'] = 'Claim';
                $payment_message = Setting::where('key', 'payment_messages_point')->pluck('value_text')->first()??'Are you going to exchange your %points% for a % deals_title%?';
                $payment_message = MyHelper::simpleReplace($payment_message,['point'=>$deals[0]['deals_voucher_price_point'],'deals_title'=>$deals[0]['deals_title']]);
            }
            else
            {
                $deals[0]['button_text'] = 'Buy';
                $payment_message = Setting::where('key', 'payment_messages_cash')->pluck('value_text')->first()??'Will you buy a %deals_title% at a price of %cash%?';
                $payment_message = MyHelper::simpleReplace($payment_message,['cash'=>$deals[0]['deals_voucher_price_cash'],'deals_title'=>$deals[0]['deals_title']]);
            }
            $payment_success_message = Setting::where('key', 'payment_success_messages')->pluck('value_text')->first()??'Do you want to use this voucher now?';
            $deals[0]['payment_message'] = $payment_message;
            $deals[0]['payment_success_message'] = $payment_success_message;
            if($deals[0]['deals_voucher_price_type']=='free'&&$deals[0]['deals_status']=='available'){
                $deals[0]['button_status']=1;
            }else {
                if($deals[0]['deals_voucher_price_type']=='point'){
                    $deals[0]['button_status']=$deals[0]['deals_voucher_price_point']<=$curBalance?1:0;
                    if($deals[0]['deals_voucher_price_point']>$curBalance){
                        $deals[0]['payment_fail_message'] = Setting::where('key', 'payment_fail_messages')->pluck('value_text')->first()??'Mohon maaf, point anda tidak cukup';
                    }
                }else{
                    if($deals[0]['deals_status']=='available'){
                        $deals[0]['button_status'] = 1;
                    }
                }
            }
        }

        //jika mobile di pagination
        if (!$request->json('web')) {
            //pagination
            if ($request->get('page')) {
                $page = $request->get('page');
            } else {
                $page = 1;
            }

            $resultData = [];
            $paginate   = 10;
            $start      = $paginate * ($page - 1);
            $all        = $paginate * $page;
            $end        = $all;
            $next       = true;

            if ($all > count($deals)) {
                $end = count($deals);
                $next = false;
            }


            for ($i=$start; $i < $end; $i++) {
                $deals[$i]['time_to_end']=strtotime($deals[$i]['deals_end'])-time();
                array_push($resultData, $deals[$i]);
            }

            $result['current_page']  = $page;
            $result['data']          = $resultData;
            $result['total']         = count($resultData);
            $result['next_page_url'] = null;
            if ($next == true) {
                $next_page = (int) $page + 1;
                $result['next_page_url'] = ENV('APP_API_URL') . 'api/deals/list?page=' . $next_page;
            }


            // print_r($deals); exit();
            if(!$result['total']){
                $result=[];
            }

            if(
                $request->json('voucher_type_point') ||
                $request->json('voucher_type_paid') ||
                $request->json('voucher_type_free') ||
                $request->json('id_city') ||
                $request->json('key_free')
            ){
                $resultMessage = 'The Voucher You Are Looking For Is Not Available';
            }else{
                $resultMessage = 'Deals Not Available Yet';
            }
            return response()->json(MyHelper::checkGet($result, $resultMessage));

        }else{
            return response()->json(MyHelper::checkGet($deals));
        }
    }

    /* list of deals that haven't ended yet */
    function listActiveDeals(Request $request){
        $post = $request->json()->all();

        $deals = Deal::where('deals_type','Deals')
        		->where('deals_end', '>', date('Y-m-d H:i:s'))
        		->where('step_complete', '=', 1)
        		->orderBy('updated_at', 'DESC');

        if(isset($post['select'])){
            $deals = $deals->select($post['select']);
        }
        $deals = $deals->get();
        return response()->json(MyHelper::checkGet($deals));
    }

    /* LIST */
    function myDeal(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $deals = DealsUser::with(['deals_voucher.deal'])
        ->where('id_user', $user['id'])
        ->where('id_deals_user', $post['id_deals_user'])
        ->whereNull('redeemed_at')
        ->whereIn('paid_status', ['Completed','Free'])
        ->first();

        return response()->json(MyHelper::checkGet($deals));
    }
    public function filterList($query,$rules,$operator='and'){
        $newRule=[];
        foreach ($rules as $var) {
            $rule=[$var['operator']??'=',$var['parameter']];
            if($rule[0]=='like'){
                $rule[1]='%'.$rule[1].'%';
            }
            $newRule[$var['subject']][]=$rule;
        }
        $where=$operator=='and'?'where':'orWhere';
        $subjects=['deals_title','deals_title','deals_second_title','deals_promo_id_type','deals_promo_id','id_brand','deals_total_voucher','deals_start', 'deals_end', 'deals_publish_start', 'deals_publish_end', 'deals_voucher_start', 'deals_voucher_expired', 'deals_voucher_duration', 'user_limit', 'total_voucher_subscription', 'deals_total_claimed', 'deals_total_redeemed', 'deals_total_used', 'created_at', 'updated_at'];
        foreach ($subjects as $subject) {
            if($rules2=$newRule[$subject]??false){
                foreach ($rules2 as $rule) {
                    $query->$where($subject,$rule[0],$rule[1]);
                }
            }
        }
        if($rules2=$newRule['voucher_code']??false){
            foreach ($rules2 as $rule) {
                $query->{$where.'Has'}('deals_vouchers',function($query) use ($rule){
                    $query->where('deals_vouchers.voucher_code',$rule[0],$rule[1]);
                });
            }
        }
        if($rules2=$newRule['used_by']??false){
            foreach ($rules2 as $rule) {
                $query->{$where.'Has'}('deals_vouchers.deals_voucher_user',function($query) use ($rule){
                    $query->where('phone',$rule[0],$rule[1]);
                });
            }
        }
        if($rules2=$newRule['deals_total_available']??false){
            foreach ($rules2 as $rule) {
                $query->$where(DB::raw('(deals.deals_total_voucher - deals.deals_total_claimed)'),$rule[0],$rule[1]);
            }
        }
        if($rules2=$newRule['id_outlet']??false){
            foreach ($rules2 as $rule) {
                $query->{$where.'Has'}('outlets',function($query) use ($rule){
                    $query->where('outlets.id_outlet',$rule[0],$rule[1]);
                });
            }
        }
        if($rules2=$newRule['voucher_claim_time']??false){
            foreach ($rules2 as $rule) {
                $rule[1]=strtotime($rule[1]);
                $query->{$where.'Has'}('deals_vouchers',function($query) use ($rule){
                    $query->whereHas('deals_user',function($query) use ($rule){
                        $query->where(DB::raw('UNIX_TIMESTAMP(deals_users.claimed_at)'),$rule[0],$rule[1]);
                    });
                });
            }
        }
        if($rules2=$newRule['voucher_redeem_time']??false){
            foreach ($rules2 as $rule) {
                $rule[1]=strtotime($rule[1]);
                $query->{$where.'Has'}('deals_vouchers',function($query) use ($rule){
                    $query->whereHas('deals_user',function($query) use ($rule){
                        $query->where('deals_users.redeemed_at',$rule[0],$rule[1]);
                    });
                });
            }
        }
        if($rules2=$newRule['voucher_used_time']??false){
            foreach ($rules2 as $rule) {
                $rule[1]=strtotime($rule[1]);
                $query->{$where.'Has'}('deals_vouchers',function($query) use ($rule){
                    $query->whereHas('deals_user',function($query) use ($rule){
                        $query->where('deals_users.used_at',$rule[0],$rule[1]);
                    });
                });
            }
        }
    }
    /* UNLIMITED */
    function unlimited($deals)
    {
        $unlimited = array_filter(array_column($deals, "available_voucher"), function ($deals) {
            if ($deals == "*") {
                return $deals;
            }
        });

        return $unlimited;
    }

    function limited($deals)
    {
        $limited = array_filter(array_column($deals, "available_voucher"), function ($deals) {
            if ($deals != "*") {
                return $deals;
            }
        });

        return $limited;
    }

    /* SORT DEALS */
    function highestAvailableVoucher($deals)
    {
        usort($deals, function ($a, $b) {
            return $a['available_voucher'] < $b['available_voucher'];
        });

        return $deals;
    }

    function lowestAvailableVoucher($deals)
    {
        usort($deals, function ($a, $b) {
            return $a['available_voucher'] > $b['available_voucher'];
        });

        return $deals;
    }

    /* INI LIST KOTA */
    function kotacuks($deals, $city = "",$admin=false)
    {
        $timeNow = date('Y-m-d H:i:s');

        foreach ($deals as $key => $value) {
            $markerCity = 0;

            $deals[$key]['outlet_by_city'] = [];

            // set time
            $deals[$key]['time_server'] = $timeNow;

            if (!empty($value['outlets'])) {
                // ambil kotanya dulu
                $kota = array_column($value['outlets'], 'city');
                $kota = array_values(array_map("unserialize", array_unique(array_map("serialize", $kota))));

                // jika ada pencarian kota
                if (!empty($city)) {
                    $cariKota = array_search($city, array_column($kota, 'id_city'));

                    if (is_integer($cariKota)) {
                        $markerCity = 1;
                    }
                }

                foreach ($kota as $k => $v) {
                    if ($v) {

                        $kota[$k]['outlet'] = [];

                        foreach ($value['outlets'] as $outlet) {
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

                $deals[$key]['outlet_by_city'] = $kota;
            }

            // unset($deals[$key]['outlets']);
            // jika ada pencarian kota
            if (!empty($city)) {
                if ($markerCity == 0) {
                    unset($deals[$key]);
                    continue;
                }
            }

            $calc = $value['deals_total_voucher'] - $value['deals_total_claimed'];

            if ($value['deals_voucher_type'] == "Unlimited") {
                $calc = '*';
            }

            if(is_numeric($calc) && $value['deals_total_voucher'] !== 0){
                if($calc||$admin){
                    $deals[$key]['percent_voucher'] = $calc*100/$value['deals_total_voucher'];
                }else{
                    unset($deals[$key]);
                    continue;
                }
            }else{
                $deals[$key]['percent_voucher'] = 100;
            }

            $deals[$key]['show'] = 1;
            $deals[$key]['available_voucher'] = (string) $calc;
            // deals masih ada?
            // print_r($deals[$key]['available_voucher']);
        }

        // print_r($deals); exit();
        $deals = array_values($deals);

        return $deals;
    }

    /* LIST USER */
    function listUserVoucher(Request $request)
    {
    	$post = $request->json()->all();
        $deals = DealsUser::join('deals_vouchers', 'deals_vouchers.id_deals_voucher', '=', 'deals_users.id_deals_voucher');

        if ($request->json('id_deals')) {
            $deals->where('deals_vouchers.id_deals', $request->json('id_deals'));
        }

        if ($request->json('rule')){
             $this->filterUserVoucher($deals,$request->json('rule'),$request->json('operator')??'and');
        }

        $deals = $deals->with([
        			'user',
        			'outlet',
        			'dealVoucher'
        		]);
        $data = $deals->orderBy('claimed_at', "DESC")->paginate(10)->toArray();
        $data['data'] = $deals->paginate(10)
        				->each(function($q){
						    $q->setAppends([
						        'get_transaction'
						    ]);
						})
	        			->toArray();

        return response()->json(MyHelper::checkGet($data));
    }

    /* FILTER LIST USER VOUCHER */
    public function filterUserVoucher($query,$rules,$operator='and'){
        $newRule=[];
        foreach ($rules as $var) {
            $rule=[$var['operator']??'=',$var['parameter']];
            if($rule[0]=='like'){
                $rule[1]='%'.$rule[1].'%';
            }
            $newRule[$var['subject']][]=$rule;
        }

        $where=$operator=='and'?'where':'orWhere';

        if($rules2=$newRule['status']??false){
            foreach ($rules2 as $rule) {

            	if ($rule[1] == 'used')
            	{
	                $query->{$where.'NotNull'}('used_at');
            	}
            	elseif ($rule[1] == 'expired')
            	{
	                $query->$where(function($q) {
	                	$q->whereNotNull('voucher_expired_at')
	                		->whereDate('voucher_expired_at','<',date("Y-m-d H:i:s"));
	                });
            	}
            	elseif ($rule[1] == 'redeemed')
            	{
	                $query->{$where.'NotNull'}('redeemed_at');
            	}
            	else
            	{
	                $query->{$where.'NotNull'}('claimed_at');
            	}
            }
        }
        if($rules2=$newRule['used_by']??false){
            foreach ($rules2 as $rule) {
                $query->{$where.'Has'}('user',function($query) use ($rule){
                    $query->where('phone',$rule[0],$rule[1]);
                });
            }
        }
        if($rules2=$newRule['claim_date']??false){
            foreach ($rules2 as $rule) {
                $query->{$where.'Date'}('claimed_at',$rule[0],date("Y-m-d H:i:s", strtotime($rule[1])));
            }
        }
        if($rules2=$newRule['id_outlet']??false){
            foreach ($rules2 as $rule) {
                $query->{$where.'Has'}('outlet',function($query) use ($rule){
                    $query->where('outlets.id_outlet',$rule[0],$rule[1]);
                });
            }
        }
    }

    /* LIST VOUCHER */
    function listVoucher(Request $request)
    {

    	if ($request->select) {
        	$deals = DealsVoucher::select($request->select);
    	}else{
        	$deals = DealsVoucher::select('*');
    	}

        if ($request->json('id_deals')) {
            $deals->where('id_deals', $request->json('id_deals'));
        }

        if ($request->is_all) {
        	$deals = $deals->get();
        }else{
        	$deals = $deals->paginate(10);
        }

        return response()->json(MyHelper::checkGet($deals));
    }

    /* UPDATE */
    function update($id, $data)
    {
        $data = $this->checkInputan($data);

        $deals = Deal::find($id);
        $data['step_complete'] = 0;
        $data['last_updated_by'] = auth()->user()->id;

        if ($deals['product_type'] != $data['product_type'] || $data['is_online'] == 0) {
        	app($this->promo_campaign)->deleteAllProductRule('deals', $id);
        }

        if ( isset($deals['id_brand']) && isset($data['id_brand']) && ($deals['id_brand'] != $data['id_brand']) ) {
        	app($this->promo_campaign)->deleteAllProductRule('deals', $id);
        }

        if ($data['deals_voucher_type'] != 'List Vouchers') {
        	DealsVoucher::where('id_deals', $id)->delete();
        }

        if ( !empty($deals['deals_total_claimed']) ) {
        	return false;
        }
        // error
        if (isset($data['error'])) {
            unset($data['error']);
            return response()->json($data);
        }

        // delete old images
        if (isset($data['deals_image'])) {
            $this->deleteImage($id);
        }

        if (isset($data['id_outlet'])) {

            // DELETE
            $this->deleteOutlet($id);

            // SAVE
            if($data['is_all_outlet'] == 0){
                $saveOutlet = $this->saveOutlet($deals, $data['id_outlet']);
            }
            unset($data['id_outlet']);
        }

        $save = Deal::where('id_deals', $id)->update($data);

        return $save;
    }

    /* DELETE IMAGE */
    function deleteImage($id)
    {
        $cekImage = Deal::where('id_deals', $id)->get()->first();

        if (!empty($cekImage)) {
            if (!empty($cekImage->deals_image)) {
                $delete = MyHelper::deletePhoto($cekImage->deals_image);
            }
        }
        return true;
    }

    /* UPDATE REQUEST */
    function updateReq(Update $request)
    {

        DB::beginTransaction();
        if ($request->json('id_deals')) {
        	$save = $this->update($request->json('id_deals'), $request->json()->all());

	        if ($save) {
	            DB::commit();
	            $dt = '';
	            switch (strtolower($request->json('deals_type'))){
	                case 'deals':
	                    $dt = 'Deals';
	                    break;
	                case 'hidden':
	                    $dt = 'Inject Voucher';
	                    break;
	                case 'welcomevoucher':
	                    $dt = 'Welcome Voucher';
	                    break;
	            }
	            $deals = Deal::where('id_deals',$request->json('id_deals'))->first()->toArray();
	            $send = app($this->autocrm)->SendAutoCRM('Update '.$dt, $request->user()->phone, [
	                'voucher_type' => $deals['deals_voucher_type']?:'',
	                'promo_id_type' => $deals['deals_promo_id_type']?:'',
	                'promo_id' => $deals['deals_promo_id']?:'',
	                'detail' => view('deals::emails.detail',['detail'=>$deals])->render()
	            ]+$deals,null,true);
		        return response()->json(MyHelper::checkUpdate($save));
	        } else {
	            DB::rollBack();
	        	return response()->json(['status' => 'fail','messages' => ['Cannot update deals because someone has already claimed a voucher']]);
	        }
        }
        else{
        	$save = $this->updatePromotionDeals($request->json('id_deals_promotion_template'), $request->json()->all());

        	if ($save) {
	            DB::commit();
		        return response()->json(MyHelper::checkUpdate($save));
	        } else {
	            DB::rollBack();
	        	return response()->json(['status' => 'fail','messages' => ['Update Promotion Deals Failed']]);
	        }
        }


    }

    /* DELETE */
    function delete($id)
    {
        // delete outlet
        DealsOutlet::where('id_deals', $id)->delete();

        $delete = Deal::where('id_deals', $id)->delete();
        return $delete;
    }

    /* DELETE REQUEST */
    function deleteReq(Delete $request)
    {
        DB::beginTransaction();

        // check spin the wheel
        if ($request->json('deals_type') !== null && $request->json('deals_type') == "Spin") {
            $spin = SpinTheWheel::where('id_deals', $request->json('id_deals'))->first();
            if ($spin != null) {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Item already used in Spin The Wheel Setting.']
                ]);
            }
        }

        $check = $this->checkDelete($request->json('id_deals'));
        if ($check) {
            // delete image first
            $this->deleteImage($request->json('id_deals'));

            $delete = $this->delete($request->json('id_deals'));

            if ($delete) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return response()->json(MyHelper::checkDelete($delete));
        } else {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Deal already used.']
            ]);
        }
    }

    /* CHECK DELETE */
    function checkDelete($id)
    {
        $database = [
            'deals_vouchers',
            'deals_payment_manuals',
            'deals_payment_midtrans',
        ];

        foreach ($database as $val) {
            // check apakah ada atau nggak tablenya
            if (Schema::hasTable($val)) {
                $cek = DB::table($val);

                if ($val == "deals_vouchers") {
                    $cek->where('deals_voucher_status', '=', 'Sent');
                }

                $cek = $cek->where('id_deals', $id)->first();

                if (!empty($cek)) {
                    return false;
                }
            }
        }

        return true;
    }

    /* OUTLET */
    function saveOutlet($deals, $id_outlet = [])
    {
        $id_deals=$deals->id_deals;
        $id_brand=$deals->id_brand;
        $dataOutlet = [];

        /*If select all outlet, not save to table deals outlet*/
        foreach ($id_outlet as $value) {
            array_push($dataOutlet, [
                'id_outlet' => $value,
                'id_deals'  => $id_deals
            ]);
        }

        if (!empty($dataOutlet)) {
            $save = DealsOutlet::insert($dataOutlet);

            return $save;
        } else {
            return false;
        }

        return true;
    }

    /* DELETE OUTLET */
    function deleteOutlet($id_deals)
    {
        $delete = DealsOutlet::where('id_deals', $id_deals)->delete();

        return $delete;
    }

    /*Welcome Voucher*/
    function listDealsWelcomeVoucher(Request $request){
        $configUseBrand = Configs::where('config_name', 'use brand')->first();

        if($configUseBrand['is_active']){
            $getDeals = Deal::join('brands', 'brands.id_brand', 'deals.id_brand')
                ->where('deals_type','WelcomeVoucher')
                ->select('deals.*','brands.name_brand')
                ->get()->toArray();
        }else{
            $getDeals = Deal::where('deals_type','WelcomeVoucher')
                ->select('deals.*')
                ->get()->toArray();
        }


        $result = [
            'status' => 'success',
            'result' => $getDeals
        ];
        return response()->json($result);
    }

    function welcomeVoucherSetting(Request $request){
        $setting = Setting::where('key', 'welcome_voucher_setting')->first();
        $configUseBrand = Configs::where('config_name', 'use brand')->first();

        if($configUseBrand['is_active']){
            $getDeals = DealTotal::join('deals', 'deals.id_deals', 'deals_total.id_deals')
                ->join('brands', 'brands.id_brand', 'deals.id_brand')
                ->select('deals.*','deals_total.deals_total','brands.name_brand')
                ->get()->toArray();
        }else{
            $getDeals = DealTotal::join('deals', 'deals.id_deals', 'deals_total.id_deals')
                ->select('deals.*','deals_total.deals_total')
                ->get()->toArray();
        }


        $result = [
            'status' => 'success',
            'data' => [
                'setting' => $setting,
                'deals' => $getDeals
            ]
        ];
        return response()->json($result);
    }

    function welcomeVoucherSettingUpdate(Request $request){
        $post = $request->json()->all();

        $deleteDealsTotal = DB::table('deals_total')->delete();//Delete all data from tabel deals total

        //insert data
        $arrInsert = [];
        $list_id = $post['list_deals_id'];
        $list_deals_total = $post['list_deals_total'];
        $count = count($list_id);

        for($i=0;$i<$count;$i++){
            $data = [
                'id_deals' => $list_id[$i],
                'deals_total' => $list_deals_total[$i],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            array_push($arrInsert,$data);
        }

        $insert = DealTotal::insert($arrInsert);
        if($insert){
            $result = [
                'status' => 'success'
            ];
        }else{
            $result = [
                'status' => 'fail'
            ];
        }

        return response()->json($result);
    }

    function welcomeVoucherSettingUpdateStatus(Request $request){
        $post = $request->json()->all();
        $status = $post['status'];
        $updateStatus = Setting::where('key', 'welcome_voucher_setting')->update(['value' => $status]);

        return response()->json(MyHelper::checkUpdate($updateStatus));
    }

    function injectWelcomeVoucher($user, $phone){
        $getDeals = DealTotal::join('deals', 'deals.id_deals', '=', 'deals_total.id_deals')
            ->select('deals.*','deals_total.deals_total')->get();
        $count = 0;
        foreach ($getDeals as $val){
            for($i=0;$i<$val['deals_total'];$i++){
                $generateVoucher = app($this->hidden_deals)->autoClaimedAssign($val, $user, $val['deals_total']);
                $count++;
            }
            $dataDeals = Deal::where('id_deals', $val['id_deals'])->first();
            app($this->deals_claim)->updateDeals($dataDeals);
        }

        $autocrm = app($this->autocrm)->SendAutoCRM('Receive Welcome Voucher', $phone,
            [
                'count_voucher'      => (string)$count
            ]
        );
        return true;
    }

    public function detail(DetailDealsRequest $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $deals = $this->getDealsData($post['id_deals'], $post['step'], $post['deals_type']);

        if (isset($deals)) {
            $deals = $deals->toArray();
        }else{
            $deals = false;
        }

        if ($deals) {
            $result = [
                'status'  => 'success',
                'result'  => $deals
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'messages'  => ['Deals Not Found']
            ];
        }

        return response()->json($result);
    }

    function getDealsData($id_deals, $step, $deals_type='Deals')
    {
    	$post['id_deals'] = $id_deals;
    	$post['step'] = $step;
    	$post['deals_type'] = $deals_type;

    	if ($deals_type == 'Promotion' || $deals_type == 'deals_promotion') {
    		$deals = DealsPromotionTemplate::where('id_deals_promotion_template', '=', $post['id_deals']);
    		$table = 'deals_promotion';
    	}else{
    		if ($deals_type == 'promotion-deals') {
    			$post['deals_type'] = 'promotion';
    		}
    		$deals = Deal::where('id_deals', '=', $post['id_deals'])->where('deals_type','=',$post['deals_type']);
    		$table = 'deals';
    	}

        if ( ($post['step'] == 1 || $post['step'] == 'all') && ($deals_type != 'Promotion') ){
			$deals = $deals->with(['outlets']);
        }

        if ($post['step'] == 2 || $post['step'] == 'all') {
			$deals = $deals->with([
                $table.'_product_discount',
                $table.'_product_discount_rules',
                $table.'_tier_discount_product',
                $table.'_tier_discount_rules',
                $table.'_buyxgety_product_requirement',
                $table.'_buyxgety_rules.product'
            ]);
        }

        if ($post['step'] == 3 || $post['step'] == 'all') {
			$deals = $deals->with([$table.'_content.'.$table.'_content_details']);
        }

        if ($post['step'] == 'all') {
			$deals = $deals->with(['created_by_user']);
        }

        $deals = $deals->first();

        return $deals;
    }

    public function updateContent(UpdateContentRequest $request)
    {
    	$post = $request->json()->all();

    	db::beginTransaction();

    	if ($post['deals_type'] != 'Promotion') {
    		$source = 'deals';
	    	$check = Deal::where('id_deals','=',$post['id_deals'])->first();
	    	if (!empty($check['deals_total_claimed']) ) {
				return [
	                'status'  => 'fail',
	                'messages' => 'Cannot update deals because someone has already claimed a voucher'
	            ];
			}
    	}
    	else
    	{
    		$source = 'deals_promotion';
    		$check = DealsPromotionTemplate::where('id_deals_promotion_template','=',$post['id_deals'])->first();
    	}

    	if ( empty($check) ) {
			return [
                'status'  => 'fail',
                'messages' => 'Deals not found'
            ];
		}

    	$update = app($this->subscription)->createOrUpdateContent($post, $source);

    	if ($update)
    	{
    		if ($post['deals_type'] != 'Promotion') {
				$update = Deal::where('id_deals','=',$post['id_deals'])->update(['deals_description' => $post['deals_description'], 'step_complete' => 0, 'last_updated_by' => auth()->user()->id]);
    		}else{
				$update = DealsPromotionTemplate::where('id_deals_promotion_template','=',$post['id_deals'])->update(['deals_description' => $post['deals_description'], 'step_complete' => 0, 'last_updated_by' => auth()->user()->id]);
    		}

            if ($update)
			{
		        DB::commit();
		    }
		    else
		    {
		        DB::rollBack();
		        return  response()->json([
		            'status'   => 'fail',
		            'messages' => 'Update Deals failed'
		        ]);
		    }
        }
        else
        {
            DB::rollBack();
            return  response()->json([
                'status'   => 'fail',
                'messages' => 'Update Deals failed'
            ]);
        }

         return response()->json(MyHelper::checkUpdate($update));
    }

    public function updateComplete(UpdateComplete $request)
    {
    	$post = $request->json()->all();

    	$check = $this->checkComplete($post['id_deals'], $step, $errors, $post['deals_type']);

		if ($check)
		{
			if ($post['deals_type'] == 'Promotion' || $post['deals_type'] == 'deals_promotion') {
				$update = DealsPromotionTemplate::where('id_deals_promotion_template','=',$post['id_deals'])->update(['step_complete' => 1, 'last_updated_by' => auth()->user()->id]);
			}else{
				$update = Deal::where('id_deals','=',$post['id_deals'])->update(['step_complete' => 1, 'last_updated_by' => auth()->user()->id]);
			}

			if ($update)
			{
				return ['status' => 'success'];
			}else{
				return ['status'=> 'fail', 'messages' => ['Update deals failed']];
			}
		}
		else
		{
			return [
				'status'	=> 'fail',
				'step' 		=> $step,
				'messages' 	=> [$errors]
			];
		}
    }

    public function checkComplete($id, &$step, &$errors, $promo_type)
    {
    	$deals = $this->getDealsData($id, 'all', $promo_type);
    	if (!$deals) {
    		$errors = 'Deals not found';
    		return false;
    	}

    	if ($promo_type == 'deals_promotion') {
    		return app($this->promotion_deals)->checkComplete($deals, $step, $errors);
    	}

    	$deals = $deals->toArray();
    	if ( $deals['is_online'] == 1)
    	{
	    	if ( empty($deals['deals_product_discount_rules']) && empty($deals['deals_tier_discount_rules']) && empty($deals['deals_buyxgety_rules']) )
	    	{
	    		$step = 2;
	    		$errors = 'Deals not complete';
	    		return false;
	    	}
    	}

    	if ( $deals['is_offline'] == 1)
    	{
    		if ( empty($deals['deals_promo_id_type']) && empty($deals['deals_promo_id']) )
	    	{
	    		$step = 2;
	    		$errors = 'Deals not complete';
	    		return false;
	    	}
    	}

    	if ( empty($deals['deals_content']) || empty($deals['deals_description'])) {
    		$step = 3;
	    	$errors = 'Deals not complete';
    		return false;
    	}

    	return true;
    }

    public function cronRemoveUserLimit(Request $request)
    {
		$now   = date('Y-m-d H:i:s');

        $deals = Deal::where('deals_end', '<=', $now)->whereHas('deals_user_limits')->get();

        if (empty($deals)) 
        {
            return response()->json(['empty']);
        }

        foreach ($deals as $key => $value) 
        {
        	db::beginTransaction();

        	$delete = DealsUserLimit::where('id_deals',$value->id_deals)->delete();

        	if (!$delete) {
        		db::rollBack();
        		continue;
        	}

            db::commit();
        }

        return response()->json(['status' => 'success']);
    }

    public function export(DetailDealsRequest $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $deals = $this->getDealsData($post['id_deals'], $post['step'], $post['deals_type']);

        if (isset($deals)) {
        	if (!empty($post['list_voucher'])) {
        		$deals->load('deals_vouchers');
        	}
            $deals = $deals->toArray();
        }else{
            $deals = false;
        }

        $data['rule'] = [];
        $data['outlet'] = [];
        if ($deals) 
        {
	        $data['outlet'] = [];
	        foreach ($deals['outlets'] as $key => $value) {
	        	$data['outlet'][] = [
	        		'outlet_code' => $value['outlet_code'],
	        		'outlet_name' => $value['outlet_name']
	        	];
	        }
	        if ($data['outlet'] == []) {
	        	unset($data['outlet']);
	        }

	        $data['content'] = [];
	        $i = 0;
	        foreach ($deals['deals_content'] as $key => $value) {
	        	$data['content'][$i] = [
	        		'title' => $value['title'],
	        		'visibility' => $value['is_active'] ? 'visible' : 'hidden'
	        	];

	        	foreach ($value['deals_content_details'] as $key2 => $value2) {
	        		if ($key == 0) {
	        			$data['content'][$i][$key2] = $value2['content'];
	        		}
	        		else{
	        			$data['content'][$i][$key2] = $value2['content'];
	        		}
	        	}
	        	$i++;
	        }

	        switch ($deals['promo_type']) 
	        {
	        	case 'Product discount':
	        		
	        		$deals['is_all_product'] = $deals['deals_product_discount_rules']['is_all_product'];
	        		$deals['product_discount_type'] = $deals['deals_product_discount_rules']['discount_type'];
	        		$deals['product_discount_value'] = $deals['deals_product_discount_rules']['discount_value'];
	        		$deals['product_discount_max_qty'] = $deals['deals_product_discount_rules']['max_product'];
	        		$deals['product_discount_max_discount'] = $deals['deals_product_discount_rules']['max_percent_discount'];

	        		$temp_product = [];
	        		foreach ($deals['deals_product_discount'] as $key => $value) {
	        			$temp_product[] = [
	        				'product_code' => $value['product']['product_code']??$value['product_group']['product_group_code']??'', 
	        				'product_name' => $value['product']['product_name']??$value['product_group']['product_group_name']??''
	        			];
	        		}

	        		$data['detail_rule_product_discount'] = $temp_product;
	        		if ($data['detail_rule_product_discount'] == [] ) {
	        			unset($data['detail_rule_product_discount']);
	        		}
	        		break;
	        	
	        	case 'Tier discount':

	        		$deals['tier_discount_product_code'] = $deals['deals_tier_discount_product']['product']['product_code']??$deals['deals_tier_discount_product']['product_group']['product_group_code']??'';
	        		$deals['tier_discount_product_name'] = $deals['deals_tier_discount_product']['product']['product_name']??$deals['deals_tier_discount_product']['product_group']['product_group_name']??'';

	        		$data['detail_rule_tier_discount'] = $deals['deals_tier_discount_rules'];
	        		foreach ($data['detail_rule_tier_discount'] as $key => $value) {
	        			unset(
	        				$data['detail_rule_tier_discount'][$key]['id_deals_tier_discount_rule'],
	        				$data['detail_rule_tier_discount'][$key]['id_deals'],
	        				$data['detail_rule_tier_discount'][$key]['created_at'],
	        				$data['detail_rule_tier_discount'][$key]['updated_at']
	        			);
	        		}
	        		if ($data['detail_rule_tier_discount'] == [] ) {
	        			unset($data['detail_rule_tier_discount']);
	        		}

	        		break;
	        	
	        	case 'Buy X Get Y':
					
					$deals['buy_x_get_y_discount_product_code'] = $deals['deals_buyxgety_product_requirement']['product']['product_code']??$deals['deals_buyxgety_product_requirement']['product_group']['product_group_code']??'';
	        		$deals['buy_x_get_y_discount_product_name'] = $deals['deals_buyxgety_product_requirement']['product']['product_name']??$deals['deals_buyxgety_product_requirement']['product_group']['product_group_name']??'';

	        		$data['detail_rule_buyxgety_discount'] = [];
	        		foreach ($deals['deals_buyxgety_rules'] as $key => $value) {
	        			$data['detail_rule_buyxgety_discount'][] = [
	        				'min_qty'	=> $value['min_qty_requirement'],
	        				'max_qty' 	=> $value['max_qty_requirement'],
	        				'discount_type'		=> $value['discount_type'],
	        				'discount_value'	=> $value['discount_value'],
	        				'max_discount' 		=> $value['max_percent_discount'],
	        				'benefit_product_code' => $value['product']['product_code']??$value['product_group']['product_group_code']??'',
	        				'benefit_product_name' => $value['product']['product_name']??$value['product_group']['product_group_name']??'',
	        				'benefit_product_qty'  => $value['benefit_qty']
	        			];
	        		}

	        		if ($data['detail_rule_buyxgety_discount'] == [] ) {
	        			unset($data['detail_rule_buyxgety_discount']);
	        		}
	        		break;
	        	
	        	default:
	        		$data['detail_rule'] = [];
	        		break;
	        }

	        if (!empty($post['list_voucher'])) {
	        	$temp_voucher = [];
	        	foreach ($deals['deals_vouchers'] as $key => $value) {
	        		$temp_voucher[]['voucher_code'] = $value['voucher_code'];
	        	}

	        	if (!empty($temp_voucher)) {
        			$data['voucher'] = $temp_voucher;
	        	}
        	}

	        unset(
	        	$deals['id_deals'],
	        	$deals['created_by'],
	        	$deals['last_updated_by'],
	        	$deals['created_by_user'],
	        	$deals['outlets'],
	        	$deals['deals_product_discount_rules'],
	        	$deals['deals_product_discount'],
	        	$deals['deals_tier_discount_rules'],
	        	$deals['deals_tier_discount_product'],
	        	$deals['deals_buyxgety_rules'],
	        	$deals['deals_buyxgety_product_requirement'],
	        	$deals['deals_status'],
	        	$deals['deals_voucher_price_type'],
	        	$deals['deals_voucher_price_pretty'],
	        	$deals['url_webview'],
	        	$deals['id_produk'],
	        	$deals['id_brand'],
	        	$deals['total_voucher_subscription'],
	        	$deals['deals_total_claimed'],
	        	$deals['deals_total_redeemed'],
	        	$deals['deals_total_used'],
	        	$deals['step_complete'],
	        	$deals['created_at'],
	        	$deals['updated_at'],
	        	$deals['deals_vouchers'],
	        	$deals['deals_content'],
	        	$deals['deals_type']
	        );

	        $temp_deals = [];

	        $deals = app($this->promo_export_import)->checkDealsInput($deals, 'export');
	        $deals = app($this->promo_export_import)->convertDealsInput($deals);

	        foreach ($deals as $key => $value) {
	        	$temp_deals[] = [$key, $value];
	        }
	        $data['rule'] = $temp_deals;

            $result = [
                'status'  => 'success',
                'result'  => $data
            ];
        } 
        else 
        {
            $result = [
                'status'  => 'fail',
                'messages'  => ['Deals Not Found']
            ];
        }

        return response()->json($result);
    }

    public function import(ImportDealsRequest $request)
    {
     	$post = $request->json()->all();
    	$deals = $post['data']['rule'];
    	$image_path = 'img/deals/';
    	$warning_image_path = 'img/deals/warning-image/';
    	$errors = [];
    	$warnings = [];

    	$deals = app($this->promo_export_import)->convertDealsInput($deals, 'import');
    	$deals = app($this->promo_export_import)->checkDealsInput($deals, 'import');
    	$post['data']['rule'] = $deals;
    	db::beginTransaction();

    	// save deals
    	unset(
    		$deals['deals_start'], 
    		$deals['deals_end'], 
    		$deals['deals_publish_start'],
    		$deals['deals_publish_end'],
    		$deals['deals_image']

    	);
    	$deals['deals_type'] 			= $post['deals_type'];
    	$deals['deals_total_claimed'] 	= 0;
    	$deals['deals_total_redeemed'] 	= 0;
    	$deals['deals_total_used'] 		= 0;
    	$deals['id_brand'] 				= Brand::select('id_brand')->first()['id_brand']??null;
    	$deals['user_limit'] 			= $deals['user_limit']??0;
        $deals['deals_voucher_start'] 	= !empty($post['deals_voucher_start']) ? date('Y-m-d H:i:s', strtotime($post['deals_voucher_start'])) : null;
        $deals['deals_voucher_expired'] = !empty($post['deals_voucher_expired']) ? date('Y-m-d H:i:s', strtotime($post['deals_voucher_expired'])) : null;
        $deals['deals_voucher_duration'] = $post['deals_voucher_duration']??null;
        $deals['created_by'] 			= auth()->user()->id;
        $deals['last_updated_by'] 		= auth()->user()->id;

        if ($post['deals_type'] == 'Deals') {
        	!empty($post['deals_start']) 	? $deals['deals_start'] = date('Y-m-d H:i:s', strtotime($post['deals_start'])) : $errors[] = 'Deals start date is required';
	        !empty($post['deals_end']) 		? $deals['deals_end'] 	= date('Y-m-d H:i:s', strtotime($post['deals_end'])) : $errors[] = 'Deals end date is required';
	        !empty($post['deals_publish_start']) ? $deals['deals_publish_start'] = date('Y-m-d H:i:s', strtotime($post['deals_publish_start'])) : $errors[] = 'Deals publish start date is required';
	        !empty($post['deals_publish_end']) ? $deals['deals_publish_end'] = date('Y-m-d H:i:s', strtotime($post['deals_publish_end'])) : $errors[] = 'Deals publish end date is required';
        }

    	if (isset($deals['deals_voucher_type'])) {
            if ($deals['deals_voucher_type'] == 'Unlimited') {
            	$deals['deals_total_voucher'] = 0;
            }

            if ($deals['deals_type'] == 'Promotion')
            {
	            if($deals['deals_voucher_type'] == 'List Vouchers'){
					$deals['deals_list_voucher'] = str_replace("\r\n", ',', $deals['voucher_code']);
				}else{
					$deals['deals_list_voucher'] = null;
				}
            }

            if($deals['deals_voucher_type'] == 'List Vouchers'){
            	$deals['deals_total_voucher'] = 0;
            }
        }

		$deals['deals_image'] = $this->uploadImageFromURL($deals['url_deals_image'], $image_path);
		if (empty($deals['deals_image'])) {
			$warnings[] = 'Deals Image url\'s invalid';
		}

		$deals['deals_warning_image'] = $this->uploadImageFromURL($deals['url_deals_warning_image'], $warning_image_path, 'warning');

		if (!empty($deals['url_deals_warning_image']) && empty($deals['deals_warning_image'])) {
			$warnings[] = 'Deals warning Image url\'s invalid';
		}
		$create = Deal::create($deals);
		
		if (!$create) {
			db::rollback();
        	return ['status' => 'fail', 'messages' => ['Create deals failed']];
		}

		// save content & detail content
		foreach ($post['data']['content'] as $key => $value) {
			$content = [
				'id_deals' => $create['id_deals'],
				'title' => $value['title'],
				'order' => $key,
				'is_active' => ($value['visibility']??false) == 'visible' ? 1 : 0,
			];

			$saveContent = DealsContent::create($content);
			unset($value['title'], $value['visibility']);
			$i = 1;
			foreach ($value as $key2 => $value2) {
				if (!empty($value2)) {
					$content_detail[$i] = [
						'id_deals_content' => $saveContent['id_deals_content'],
						'content' => $value2,
						'order' => $i,
						'created_at' => date('Y-m-d H:i:s'),
	            		'updated_at' => date('Y-m-d H:i:s')
					];
					$i++;
				}
			}
			if (!empty($content_detail)) {
				$saveContentDetail = DealsContentDetail::insert($content_detail);
			}
		}

		// save outlet
		if ( !empty($post['data']['outlet']) ) 
		{
			$outletCode = [];
			$outletCodeName = [];
			$id_outlet = [];
			foreach ($post['data']['outlet']??[] as $key => $value) {
				$outletCode[] = $value['outlet_code'];
				$outletCodeName[$value['outlet_code']] = $value['outlet_name'];
			}

			$outlet = Outlet::whereIn('outlet_code', $outletCode)->select('id_outlet', 'outlet_code')->get();

			foreach ($outlet as $key => $value) {
				$id_outlet[] = $value['id_outlet'];
				unset($outletCodeName[$value['outlet_code']]);
			}
			$saveOutlet = $this->saveOutlet($create, $id_outlet);

			foreach ($outletCodeName as $key => $value) {
				$errors[] = 'Outlet '.$key.' - '.$value.' not found';
			}
		}

    	switch ($deals['promo_type']) 
        {
        	case 'Product discount':
        		$rule['id_deals'] = $create['id_deals'];
        		$rule['is_all_product'] = $deals['is_all_product'];
        		$rule['discount_type'] 	= $deals['product_discount_type'];
        		$rule['discount_value'] = $deals['product_discount_value'];
        		$rule['max_product'] 	= $deals['product_discount_max_qty'];
        		$rule['max_percent_discount'] = $deals['product_discount_max_discount'];
        		$saveRule = DealsProductDiscountRule::create($rule);

        		if (!empty($post['data']['detail_rule_product_discount'])) 
				{
					$ruleBenefit = [];
					$ruleProductCode = [];
					$ruleProductCodeName = [];
					foreach ( $post['data']['detail_rule_product_discount']??[] as $key => $value ) {
						$ruleProductCode[] = $value['product_code'];
						$ruleProductCodeName[$value['product_code']] = $value['product_name'];
					}

					if ($create['product_type'] == 'single') {
						$ruleProduct = Product::whereIn('product_code', $ruleProductCode)->select('id_product', 'product_code')->get();
					}else{					
						$ruleProduct = ProductGroup::where('product_group_code', $ruleProductCode)->select('id_product_group', 'product_group_code')->get();
					}

					foreach ($ruleProduct as $key => $value) {
						$ruleProductId[$value['product_code']??$value['product_group_code']] = $value['id_product']??$value['product_group_code'];
						unset($ruleProductCodeName[$value['product_code']??$value['product_group_code']]);
					}

	        		foreach ( $post['data']['detail_rule_product_discount']??[] as $key => $value ) {

	        			if ( isset($ruleProductCodeName[$value['product_code']]) ) {
	        				continue;
	        			}

	        			$ruleBenefit[] = [
	        				'id_deals'				=> $create['id_deals'],
	        				'product_type'			=> $create['product_type'],
	        				'id_product' 			=> $ruleProductId[$value['product_code']],
	        				'created_at' 			=> date('Y-m-d H:i:s'),
	            			'updated_at' 			=> date('Y-m-d H:i:s')
	        			];
	        		}

					$saveRuleBenefit = DealsProductDiscount::insert($ruleBenefit);

					foreach ($ruleProductCodeName as $key => $value) {
						$errors[] = 'Product '.$key.' - '.$value.' not found';
					}
				}
        		break;
        	
        	case 'Tier discount':

				$rule['id_deals'] = $create['id_deals'];
				$rule['product_type'] = $create['product_type'];

				if ($create['product_type'] == 'single') {
					$rule['id_product'] = Product::where('product_code', $post['data']['rule']['tier_discount_product_code'])->select('id_product')->first()['id_product']??null;
				}else{					
					$rule['id_product'] = ProductGroup::where('product_group_code', $post['data']['rule']['tier_discount_product_code'])->select('id_product_group')->first()['id_product_group']??null;
				}

				if (empty($rule['id_product'])) {
					$errors[] = 'Product '.$post['data']['rule']['tier_discount_product_code'].' - '.$post['data']['rule']['tier_discount_product_name'].' not found';
					break;
				}

				$save = DealsTierDiscountProduct::create($rule);
        		foreach ($post['data']['detail_rule_tier_discount'] as $key => $value) {

        			$ruleBenefit[] = [
        				'id_deals'				=> $create['id_deals'],
        				'min_qty'				=> $value['min_qty'],
        				'max_qty'				=> $value['max_qty'],
        				'discount_type'			=> $value['discount_type'],
        				'discount_value'		=> $value['discount_value'],
        				'max_percent_discount'	=> $value['max_percent_discount'],
        				'created_at' 			=> date('Y-m-d H:i:s'),
	            		'updated_at' 			=> date('Y-m-d H:i:s')
        			];
        		}
        		
        		$saveRuleBenefit = DealsTierDiscountRule::insert($ruleBenefit);
        		break;
        	
        	case 'Buy X Get Y':

				$rule['id_deals'] = $create['id_deals'];
				$rule['product_type'] = $create['product_type'];

				if ($create['product_type'] == 'single') {
					$rule['id_product'] = Product::where('product_code', $post['data']['rule']['buy_x_get_y_discount_product_code'])->select('id_product')->first()['id_product']??null;
				}else{					
					$rule['id_product'] = ProductGroup::where('product_group_code', $post['data']['rule']['buy_x_get_y_discount_product_code'])->select('id_product_group')->first()['id_product_group']??null;
				}

				if (empty($rule['id_product'])) {
					$errors[] = 'Product '.$post['data']['rule']['buy_x_get_y_discount_product_code'].' - '.$post['data']['rule']['buy_x_get_y_discount_product_name'].' not found';
					break;
				}

				$save = DealsBuyxgetyProductRequirement::create($rule);

				if (!empty($post['data']['detail_rule_buyxgety_discount'])) 
				{
					$ruleBenefit = [];
					$ruleProductCode = [];
					$ruleProductCodeName = [];
					foreach ( $post['data']['detail_rule_buyxgety_discount']??[] as $key => $value ) {
						$ruleProductCode[] = $value['benefit_product_code'];
						$ruleProductCodeName[$value['benefit_product_code']] = $value['benefit_product_name'];
					}

					$ruleProduct = Product::whereIn('product_code', $ruleProductCode)->select('id_product','product_code')->get();

					foreach ($ruleProduct as $key => $value) {
						$ruleProductId[$value['product_code']] = $value['id_product'];
						unset($ruleProductCodeName[$value['product_code']]);
					}

	        		foreach ( $post['data']['detail_rule_buyxgety_discount']??[] as $key => $value ) {

	        			if ( isset($ruleProductCodeName[$value['benefit_product_code']]) ) {
	        				continue;
	        			}

	        			$ruleBenefit[] = [
	        				'id_deals'				=> $create['id_deals'],
	        				'min_qty_requirement'	=> $value['min_qty'],
	        				'max_qty_requirement' 	=> $value['max_qty'],
	        				'discount_type'			=> $value['discount_type'],
	        				'discount_value'		=> $value['discount_value'],
	        				'max_percent_discount' 	=> $value['max_discount'],
	        				'benefit_id_product' 	=> $ruleProductId[$value['benefit_product_code']],
	        				'benefit_qty'  			=> $value['benefit_product_qty'],
	        				'created_at' 			=> date('Y-m-d H:i:s'),
	            			'updated_at' 			=> date('Y-m-d H:i:s')
	        			];
	        		}

					$saveRuleBenefit = DealsBuyxgetyRule::insert($ruleBenefit);

					foreach ($ruleProductCodeName as $key => $value) {
						$errors[] = 'Product '.$key.' - '.$value.' not found';
					}
				}
        		break;
        	
        	default:
        		$errors[] = 'Deals rules not found';
        		break;
        }

        if ( !empty($post['data']['voucher']) ) {
        	$voucher = array_column($post['data']['voucher'], 'voucher_code');
        	
        	$strVoucher = array_map(
				function($value) { return (string) strtoupper($value); },
				$voucher
			);
        	$voucher_new = DealsVoucher::whereIn('voucher_code', $strVoucher)->get()->toArray();
        	$voucher_new = array_column($voucher_new, 'voucher_code');

        	$voucher_diff = array_diff($strVoucher,$voucher_new);
        	$voucher_same = array_intersect($voucher_new, $strVoucher);


            if (empty($voucher_diff)) 
            {
            	$warnings[] = 'No vouchers imported';
            }
            else
            {
	        	$dataVoucher = [];
	        	foreach ($voucher_diff as $value) {
	                array_push($dataVoucher, [
	                    'id_deals'             => $create['id_deals'],
	                    'voucher_code'         => strtoupper($value),
	                    'deals_voucher_status' => 'Available',
	                    'created_at'           => date('Y-m-d H:i:s'),
	                    'updated_at'           => date('Y-m-d H:i:s')
	                ]);
	            }

	            $saveVoucher = DealsVoucher::insert($dataVoucher);
	            $updateDeals = Deal::where('id_deals', $create['id_deals'])->update(['deals_total_voucher' => count($voucher_diff)]);
            }

        	foreach ($voucher_same as $key => $value) {
        		$warnings[] = 'Voucher '.$value.' already exists';
        	}
        }
        elseif ($create['deals_voucher_type'] == 'List Vouchers') 
        {
        	$warnings[] = 'No vouchers imported';
        }

        if (!empty($errors)) {
        	db::rollback();
        	return ['status' => 'fail', 'messages' => $errors];
        }

        db::commit();
        $result = [
        	'status' => 'success', 
        	'messages' => ['Deals has been imported'],
        	'deals'	=> ['id_deals' => $create['id_deals'], 'created_at'  => $create['created_at']]
        ];
        if (!empty($warnings)) {
        	$result['warning'] = $warnings;
        }
    	return $result;
    }

    public function uploadImageFromURL($url, $path, $img_type='deals')
    {
    	if (empty($url)) {
    		return null;
    	}

    	try {
    		@$image = file_get_contents($url);
    		
	    	if ($image === false) {
	    		return null;
	    	}
    	} catch (Exception $e) {
	    	return null;
    		
    	}
    	if ($img_type == 'warning') {
    		$upload = $this->uploadPhotoStrict($image, ($path), 100, 100);
    	}else{
    		$upload = $this->uploadPhotoStrict($image, ($path), 500, 500);
    	}
    	
		if (isset($upload['status']) && $upload['status'] == "success") {
            $deals_image = $upload['path'];
        } else {
            $result = [
                'error'    => 1,
                'status'   => 'fail',
                'messages' => ['fail upload image']
            ];

        	return $result;
        }

        return $deals_image;
    }

    public function getProductByCode($product_type, $code)
    {
		if ($product_type == 'single') 
		{
    		$product = Product::select('id_product')->whereIn('product_code',$code)->first();
    	}
    	else
    	{
    		$product = ProductGroup::select('id_product_group')->where('product_group_code', $code)->first();
    	}

    	return $product['id_product']??$product['id_product_group']??null;
    }

   	public static function uploadPhotoStrict($foto, $path, $width=800, $height=800, $name=null, $forceextension=null) {
		// kalo ada foto1
   		$decoded = ($foto);
		if($forceextension != null)
			$ext = $forceextension;
		else
			$ext = MyHelper::checkExtensionImageBase64($decoded);

		if($name != null)
			$pictName = $name.$ext;
		else
			$pictName = mt_rand(0, 1000).''.time().''.$ext;

		// path
		$upload = $path.$pictName;

		if($ext=='.gif'){
			if(env('STORAGE') &&  env('STORAGE') == 's3'){
				$resource = $decoded;

				$save = Storage::disk('s3')->put($upload, $resource, 'public');
				if ($save) {
						$result = [
							'status' => 'success',
							'path'  => $upload
						];
				}
				else {
					$result = [
						'status' => 'fail'
					];
				}
			}else{
				if (!file_exists($path)) {
					mkdir($path, 666, true);
				}
				if (file_put_contents($upload, $decoded)) {
						$result = [
							'status' => 'success',
							'path'  => $upload
						];
				}
				else {
					$result = [
						'status' => 'fail'
					];
				}
			}
		}else{
			$img = Image::make($decoded);
			$imgwidth = $img->width();
			$imgheight = $img->height();

			/* if($width > 1000){
					$img->resize(1000, null, function ($constraint) {
							$constraint->aspectRatio();
							$constraint->upsize();
					});
			} */

			if($imgwidth < $imgheight){
				//potrait
				if($imgwidth < $width){
					$img->resize($width, null, function ($constraint) {
						$constraint->aspectRatio();
						$constraint->upsize();
					});
				}

				if($imgwidth > $width){
					$img->resize($width, null, function ($constraint) {
						$constraint->aspectRatio();
					});
				}
			} else {
				//landscape
				if($imgheight < $height){
					$img->resize(null, $height, function ($constraint) {
						$constraint->aspectRatio();
						$constraint->upsize();
					});
				}
				if($imgheight > $height){
					$img->resize(null, $height, function ($constraint) {
						$constraint->aspectRatio();
					});
				}

			}
			/* if($imgwidth < $width){
				$img->resize($width, null, function ($constraint) {
					$constraint->aspectRatio();
					$constraint->upsize();
				});
				$imgwidth = $img->width();
				$imgheight = $img->height();
			}

			if($imgwidth > $width){
				$img->resize($width, null, function ($constraint) {
					$constraint->aspectRatio();
				});
				$imgwidth = $img->width();
				$imgheight = $img->height();
			}

			if($imgheight < $height){
				$img->resize(null, $height, function ($constraint) {
					$constraint->aspectRatio();
					$constraint->upsize();
				});
			} */

			$img->crop($width, $height);

			if(env('STORAGE') &&  env('STORAGE') == 's3'){
				$resource = $img->stream()->detach();

				$save = Storage::disk('s3')->put($upload, $resource, 'public');
				if ($save) {
						$result = [
							'status' => 'success',
							'path'  => $upload
						];
				}
				else {
					$result = [
						'status' => 'fail'
					];
				}
			}else{
				if ($img->save($upload)) {
						$result = [
							'status' => 'success',
							'path'  => $upload
						];
				}
				else {
					$result = [
						'status' => 'fail'
					];
				}
			}
		}


		return $result;
	}

	function dealsPaginate($query, $request)
	{

		$query = $query->addSelect('deals.updated_at')->paginate($request->paginate);

		return MyHelper::checkGet($query);
	}

	/* UPDATE */
    function updatePromotionDeals($id, $data)
    {
        $data = $this->checkInputan($data);
        $deals = DealsPromotionTemplate::find($id);
        unset(
        	$data['deals_type'],
        	$data['deals_voucher_price_point'],
        	$data['deals_voucher_price_cash'],
        	$data['is_all_outlet'],
        	$data['id_outlet']
        );
        $data['step_complete'] = 0;
        $data['last_updated_by'] = auth()->user()->id;

        if ( $deals['product_type'] != $data['product_type'] || $data['is_online'] == 0 ) {
        	app($this->promo_campaign)->deleteAllProductRule('deals_promotion', $id);
        }

        if ( isset($deals['id_brand']) && isset($data['id_brand']) && ($deals['id_brand'] != $data['id_brand']) ) {
        	app($this->promo_campaign)->deleteAllProductRule('deals', $id);
        }

        // error
        if (isset($data['error'])) {
            unset($data['error']);
            return ($data);
        }

        // delete old images
        if (isset($data['deals_image'])) {
            app($this->promotion_deals)->deleteImage($id);
        }

        $save = DealsPromotionTemplate::where('id_deals_promotion_template', $id)->update($data);

        return $save;
    }
}
