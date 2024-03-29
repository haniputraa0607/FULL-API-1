<?php

namespace Modules\Subscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;

use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\FeaturedSubscription;
use Modules\Subscription\Entities\SubscriptionOutlet;
use Modules\Subscription\Entities\SubscriptionContent;
use Modules\Subscription\Entities\SubscriptionContentDetail;
use Modules\Subscription\Entities\SubscriptionUser;
use Modules\Subscription\Entities\SubscriptionUserVoucher;
use Modules\Deals\Entities\DealsContent;
use Modules\Deals\Entities\DealsContentDetail;
use Modules\Promotion\Entities\DealsPromotionContent;
use Modules\Promotion\Entities\DealsPromotionContentDetail;
use App\Http\Models\Setting;

use Modules\Subscription\Http\Requests\ListSubscription;
use Modules\Subscription\Http\Requests\Step1Subscription;
use Modules\Subscription\Http\Requests\Step2Subscription;
use Modules\Subscription\Http\Requests\Step3Subscription;
use Modules\Subscription\Http\Requests\DetailSubscription;
use DB;

class ApiSubscription extends Controller
{

    function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->user     = "Modules\Users\Http\Controllers\ApiUser";
    }

    public $saveImage = "img/subscription/";

        /* CHECK INPUTAN */
    function checkInputan($post)
    {

        $data = [];

        if (isset($post['id_subscription'])) {
            $data['id_subscription'] = $post['id_subscription'];
        }

        // title, img , periode
        if (isset($post['subscription_title'])) {
            $data['subscription_title'] = $post['subscription_title'];
        }
        if (isset($post['subscription_sub_title'])) {
            $data['subscription_sub_title'] = $post['subscription_sub_title'];
        }

        if (isset($post['subscription_image'])) {

            if (!file_exists($this->saveImage)) {
                mkdir($this->saveImage, 0777, true);
            }

            $upload = MyHelper::uploadPhotoStrict($post['subscription_image'], $this->saveImage, 600, 250);

            if (isset($upload['status']) && $upload['status'] == "success") {
                $data['subscription_image'] = $upload['path'];
            } else {
                $result = [
                    'error'    => 1,
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ];

                return $result;
            }
        }

        if (isset($post['subscription_start'])) {
            $data['subscription_start'] = date('Y-m-d H:i:s', strtotime($post['subscription_start']));
        }
        if (isset($post['subscription_end'])) {
            $data['subscription_end'] = date('Y-m-d H:i:s', strtotime($post['subscription_end']));
        }
        if (isset($post['subscription_publish_start'])) {
            $data['subscription_publish_start'] = date('Y-m-d H:i:s', strtotime($post['subscription_publish_start']));
        }
        if (isset($post['subscription_publish_end'])) {
            $data['subscription_publish_end'] = date('Y-m-d H:i:s', strtotime($post['subscription_publish_end']));
        }

        // Rule

        // ---------------------------- POINT
        if (isset($post['subscription_price_point'])) {
            $data['subscription_price_cash'] = null;
            $data['subscription_price_point'] = $post['subscription_price_point'];
        }
        // ---------------------------- CASH
        if (isset($post['subscription_price_cash'])) {
            $data['subscription_price_cash'] = $post['subscription_price_cash'];
            $data['subscription_price_point'] = null;
        }
        // ---------------------------- FREE
        if ( ($post['prices_by']??false) == 'money' ) {
            $data['subscription_price_cash'] = $post['subscription_price_cash'];
            $data['subscription_price_point'] = null;
            $data['is_free'] = 1;
        }
        elseif ( ($post['prices_by']??false) == 'point' )
        {
            $data['subscription_price_cash'] = null;
            $data['subscription_price_point'] = $post['subscription_price_point'];
        }
        else
        {
            $data['subscription_price_cash'] = null;
            $data['subscription_price_point'] = null;
        }

        if (isset($post['id_outlet'])) {
            $data['id_outlet'] = $post['id_outlet'];
        }

        if (isset($post['subscription_total'])) {
            $data['subscription_total'] = $post['subscription_total'];
        }

        if ( ($post['subscription_total_type']??0) == 'unlimited' ) {
            $data['subscription_total'] = 0;
        }else{
            $data['subscription_total'] = $post['subscription_total'];
        }

        if (isset($post['user_limit'])) {
            $data['user_limit'] = $post['user_limit'];
        }

        if (isset($post['subscription_voucher_start'])) {
            $data['subscription_voucher_start'] = date('Y-m-d H:i:s', strtotime($post['subscription_voucher_start']));
        }
        // ---------------------------- DURATION
        if (isset($post['subscription_voucher_duration'])) {
            $data['subscription_voucher_duration'] = $post['subscription_voucher_duration'];
            $data['subscription_voucher_expired'] = null;
        }

        // ---------------------------- EXPIRED
        if (isset($post['subscription_voucher_expired'])) {
            $data['subscription_voucher_duration'] = null;
            $data['subscription_voucher_expired'] = date('Y-m-d H:i:s', strtotime($post['subscription_voucher_expired']));
        }

        if (isset($post['subscription_voucher_total'])) {
            $data['subscription_voucher_total'] = $post['subscription_voucher_total'];
        }
        if ( ($post['voucher_type']??0)=='percent' ) {

            if (isset($post['subscription_voucher_percent'])) {
                $data['subscription_voucher_percent'] = $post['subscription_voucher_percent'];
                $data['subscription_voucher_nominal'] = null;
            }
            if (isset($post['subscription_voucher_percent_max'])) {
                $data['subscription_voucher_percent_max'] = $post['subscription_voucher_percent_max'];
            }
        }else{

            if (isset($post['subscription_voucher_nominal'])) {
                $data['subscription_voucher_percent'] = null;
                $data['subscription_voucher_percent_max'] = null;
                $data['subscription_voucher_nominal'] = $post['subscription_voucher_nominal'];
            }
        }
        if (isset($post['subscription_minimal_transaction'])) {
            $data['subscription_minimal_transaction'] = $post['subscription_minimal_transaction'];
        }
        if (isset($post['daily_usage_limit'])) {
            $data['daily_usage_limit'] = $post['daily_usage_limit'];
        }
        if (isset($post['new_purchase_after'])) {
            $data['new_purchase_after'] = $post['new_purchase_after'];
        }
        if ( ($post['purchase_limit']??0) == 'no_limit' ) {
            $data['new_purchase_after'] = 'No Limit';
        }



        if (isset($post['deals_description'])) {
            $data['deals_description'] = $post['deals_description'];
        }
        if (isset($post['deals_tos'])) {
            $data['deals_tos'] = $post['deals_tos'];
        }
        if (isset($post['deals_short_description'])) {
            $data['deals_short_description'] = $post['deals_short_description'];
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
        if (isset($post['user_limit'])) {
            $data['user_limit'] = $post['user_limit'];
        }
        if (isset($post['subscription_description'])) {
            $data['subscription_description'] = $post['subscription_description'];
        }
        return $data;
    }

    public function participateAjax(Request $request)
    {
        $post = $request->json()->all();
        // return $post['id_subscription'];
        $query = SubscriptionUser::
                    where('id_subscription', '=', $post['id_subscription'])
                    ->select(
                        'subscription_users.*',
                        'users.*'
                    )
                    ->join('users', 'subscription_users.id_user','=','users.id')
                    ->withCount(['subscription_user_vouchers as kuota'])
                    ->withCount(['subscription_user_vouchers as used' => function($q){
                        $q->whereNotNull('used_at');
                    }])
                    ->withCount(['subscription_user_vouchers as available' => function($q){
                        $q->whereNull('used_at');
                    }])
                    ->groupBy('id_subscription_user');

        $column = ['subscription_receipt', 'user', 'bought_at', 'expired_at', 'payment_status', 'payment_price', 'available', 'history' ];

        $count = $query->get()->count();
        if($post['start']){
            $query = $query->skip($post['start']);
        }
        if($post['length']>0){
            $query = $query->take($post['length']);
        }

        foreach ($post['order'] as $value) {
            switch ($column[$value['column']]) {
                case 'subscription_receipt':
                $query->orderBy('subscription_user_receipt_number',$value['dir']);
                break;

                case 'user':
                $query->orderBy('users.name',$value['dir']);
                break;

                case 'bought_at':
                $query->orderBy('bought_at',$value['dir']);
                break;

                case 'expired_at':
                $query->orderBy('subscription_expired_at',$value['dir']);
                break;

                case 'payment_status':
                $query->orderBy('paid_status',$value['dir']);
                break;

                case 'payment_price':
                $query->orderBy('subscription_price_point',$value['dir'])
                        ->orderBy('subscription_price_cash',$value['dir']);
                break;

                case 'available':
                $query->orderBy('available',$value['dir']);
                break;

                default:
                $query->orderBy('id_subscription_user',$value['dir']);
                break;
            }
        }
        $query = $query->get();

        if (isset($query) && !empty($query)) {
            $query = $query->toArray();
            $result = [
                'status'  => 'success',
                'result'  => $query,
                'rule'    => $post,
                'count'   => $count
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'message'  => ['No Participate']
            ];
        }
        return response()->json($result);
    }

    public function transaction(Request $request)
    {
        $post = $request->json()->all();

        $data = SubscriptionUserVoucher::
                    join('subscription_users', 'subscription_users.id_subscription_user', '=', 'subscription_user_vouchers.id_subscription_user')
                    // ->join('subscriptions', 'subscription_users.id_subscription', '=', 'subscriptions.id_subscription')
                    ->where('subscription_user_receipt_number', '=', $post['subscription_user_receipt_number'])
                    ->whereNotNull('used_at')
                    ->with([
                        'transaction' => function($q){
                            $q->select(
                                'id_outlet',
                                'id_transaction',
                                'transaction_receipt_number'
                            );
                        },
                        'transaction.productTransaction' => function($q){
                            $q->select(
                                DB::raw('SUM(transaction_product_qty) as total_item'),
                                'id_transaction_product',
                                'id_transaction'
                            );
                        },
                        'transaction.outlet' => function($q){
                            $q->select('id_outlet','outlet_name');
                        }
                    ])
                    ->get()
                    ->toArray();
        return $data;

    }

    public function create(Step1Subscription $request)
    {
        $data = $request->json()->all();
        $data = $this->checkInputan($data);

        // error
        DB::beginTransaction();
        if (isset($data['error'])) {
            unset($data['error']);
            return response()->json($data);
        }
        $save = Subscription::updateOrCreate(['id_subscription' => $data['id_subscription']??''], $data);

        if ($save) {
            DB::commit();
        } else {
            DB::rollBack();
        }
        return response()->json(MyHelper::checkCreate($save));
    }

    public function updateRule(Step2Subscription $request)
    {
        $data = $request->json()->all();

        $data = $this->checkInputan($data);
        // error
        DB::beginTransaction();

        if (isset($data['error'])) {
            unset($data['error']);
            return response()->json($data);
        }

        if (isset($data['id_outlet'])) {

            $data['is_all_outlet'] = null;
            // DELETE
            $this->deleteOutlet($data['id_subscription']);
            if ( ($data['id_outlet'][0]??0) == 'all')
            {
                $data['is_all_outlet'] = 1;
            }
            else
            {
                // SAVE
                $subs=Subscription::find($data['id_subscription']);
                $saveOutlet = $this->saveOutlet($subs['id_subscription'], $data['id_outlet']);
            }
            unset($data['id_outlet']);
        }
        $save = Subscription::where('id_subscription', $data['id_subscription'])->update($data);
        // return $save;

        if ($save) {
            DB::commit();
        } else {
            DB::rollBack();
        }
        return response()->json(MyHelper::checkCreate($save));
    }

    public function updateContent(Step3Subscription $request)
    {
        $post = $request->json()->all();

        $data_content = [];
        $data_content_detail = [];
        $content_order = 1;

        DB::beginTransaction();

        //Rapiin data yg masuk
        foreach ($post['id_subscription_content'] as $key => $value) {
            $data_content[$key]['id_subscription'] = $post['id_subscription'];
            $data_content[$key]['id_subscription_content'] = $value;
            $data_content[$key]['title'] = $post['content_title'][$key];
            $data_content[$key]['is_active'] = ($post['visible'][$key+1]??0) ? 1 : null;
            $data_content[$key]['order'] = ($content_order++);
            $data_content[$key]['created_at'] = date('Y-m-d H:i:s');
            $data_content[$key]['updated_at'] = date('Y-m-d H:i:s');

            $detail_order = 1;
            if ( ($post['id_content_detail'][$key+1]??0) ) {
                foreach ($post['id_content_detail'][$key+1] as $key2 => $value2) {
                    $data_content_detail[$key][$key2]['id_subscription_content'] = $value;
                    $data_content_detail[$key][$key2]['id_subscription_content_detail'] = $value2;
                    $data_content_detail[$key][$key2]['content'] = $post['content_detail'][$key+1][$key2];
                    $data_content_detail[$key][$key2]['order'] = $detail_order++;
                    $data_content_detail[$key][$key2]['created_at'] = date('Y-m-d H:i:s');
                    $data_content_detail[$key][$key2]['updated_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        // hapus content & detail
        $del_content = SubscriptionContent::where('id_subscription','=',$post['id_subscription'])->delete();

        // create content & detail
        foreach ($post['id_subscription_content'] as $key => $value)
        {
            $save = SubscriptionContent::create($data_content[$key]);

            $id_subscription_content = $save['id_subscription_content'];

            if ( ($post['id_content_detail'][$key+1]??0) ) {

                foreach ($post['id_content_detail'][$key+1] as $key2 => $value2) {

                    $data_content_detail[$key][$key2]['id_subscription_content'] = $id_subscription_content;

                    $save = SubscriptionContentDetail::create($data_content_detail[$key][$key2]);
                }
            }
        }

        // update description
        $data_subs['subscription_description'] = $post['subscription_description'];
        $data_subs['subscription_step_complete'] = 1;
        $save = Subscription::where('id_subscription','=',$post['id_subscription'])->update($data_subs);

        if ($save) {
            DB::commit();
        } else {
            DB::rollBack();
        }
        return response()->json(MyHelper::checkUpdate($save));
    }

    public function createOrUpdateContent($data, $source='subscription')
    {
        $post = $data;
        $data_content = [];
        $data_content_detail = [];
        $content_order = 1;

        if ($source == 'deals') {
        	$contentTable = new DealsContent;
        	$contentTableDetail = new DealsContentDetail;
        }elseif ($source == 'deals_promotion') {
        	$contentTable = new DealsPromotionContent;
        	$contentTableDetail = new DealsPromotionContentDetail;
        	$source = 'deals';
        }else{
        	$contentTable = new SubscriptionContent;
        	$contentTableDetail = new SubscriptionContentDetail;
        }
        //Rapiin data yg masuk
        foreach ($post['id_'.$source.'_content'] as $key => $value) {
            $data_content[$key]['id_'.$source] = $post['id_'.$source];
            $data_content[$key]['id_'.$source.'_content'] = $value;
            $data_content[$key]['title'] = $post['content_title'][$key];
            $data_content[$key]['is_active'] = ($post['visible'][$key+1]??0) ? 1 : null;
            $data_content[$key]['order'] = ($content_order++);
            $data_content[$key]['created_at'] = date('Y-m-d H:i:s');
            $data_content[$key]['updated_at'] = date('Y-m-d H:i:s');

            $detail_order = 1;
            if ( ($post['id_content_detail'][$key+1]??0) ) {
                foreach ($post['id_content_detail'][$key+1] as $key2 => $value2) {
                    $data_content_detail[$key][$key2]['id_'.$source.'_content'] = $value;
                    $data_content_detail[$key][$key2]['id_'.$source.'_content_detail'] = $value2;
                    $data_content_detail[$key][$key2]['content'] = $post['content_detail'][$key+1][$key2];
                    $data_content_detail[$key][$key2]['order'] = $detail_order++;
                    $data_content_detail[$key][$key2]['created_at'] = date('Y-m-d H:i:s');
                    $data_content_detail[$key][$key2]['updated_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        // hapus content & detail
        $del_content = $contentTable->where('id_'.$source,'=',$post['id_'.$source])->delete();

        // create content & detail
        foreach ($post['id_'.$source.'_content'] as $key => $value)
        {
            $save = $contentTable->create($data_content[$key]);

            $id_content = $save['id_'.$source.'_content'];

            if ( ($post['id_content_detail'][$key+1]??0) ) {

                foreach ($post['id_content_detail'][$key+1] as $key2 => $value2) {

                    $data_content_detail[$key][$key2]['id_'.$source.'_content'] = $id_content;

                    $save = $contentTableDetail->create($data_content_detail[$key][$key2]);
                }
            }
        }

        if ($save)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function updateAll(DetailSubscription $request)
    {
        $data = $request->json()->all();

        DB::beginTransaction();
        $new_data = $this->checkInputan($data);

        //update subscription outlet
        if (isset($data['id_outlet'])) {
            $new_data['is_all_outlet'] = null;
            // DELETE
            $this->deleteOutlet($data['id_subscription']);
            if ( ($data['id_outlet'][0]??0) == 'all')
            {
                $new_data['is_all_outlet'] = 1;
            }
            else
            {
                // SAVE
                $saveOutlet = $this->saveOutlet($data['id_subscription'], $data['id_outlet']);
            }
            unset($new_data['id_outlet']);
        }

        //update subsscription content
        $update_content = $this->createOrUpdateContent($data);

        if (!$update_content) {
            return  response()->json([
                'status'   => 'fail',
                'messages' => 'Update Subscription Content failed'
            ]);
        }

        //update subscription
        $new_data['subscription_step_complete'] = 1;
        $save = Subscription::where('id_subscription', '=', $data['id_subscription'])->update($new_data);

        if ($save) {
            DB::commit();
        } else {
            DB::rollBack();
            return  response()->json([
                'status'   => 'fail',
                'messages' => 'Update Subscription failed'
            ]);
        }

        return response()->json(MyHelper::checkUpdate($save));

    }

    /* SAVE OUTLET */
    function saveOutlet($id_subs, $id_outlet = [])
    {
        $dataOutlet = [];

        foreach ($id_outlet as $value) {
            array_push($dataOutlet, [
                'id_outlet' => $value,
                'id_subscription'  => $id_subs,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        if (!empty($dataOutlet)) {
            $save = SubscriptionOutlet::insert($dataOutlet);

            return $save;
        } else {
            return false;
        }

        return true;
    }

    /* DELETE OUTLET */
    function deleteOutlet($id_subscription)
    {
        $delete = SubscriptionOutlet::where('id_subscription', $id_subscription)->delete();

        return $delete;
    }

    public function showStep1(Request $request)
    {
        $post = $request->json()->all();
        $data = Subscription::
                    where('id_subscription','=',$post['id_subscription'])
                    ->select(
                        'id_subscription',
                        'subscription_title',
                        'subscription_sub_title',
                        'subscription_start',
                        'subscription_end',
                        'subscription_publish_start',
                        'subscription_publish_end',
                        'subscription_image'
                    )
                    ->first()
                    ->toArray();
        return response()->json(MyHelper::checkGet($data));
    }

    public function showStep2(Request $request)
    {
        $post = $request->json()->all();
        $data = Subscription::
                    where('id_subscription','=',$post['id_subscription'])
                    ->with(['outlets' => function($q){
                        $q->select(
                            'outlets.id_outlet',
                            'outlet_code',
                            'outlet_name'
                        );
                    }])
                    ->first()
                    ->toArray();
        return response()->json(MyHelper::checkGet($data));
    }

    public function showStep3(Request $request)
    {
        $post = $request->json()->all();
        $data = Subscription::
                    where('id_subscription','=',$post['id_subscription'])
                    ->with(
                        'subscription_content',
                        'subscription_content.subscription_content_details'
                    )
                    ->first()
                    ->toArray();

        return response()->json(MyHelper::checkGet($data));
    }

    public function detail(Request $request)
    {
        $post = $request->json()->all();
        $data = Subscription::
                    where('id_subscription','=',$post['id_subscription'])
                    ->with([
                        'subscription_content',
                        'subscription_content.subscription_content_details',
                        'outlets' => function($q){
                            $q->select(
                                'outlets.id_outlet',
                                'outlet_code',
                                'outlet_name'
                            );
                        }
                    ])
                    ->withCount('subscription_users')
                    // ->select(
                    //     'subscription_description'
                    // )
                    ->first()
                    ->toArray();

        $data['total_used_voucher'] = SubscriptionUserVoucher::join('subscription_users', 'subscription_user_vouchers.id_subscription_user','=','subscription_users.id_subscription_user')->where('id_subscription','=',$post['id_subscription'])->whereNotNull('used_at')->count();

        return response()->json(MyHelper::checkGet($data));
    }

    public function listSubscription(ListSubscription $request)
    {
        $post = $request->json()->all();
        $subs = (new Subscription)->newQuery();
        $user = $request->user();
        $curBalance = (int) $user->balance??0;

        // return $post;
        if ($request->json('forSelect2')) {
            return MyHelper::checkGet($subs->with(['outlets', 'users'])->whereDoesntHave('featured_subscriptions')->get());
        }

        if ($request->json('id_outlet') && is_integer($request->json('id_outlet'))) {
            $subs = $subs->join('subscription_outlets', 'subscriptions.id_subscription', '=', 'subscription_outlets.id_subscription')
                        ->where('id_outlet', $request->json('id_outlet'))
                        ->addSelect('subscriptions.*')->distinct();
        }

        if ( empty($request->json('admin')) ) {
            $subs = $subs->whereNotNull('subscription_step_complete');
        }
        if ($request->json('id_subscription')) {
            // add content for detail subscription
            $subs = $subs->where('id_subscription', '=', $request->json('id_subscription'))
                        ->with([
                            'outlets.city',
                            'subscription_content' => function($q) {
                                $q->orderBy('order')
                                    ->where('is_active', '=', 1)
                                    ->addSelect(
                                        'id_subscription',
                                        'id_subscription_content',
                                        'title',
                                        'order'
                                    );
                            },
                            'subscription_content.subscription_content_details' => function($q) {
                                $q->orderBy('order')
                                    ->addSelect(
                                        'id_subscription_content_detail',
                                        'id_subscription_content',
                                        'content',
                                        'order'
                                    );
                            }
                        ]);
        }

        if ($request->json('publish')) {
            $subs = $subs->where('subscription_publish_end', '>=', date('Y-m-d H:i:s'));
        }

        if ($request->json('key_free')) {
            $subs = $subs->where(function($query) use ($request){
                $query->where('subscription_title', 'LIKE', '%' . $request->json('key_free') . '%')
                    ->orWhere('subscription_sub_title', 'LIKE', '%' . $request->json('key_free') . '%');
            });
        }

        $subs->where(function ($query) use ($request) {

            // Cash
            if ($request->json('subscription_type_paid')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNotNull('subscription_price_cash');
                    if(is_numeric($val=$request->json('price_range_start'))){
                        $amp->where('subscription_price_cash','>=',$val);
                    }
                    if(is_numeric($val=$request->json('price_range_end'))){
                        $amp->where('subscription_price_cash','<=',$val);
                    }
                });
            }

            // Point
            if ($request->json('subscription_type_point')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNotNull('subscription_price_point');
                    if(is_numeric($val=$request->json('point_range_start'))){
                        $amp->where('subscription_price_point','>=',$val);
                    }
                    if(is_numeric($val=$request->json('point_range_end'))){
                        $amp->where('subscription_price_point','<=',$val);
                    }
                });
            }

            // Free
            if ($request->json('subscription_type_free')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNull('subscription_price_point')->whereNull('subscription_price_cash');
                });
            }
        });

        if ($request->json('lowest_point')) {
            $subs->orderBy('subscription_price_point', 'ASC');
        }
        if ($request->json('highest_point')) {
            $subs->orderBy('subscription_price_point', 'DESC');
        }

        if ($request->json('alphabetical')) {
            $subs->orderBy('subscription_title', 'ASC');

        } else if ($request->json('alphabetical-desc')) {
            $subs->orderBy('subscription_title', 'DESC');

        } else if ($request->json('newest')) {
            $subs->orderBy('subscription_publish_start', 'DESC');

        } else if ($request->json('oldest')) {
            $subs->orderBy('subscription_publish_start', 'ASC');

        } else {
            $subs->orderBy('subscription_end', 'ASC');
        }
        if ($request->json('id_city')) {
            $subs->with('outlets','outlets.city');
        }

        $subs = $subs->get()->toArray();

        if (!empty($subs)) {
            $city = "";

            if ($request->json('id_city')) {
                $city = $request->json('id_city');
            }

            $subs = $this->kota($subs, $city, $request->json('admin'));

        }

        if ($request->json('highest_available_subscription')) {
            $tempSubs = [];
            $subsUnlimited = $this->unlimited($subs);

            if (!empty($subsUnlimited)) {
                foreach ($subsUnlimited as $key => $value) {
                    array_push($tempSubs, $subs[$key]);
                }
            }

            $limited = $this->limited($subs);

            if (!empty($limited)) {
                $tempTempSubs = [];
                foreach ($limited as $key => $value) {
                    array_push($tempTempSubs, $subs[$key]);
                }

                $tempTempSubs = $this->highestAvailableVoucher($tempTempSubs);

                // return $tempTempDeals;
                $tempSubs =  array_merge($tempSubs, $tempTempSubs);
            }

            $subs = $tempSubs;
        }

        if ($request->json('lowest_available_subscription')) {
            $tempSubs = [];

            $limited = $this->limited($subs);

            if (!empty($limited)) {
                foreach ($limited as $key => $value) {
                    array_push($tempSubs, $subs[$key]);
                }

                $tempSubs = $this->lowestAvailableVoucher($tempSubs);
            }

            $subsUnlimited = $this->unlimited($subs);

            if (!empty($subsUnlimited)) {
                foreach ($subsUnlimited as $key => $value) {
                    array_push($tempSubs, $subs[$key]);
                }
            }

            $subs = $tempSubs;
        }

        // if subs detail, add webview url & btn text
        if ($request->json('id_subscription') && !empty($subs)) {
            //url webview
            $subs[0]['webview_url'] = env('APP_URL') . "api/webview/subscription/" . $subs[0]['id_subscription'];
            // text tombol beli
            $subs[0]['button_text'] = $subs[0]['subscription_price_type']=='free'?'Ambil':'Tukar';
            $subs[0]['button_status'] = 0;
            //text konfirmasi pembelian
            if($subs[0]['subscription_price_type']=='free'){
                //voucher free
                $payment_message = Setting::where('key', 'payment_messages')->pluck('value_text')->first()??'Kamu yakin ingin membeli subscription ini?';
                $payment_message = MyHelper::simpleReplace($payment_message,['subscription_title'=>$subs[0]['subscription_title']]);
            }elseif($subs[0]['subscription_price_type']=='point'){
                $payment_message = Setting::where('key', 'payment_messages_point')->pluck('value_text')->first()??'Anda akan menukarkan %point% points anda dengan subscription %subscription_title%?';
                $payment_message = MyHelper::simpleReplace($payment_message,['point'=>$subs[0]['subscription_price_point'],'subscription_title'=>$subs[0]['subscription_title']]);
            }else{
                $payment_message = Setting::where('key', 'payment_messages')->pluck('value_text')->first()??'Kamu yakin ingin membeli subscription %subscription_title%?';
                $payment_message = MyHelper::simpleReplace($payment_message,['subscription_title'=>$subs[0]['subscription_title']]);
            }

            $payment_success_message = Setting::where('key', 'payment_success_messages')->pluck('value_text')->first()??'Anda telah membeli subscription %subscription_title%';
            $payment_success_message = MyHelper::simpleReplace($payment_success_message,['subscription_title'=>$subs[0]['subscription_title']]);


            $subs[0]['payment_message'] = $payment_message??'';
            $subs[0]['payment_success_message'] = $payment_success_message;

            if($subs[0]['subscription_price_type']=='free'&&$subs[0]['subscription_status']=='available'){
                $subs[0]['button_status']=1;
            }else {
                if($subs[0]['subscription_price_type']=='point'){
                    $subs[0]['button_status']=$subs[0]['subscription_price_point']<=$curBalance?1:0;
                    if($subs[0]['subscription_price_point']>$curBalance){
                        $subs[0]['payment_fail_message'] = Setting::where('key', 'payment_fail_messages')->pluck('value_text')->first()??'Mohon maaf, point anda tidak cukup';
                    }
                }else{
                    $subs[0]['button_text'] = 'Beli';
                    if($subs[0]['subscription_status']=='available'){
                        $subs[0]['button_status'] = 1;
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
            $listData   = [];
            $paginate   = 10;
            $start      = $paginate * ($page - 1);
            $all        = $paginate * $page;
            $end        = $all;
            $next       = true;

            if ($all > count($subs)) {
                $end = count($subs);
                $next = false;
            }

            for ($i=$start; $i < $end; $i++) {
                $subs[$i]['time_to_end']=strtotime($subs[$i]['subscription_end'])-time();

                $list[$i]['id_subscription'] = $subs[$i]['id_subscription'];
                $list[$i]['url_subscription_image'] = $subs[$i]['url_subscription_image'];
                $list[$i]['time_to_end'] = $subs[$i]['time_to_end'];
                $list[$i]['subscription_start'] = $subs[$i]['subscription_start'];
                $list[$i]['subscription_publish_start'] = $subs[$i]['subscription_publish_start'];
                $list[$i]['subscription_end'] = $subs[$i]['subscription_end'];
                $list[$i]['subscription_publish_end'] = $subs[$i]['subscription_publish_end'];
                $list[$i]['subscription_price_cash'] = $subs[$i]['subscription_price_cash'];
                $list[$i]['subscription_price_point'] = $subs[$i]['subscription_price_point'];
                $list[$i]['subscription_price_type'] = $subs[$i]['subscription_price_type'];
                $list[$i]['time_server'] = date('Y-m-d H:i:s');
                array_push($resultData, $subs[$i]);
                array_push($listData, $list[$i]);
            }

            $result['current_page']  = $page;
            if (!$request->json('id_subscription')) {

                $result['data']          = $listData;
            }else{

                $result['data']          = $resultData;
            }
            $result['total']         = count($resultData);
            $result['next_page_url'] = null;
            if ($next == true) {
                $next_page = (int) $page + 1;
                $result['next_page_url'] = ENV('APP_API_URL') . 'api/subscription/list?page=' . $next_page;
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
                $resultMessage = 'Sorry, deals you are looking for is not yet available';
            }else{
                $resultMessage = 'Look forward to interesting deals from us';
            }
            return response()->json(MyHelper::checkGet($result, $resultMessage));

        }else{
            return response()->json(MyHelper::checkGet($subs));
        }
    }

    function unlimited($subs)
    {
        $unlimited = array_filter(array_column($subs, "available_subscription"), function ($subs) {
            if ($subs == "*") {
                return $subs;
            }
        });

        return $unlimited;
    }

    function limited($subs)
    {
        $limited = array_filter(array_column($subs, "available_subscription"), function ($subs) {
            if ($subs != "*") {
                return $subs;
            }
        });

        return $limited;
    }

    /* SORT DEALS */
    function highestAvailableVoucher($subs)
    {
        usort($subs, function ($a, $b) {
            return $a['available_subscription'] < $b['available_subscription'];
        });

        return $subs;
    }

    function lowestAvailableVoucher($subs)
    {
        usort($subs, function ($a, $b) {
            return $a['available_subscription'] > $b['available_subscription'];
        });

        return $subs;
    }

    /* INI LIST KOTA */
    public function kota($subs, $city = "", $admin=false)
    {
        $timeNow = date('Y-m-d H:i:s');

        foreach ($subs as $key => $value) {
            $markerCity = 0;

            $subs[$key]['outlet_by_city'] = [];

            // set time
            $subs[$key]['time_server'] = $timeNow;

            if (!empty($value['outlets'])) {
                // ambil kotanya dulu
        // return $value['outlets'];
                $kota = array_column($value['outlets'], 'city');
                $kota = array_values(array_map("unserialize", array_unique(array_map("serialize", $kota))));
        // return [$kota];

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

                $subs[$key]['outlet_by_city'] = $kota;
            }

            // unset($subs[$key]['outlets']);
            // jika ada pencarian kota
            if (!empty($city)) {
                if ($markerCity == 0) {
                    unset($subs[$key]);
                    continue;
                }
            }

            $calc = $value['subscription_total'] - $value['subscription_bought'];

            if ( empty($value['subscription_total']) ) {
                $calc = '*';
            }

            if(is_numeric($calc)){
                if($calc||$admin){
                    $subs[$key]['percent_subscription'] = $calc*100/$value['subscription_total'];
                }else{
                    unset($subs[$key]);
                    continue;
                }
            }else{
                $subs[$key]['percent_voucher'] = 100;
            }
            $subs[$key]['available_subscription'] = (string) $calc;
            // subs masih ada?
            // print_r($subs[$key]['available_voucher']);
        }

        // print_r($subs); exit();
        $subs = array_values($subs);

        return $subs;
    }

    public function mySubscription(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $subs = SubscriptionUser::
                with([
                    'subscription.outlets.city',
                    'subscription.subscription_content' => function($q) {
                        $q->orderBy('order')
                            ->where('is_active', '=', 1)
                            ->addSelect(
                                'id_subscription',
                                'id_subscription_content',
                                'title',
                                'order'
                            );
                    },
                    'subscription.subscription_content.subscription_content_details' => function($q) {
                        $q->orderBy('order')
                            ->addSelect(
                                'id_subscription_content_detail',
                                'id_subscription_content',
                                'content',
                                'order'
                            );
                    },
                    'subscription_user_vouchers' => function($q){
                        $q->whereNotNull('used_at');
                    },
                    'subscription_user_vouchers.transaction' => function($q){
                        $q->select('id_outlet', 'id_transaction');
                    },
                    'subscription_user_vouchers.transaction.productTransaction' => function($q){
                        $q->select(
                            DB::raw('SUM(transaction_product_qty) as total_item'),
                            'id_transaction_product',
                            'id_transaction'
                        );
                    },
                    'subscription_user_vouchers.transaction.outlet' => function($q){
                        $q->select('id_outlet','outlet_name');
                    }
                ])
                ->where('id_user', $user['id'])
                ->where('subscription_expired_at', '>=',date('Y-m-d H:i:s'))
                ->whereIn('paid_status', ['Completed','Free'])
                ->withCount(['subscription_user_vouchers as used_voucher' => function($q){
                    $q->whereNotNull('used_at');
                }])
                ->withCount(['subscription_user_vouchers as available_voucher' => function($q){
                    $q->whereNull('used_at');
                }]);

        if (isset($post['expired_start'])) {
            $subs->whereDate('subscription_expired_at', '>=',date('Y-m-d', strtotime($post['expired_start'])));
        }

        if (isset($post['expired_end'])) {
            $subs->whereDate('subscription_expired_at', '<=',date('Y-m-d', strtotime($post['expired_end'])));
        }

        //search by outlet
        if(isset($post['id_outlet']) && is_numeric($post['id_outlet'])){
            $subs->join('subscription_user_vouchers', 'subscription_users.id_subscription_user', 'subscription_user_vouchers.id_subscription_user')
                    ->join('subscriptions', 'subscriptions.id_subscription', 'subscription_users.id_subscription')
                    ->join('subscription_outlets', 'subscriptions.id_subscription', 'subscription_outlets.id_subscription')
                    ->where(function ($query) use ($post) {
                        $query->orWhere('subscription_outlets.id_outlet', $post['id_outlet']);
                    })
                    ->distinct();
        }

        if(isset($post['key_free']) && $post['key_free'] != null){
            if(!MyHelper::isJoined($subs,'subscription_user_vouchers')){
                $subs->leftJoin('subscription_user_vouchers', 'subscription_users.id_subscription_user', 'subscription_user_vouchers.id_subscription_user');
            }
            if(!MyHelper::isJoined($subs,'subscriptions')){
                $subs->leftJoin('subscriptions', 'subscriptions.id_subscription', 'subscription_users.id_subscription');
            }
            $subs->where(function ($query) use ($post) {
                $query->where('subscriptions.subscription_title', 'LIKE', '%'.$post['key_free'].'%')
                        ->orWhere('subscriptions.subscription_sub_title', 'LIKE', '%'.$post['key_free'].'%');
            });
        }

        if (isset($post['oldest']) && ($post['oldest'] == 1 || $post['oldest'] == '1')) {
                $subs = $subs->orderBy('subscription_users.bought_at', 'asc');
        }

        if (isset($post['newest']) && ($post['newest'] == 1 || $post['newest'] == '1')) {
                $subs = $subs->orderBy('subscription_users.bought_at', 'desc');
        }

        elseif (isset($post['newest_expired']) && ($post['newest_expired'] == 1 || $post['newest_expired'] == '1')) {
            $subs = $subs->orderBy('subscription_expired_at', 'asc');
        }

        if (isset($post['used']) && ($post['used'] == 1 || $post['used'] == '1'))  {
            $subs = $subs->orderBy('used_voucher', 'desc');
        }
        if (isset($post['available']) && ($post['available'] == 1 || $post['available'] == '1')) {
            $subs = $subs->orderBy('available_voucher', 'desc');
        }

// return $subs->get();
        if ( isset($post['id_subscription_user']) )
        {
            $subs = $subs->where('id_subscription_user', '=', $post['id_subscription_user'])
                         ->first()->toArray();

            if (!empty($subs)) {

                $subscription = $this->kota([$subs['subscription']], "", $request->json('admin'));

                $subs['outlet_by_city'] = $subscription[0]['outlet_by_city']??'';
            }
            if ($subs) {
                if (empty($subs['subscription']['subscription_image'])) {
                    $subs['url_subscription_image'] = env('S3_URL_API').'img/default.jpg';
                }
                else {
                    $subs['url_subscription_image'] = env('S3_URL_API').$subs['subscription']['subscription_image'];
                }
                $subs['time_server'] = date('Y-m-d H:i:s');
                $subs['time_to_end'] = strtotime($subs['subscription_expired_at'])-time();
                $subs['url_webview'] = env('APP_API_URL') ."api/webview/mysubscription/". $subs['id_subscription_user'];
            }
            $data = $subs;
        }
        else
        {

            $subs = $subs->get();
            $data = [];
            if(!empty($subs) && !empty($subs[0])){
                foreach ($subs as $key => $sub) {
                    //check voucher total
                    if ($sub['subscription_user_vouchers_count'] < $sub['subscription']['subscription_voucher_total']) {

                        $data[$key]['id_subscription']              = $sub['subscription']['id_subscription'];
                        $data[$key]['id_subscription_user']         = $sub['id_subscription_user'];
                        $data[$key]['subscription_end']             = date('Y-m-d H:i:s', strtotime($sub['subscription']['subscription_end']));
                        $data[$key]['subscription_publish_end']     = date('Y-m-d H:i:s', strtotime($sub['subscription']['subscription_publish_end']));
                        $data[$key]['subscription_expired_at']      = $sub['subscription_expired_at'];
                        $data[$key]['subscription_voucher_total']   = $sub['subscription']['subscription_voucher_total'];
                        $data[$key]['used_voucher']                 = $sub['used_voucher'];
                        $data[$key]['available_voucher']            = $sub['available_voucher'];
                        if (empty($sub['subscription']['subscription_image'])) {
                            $data[$key]['url_subscription_image'] = env('S3_URL_API').'img/default.jpg';
                        }
                        else {
                            $data[$key]['url_subscription_image'] = env('S3_URL_API').$sub['subscription']['subscription_image'];
                        }

                        $data[$key]['time_to_end']                  = strtotime($sub['subscription']['subscription_expired_at'])-time();
                        $data[$key]['url_webview']                  = env('APP_API_URL') ."api/webview/mysubscription/". $sub['id_subscription_user'];
                        $data[$key]['time_server']                  = date('Y-m-d H:i:s');
                    }
                }

                if ($request->get('page')) {
                    $page = $request->get('page');
                } else {
                    $page = 1;
                }

                $resultData = [];
                $listData   = [];
                $paginate   = 10;
                $start      = $paginate * ($page - 1);
                $all        = $paginate * $page;
                $end        = $all;
                $next       = true;

                if ($all > count($subs)) {
                    $end = count($subs);
                    $next = false;
                }

                for ($i=$start; $i < $end; $i++) {
                    array_push($resultData, $data[$i]);
                }

                $result['current_page']  = $page;
                $result['data']          = $resultData;
                $result['total']         = count($resultData);
                $result['next_page_url'] = null;
                if ($next == true) {
                    $next_page = (int) $page + 1;
                    $result['next_page_url'] = ENV('APP_API_URL') . 'api/subscription/me?page=' . $next_page;
                }

                // print_r($deals); exit();
                if(!$result['total']){
                    $result=[];
                }
                $data = $result;
            }
            else
            {
                $empty_text = Setting::where('key','=','message_mysubscription_empty_header')
                                ->orWhere('key','=','message_mysubscription_empty_content')
                                ->orderBy('id_setting')
                                ->get();
                $text['header'] = $empty_text[0]['value']??'Anda belum memiliki Paket.';
                $text['content'] = $empty_text[1]['value']??'Banyak keuntungan dengan berlangganan.';
                return  response()->json([
                    'status'   => 'fail',
                    'messages' => ['My Subscription is empty'],
                    'empty'    => $text,
                ]);
            }
        }
        return response()->json($this->checkGet($data));
    }

    public static function  checkGet($data, $message = null){
            if($data && !empty($data)) return ['status' => 'success', 'result' => $data];
            else if(empty($data)) {
                if($message == null){
                    $message = 'Data not found';
                }
                return [
                    'status'    => 'fail',
                    'messages'  => [$message],
                    'empty'     => [
                        'header'    => "",
                        'content'   => ""
                    ]
                ];
            }
            else return ['status' => 'fail', 'messages' => ['failed to retrieve data']];
    }

}
