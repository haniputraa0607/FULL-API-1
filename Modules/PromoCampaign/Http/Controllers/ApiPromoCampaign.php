<?php

namespace Modules\PromoCampaign\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\PromoCampaign\Entities\PromoCampaignOutlet;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\PromoCampaign\Entities\PromoCampaignProductDiscount;
use Modules\PromoCampaign\Entities\PromoCampaignProductDiscountRule;
use Modules\PromoCampaign\Entities\PromoCampaignTierDiscountProduct;
use Modules\PromoCampaign\Entities\PromoCampaignTierDiscountRule;
use Modules\PromoCampaign\Entities\PromoCampaignBuyxgetyProductRequirement;
use Modules\PromoCampaign\Entities\PromoCampaignBuyxgetyRule;
use Modules\PromoCampaign\Entities\PromoCampaignProductCategoryRule;
use Modules\PromoCampaign\Entities\PromoCampaignProductCategoryProductRequirement;
use Modules\PromoCampaign\Entities\CategoryPromoCampaignProductCategoryProductRequirement;
use Modules\PromoCampaign\Entities\PromoCampaignHaveTag;
use Modules\PromoCampaign\Entities\PromoCampaignTag;
use Modules\PromoCampaign\Entities\PromoCampaignReport;
use Modules\PromoCampaign\Entities\PromoCampaignDay;
use Modules\PromoCampaign\Entities\UserReferralCode;
use Modules\PromoCampaign\Entities\PromoCampaignDiscountDeliveryRule;
use Modules\PromoCampaign\Entities\PromoCampaignShipmentMethod;

use Modules\Deals\Entities\DealsProductDiscount;
use Modules\Deals\Entities\DealsProductDiscountRule;
use Modules\Deals\Entities\DealsTierDiscountProduct;
use Modules\Deals\Entities\DealsTierDiscountRule;
use Modules\Deals\Entities\DealsBuyxgetyProductRequirement;
use Modules\Deals\Entities\DealsBuyxgetyRule;
use Modules\Deals\Entities\DealsDiscountDeliveryRule;
use Modules\Deals\Entities\DealsShipmentMethod;
use Modules\Deals\Entities\DealsProductCategoryRule;
use Modules\Deals\Entities\DealsProductCategoryProductRequirement;
use Modules\Deals\Entities\CategoryDealsProductCategoryProductRequirement;
use Modules\Deals\Entities\ProductVariantDealsProductCategoryProductRequirement;

use Modules\Promotion\Entities\DealsPromotionProductDiscount;
use Modules\Promotion\Entities\DealsPromotionProductDiscountRule;
use Modules\Promotion\Entities\DealsPromotionTierDiscountProduct;
use Modules\Promotion\Entities\DealsPromotionTierDiscountRule;
use Modules\Promotion\Entities\DealsPromotionBuyxgetyProductRequirement;
use Modules\Promotion\Entities\DealsPromotionBuyxgetyRule;
use Modules\Promotion\Entities\DealsPromotionDiscountDeliveryRule;
use Modules\Promotion\Entities\DealsPromotionShipmentMethod;

use Modules\ProductVariant\Entities\ProductGroup;
use App\Http\Models\ProductCategory;
use Modules\ProductVariant\Entities\ProductVariant;

use App\Http\Models\User;
use App\Http\Models\Campaign;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\Setting;
use App\Http\Models\Voucher;
use App\Http\Models\Treatment;
use App\Http\Models\Deal;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsPromotionTemplate;

use Modules\PromoCampaign\Http\Requests\Step1PromoCampaignRequest;
use Modules\PromoCampaign\Http\Requests\Step2PromoCampaignRequest;
use Modules\PromoCampaign\Http\Requests\DeletePromoCampaignRequest;
use Modules\PromoCampaign\Http\Requests\ValidateCode;

use Modules\PromoCampaign\Lib\PromoCampaignTools;
use App\Lib\MyHelper;
use App\Jobs\GeneratePromoCode;
use DB;
use Hash;
use Modules\SettingFraud\Entities\DailyCheckPromoCode;
use Modules\SettingFraud\Entities\LogCheckPromoCode;
use Illuminate\Support\Facades\Auth;

class ApiPromoCampaign extends Controller
{

	function __construct() {
        date_default_timezone_set('Asia/Jakarta');

        $this->online_transaction   = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->voucher   	= "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->fraud   		= "Modules\SettingFraud\Http\Controllers\ApiFraud";
        $this->promo       	= "Modules\PromoCampaign\Http\Controllers\ApiPromo";
        $this->autocrm      = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
    }

    public function index(Request $request)
    {
        $post = $request->json()->all();
        $promo_type = $request->get('promo_type');

        try {

            $query = PromoCampaign::with([
                        'user'
                    ])
                    ->OrderBy('promo_campaigns.id_promo_campaign', 'DESC');
            $count = (new PromoCampaign)->newQuery();

            if (isset($promo_type)) {

                $query = $query->where('promo_type', '=' ,$promo_type);

            }

            if ($request->json('rule')) {
                $filter = $this->filterList($query, $request);
                $this->filterList($count, $request);
            }

            if(!empty($query)){
                $query = $query->paginate(10)->toArray();
                $result = [
                    'status'     => 'success',
                    'result'     => $query,
                    'count'      => count($query)
                ];
            }else{

                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Promo Campaign is empty']
                ]);
            }

            if ($filter??false) {
                $result = array_merge($result, $filter);
            }

            return response()->json($result);

        } catch (\Exception $e) {

            return response()->json(['status' => 'error', 'messages' => [$e->getMessage()]]);
        }
    }

    protected function filterList($query, $request)
    {
        $allowed = array(
            'operator' => ['=', 'like', '<', '>', '<=', '>='],
            'subject' => [
                'campaign_name',
                'promo_title',
                'code_type',
                'prefix_code',
                'number_last_code',
                'total_code',
                'date_start',
                'date_end',
                'is_all_outlet',
                'promo_type',
                'used_code',
                'id_outlet',
                'id_product',
                'id_user',
                'used_by_user',
                'used_at_outlet',
                'promo_code'
            ],
            'mainSubject' => [
                'campaign_name',
                'promo_title',
                'code_type',
                'prefix_code',
                'number_last_code',
                'total_code',
                'date_start',
                'date_end',
                'is_all_outlet',
                'promo_type',
                'used_code'
            ]
        );
        $request->validate([
            'operator' => 'required|in:or,and',
            'rule.*.subject' => 'required|in:' . implode(',', $allowed['subject']),
            'rule.*.operator' => 'in:' . implode(',', $allowed['operator']),
            'rule.*.parameter' => 'required'
        ]);
        $return = [];
        $where = $request->json('operator') == 'or' ? 'orWhere' : 'where';
        if ($request->json('date_start')) {
            $query->where('date_start', '>=', $request->json('date_start'));
        }
        if ($request->json('date_end')) {
            $query->where('date_end', '<=', $request->json('date_end'));
        }
        $rule = $request->json('rule');
        foreach ($rule as $value) {
            if (in_array($value['subject'], $allowed['mainSubject'])) {
                if (!in_array($value['subject'], $allowed['subject'])) {
                    continue;
                }
                if (!(isset($value['operator']) && $value['operator'] && in_array($value['operator'], $allowed['operator']))) {
                    $value['operator'] = '=';
                }
                if ($value['operator'] == 'like') {
                    $query->$where($value['subject'], $value['operator'], '%' . $value['parameter'] . '%');
                } else {
                    $query->$where($value['subject'], $value['operator'], $value['parameter']);
                }
            } else {
                switch ($value['subject']) {
                    case 'id_outlet':
                    if ($value['parameter'] == '0') {
                        $query->$where('is_all_outlet', '1');
                    } else {
                        $query->leftJoin('promo_campaign_outlets', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_outlets.id_promo_campaign');
                        $query->$where(function ($query) use ($value) {
                            $query->where('promo_campaign_outlets.id_outlet', $value['parameter']);
                            $query->orWhere('is_all_outlet', '1');
                        });
                    }
                    break;

                    case 'id_user':
                    $query->leftJoin('promo_campaign_user_filters', 'promo_campaign_user_filters.id_promo_campaign', '=', 'promo_campaigns.id_promo_campaign');
                    switch ($value['parameter']) {
                        case 'all user':
                        $query->$where('promo_campaign_user_filters.subject', 'all_user');
                        break;

                        case 'new user':
                        $query->$where(function ($query) {
                            $query->where('promo_campaign_user_filters.subject', 'count_transaction');
                            $query->where('promo_campaign_user_filters.parameter', '0');
                        });
                        break;

                        case 'existing user':
                        $query->$where(function ($query) {
                            $query->where('promo_campaign_user_filters.subject', 'count_transaction');
                            $query->where('promo_campaign_user_filters.parameter', '1');
                        });
                        break;

                        default:
                                # code...
                        break;
                    }
                    break;

                    case 'id_product':
                    $query->leftJoin('promo_campaign_buyxgety_product_requirements', 'promo_campaign_buyxgety_product_requirements.id_promo_campaign', '=', 'promo_campaigns.id_promo_campaign');
                    $query->leftJoin('promo_campaign_product_discounts', 'promo_campaign_product_discounts.id_promo_campaign', '=', 'promo_campaigns.id_promo_campaign');
                    $query->leftJoin('promo_campaign_tier_discount_products', 'promo_campaign_tier_discount_products.id_promo_campaign', '=', 'promo_campaigns.id_promo_campaign');
                    if ($value['parameter'] == '0') {
                        $query->$where(function ($query) {
                            $query->where('promo_type', 'Product discount');
                            $query->where('promo_campaign_product_discounts.id_product', null);
                        });
                    } else {
                        $query->$where(DB::raw('IF(promo_type=\'Product discount\',promo_campaign_product_discounts.id_product,IF(promo_type=\'Tier discount\',promo_campaign_tier_discount_products.id_product,promo_campaign_buyxgety_product_requirements.id_product))'), $value['parameter']);
                    }
                    break;

                    case 'used_by_user':
                    $wherein=$where.'In';
                    $query->$wherein('id_promo_campaign',function($query) use ($value,$where){
                        $query->select('id_promo_campaign')->from(with(new Reports)->getTable())->where('user_phone',$value['operator'],$value['operator'] == 'like'?'%'.$value['parameter'].'%':$value['parameter'])->groupBy('id_promo_campaign');
                    });
                    break;

                    case 'used_at_outlet':
                    $wherein=$where.'In';
                    $query->$wherein('id_promo_campaign',function($query) use ($value,$where){
                        $query->select('id_promo_campaign')->from(with(new Reports)->getTable())->where('id_outlet',$value['parameter'])->groupBy('id_promo_campaign');
                    });
                    break;

                    case 'promo_code':
                    $wherein=$where.'In';
                    $query->$wherein('id_promo_campaign',function($query) use ($value,$where){
                        $query->select('id_promo_campaign')->from(with(new PromoCode)->getTable())->where('promo_code',$value['operator'],$value['operator'] == 'like'?'%'.$value['parameter'].'%':$value['parameter'])->groupBy('id_promo_campaign');
                    });
                    break;

                    default:
                        # code...
                    break;
                }
            }
            $return[] = $value;
        }
        return [
            'rule' => $return,
            'operator' => $request->json('operator')
        ];
    }

    public function detail(Request $request)
    {
        $post = $request->json()->all();
        $data = [
            'user',
            'promo_campaign_have_tags.promo_campaign_tag',
            'outlets',
            'promo_campaign_product_discount_rules',
            'promo_campaign_product_discount',
            'promo_campaign_tier_discount_rules',
            'promo_campaign_tier_discount_product',
            'promo_campaign_buyxgety_rules.product',
            'promo_campaign_buyxgety_product_requirement',
            'promo_campaign_productcategory_category_requirements',
            'promo_campaign_productcategory_rules.product_category',
            'promo_campaign_shipment_method',
            'promo_campaign_discount_delivery_rules',
            'promo_campaign_days'
            // 'promo_campaign_reports'
        ];
        $promoCampaign = PromoCampaign::with($data)->where('id_promo_campaign', '=', $post['id_promo_campaign'])->first();
        if ($promoCampaign['code_type'] == 'Single') {
        	$promoCampaign->load('promo_campaign_promo_codes');
        }
        $promoCampaign = $promoCampaign->toArray();
        if ($promoCampaign) {

            $promoCampaign['used_code'] = PromoCampaignReport::where('promo_campaign_reports.id_promo_campaign', $post['id_promo_campaign'])->get()->count();
            $total = PromoCampaignReport::where('promo_campaign_reports.id_promo_campaign', $post['id_promo_campaign']);
            $this->filterReport($total,$request,$foreign);
            foreach ($foreign as $value) {
                $total->leftJoin(...$value);
            }
            $promoCampaign['total'] = $total->get()->count();

            $total2 = PromoCampaignPromoCode::join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')->where('promo_campaign_promo_codes.id_promo_campaign', $post['id_promo_campaign']);
            $this->filterCoupon($total2,$request,$foreign);
            foreach ($foreign as $value) {
                $total->leftJoin(...$value);
            }
            $promoCampaign['total2'] = $total2->get()->count();
            $result = [
                'status'  => 'success',
                'result'  => $promoCampaign
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'message'  => ['Promo Campaign Not Found']
            ];
        }
        return response()->json($result);
    }
    public function detail2(Request $request) {
        $post = $request->json()->all();

        $promoCampaign = PromoCampaign::with(
                            'user',
                            'promo_campaign_have_tags.promo_campaign_tag',
                            'promo_campaign_product_discount_rules',
                            'promo_campaign_product_discount.product.category',
                            'promo_campaign_tier_discount_rules',
                            'promo_campaign_tier_discount_product.product',
                            'promo_campaign_buyxgety_rules.product',
                            'promo_campaign_buyxgety_product_requirement.product',
                            'promo_campaign_reports',
                            'outlets'
                        )
                        ->where('id_promo_campaign', '=', $post['id_promo_campaign'])
                        ->first();

        if ( ($promoCampaign['code_type']??'')=='Single' ) {
            $promoCampaignPromoCode = PromoCampaignPromoCode::where('id_promo_campaign', '=', $post['id_promo_campaign'])->first();
        }

        if ($promoCampaign) {
            $promoCampaign = $promoCampaign->toArray();
            $promoCampaign['count'] = count($promoCampaign['promo_campaign_reports']);
            if ($promoCampaignPromoCode??false) {
                $promoCampaign['promo_campaign_promo_codes'] = $promoCampaignPromoCode;
            }
            $result = [
                'status'  => 'success',
                'result'  => $promoCampaign
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'message'  => ['Promo Campaign Not Found']
            ];
        }
        return response()->json($result);
    }

    public function report(Request $request)
    {
        $post = $request->json()->all();
        $query = PromoCampaignReport::select('promo_campaign_reports.*')->with(['promo_campaign_promo_code','transaction','outlet'])->where('promo_campaign_reports.id_promo_campaign', $post['id_promo_campaign']);
        $filter = null;
        $count = (new PromoCampaignReport)->newQuery()->where('promo_campaign_reports.id_promo_campaign', $post['id_promo_campaign']);
        $total = (new PromoCampaignReport)->newQuery()->where('promo_campaign_reports.id_promo_campaign', $post['id_promo_campaign']);
        $foreign=[];
        $foreign2=[];
        if($post['rule']??false){
            $this->filterReport($query,$request,$foreign);
            $this->filterReport($count,$request,$foreign2);
        }
        $column = ['promo_code','user_name','created_at','receipt_number','outlet','device_type'];
        if($post['start']){
            $query->skip($post['start']);
        }
        if($post['length']>0){
            $query->take($post['length']);
        }
        foreach ($post['order'] as $value) {
            switch ($column[$value['column']]) {
                case 'promo_code':
                $foreign['promo_campaign_promo_codes']=array('promo_campaign_promo_codes','promo_campaign_promo_codes.id_promo_campaign_promo_code','=','promo_campaign_reports.id_promo_campaign_promo_code');
                $query->orderBy('promo_code',$value['dir']);
                break;

                case 'receipt_number':
                $foreign['transactions']=array('transactions','transactions.id_transaction','=','promo_campaign_reports.id_transaction');
                $query->orderBy('transaction_receipt_number',$value['dir']);
                break;

                case 'outlet':
                $foreign['outlets']=array('outlets','outlets.id_outlet','=','promo_campaign_reports.id_outlet');
                $query->orderBy('outlet_name',$value['dir']);
                break;

                default:
                $query->orderBy('promo_campaign_reports.'.$column[$value['column']],$value['dir']);
                break;
            }
        }
        foreach ($foreign as $value) {
            $query->leftJoin(...$value);
        }
        foreach ($foreign2 as $value) {
            $count->leftJoin(...$value);
        }

        $query = $query->get()->toArray();
        $count = $count->get()->count();
        $total = $total->get()->count();

        if (isset($query) && !empty($query)) {
            $result = [
                'status'  => 'success',
                'result'  => $query,
                'total'  => $total,
                'count'  => $count
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'message'  => ['No Report']
            ];
        }
        return response()->json($result);
    }

    protected function filterReport($query, $request,&$foreign='')
    {
        $query->groupBy('promo_campaign_reports.id_promo_campaign_report');
        $allowed = array(
            'operator' => ['=', 'like', '<', '>', '<=', '>='],
            'subject' => ['promo_code','user_phone','created_at','receipt_number','id_outlet','device_type','outlet_count','user_count'],
            'mainSubject' => ['user_phone','created_at','id_outlet','device_type']
        );
        $return = [];
        $where = $request->json('operator') == 'or' ? 'orWhere' : 'where';
        $rule = $request->json('rule');
        $query->where(function($queryx) use ($rule,$allowed,$where,$query,&$foreign,$request){
            $foreign=array();
            $outletCount=0;
            $userCount=0;
            foreach ($rule??[] as $value) {
                if (!in_array($value['subject'], $allowed['subject'])) {
                    continue;
                }
                if (!(isset($value['operator']) && $value['operator'] && in_array($value['operator'], $allowed['operator']))) {
                    $value['operator'] = '=';
                }
                if ($value['operator'] == 'like') {
                    $value['parameter'] = '%' . $value['parameter'] . '%';
                }
                if (in_array($value['subject'], $allowed['mainSubject'])) {
                    if($value['subject']=='created_at'){
                        $queryx->$where(\DB::raw('UNIX_TIMESTAMP(promo_campaign_reports.'.$value['subject'].')'), $value['operator'], strtotime($value['parameter']));
                    }else{
                        $queryx->$where('promo_campaign_reports.'.$value['subject'], $value['operator'], $value['parameter']);
                    }
                } else {
                    switch ($value['subject']) {
                        case 'promo_code':
                        $foreign['promo_campaign_promo_codes']=['promo_campaign_promo_codes','promo_campaign_promo_codes.id_promo_campaign_promo_code','=','promo_campaign_reports.id_promo_campaign_promo_code'];
                        $queryx->$where('promo_code', $value['operator'], $value['parameter']);
                        break;

                        case 'receipt_number':
                        $foreign['transactions']=['transactions','transactions.id_transaction','=','promo_campaign_reports.id_transaction'];
                        $queryx->$where('transaction_receipt_number', $value['operator'], $value['parameter']);
                        break;

                        case 'outlet_count':
                        if(!$outletCount){
                            $query->addSelect('outlet_total');
                            $outletCount=1;
                        }
                        $foreign['t2']=[\DB::raw('(SELECT COUNT(*) AS outlet_total, id_outlet FROM `promo_campaign_reports` WHERE id_promo_campaign = '.$request->json('id_promo_campaign').' GROUP BY id_outlet) AS `t2`'),'promo_campaign_reports.id_outlet','=','t2.id_outlet'];
                        $queryx->$where('outlet_total', $value['operator'], $value['parameter']);
                        break;


                        case 'user_count':
                        if(!$userCount){
                            $query->addSelect('user_total');
                            $userCount=1;
                        }
                        $foreign['t3']=[\DB::raw('(SELECT COUNT(*) AS user_total, id_user FROM `promo_campaign_reports` WHERE id_promo_campaign = '.$request->json('id_promo_campaign').' GROUP BY id_user) AS `t3`'),'promo_campaign_reports.id_user','=','t3.id_user'];
                        $queryx->$where('user_total', $value['operator'], $value['parameter']);
                        break;

                        default:
                            # code...
                        break;
                    }
                }
                $return[] = $value;
            }
        });
        return ['filter' => $return, 'filter_operator' => $request->json('operator')];
    }

    public function Coupon(Request $request)
    {
        $post = $request->json()->all();

        $query = PromoCampaignPromoCode::select('promo_campaign_promo_codes.*', 'promo_campaigns.limitation_usage')
                ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
                ->where('promo_campaign_promo_codes.id_promo_campaign', $post['id_promo_campaign']);

        $filter = null;
        $count = (new PromoCampaignPromoCode)->newQuery()
                    ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
                    ->where('promo_campaign_promo_codes.id_promo_campaign', $post['id_promo_campaign']);
        $total = (new PromoCampaignPromoCode)->newQuery()
                    ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
                    ->where('promo_campaign_promo_codes.id_promo_campaign', $post['id_promo_campaign']);
        $foreign=[];
        $foreign2=[];
        if($post['rule2']??false){
            $this->filterCoupon($query,$request,$foreign);
            $this->filterCoupon($count,$request,$foreign2);
        }
        $column = ['promo_code','status','usage','available','limitation_usage'];
        if($post['start']){
            $query->skip($post['start']);
        }
        if($post['length']>0){
            $query->take($post['length']);
        }
        foreach ($post['order'] as $value) {
            switch ($column[$value['column']]) {
                case 'status':
                case 'available':
                $query->orderBy('usage',$value['dir']);
                break;

                case 'limitation_usage':
                $query->orderBy('limitation_usage',$value['dir']);
                break;

                default:
                $query->orderBy('promo_campaign_promo_codes.'.$column[$value['column']],$value['dir']);
                break;
            }
        }
        foreach ($foreign as $value) {
            $query->leftJoin(...$value);
        }
        foreach ($foreign2 as $value) {
            $count->leftJoin(...$value);
        }

        $query = $query->get()->toArray();
        $count = $count->get()->count();
        $total = $total->get()->count();

        if (isset($query) && !empty($query)) {
            $result = [
                'status'  => 'success',
                'result'  => $query,
                'total'  => $total,
                'count'  => $count
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'message'  => ['No Report']
            ];
        }
        return response()->json($result);
    }

    protected function filterCoupon($query, $request,&$foreign='')
    {
        $query->groupBy('promo_campaign_promo_codes.id_promo_campaign_promo_code');
        $allowed = array(
            'operator' => ['=', 'like', '<', '>', '<=', '>='],
            'subject' => ['coupon_code','status','used','available','max_used'],
        );
        $return = [];
        $where = $request->json('operator2') == 'or' ? 'orWhere' : 'where';
        $whereRaw = $request->json('operator2') == 'or' ? 'orWhereRaw' : 'whereRaw';
        $rule = $request->json('rule2');
        $query->where(function($queryx) use ($rule,$allowed,$where,$query,&$foreign,$request,$whereRaw){
            $foreign=array();
            $outletCount=0;
            $userCount=0;
            foreach ($rule??[] as $value) {
                if (!in_array($value['subject'], $allowed['subject'])) {
                    continue;
                }
                if (!(isset($value['operator']) && $value['operator'] && in_array($value['operator'], $allowed['operator']))) {
                    $value['operator'] = '=';
                }
                if ($value['operator'] == 'like') {
                    $value['parameter'] = '%' . $value['parameter'] . '%';
                }
                switch ($value['subject']) {
                    case 'coupon_code':
                    $queryx->$where('promo_code', $value['operator'], $value['parameter']);
                    break;

                    case 'status':
                    if ($value['parameter'] == 'Not used')
                    {
                        $queryx->$where('usage', '=', 0);
                    }
                    elseif( $value['parameter'] == 'Used' )
                    {
                        $queryx->$where('usage', '=', 'limitation_usage');
                    }
                    else
                    {
                        $queryx->$where('usage', '!=', 0)->$where('usage', '!=', 'limitation_usage');
                    }

                    break;

                    case 'used':
                    $queryx->$where('usage', $value['operator'], $value['parameter']);
                    break;

                    case 'available':
                    $queryx->$whereRaw('limitation_usage - promo_campaign_promo_codes.usage '.$value['operator'].' '.$value['parameter']);
                    break;

                    case 'max_used':
                    $queryx->$where('limitation_usage', $value['operator'], $value['parameter']);
                    break;

                    default:
                        # code...
                    break;
                }

                $return[] = $value;
            }
        });
        return ['filter' => $return, 'filter_operator' => $request->json('operator')];
    }

    public function getTag(Request $request)
    {
        $post = $request->json()->all();

        $data = PromoCampaignTag::get()->toArray();

        return response()->json($data);
    }

    public function check(Request $request)
    {
        $post = $request->json()->all();

        if ($post['type_code'] == 'single') {
            $query = PromoCampaignPromoCode::where('promo_code', '=', $post['search_code']);
        } else {
            $query = PromoCampaign::where('prefix_code', '=', $post['search_code']);
        }

        if (is_numeric($request->promo_id)) {
        	$query = $query->where('id_promo_campaign', '!=', $request->promo_id);
        }
        $checkCode = $query->first();

        if ($checkCode) {
            $result = [
                'status'  => 'not available'
            ];
        } else {
            $result = [
                'status'  => 'available'
            ];
        }
        return response()->json($result);
    }

    public function step1(Step1PromoCampaignRequest $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $post['prefix_code'] = strtoupper($post['prefix_code']);

        $post['date_start'] = $this->generateDate($post['date_start']);
        $post['date_end']   = $this->generateDate($post['date_end']);

        if ($post['code_type'] == 'Multiple') {

        	$max_char_digit = 28;
        	$max_coupon_posibility = pow($max_char_digit, $post['number_last_code']);
            if ( $max_coupon_posibility < $post['total_coupon'] ) {
            	$result = [
                    'status'  => 'fail',
                    'messages'  => ['Total Coupon must be equal or less than total Generate random code']
                ];
                return $result;
            }
            $allow_char = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            for ($i=0; isset($post['prefix_code'][$i]) ; $i++) {
            	$strpos = strpos($allow_char, $post['prefix_code'][$i]);
            	if ($strpos === false) {
            		// return [$post['prefix_code'][$i]];
            		$result =  [
	                    'status'  => 'fail',
	                    'messages'  => ['Prefix code must be alphanumeric']
	                ];
                	return response()->json($result);
            	}
            }
        }
        
        DB::beginTransaction();

        $days = [];
        if (isset($post['selected_day'])) {
            $days = $post['selected_day'];
            unset($post['selected_day']);
        }

        if (isset($post['id_promo_campaign'])) {
            $post['last_updated_by'] = $user['id'];
            $datenow = date("Y-m-d H:i:s");
            $checkData = PromoCampaign::with([
            				'promo_campaign_have_tags.promo_campaign_tag',
            				'promo_campaign_promo_codes' => function($q) {
            					$q->limit(1);
            				},
            				'promo_campaign_reports' => function($q) {
            					$q->limit(1);
            				}
            			])
            			->where('id_promo_campaign', '=', $post['id_promo_campaign'])
            			->first();

            if (!$checkData) {
				return  [
                    'status'  => 'fail',
                    'message'  => ['Promo Campaign not found']
                ];
			}
            $forSelected = $checkData;

			if ($checkData->promo_campaign_reports[0]??false) {
           		return response()->json([
                    'status'  => 'fail',
                    'messages'  => ['Cannot update, promo already used']
                ]);
           	}

            if ($checkData->product_type != $post['product_type']) {

				$delete_rule = $this->deleteAllProductRule('promo_campaign', $post['id_promo_campaign']);
				// $delete_outlet_rule = $this->deleteOutletRule('promo_campaign', $post['id_promo_campaign']);
				if (!$delete_rule) {
	           		return response()->json([
	                    'status'  => 'fail',
	                    'messages'  => ['Update Failed']
	                ]);
	           	}
			}

            if ($checkData['code_type'] == 'Single') {
                $checkData['promo_code'] = $checkData->promo_campaign_promo_codes[0]->promo_code;
            }

            if($checkData['is_all_days'] != $post['is_all_days'] || $post['is_all_days'] == 1){
                PromoCampaignDay::where('id_promo_campaign', $checkData['id_promo_campaign'])->delete();
            }

            if ($checkData['code_type'] != $post['code_type'] ||
            	$checkData['prefix_code'] != $post['prefix_code'] ||
            	$checkData['number_last_code'] != $post['number_last_code'] ||
            	$checkData['promo_code'] != $post['promo_code'] ||
            	$checkData['total_coupon'] != $post['total_coupon']
            	)
            {

                $promo_code = $post['promo_code']??null;

                unset($post['promo_code']);

                if (isset($post['promo_tag'])) {
                    $insertTag = $this->insertTag('update', $post['id_promo_campaign'], $post['promo_tag']);
                    unset($post['promo_tag']);
                }
                
                $promoCampaign = PromoCampaign::where('id_promo_campaign', '=', $post['id_promo_campaign'])->updateWithUserstamps($post);

                if (!$promoCampaign) {
                	DB::rollBack();
                	return response()->json([
	                    'status'  => 'fail',
	                    'messages'  => ['Update Failed']
	                ]);
                }
                
                $generateCode = $this->generateCode('update', $post['id_promo_campaign'], $post['code_type'], $promo_code, $post['prefix_code'], $post['number_last_code'], $post['total_coupon']);

                if ($generateCode['status'] == 'success') {
                    $result = [
                        'status'  => 'success',
                        'result'  => 'Update Promo Campaign & Promo Code Success',
                        'promo-campaign'  => $post
                    ];
                } else {

                    DB::rollBack();
                    $result = [
                        'status'  => 'fail',
                        'message'  => ['Create Another Unique Promo Code']
                    ];
                }
            } else {

                $promo_code = $post['promo_code']??null;
                if (isset($post['promo_code']) || $post['promo_code'] == null) {
                    unset($post['promo_code']);
                }

                if (isset($post['promo_tag'])) {
                    $insertTag = $this->insertTag('update', $post['id_promo_campaign'], $post['promo_tag']);
                    unset($post['promo_tag']);
                }

                $promoCampaign = PromoCampaign::where('id_promo_campaign', '=', $post['id_promo_campaign'])->first();
                $promoCampaignUpdate = $promoCampaign->update($post);
                $generateCode = $this->generateCode('update', $post['id_promo_campaign'], $post['code_type'], $promo_code, $post['prefix_code'], $post['number_last_code'], $post['total_coupon']);


                if ($promoCampaignUpdate == 1) {
                    $promoCampaign = $promoCampaign->toArray();

                    $result = [
                        'status'  => 'success',
                        'result'  => 'Promo Campaign has been updated',
                        'promo-campaign'  => $post
                    ];
                    $send = app($this->autocrm)->SendAutoCRM('Update Promo Campaign', $user['phone'], [
                        'campaign_name' => $promoCampaign['campaign_name']?:'',
                        'promo_title' => $promoCampaign['promo_title']?:'',
                        'code_type' => $promoCampaign['code_type']?:'',
                        'prefix_code' => $promoCampaign['prefix_code']?:'',
                        'number_last_code' => $promoCampaign['number_last_code']?:'',
                        'total_coupon' => number_format($promoCampaign['total_coupon'],0,',','.')?:'',
                        'created_at' => date('d F Y H:i',strtotime($promoCampaign['created_at']))?:'',
                        'updated_at' => date('d F Y H:i',strtotime($promoCampaign['updated_at']))?:'',
                        'detail' => view('promocampaign::emails.detail',['detail'=>$promoCampaign])->render()
                    ] + $promoCampaign,null,true);
                } else {
                    DB::rollBack();
                    $result = ['status'  => 'fail'];
                }
            }

        } else {
            $post['created_by'] = $user['id'];
            if ($post['code_type'] == 'Single') {
                $post['prefix_code'] = null;
                $post['number_last_code'] = null;
            } else {
                $post['promo_code'] = null;
            }

            if ($post['date_start'] <= date("Y-m-d H:i:s")) {
                $start_date = new \DateTime($post['date_start']);
                $diff_date = $start_date->diff(new \DateTime($post['date_end']));

                $date_end = new \DateTime(date("Y-m-d H:i:s"));
                $date_end->add(new \DateInterval($diff_date->format('P%yY%mM%dDT%hH%iM%sS')));

                $post['date_start'] = date("Y-m-d H:i:s");
                $post['date_end']   = $date_end->format('Y-m-d H:i:s');
            }

            $promoCampaign = PromoCampaign::create($post);
            $forSelected = $promoCampaign;
            $generateCode = $this->generateCode('insert', $promoCampaign['id_promo_campaign'], $post['code_type'], $post['promo_code'], $post['prefix_code'], $post['number_last_code'], $post['total_coupon']);
            if (isset($post['promo_tag'])) {
                $insertTag = $this->insertTag(null, $promoCampaign['id_promo_campaign'], $post['promo_tag']);
            }

            $post['id_promo_campaign'] = $promoCampaign['id_promo_campaign'];

            if ($generateCode['status'] == 'success') {
                $result = [
                    'status'  => 'success',
                    'result'  => 'Creates Promo Campaign & Promo Code Success',
                    'promo-campaign'  => $post
                ];
                $promoCampaign = $promoCampaign->toArray();
                $send = app($this->autocrm)->SendAutoCRM('Create Promo Campaign', $user['phone'], [
                    'campaign_name' => $promoCampaign['campaign_name']?:'',
                    'promo_title' => $promoCampaign['promo_title']?:'',
                    'code_type' => $promoCampaign['code_type']?:'',
                    'prefix_code' => $promoCampaign['prefix_code']?:'',
                    'number_last_code' => $promoCampaign['number_last_code']?:'',
                    'total_coupon' => number_format($promoCampaign['total_coupon'],0,',','.')?:'',
                    'created_at' => date('d F Y H:i',strtotime($promoCampaign['created_at']))?:'',
                    'updated_at' => date('d F Y H:i',strtotime($promoCampaign['updated_at']))?:'',
                    'detail' => view('promocampaign::emails.detail',['detail'=>$promoCampaign])->render()
                ] + $promoCampaign,null,true);
            } else {
                DB::rollBack();
                $result = [
                    'status'  => 'fail',
                    'message'  => ['Create Another Unique Promo Code']
                ];
            }
        }

        if($post['is_all_days'] == 0 && isset($post['selected_day'])){
            $saveDays = $this->selectedDays($forSelected, $post['selected_day']);
            if (!$saveDays) {
                DB::rollBack();
                $result = [
                    'status'  => 'fail',
                    'message'  => ['Failed to create promo days']
                ];
            }
        }

        DB::commit();
        return response()->json($result);
    }

    public function selectedDays($promo_campaign, $selected_days){
    		
        $table = new PromoCampaignDay;
    	$id_promo_campaign = $promo_campaign->id_promo_campaign;

    	$delete = $table::where('id_promo_campaign', $id_promo_campaign)->delete();

        $data_days = [];

        foreach ($selected_days as $value) {
            array_push($data_days, [
                'id_promo_campaign'  => $id_promo_campaign,
                'day' 	=> $value
            ]);
        }

        if (!empty($data_days)) {
            $save = $table::insert($data_days);
            return $save;
        } else {
            return false;
        }

        return true;
    }

    public function step2(Step2PromoCampaignRequest $request)
    {
        $post = $request->json()->all();
        $post['promo_type'] = $post['promo_type']??null;
        $user = $request->user();

        if (!empty($post['id_deals'])) {
        	if ( $post['deals_type'] != 'Promotion' ) {
        		$source = 'deals';
	        	$table = new Deal;
	        	$id_table = 'id_deals';
	        	$id_post = $post['id_deals'];
	        	$error_message = 'Deals';
	        	$warning_image = 'deals';
        	}else {
	        	$source = 'deals_promotion';
	        	$table = new DealsPromotionTemplate;
	        	$id_table = 'id_deals_promotion_template';
	        	$id_post = $post['id_deals'];
	        	$error_message = 'Deals';
	        	$warning_image = 'deals';
	        }
        }else{
        	$source = 'promo_campaign';
        	$table = new PromoCampaign;
        	$id_table = 'id_promo_campaign';
        	$id_post = $post['id_promo_campaign'];
	        $warning_image = 'promo_campaign';
        	$error_message = 'Deals';
        }

        DB::beginTransaction();
        $dataPromoCampaign['promo_type'] = $post['promo_type'];
        if ($source == 'promo_campaign') {
        	$saveImagePath = 'img/promo-campaign/warning-image/';
        	$dataPromoCampaign['step_complete'] = 1;
	        $dataPromoCampaign['last_updated_by'] = $user['id'];
	        $dataPromoCampaign['user_type'] = $post['filter_user'];
	        $dataPromoCampaign['specific_user'] = $post['specific_user']??null;
	        $dataPromoCampaign['min_basket_size'] = $post['min_basket_size']??null;

	        if ($post['filter_outlet'] == 'All Outlet')
	        {
	            $createFilterOutlet = $this->createOutletFilter('all_outlet', 1, $post['id_promo_campaign'], null);
	        }
	        elseif ($post['filter_outlet'] == 'Selected')
	        {
	            $createFilterOutlet = $this->createOutletFilter('selected', 0, $post['id_promo_campaign'], $post['multiple_outlet']);
	        }
	        else
	        {
	            $createFilterOutlet = [
	                'status'  => 'fail',
	                'message' => 'Create Filter Outlet Failed'
	            ];
	            DB::rollBack();
	            return response()->json($createFilterOutlet);
	        }
        }
        else
        {
        	$saveImagePath = 'img/deals/warning-image/';
        	$dataPromoCampaign['deals_promo_id_type']	    = $post['deals_promo_id_type']??null;
        	$dataPromoCampaign['deals_promo_id']		    = $dataPromoCampaign['deals_promo_id_type'] == 'nominal' ? $post['deals_promo_id_nominal'] : ($post['deals_promo_id_promoid']??null);
        	$dataPromoCampaign['last_updated_by'] 		    = auth()->user()->id;
        	$dataPromoCampaign['step_complete']			    = 0;
        	$dataPromoCampaign['min_basket_size'] 		    = $post['min_basket_size']??null;
        	$dataPromoCampaign['autoresponse_inbox'] 		= $post['autoresponse_inbox']??null;
        	$dataPromoCampaign['autoresponse_notification'] = $post['autoresponse_notification']??null;
        }

		$image = $table::where($id_table, $id_post)->first();

		if (!empty($post['id_deals'])) {
			if (!empty($image['deals_total_claimed']) ) {
				return [
	                'status'  => 'fail',
	                'message' => 'Cannot update deals because someone has already claimed a voucher'
	            ];
			}
		}
        if (isset($post['promo_warning_image']) && empty($post['use_global'])) {
			$img_name = rand(10,99).$id_post.rand(10,99).'-'.time();
			$upload = MyHelper::uploadPhotoStrict($post['promo_warning_image'], $saveImagePath, 100, 100, $img_name, '.png');


			if (isset($upload['status']) && $upload['status'] == "success") {
				if(isset($image[$warning_image.'_warning_image']) && file_exists($image[$warning_image.'_warning_image'])){
					unlink($image[$warning_image.'_warning_image']);
				}
				$dataPromoCampaign[$warning_image.'_warning_image'] = $upload['path'];
			}
			else {
				$result = [
					'error'    => 1,
					'status'   => 'fail',
					'messages' => ['fail upload image']
				];

				return $result;
			}
		}else{
			if (!empty($post['use_global'])) {
				$dataPromoCampaign[$warning_image.'_warning_image'] = null;
			}
			elseif(isset($image[$warning_image.'_warning_image']) && file_exists($image[$warning_image.'_warning_image'])){
				$dataPromoCampaign[$warning_image.'_warning_image'] = $image[$warning_image.'_warning_image'];
			}
			else{
				$dataPromoCampaign[$warning_image.'_warning_image'] = null;
			}
		}

        $update = $table::where($id_table, $id_post)->updateWithUserstamps($dataPromoCampaign);

		if( ($post['promo_type'] ?? false) == 'Discount delivery'){
        	$update_shipment_rule = $this->createShipmentRule($source, $id_table, $id_post, $post);

	        if ($update_shipment_rule['status'] != 'success') {
	        	return $update_shipment_rule;
	        }
		}

        if ($post['promo_type'] == 'Product Discount') {
            if ($post['filter_product'] == 'All Product') {
                $createFilterProduct = $this->createProductFilter('all_product', 1, $id_post, null, $post['discount_type'], $post['discount_value'], $post['max_product'], $post['max_percent_discount'], $post['product_type'], $source, $table, $id_table);
            } elseif ($post['filter_product'] == 'Selected') {
                $createFilterProduct = $this->createProductFilter('selected', 0, $id_post, $post['multiple_product'], $post['discount_type'], $post['discount_value'], $post['max_product'], $post['max_percent_discount'], $post['product_type'], $source, $table, $id_table);
            } else {
                $createFilterProduct = [
                    'status'  => 'fail',
                    'messages' => 'Create Promo Type Failed'
                ];
                DB::rollBack();
                return response()->json($createFilterProduct);
            }
        } elseif ($post['promo_type'] == 'Tier discount') {

            try {
                $createFilterProduct = $this->createPromoTierDiscount($id_post, array($post['product']), $post['discount_type'], $post['promo_rule'], $post['product_type'], $source, $table, $id_table);
            } catch (Exception $e) {
                $createFilterProduct = [
                    'status'  => 'fail',
                    'messages' => 'Create Promo Type Failed'
                ];
                DB::rollBack();
                return response()->json($createFilterProduct);
            }

        } elseif ($post['promo_type'] == 'Buy X Get Y') {
            try {
                $createFilterProduct = $this->createBuyXGetYDiscount($id_post, $post['product'], $post['promo_rule'], $post['product_type'], $source, $table, $id_table);

            } catch (Exception $e) {
                $createFilterProduct = [
                    'status'  => 'fail',
                    'messages' => 'Create Promo Type Failed'
                ];
                DB::rollBack();
                return response()->json($createFilterProduct);
            }
        } elseif ($post['promo_type'] == 'Promo Product Category' || $post['promo_type'] == 'Voucher Product Category' ) {
            try {
                $createFilterProduct = $this->createProductCategoryDiscount($id_post, $post['category_product'], $post['id_product_variant']??null, $post['promo_rule'], $post['product_type'], $post['auto_apply']??0, $source, $table, $id_table, $post['product_variants']??null);

            } catch (Exception $e) {
                $createFilterProduct = [
                    'status'  => 'fail',
                    'messages' => 'Create Promo Type Failed'
                ];
                DB::rollBack();
                return response()->json($createFilterProduct);
            }
        } elseif ($post['promo_type'] == 'Discount delivery') {
            try {
                $createFilterProduct = $this->createDiscountDelivery($id_post, $post['discount_type'], $post['discount_value'], $post['max_percent_discount'], $source, $table, $id_table);
            } catch (Exception $e) {
                $createFilterProduct = [
                    'status'  => 'fail',
                    'messages' => 'Create Promo Type Failed'
                ];
                DB::rollBack();
                return response()->json($createFilterProduct);
            }
        }else {
        	$createFilterProduct = $this->deleteAllProductRule($source, $id_post);
        	if ($createFilterProduct) {
    	    	$createFilterProduct = ['status' => 'success'];
    	    }else{
    	    	$createFilterProduct = [
                    'status'  => 'fail',
                    'messages' => 'Create Promo Type Failed'
                ];
                DB::rollBack();
                return response()->json($createFilterProduct);
    	    }
        }

        DB::commit();
        return response()->json($createFilterProduct);
    }

    function createOutletFilter($parameter, $operator, $id_promo_campaign, $outlet)
    {
        if (PromoCampaignOutlet::where('id_promo_campaign', '=', $id_promo_campaign)->exists()) {
            PromoCampaignOutlet::where('id_promo_campaign', '=', $id_promo_campaign)->delete();
        }

        if ($parameter == 'all_outlet') {
            try {
                PromoCampaign::where('id_promo_campaign', '=', $id_promo_campaign)->updateWithUserstamps(['is_all_outlet' => $operator]);
                $result = ['status'  => 'success'];
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Create Filter Outlet Failed'
                ];
                DB::rollBack();
                return response()->json($result);
            }
        } else {
            $dataOutlet = [];
            for ($i = 0; $i < count($outlet); $i++) {
                $dataOutlet[$i]['id_outlet']            = array_values($outlet)[$i];
                $dataOutlet[$i]['id_promo_campaign']    = $id_promo_campaign;
                $dataOutlet[$i]['created_at']           = date('Y-m-d H:i:s');
                $dataOutlet[$i]['updated_at']           = date('Y-m-d H:i:s');
                $dataOutlet[$i]['created_by']           = Auth::id();
                $dataOutlet[$i]['updated_by']           = Auth::id();
            }
            try {
                PromoCampaignOutlet::insert($dataOutlet);
                PromoCampaign::where('id_promo_campaign', '=', $id_promo_campaign)->updateWithUserstamps(['is_all_outlet' => $operator]);
                $result = ['status'  => 'success'];
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Create Filter Outlet Failed'
                ];
                DB::rollBack();
                return response()->json($result);
            }
        }
        return $result;
    }

    function createShipmentRule($source, $id_table, $id_post, $post)
    {
    	if ($source == 'promo_campaign') 
    	{
	        $table_shipment = new PromoCampaignShipmentMethod;
	        $table_promo = new PromoCampaign;
    	}
    	elseif ($source == 'deals') 
    	{
	        $table_shipment = new DealsShipmentMethod;
	        $table_promo = new Deal;
    	}
    	elseif ($source == 'deals_promotion')
    	{
    		$table_shipment = new DealsPromotionShipmentMethod;
	        $table_promo = new DealsPromotionTemplate;
	        $id_table = 'id_deals';
    	}

        $table_shipment::where($id_table, '=', $id_post)->delete();

        if ($post['promo_type'] == 'Discount delivery') {
        	$post['filter_shipment'] = 'selected_shipment';
        	$post['shipment_method'] = $post['shipment_method'] ?? [];
        }

        if ($post['filter_shipment'] == 'all_shipment') {
            try {
            	if ($source == 'deals_promotion') {
            		$id_table = 'id_deals_promotion_template';
            	}
                $table_promo::where($id_table, '=', $id_post)->update(['is_all_shipment' => 1]);
                $result = ['status'  => 'success'];
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Create Shipment Rule Failed'
                ];
                return $result;
            }
        } else {

            $data_shipment = [];
            foreach ($post['shipment_method'] as $key => $value) {
            	if ($value == 'Pickup Order' && $post['promo_type'] == 'Discount delivery') {
            		continue;
            	}
            	$temp_data = [
	                $id_table => $id_post,
	            	'shipment_method' => $value,
	                'created_at' => date('Y-m-d H:i:s'),
	                'updated_at' => date('Y-m-d H:i:s')
            	];
            	$data_shipment[] = $temp_data;
            }

            if (empty($data_shipment)) {
            	if ($post['promo_type'] != 'Discount delivery') {
            		$delivery_pickup = [
		                $id_table => $id_post,
		            	'shipment_method' => 'Pickup Order',
		                'created_at' => date('Y-m-d H:i:s'),
		                'updated_at' => date('Y-m-d H:i:s')
	            	];
            		$data_shipment[] = $delivery_pickup;
            	}

            	$availableDelivery = config('delivery_method');

		        $setting  = json_decode(MyHelper::setting('active_delivery_methods', 'value_text', '[]'), true) ?? [];
		        $deliveries = [];

		        foreach ($setting as $value) {

		            $delivery = $availableDelivery[$value['code'] ?? ''] ?? false;

		            if ( !$delivery || !($delivery['status'] ?? false) ) {
		                unset($availableDelivery[$value['code']]);
		                continue;
		            }

		            $data_shipment[] = [
		                $id_table => $id_post,
		            	'shipment_method' => $value['code'],
		                'created_at' => date('Y-m-d H:i:s'),
		                'updated_at' => date('Y-m-d H:i:s')
	            	];
		        }
            }

            try {
            	if ($source == 'deals_promotion') {
            		$id_table = 'id_deals_promotion_template';
            	}
                $table_promo::where($id_table, '=', $id_post)->update(['is_all_shipment' => 0]);
                $table_shipment::insert($data_shipment);
                $result = ['status'  => 'success'];
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Create Shipment Rule Failed'
                ];
                return $result;
            }
        }
        return $result;
    }

    public function deleteAllProductRule($source, $id_post)
    {
    	try {

	    	if ($source == 'promo_campaign')
	    	{
		        PromoCampaignProductDiscountRule::where('id_promo_campaign', '=', $id_post)->delete();
		        PromoCampaignTierDiscountRule::where('id_promo_campaign', '=', $id_post)->delete();
		        PromoCampaignBuyxgetyRule::where('id_promo_campaign', '=', $id_post)->delete();
		        PromoCampaignProductCategoryRule::where('id_promo_campaign', '=', $id_post)->delete();
		        PromoCampaignDiscountDeliveryRule::where('id_promo_campaign', '=', $id_post)->delete();

		        PromoCampaignTierDiscountProduct::where('id_promo_campaign', '=', $id_post)->delete();
		        PromoCampaignProductDiscount::where('id_promo_campaign', '=', $id_post)->delete();
		        PromoCampaignBuyxgetyProductRequirement::where('id_promo_campaign', '=', $id_post)->delete();
		        $product_cat = PromoCampaignProductCategoryProductRequirement::where('id_promo_campaign', '=', $id_post)->first();
                if($product_cat){
                    CategoryPromoCampaignProductCategoryProductRequirement::where('id_promo_campaign_productcategory_category_requirement',$product_cat['id_promo_campaign_productcategory_category_requirement'])->delete();
                }
                PromoCampaignProductCategoryProductRequirement::where('id_promo_campaign', '=', $id_post)->delete();

	    	}
	    	elseif ($source == 'deals')
	    	{
	    		DealsProductDiscountRule::where('id_deals', '=', $id_post)->delete();
		        DealsTierDiscountRule::where('id_deals', '=', $id_post)->delete();
		        DealsBuyxgetyRule::where('id_deals', '=', $id_post)->delete();
				DealsDiscountDeliveryRule::where('id_deals', '=', $id_post)->delete();
		        DealsProductCategoryRule::where('id_deals', '=', $id_post)->delete();

		        DealsTierDiscountProduct::where('id_deals', '=', $id_post)->delete();
		        DealsProductDiscount::where('id_deals', '=', $id_post)->delete();
		        DealsBuyxgetyProductRequirement::where('id_deals', '=', $id_post)->delete();
                $product_cat = DealsProductCategoryProductRequirement::where('id_deals', '=', $id_post)->first();
                if($product_cat){
                    CategoryDealsProductCategoryProductRequirement::where('id_deals_productcategory_category_requirement',$product_cat['id_deals_productcategory_category_requirement'])->delete();
                }
                DealsProductCategoryProductRequirement::where('id_deals', '=', $id_post)->delete();


	    	}
	    	elseif ($source == 'deals_promotion')
	    	{
	    		DealsPromotionProductDiscountRule::where('id_deals', '=', $id_post)->delete();
		        DealsPromotionTierDiscountRule::where('id_deals', '=', $id_post)->delete();
		        DealsPromotionBuyxgetyRule::where('id_deals', '=', $id_post)->delete();
				DealsPromotionDiscountDeliveryRule::where('id_deals', '=', $id_post)->delete();

		        DealsPromotionTierDiscountProduct::where('id_deals', '=', $id_post)->delete();
		        DealsPromotionProductDiscount::where('id_deals', '=', $id_post)->delete();
		        DealsPromotionBuyxgetyProductRequirement::where('id_deals', '=', $id_post)->delete();

	    	}

	    	return true;
    	} catch (Exception $e) {
    		return false;
    	}
    }

    public function createProductFilter($parameter, $operator, $id_post, $product, $discount_type, $discount_value, $max_product, $max_percent_discount, $product_type, $source, $table, $id_table)
    {
    	$delete_rule = $this->deleteAllProductRule($source, $id_post);

    	if (!$delete_rule) {
    		$result = [
                'status'  => 'fail',
                'message' => 'Create Filter Product Failed'
            ];
            DB::rollBack();
            return response()->json($result);
    	}

    	if ($source == 'promo_campaign')
    	{
	        $table_product_discount_rule = new PromoCampaignProductDiscountRule;
	        $table_product_discount = new PromoCampaignProductDiscount;
    	}
    	elseif ($source == 'deals')
    	{
	        $table_product_discount_rule = new DealsProductDiscountRule;
	        $table_product_discount = new DealsProductDiscount;
    	}
    	elseif ($source == 'deals_promotion')
    	{
    		$table_product_discount_rule = new DealsPromotionProductDiscountRule;
	        $table_product_discount = new DealsPromotionProductDiscount;
	        $id_table = 'id_deals';
    	}

    	if ($discount_type == 'Nominal') {
        	$max_percent_discount = NULL;
        }

        if ($discount_type == 'Nominal') {
        	$max_percent_discount = NULL;
        }

        $data = [

            $id_table => $id_post,
            'is_all_product'    		=> $operator,
            'discount_type'     		=> $discount_type,
            'discount_value'    		=> $discount_value,
            'max_product'       		=> $max_product,
            'max_percent_discount'      => $max_percent_discount,
            'created_at'        		=> date('Y-m-d H:i:s'),
            'updated_at'        		=> date('Y-m-d H:i:s'),
            'created_by'        		=> Auth::id(),
            'updated_by'        		=> Auth::id()
        ];
        if ($parameter == 'all_product') {
            try {
                $table_product_discount_rule::insert($data);
                $result = ['status'  => 'success'];
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Create Filter Product Failed'
                ];
                DB::rollBack();
                return response()->json($result);
            }
        } else {
            $dataProduct = [];
            for ($i = 0; $i < count($product); $i++) {
                $dataProduct[$i]['id_product']           = array_values($product)[$i];
                $dataProduct[$i][$id_table]    			 = $id_post;
                $dataProduct[$i]['product_type']		 = $product_type;
                $dataProduct[$i]['created_at']           = date('Y-m-d H:i:s');
                $dataProduct[$i]['updated_at']           = date('Y-m-d H:i:s');
                $dataProduct[$i]['created_by']           = Auth::id();
                $dataProduct[$i]['updated_by']           = Auth::id();
            }

            try {
                $table_product_discount_rule::insert($data);
                $table_product_discount::insert($dataProduct);
                $result = ['status'  => 'success'];
            } catch (\Exception $e) {
                $result = [
                    'status'  => 'fail',
                    'message' => 'Create Filter Product Failed'
                ];
                DB::rollBack();
                return response()->json($result);
            }
        }
        return $result;
    }

    public function createPromoTierDiscount($id_post, $product, $discount_type, $rules, $product_type, $source, $table, $id_table)
    {
        if (!$rules) {
            return [
                'status'  => 'fail',
                'message' => 'Rule empty'
            ];
        }

        $delete_rule = $this->deleteAllProductRule($source, $id_post);

    	if (!$delete_rule) {
    		$result = [
                'status'  => 'fail',
                'message' => 'Create Filter Product Failed'
            ];
            DB::rollBack();
            return response()->json($result);
    	}

    	if ($source == 'promo_campaign')
    	{
	        $table_tier_discount_rule = new PromoCampaignTierDiscountRule;
	        $table_tier_discount_product = new PromoCampaignTierDiscountProduct;
    	}
    	elseif ($source == 'deals')
    	{
	        $table_tier_discount_rule = new DealsTierDiscountRule;
	        $table_tier_discount_product = new DealsTierDiscountProduct;
    	}
    	elseif ($source == 'deals_promotion')
    	{
    		$table_tier_discount_rule = new DealsPromotionTierDiscountRule;
	        $table_tier_discount_product = new DealsPromotionTierDiscountProduct;
	        $id_table = 'id_deals';
    	}

    	if ($discount_type == 'Nominal') {
        	$is_nominal = 1;
        }else{
        	$is_nominal = 0;
        }

        if ($discount_type == 'Nominal') {
        	$is_nominal = 1;
        }else{
        	$is_nominal = 0;
        }

        $data = [];
        foreach ($rules as $key => $rule) {
            $data[$key] = [
                $id_table => $id_post,
                'discount_type'     => $discount_type,
                'max_qty'           => $rule['max_qty'],
                'min_qty'           => $rule['min_qty'],
                'discount_value'    => $rule['discount_value'],
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
                'created_by'        => Auth::id(),
            	'updated_by'        => Auth::id()
            ];
	        if ($is_nominal) {
	        	$data[$key]['max_percent_discount'] = null;
	        }else{
	        	$data[$key]['max_percent_discount'] = $rule['max_percent_discount'];
	        }
        }

        $dataProduct = [];
        for ($i = 0; $i < count($product); $i++) {
            $dataProduct[$i]['id_product']           = array_values($product)[$i];
            $dataProduct[$i][$id_table]    			 = $id_post;
            $dataProduct[$i]['product_type']    	 = $product_type;
            $dataProduct[$i]['created_at']           = date('Y-m-d H:i:s');
            $dataProduct[$i]['updated_at']           = date('Y-m-d H:i:s');
            $dataProduct[$i]['created_by']           = Auth::id();
            $dataProduct[$i]['updated_by']           = Auth::id();
        }

        try {
            $table_tier_discount_rule::insert($data);
            $table_tier_discount_product::insert($dataProduct);
            $result = ['status'  => 'success'];
        } catch (\Exception $e) {
            $result = [
                'status'  => 'fail',
                'message' => 'Create Filter Product Failed'
            ];
            DB::rollBack();
            return response()->json($result);
        }
        return $result;
    }

    public function createBuyXGetYDiscount($id_post, $product, $rules, $product_type, $source, $table, $id_table)
    {
        if (!$rules) {
            return [
                'status'  => 'fail',
                'message' => 'Rule empty'
            ];
        }
        $delete_rule = $this->deleteAllProductRule($source, $id_post);

    	if (!$delete_rule) {
    		$result = [
                'status'  => 'fail',
                'message' => 'Create Filter Product Failed'
            ];
            DB::rollBack();
            return response()->json($result);
    	}

    	if ($source == 'promo_campaign')
    	{
	        $table_buyxgety_discount_rule = new PromoCampaignBuyxgetyRule;
	        $table_buyxgety_discount_product = new PromoCampaignBuyxgetyProductRequirement;
    	}
    	elseif ($source == 'deals')
    	{
	        $table_buyxgety_discount_rule = new DealsBuyxgetyRule;
	        $table_buyxgety_discount_product = new DealsBuyxgetyProductRequirement;
    	}
    	elseif ($source == 'deals_promotion')
    	{
	        $table_buyxgety_discount_rule = new DealsPromotionBuyxgetyRule;
	        $table_buyxgety_discount_product = new DealsPromotionBuyxgetyProductRequirement;
	        $id_table = 'id_deals';
    	}

        $data = [];
        foreach ($rules as $key => $rule) {

            $data[$key] = [
                $id_table   	=> $id_post,
                'benefit_id_product'  	=> $rule['benefit_id_product'] == 0 ? $product : $rule['benefit_id_product'],
                'max_qty_requirement' 	=> $rule['max_qty_requirement'],
                'min_qty_requirement' 	=> $rule['min_qty_requirement'],
                'benefit_qty'         	=> $rule['benefit_qty'],
                'max_percent_discount'  => $rule['max_percent_discount'],
                'created_at'        	=> date('Y-m-d H:i:s'),
            	'updated_at'        	=> date('Y-m-d H:i:s'),
                'created_by'        	=> Auth::id(),
            	'updated_by'        	=> Auth::id()
            ];

            if ($rule['benefit_type'] == "percent")
            {
                $data[$key]['discount_type'] = 'percent';
                $data[$key]['discount_value'] = $rule['discount_percent'];
                $data[$key]['benefit_qty'] = 1;
            }
            elseif($rule['benefit_type'] == "nominal")
            {
            	$data[$key]['discount_type'] = 'nominal';
                $data[$key]['discount_value'] = $rule['discount_nominal'];
                $data[$key]['benefit_qty'] = 1;
                $data[$key]['max_percent_discount'] = null;
            }
            elseif($rule['benefit_type'] == "free")
            {
                $data[$key]['discount_type'] = 'percent';
                $data[$key]['discount_value'] = 100;
                $data[$key]['max_percent_discount'] = null;
            }
            else
            {
                $data[$key]['discount_type'] = 'nominal';
                $data[$key]['discount_value'] = 0;
                $data[$key]['benefit_qty'] = 1;
            }

        }

        $dataProduct['id_product']           = $product;
        $dataProduct[$id_table]    			 = $id_post;
        $dataProduct['product_type']    	 = $product_type;
        $dataProduct['created_at']           = date('Y-m-d H:i:s');
        $dataProduct['updated_at']           = date('Y-m-d H:i:s');
        $dataProduct['created_by']           = Auth::id();
        $dataProduct['updated_by']           = Auth::id();

        try {
            $table_buyxgety_discount_rule::insert($data);
            $table_buyxgety_discount_product::insert($dataProduct);
            $result = ['status'  => 'success'];
        } catch (\Illuminate\Database\QueryException $e) {
            $result = [
                'status'  => 'fail',
                'message' => $e->getMessage()
            ];
            DB::rollBack();
            return response()->json($result);
        }
        return $result;
    }

    public function createProductCategoryDiscount($id_post, $category_product, $id_product_variant = null, $rules, $product_type, $auto_apply, $source, $table, $id_table, $product_variants = null)
    {
        if (!$rules) {
            return [
                'status'  => 'fail',
                'message' => 'Rule empty'
            ];
        }
        $delete_rule = $this->deleteAllProductRule($source, $id_post);

    	if (!$delete_rule) {
    		$result = [
                'status'  => 'fail',
                'message' => 'Create Filter Product Failed'
            ];
            DB::rollBack();
            return response()->json($result);
    	}

    	if ($source == 'promo_campaign')
    	{
	        $table_productcategory_discount_rule = new PromoCampaignProductCategoryRule;
	        $table_productcategory_discount_product = new PromoCampaignProductCategoryProductRequirement;
            $table_product_category = new CategoryPromoCampaignProductCategoryProductRequirement;
    	}elseif($source == 'deals'){
            $table_productcategory_discount_rule = new DealsProductCategoryRule;
	        $table_productcategory_discount_product = new DealsProductCategoryProductRequirement;
            $table_product_category = new CategoryDealsProductCategoryProductRequirement;
            $table_product_variant = new ProductVariantDealsProductCategoryProductRequirement;
        }

        $data = [];
        foreach ($rules as $key => $rule) {

            $data[$key] = [
                $id_table   	        => $id_post,
                'min_qty_requirement' 	=> $rule['min_qty_requirement'],
                'benefit_qty'         	=> $rule['benefit_qty'],
                'max_percent_discount'  => $rule['max_percent_discount']??null,
                'created_at'        	=> date('Y-m-d H:i:s'),
            	'updated_at'        	=> date('Y-m-d H:i:s'),
                'created_by'        	=> Auth::id(),
            	'updated_by'        	=> Auth::id()
            ];

            if ($rule['benefit_type'] == "percent")
            {
                $data[$key]['discount_type'] = 'percent';
                $data[$key]['discount_value'] = $rule['discount_percent'];
            }
            elseif($rule['benefit_type'] == "nominal")
            {
            	$data[$key]['discount_type'] = 'nominal';
                $data[$key]['discount_value'] = $rule['discount_nominal'];
                $data[$key]['max_percent_discount'] = null;
            }
            elseif($rule['benefit_type'] == "free")
            {
                $data[$key]['discount_type'] = 'percent';
                $data[$key]['discount_value'] = 100;
                $data[$key]['max_percent_discount'] = null;
            }
            else
            {
                $data[$key]['discount_type'] = 'nominal';
                $data[$key]['discount_value'] = 0;
            }

        }

        if ($source == 'promo_campaign')
    	{
            $dataProduct['id_product_variant']   = $id_product_variant;
    	}

        $dataProduct[$id_table]    			 = $id_post;
        $dataProduct['product_type']    	 = $product_type;
        $dataProduct['auto_apply']    	     = $auto_apply;
        $dataProduct['created_at']           = date('Y-m-d H:i:s');
        $dataProduct['updated_at']           = date('Y-m-d H:i:s');
        $dataProduct['created_by']           = Auth::id();
        $dataProduct['updated_by']           = Auth::id();

        try {
            $table_productcategory_discount_rule::insert($data);
            $create_requirement = $table_productcategory_discount_product::create($dataProduct);
            if($create_requirement){
                $dataCategoryPro = [];
                foreach($category_product as $cat_pro){
                    if ($source == 'promo_campaign'){
                        $dataCategoryPro[] = [
                            'id_promo_campaign_productcategory_category_requirement' => $create_requirement['id_promo_campaign_productcategory_category_requirement'],
                            'id_product_category' => $cat_pro
                        ]; 
                    }elseif($source == 'deals'){
                        $dataCategoryPro[] = [
                            'id_deals_productcategory_category_requirement' => $create_requirement['id_deals_productcategory_category_requirement'],
                            'id_product_category' => $cat_pro
                        ]; 
                    }
                }
                $table_product_category::insert($dataCategoryPro);
                
                $dataVariants = [];
                if($source == 'deals'){
                    foreach($product_variants as $variant){
                        $dataVariants[] = [
                            'id_deals_productcategory_category_requirement' => $create_requirement['id_deals_productcategory_category_requirement'],
                            'id_product_variant' => $variant
                        ]; 
                    }
                    $table_product_variant::insert($dataVariants);
                }
            }
            $result = ['status'  => 'success'];
        } catch (\Illuminate\Database\QueryException $e) {
            $result = [
                'status'  => 'fail',
                'message' => $e->getMessage()
            ];
            DB::rollBack();
            return response()->json($result);
        }
        return $result;
    }

    public function createDiscountDelivery($id_post, $discount_type, $discount_value, $max_percent_discount, $source, $table, $id_table)
    {
    	$delete_rule = $this->deleteAllProductRule($source, $id_post);

    	if (!$delete_rule) {
    		$result = [
                'status'  => 'fail',
                'message' => 'Create Filter Product Failed'
            ];
            DB::rollBack();
            return response()->json($result);
    	}

    	if ($source == 'promo_campaign') 
    	{
	        $table_discount_bill_rule = new PromoCampaignDiscountDeliveryRule;
    	}
    	elseif ($source == 'deals') 
    	{
	        $table_discount_bill_rule = new DealsDiscountDeliveryRule;
    	}
    	elseif ($source == 'deals_promotion')
    	{
    		$table_discount_bill_rule = new DealsPromotionDiscountDeliveryRule;
	        $id_table = 'id_deals';
    	}

    	if ($discount_type == 'Nominal') {
        	$max_percent_discount = NULL;
        }

        $data = [

            $id_table 				=> $id_post,
            'discount_type'     	=> $discount_type,
            'discount_value'    	=> $discount_value,
            'max_percent_discount'  => $max_percent_discount,
            'created_at'        	=> date('Y-m-d H:i:s'),
            'updated_at'        	=> date('Y-m-d H:i:s')
        ];

        try {
            $table_discount_bill_rule::insert($data);
            $result = ['status'  => 'success'];
        } catch (\Exception $e) {
            $result = [
                'status'  => 'fail',
                'message' => 'Create Discount Failed'
            ];
            DB::rollBack();
            return response()->json($result);
        }
        
        return $result;
    }

    function generateDate($date)
    {
        $datetimearr    = explode(' - ', $date);

        $datearr        = explode(' ', $datetimearr[0]);

        $date = $datearr[0].'-'.$datearr[1].'-'.$datearr[2];

        $date = date('Y-m-d', strtotime($date)).' '.$datetimearr[1] . ":00";
        return $date;

    }

    function removeDuplicateCode($code, $total_coupon)
    {
    	$unique_code = array_column($code, 'promo_code');
        // $unique_code = array_intersect_key($unique_code, array_unique( array_map("strtolower", $unique_code)));
        $unique_code = array_unique($unique_code);
        $code = array_filter($code, function ($key, $value) use ($unique_code) {
        	return in_array($value, array_keys($unique_code));
        }, ARRAY_FILTER_USE_BOTH);
        $duplicate = $total_coupon-count($code);

        return [
        	'code' => $code,
        	'duplicate' => $duplicate
        ];
    }

    function generateMultipleCode($old_code=null, $id, $prefix_code, $number_last_code, $total_coupon)
    {
    	if (empty($old_code)) {
    		$i = 0;
    	}else{
    		$i = count($old_code)-1;
    		$total_coupon = $i+$total_coupon;
    	}
    	for (; $i < $total_coupon; $i++)
        {
            $generateCode[$i]['id_promo_campaign']  = $id;
            $generateCode[$i]['promo_code']         = implode('', [$prefix_code, MyHelper::createrandom($number_last_code, 'PromoCode')]);
            $generateCode[$i]['created_at']         = date('Y-m-d H:i:s');
            $generateCode[$i]['updated_at']         = date('Y-m-d H:i:s');
        	array_push($old_code, $generateCode[$i]);
        }
        return $old_code;
    }

    function generateCode($status, $id, $type_code, $promo_code = null, $prefix_code = null, $number_last_code = null, $total_coupon = null)
    {
        $generateCode = [];
        if ($type_code == 'Multiple')
        {
            if ($total_coupon <= 1000)
            {
                for ($i = 0; $i < $total_coupon; $i++)
                {
                    $generateCode[$i]['id_promo_campaign']  = $id;
                    $generateCode[$i]['promo_code']         = implode('', [$prefix_code, MyHelper::createrandom($number_last_code, 'PromoCode')]);
                    $generateCode[$i]['created_at']         = date('Y-m-d H:i:s');
                    $generateCode[$i]['updated_at']         = date('Y-m-d H:i:s');
                    $generateCode[$i]['created_by']         = Auth::id();
                    $generateCode[$i]['updated_by']         = Auth::id();
                }

                // $unique_code = $this->removeDuplicateCode($generateCode, $total_coupon);
                // $duplicate = $unique_code['duplicate'];
                // $generateCode2 = $unique_code['code'];
                // $i = 0;
                // while ($duplicate != 0 )
                // {
                // 	$generateCode2 = $this->generateMultipleCode($generateCode2, $id, $prefix_code, $number_last_code, $total_coupon);
                // 	$unique_code = $this->removeDuplicateCode($generateCode2, $total_coupon);
                // 	$duplicate = $unique_code['duplicate'];
                // 	$generateCode2 = $unique_code['code'];

                // }
                // $generateCode = $generateCode2;
            }
            else
            {
                GeneratePromoCode::dispatch($status, $id, $prefix_code, $number_last_code, $total_coupon, Auth::id())->allOnConnection('database');
                $result = ['status'  => 'success'];
                return $result;
            }
        }
        else
        {
        	$promo_code = (string) $promo_code;
        	$checkCode = PromoCampaignPromoCode::where('promo_code',$promo_code)->where('id_promo_campaign','!=',$id)->first();

        	if ($checkCode) {
        		return ['status' => 'fail', 'messages' => 'promo code already exists'];
        	}
            $generateCode['id_promo_campaign']  = $id;
            $generateCode['promo_code']         = $promo_code;
            $generateCode['created_at']         = date('Y-m-d H:i:s');
            $generateCode['updated_at']         = date('Y-m-d H:i:s');
            $generateCode['created_by']         = Auth::id();
            $generateCode['updated_by']         = Auth::id();
        }

        if ($status == 'insert')
        {
            try
            {
              	PromoCampaignPromoCode::insert($generateCode);
                $result = ['status'  => 'success'];
            }
            catch (\Exception $e)
            {
                $result = ['status' => 'fail'];
            }
        }
        else
        {
            try
            {
                PromoCampaignPromoCode::where('id_promo_campaign', $id)->delete();
                PromoCampaignPromoCode::insert($generateCode);
                $result = ['status'  => 'success'];
            }
            catch (\Exception $e)
            {
                $result = ['status' => 'fail'];
            }
        }

        return $result;
    }

    public function insertTag($status = null, $id_promo_campaign, $promo_tag)
    {
        foreach ($promo_tag as $key => $value) {
            $data = ['tag_name' => $value];
            $tag[] = PromoCampaignTag::updateOrCreate(['tag_name' => $value])->id_promo_campaign_tag;
        }
        $tagID = [];
        for ($i = 0; $i < count($tag); $i++) {
            if (is_numeric(array_values($tag)[$i])) {
                $tagID[$i]['id_promo_campaign_tag']     = array_values($tag)[$i];
                $tagID[$i]['id_promo_campaign']         = $id_promo_campaign;
                $tagID[$i]['created_by']         		= Auth::id();
                $tagID[$i]['updated_by']         		= Auth::id();
            }
        }

        if ($status == 'update') {
            PromoCampaignHaveTag::where('id_promo_campaign', '=', $id_promo_campaign)->delete();
        }

        try {
            PromoCampaignHaveTag::insert($tagID);
            $result = ['status'  => 'success'];
        } catch (\Exception $e) {
            $result = ['status' => 'fail'];
        }

        return $result;
    }

    public function showStep1(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $promoCampaign = PromoCampaign::with([
                            'promo_campaign_have_tags.promo_campaign_tag',
                            'promo_campaign_days',
                        ])
                        ->where('id_promo_campaign', '=', $post['id_promo_campaign'])->first();

        if (!empty($promoCampaign) && $promoCampaign['code_type'] == 'Single') {
            $promoCampaign = $promoCampaign->with('promo_campaign_promo_codes', 'promo_campaign_have_tags.promo_campaign_tag','promo_campaign_days')->where('id_promo_campaign', '=', $post['id_promo_campaign'])->first();
        }

        if (isset($promoCampaign)) {
            $promoCampaign = $promoCampaign->toArray();
        }else{
            $promoCampaign = false;
        }

        if ($promoCampaign) {
            $result = [
                'status'  => 'success',
                'result'  => $promoCampaign
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'messages'  => ['Promo Campaign Not Found']
            ];
        }
        return response()->json($result);
    }

    public function showStep2(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $promoCampaign = PromoCampaign::with([
                            'user',
                            'products',
                            'promo_campaign_have_tags.promo_campaign_tag',
                            'promo_campaign_product_discount',
                            'promo_campaign_product_discount_rules',
                            'promo_campaign_tier_discount_product',
                            'promo_campaign_tier_discount_rules',
                            'promo_campaign_buyxgety_product_requirement',
                            'promo_campaign_buyxgety_rules',
                            'promo_campaign_productcategory_category_requirements',
                            'promo_campaign_productcategory_rules',
                            'outlets',
                            'promo_campaign_shipment_method',
                            'promo_campaign_discount_delivery_rules',
                            'promo_campaign_days'
                        ])
                        ->where('id_promo_campaign', '=', $post['id_promo_campaign'])
                        ->first();

        if (isset($promoCampaign)) {
            $promoCampaign = $promoCampaign->toArray();
        }else{
            $promoCampaign = false;
        }

        if ($promoCampaign) {
            $result = [
                'status'  => 'success',
                'result'  => $promoCampaign
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'messages'  => ['Promo Campaign Not Found']
            ];
        }

        return response()->json($result);
    }

    public function getData(Request $request)
    {
        $post = $request->json()->all();
        $data = [];
        switch ($post['get']) {
        	case 'Outlet':
            	$data = Outlet::select('id_outlet', DB::raw('CONCAT(outlet_code, " - ", outlet_name) AS outlet'))->get()->toArray();
        		break;
        	
        	case 'Product':
        		if ($post['type'] == 'group')
		        {
		            $data = ProductGroup::select('id_product_group as id_product', DB::raw('CONCAT(product_group_code, " - ", product_group_name) AS product'))->whereNotNull('id_product_category')->get()->toArray();
		        }
		        else
		        {
		            $data = Product::select('id_product', DB::raw('CONCAT(product_code, " - ", product_name) AS product'))
		            		->whereHas('product_group', function($q) {
		            			$q->whereNotNull('id_product_category');
		            		})
		            		->get()
		            		->toArray();
		        }
        		break;
            
            case 'Category':
                
                $data = ProductCategory::select('id_product_category','product_category_name')->get()->toArray();
                break;

        	case 'ProductGroup':
        		$data = ProductGroup::select('id_product_group', DB::raw('CONCAT(product_group_code, " - ", product_group_name) AS product_group'))->whereNotNull('id_product_category')->get()->toArray();
        		break;

        	case 'promo':
        		$now = date('Y-m-d H:i:s');
        		switch ($post['type']) {
        			case 'promo_campaign':
        				$data = PromoCampaign::select('promo_campaigns.id_promo_campaign as id_promo', DB::raw('CONCAT(promo_code, " - ", campaign_name) AS promo'))
        						->where('code_type','Single')
        						->join('promo_campaign_promo_codes', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
        						->where('date_start', '<', $now)
        						->where('date_end', '>', $now)
        						->where('step_complete','=',1)
					            ->where( function($q){
					            	$q->whereColumn('usage','<','limitation_usage')
					            		->orWhere('code_type','Single')
					            		->orWhere('limitation_usage',0);
					            })
					            ->get()->toArray();
        				break;
        			
        			default:
        				# code...
        				break;
        		}
        		break;

        	case 'shipment_method':
        		$availableDelivery = config('delivery_method');
		        $show_inactive = $POST['show_inactive'] ?? false;

		        $setting  = json_decode(MyHelper::setting('active_delivery_methods', 'value_text', '[]'), true) ?? [];
		        $deliveries = [];

		        foreach ($setting as $value) {

		            $delivery = $availableDelivery[$value['code'] ?? ''] ?? false;

		            if (!$delivery || !($delivery['status'] ?? false) || (!$show_inactive && !($value['status'] ?? false))) {
		                unset($availableDelivery[$value['code']]);
		                continue;
		            }

		            $delivery = [
		            	'code'	   => $value['code'],
				        'type'     => $delivery['type'],
				        'text'     => $delivery['text'],
				        'logo'     => $delivery['logo'],
				        'status'   => (int) $value['status'] ?? 0
		            ];

		            if (($options['code'] ?? false)) {
		            	if ($options['code'] != $value['code']) {
			            	continue;
		            	}  else {
			            	return $delivery;
			            }
		            }
		            $deliveries[] = $delivery;
		            unset($availableDelivery[$value['code']]);
		        }
		        if ($show_inactive) {
		            foreach ($availableDelivery as $code => $delivery) {
		                if (!$delivery['status']) {
		                    continue;
		                }
		                $deliveries[] = [
		                    'code'     => $code,
					        'type'     => $delivery['type'],
					        'text'     => $delivery['text'],
					        'logo'     => $delivery['logo'],
					        'status'   => 0
		                ];
		            }
		        }

		        $data = $deliveries;

        		break;

        	
        	default:
        		# code...
        		break;
        }

        return response()->json($data);
    }

    public function delete(DeletePromoCampaignRequest $request){
        $post = $request->json()->all();
        $user = auth()->user();
        $password = $request['password'];
        DB::beginTransaction();
        if (Hash::check($password, $user->password)){

            $checkData = PromoCampaign::where('id_promo_campaign','=',$post['id_promo_campaign'])->first();
            if ($checkData) {
                $delete = PromoCampaign::where('id_promo_campaign','=',$post['id_promo_campaign'])->delete();
                DB::commit();
                return MyHelper::checkDelete($delete);
            }else{
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['promo campaign not found']
                ]);
            }

        } else {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['unauthenticated']
            ]);
        }
    }

    public function validateCode(ValidateCode $request){
        $id_user 		= $request->user()->id;
        $phone 	 		= $request->user()->phone;
        $device_id		= $request->device_id;
        $device_type	= $request->device_type;
        $id_outlet		= $request->id_outlet;

        $code=PromoCampaignPromoCode::where('promo_code',$request->promo_code)
                ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
                ->where('step_complete', '=', 1)
                ->where( function($q){
                	$q->whereColumn('usage','<','limitation_usage')
                		->orWhere('code_type','Single');
                } )
                ->first();

        if(!$code){
            return [
                'status'=>'fail',
                'messages'=>['Promo code not valid']
            ];
        }
        $pct=new PromoCampaignTools();
        if(!$pct->validateUser($code->id_promo_campaign, $id_user, $phone, $device_type, $device_id, $errore,$code->id_promo_campaign_promo_code)){
            return [
                'status'=>'fail',
                'messages'=>$errore??['Promo code not valid']
            ];
        }
        $errors=[];
        $trx=$request->item;

        // return $pct->getRequiredProduct($code->id_promo_campaign);
        return [$pct->validatePromo($code->id_promo_campaign, $id_outlet, $trx, $errors), $errors];
        if($result=$pct->validatePromo($code->id_promo_campaign, $id_outlet, $trx, $errors)){
            $code->load('promo_campaign');
            $result['promo_title']=$code->promo_campaign->campaign_name;
            $result['promo_code']=$request->promo_code;
        }else{
            $result=[
                'status'=>'fail',
                'messages'=>$errors
            ];
            return $result;
        }
        return MyHelper::checkGet($result);
    }

    public function checkValid(ValidateCode $request)
    {
    	$id_user 		= $request->user()->id;
        $phone 	 		= $request->user()->phone;
        $device_id		= $request->device_id;
        $device_type	= $request->device_type;
        $id_outlet		= $request->id_outlet;
        // $ip 			= $request->ip();
        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];
        if(strpos($ip,',') !== false) {
            $ip = substr($ip,0,strpos($ip,','));
        }
    	$pct 			= new PromoCampaignTools();

        if ($request->promo_code && !$request->id_deals_user)
        {
            /* Check promo code*/
            $dataCheckPromoCode = [
                'id_user'    => $id_user,
                'device_id'  => $device_id,
                'promo_code' => $request->promo_code,
                'ip'         => $ip
            ];
            $checkFraud = app($this->fraud)->fraudCheckPromoCode($dataCheckPromoCode);
            if($checkFraud['status'] == 'fail'){
                return $checkFraud;
            }
            /* End check promo code */

	        // get data promo code, promo campaign, outlet, rule, and product
	        $code=PromoCampaignPromoCode::where('promo_code',$request->promo_code)
	                ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
	                ->where('step_complete', '=', 1)
	                ->where( function($q){
		            	$q->where(function($q2) {
		            		$q2->where('code_type', 'Multiple')
			            		->where(function($q3) {
					            	$q3->whereColumn('usage','<','limitation_usage')
					            		->orWhere('limitation_usage',0);
			            		});

		            	}) 
		            	->orWhere(function($q2) {
		            		$q2->where('code_type','Single')
			            		->where(function($q3) {
					            	$q3->whereColumn('total_coupon','>','used_code')
					            		->orWhere('total_coupon',0);
			            		});
		            	});
		            })
	                ->with([
						'promo_campaign.promo_campaign_outlets',
						'promo_campaign.promo_campaign_product_discount.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign.promo_campaign_buyxgety_product_requirement',
						'promo_campaign.promo_campaign_tier_discount_product.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
                        'promo_campaign.promo_campaign_productcategory_category_requirements.product_category.product_category' => function($q) {
                            $q->select('id_product_category', 'product_category_name');
                        },
						'promo_campaign.promo_campaign_product_discount_rules',
						'promo_campaign.promo_campaign_tier_discount_rules',
                        'promo_campaign.promo_campaign_buyxgety_rules',
                        'promo_campaign.promo_campaign_productcategory_rules',
                        'promo_campaign.promo_campaign_referral',
                        'promo_campaign.promo_campaign_discount_delivery_rules'
					])
	                ->first();

	        if(!$code){
	            return [
	                'status'=>'fail',
                    'messages'=>['Promo code not valid'],

	            ];
	        }

	        if ($code['promo_campaign']['date_start'] > date('Y-m-d H:i:s')) {
        		return [
	                'status'=>'fail',
                    'messages'=>['Promo code not available']
	            ];
        	}

	        if ($code['promo_campaign']['date_end'] < date('Y-m-d H:i:s')) {
        		return [
	                'status'=>'fail',
                    'messages'=>['Promo campaign is ended']
	            ];
        	}

	        if($code->promo_campaign->promo_type == 'Referral'){
	            $referer = UserReferralCode::where('id_promo_campaign_promo_code',$code->id_promo_campaign_promo_code)
	                ->join('users','users.id','=','user_referral_codes.id_user')
	                ->where('users.is_suspended','=',0)
	                ->first();
	            if(!$referer){
	                return [
	                    'status'=>'fail',
                        'messages'=>['Promo code not valid']
	                ];
	            }
	        }

	    	$query_obj = $code;
	    	$code = $code->toArray();

        	// check user
	        if(!$pct->validateUser($code['id_promo_campaign'], $id_user, $phone, $device_type, $device_id, $errors,$code['id_promo_campaign_promo_code'])){
	            return [
	                'status'=>'fail',
                    'messages'=>$errors??['Promo code not valid']
	            ];
	        }

	    	$query = $code;
	    	$source = 'promo_campaign';
        }
        elseif (!$request->promo_code && $request->id_deals_user)
        {
        	/* Check voucher */
        	$deals = DealsUser::where('id_deals_user', '=', $request->id_deals_user)
        			->whereIn('paid_status', ['Free', 'Completed'])
        			->whereNull('used_at')
        			->with([
                        'dealVoucher.deals.outlets_active',
                        'dealVoucher.deals.deals_product_discount.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
                        'dealVoucher.deals.deals_tier_discount_product.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
                        'dealVoucher.deals.deals_buyxgety_product_requirement.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
                        'dealVoucher.deals.deals_product_discount_rules',
                        'dealVoucher.deals.deals_tier_discount_rules',
                        'dealVoucher.deals.deals_buyxgety_rules',
                        'dealVoucher.deals.deals_discount_delivery_rules'
                    ])
        			->first();

			if(!$deals){
	            return [
	                'status'=>'fail',
                    'messages'=>['Voucher not valid']
	            ];
	        }

	        if ($deals['voucher_expired_at'] < date('Y-m-d H:i:s')) {
        		return [
	                'status'=>'fail',
	                'messages'=>['Voucher is expired']
	            ];
        	}

        	if ($deals['voucher_active_at'] > date('Y-m-d H:i:s') && !empty($deals['voucher_active_at']) ) {
        		return [
	                'status'=>'fail',
	                'messages'=>['Voucher is not active yet']
	            ];
        	}

        	$query_obj = $deals['dealVoucher'];
        	$deals = $deals->toArray();
	    	$query = $deals['deal_voucher'];
	    	$source = 'deals';
        }
        else
        {
        	return [
                'status'=>'fail',
                'messages'=>['Can only use either Promo Code or Voucher']
            ];
        }

    	$getProduct = $this->getProduct($source,$query_obj[$source]);
    	$desc = $this->getPromoDescription($source, $query[$source], $getProduct['product']??'');

        $errors=[];

        // check outlet
		if (isset($id_outlet)) {
			if (!$pct->checkOutletRule($id_outlet, $query[$source]['is_all_outlet']??0,$query[$source][$source.'_outlets']??$query[$source]['outlets_active'])) {
					return [
	                'status'=>'fail',
	                'messages'=>['Promo cannot be used at this outlet']
	            ];
			}
		}

		$result['title'] 			= $query[$source]['promo_title']??$query[$source]['deals_title'];
		$result['description']		= $desc;
		$result['promo_detail']		= "";
		$result['promo_code'] 		= $request->promo_code;
		$result['id_deals_user'] 	= $request->id_deals_user;
		$result['webview_url'] 		= "";
		$result['webview_url_v2'] 	= "";
        $result['promo_type'] 		= 'discount';

        if ($query[$source]['promo_type'] == 'Discount delivery') {
        	$result['promo_type'] = 'delivery';
        }

		$result = MyHelper::checkGet($result);
		// check item
		if (!empty($request->item)) {
        	$bearer = $request->header('Authorization');

	        if ($bearer == "") {
	            return [
	                'status'=>'fail',
	                'messages'=>['Promo code not valid']
	            ];
	        }
	        
	        $post = $request->json()->all();
	        $post['log_save'] = 1;
	        $custom_request = new \Modules\Transaction\Http\Requests\CheckTransaction;
			$custom_request = $custom_request
							->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($post))
							->merge($post)
							->setUserResolver(function () use ($request) {
								return $request->user();
							});
			$trx =  app($this->online_transaction)->checkTransaction($custom_request);
	        // $trx = MyHelper::postCURLWithBearer('api/transaction/check', $post, $bearer);

	        if (!empty($trx['status']) && $trx['status'] != 'success') {
	        	if (!empty($trx['status']) && $trx['status'] == 'fail') {
	        		return $trx;
	        	}else{
		        	return [
		                'status'=>'fail',
		                'messages'=>['Something went wrong']
		            ];
	        	}
	        }

	        if (!empty($trx['result'])) {
		        foreach ($trx['result'] as $key => $value) {
		        	$result['result'][$key] = $value;
		        }
		        $result['messages'] = $trx['messages'];
		        $result['promo_error'] = $trx['promo_error'];
		        $result['result']['description'] = $trx['promo']['description'];
		        $result['result']['promo_detail'] = $trx['promo']['detail'];
		        $result['result']['is_free'] = $trx['promo']['is_free'];
		        $result['promo'] = $trx['promo'];
	        }else{
	        	return [
	                'status'=>'fail',
	                'messages'=>['Something went wrong']
	            ];
	        }
        }

        if ($source == 'deals') {
        	$change_used_voucher = app($this->promo)->usePromo($source, $request->id_deals_user, $query);
        	if (($change_used_voucher['status']??false) == 'success') {
	        	$result['result']['webview_url'] = $change_used_voucher['webview_url'];
	        	$result['result']['webview_url_v2'] = $change_used_voucher['webview_url_v2'];
        	}else{
        		return [
	                'status'=>'fail',
	                'messages'=>['Something went wrong']
	            ];
        	}
        }else{
        	$change_used_voucher = app($this->promo)->usePromo($source, $query['id_promo_campaign_promo_code'], $query);
        	if (!$change_used_voucher) {
        		return [
	                'status'=>'fail',
	                'messages'=>['Something went wrong']
	            ];
        	}
        }

		return $result;
    }

    public function getProduct($source, $query)
    {
    	$query_obj = $query;
    	if (!is_array($query)) {
			$query = $query->toArray();
    	}
    	$get_product_name = false;
    	$get_category_name = false;
    	if ( ($query[$source.'_product_discount_rules']['is_all_product']??false) == 1 || ($query['promo_type']??false) == 'Referral')
        {
        	$applied_product = '*';
        	$product = 'All Product';
        }
        elseif ( !empty($query[$source.'_product_discount']) )
        {
        	$rule = "$source.'_product_discount'";
        	$applied_product = $query[$source.'_product_discount'];
        	if (count($applied_product) == 1) {
        		if ($applied_product[0]['product_type'] == 'group') {
        			$product = $applied_product[0]['product_group']['product_group_name']??'specified product';
        		}else{
        			$product = $applied_product[0]['product']['product_name']??'specified product';
	        		if ($applied_product[0]['product']['product_name']??false) {
	        			$get_product_name = true;
	        			$id_product = $applied_product[0]['product']['id_product'];
	        		}
        		}
        	}
        	else{
        		$product = 'specified product';
        	}
        	// $product = $applied_product[0]['product']['product_name']??'specified product';
        }
        elseif ( !empty($query[$source.'_tier_discount_product']) )
        {
        	$rule = $source.'_tier_discount_product';
        	$applied_product = $query[$source.'_tier_discount_product'];
        	if ($applied_product['product_type'] == 'group') {
        		$product = $applied_product['product_group']['product_group_name']??'specified product';
        	}else{
        		$product = $applied_product['product']['product_name']??'specified product';
	        	if ($applied_product['product']['product_name']??false) {
	    			$get_product_name = true;
	    			$id_product = $applied_product['product']['id_product'];
	    		}
        	}
        }
        elseif ( !empty($query[$source.'_buyxgety_product_requirement']) )
        {
        	$rule = $source.'_buyxgety_product_requirement';
        	$applied_product = $query[$source.'_buyxgety_product_requirement'];
        	if ($applied_product['product_type'] == 'group') {
        		$product = $applied_product['product_group']['product_group_name']??'specified product';
        	}else{
        		$product = $applied_product['product']['product_name']??'specified product';
	        	if ($applied_product['product']['product_name']??false) {
	    			$get_product_name = true;
	    			$id_product = $applied_product['product']['id_product'];
	    		}
        	}
        }
        elseif ( !empty($query[$source.'_productcategory_category_requirements']) )
        {
        	$rule = $source.'_productcategory_category_requirements';
        	$applied_product = $query[$source.'_productcategory_category_requirements'];
            $products = [];
            foreach($applied_product['product_category'] ?? [] as $applied){
                $products[] = $applied['product_category']['product_category_name']??null;
            }
            if(count($products)>0){
                if(count($products)==1){
                    $product = $products[0];
                }elseif(count($products)==2){
                    $product = $products[0].' or '.$products[1];
                }else{
                    $product = '';
                    foreach($products as $index => $prod){
                        if($index==0){
                            $product = $prod;
                        }elseif($index+1==count($products)){
                            $product = $product.' or '.$prod;
                        }else{
                            $product = $product.', '.$prod;
                        }
                    }
                }
            }else{
                $product = 'specified product';
            }
            if($applied_product['product_variant']['product_variant_name']??false){
                $parent = ProductVariant::where('id_product_variant',$applied_product['product_variant']['parent'])->first();
                if($parent){
                    if($applied_product['product_variant']['product_variant_name']=='general_size'){
                        $product = $product.' without Variant Size';
                        $product = $product.' without Variant Size';
                    }elseif($applied_product['product_variant']['product_variant_name']=='general_type'){
                        $product = $product.' without Variant Type';
                    }else{
                        $product = $product.' with '.$applied_product['product_variant']['product_variant_name'].' '.$parent['product_variant_name'];
                    }

                }
            }
        	
        }
        else
        {
        	$applied_product = "";
        	$product = "";
        }

        if ($get_product_name) {
        	$pct = new PromoCampaignTools;
        	$promo_product = Product::where('id_product', $id_product)->with('product_group', 'product_variants')->first();
			$product = $pct->getProductName($promo_product->product_group, $promo_product->product_variants);
        }elseif($get_category_name){
        	$promo_product = ProductCategory::where('id_product_category', $id_product_category)->first()['product_category_name'] ?? '';
        }

        $result = [
        	'applied_product' => $applied_product,
        	'product' => $product
        ];
        return $result;
    }

    public function getPromoDescription($source, $query, $product)
    {
    	// add description
        if ($query['promo_type'] == 'Product discount')
        {
        	if ($product == 'All Product') {
        		$product = 'this item';
        	}
        	elseif ($product == 'specified product') {
        		$product = 'these products';
        	}else {
        		$product = 'purchasing "'.$product.'"';
        	}

        	$discount = $query[$source.'_product_discount_rules']['discount_type']??'Nominal';
        	$qty = $query[$source.'_product_discount_rules']['max_product']??0;

        	if ($discount == 'Percent') {
        		$discount = ($query[$source.'_product_discount_rules']['discount_value']??0).'%';
        	}else{
        		$discount = 'IDR '.number_format(($query[$source.'_product_discount_rules']['discount_value']??0),0,',','.');
        	}

        	if ( empty($qty) ) {
    			$key = 'description_product_discount_no_qty';
				// $key_null = 'Anda berhak mendapatkan potongan %discount% untuk pembelian %product%';
				$key_null = 'You are entitled to a %discount% discount on %product%';
        	}else{
        		$key = 'description_product_discount';
				// $key_null = 'Anda berhak mendapatkan potongan %discount% untuk pembelian %product%. Maksimal %qty% buah untuk setiap produk.';
				$key_null = 'You are entitled to a %discount% discount on %product%. Maximum discount %qty% qty for each product.';
			}

			$desc = Setting::where('key', '=', $key)->first()['value']??$key_null;

			$desc = MyHelper::simpleReplace($desc,['discount'=>$discount, 'product'=>$product, 'qty'=>$qty]);
    	}
    	elseif ($query['promo_type'] == 'Tier discount')
    	{
    		$min_qty = 1;
    		$max_qty = 1;

    		foreach ($query[$source.'_tier_discount_rules'] as $key => $rule) {
				$min_req=$rule['min_qty'];
				$max_req=$rule['max_qty'];

				if($min_qty===null||$rule['min_qty']<$min_qty){
					$min_qty=$min_req;
				}
				if($max_qty===null||$rule['max_qty']>$max_qty){
					$max_qty=$max_req;
				}
    		}
    		$key = 'description_tier_discount';
    		$key_null = 'Anda berhak mendapatkan potongan setelah melakukan pembelian %product% sebanyak %minmax%';
    		$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
    		$desc = Setting::where('key', '=', 'description_tier_discount')->first()['value']??$key_null;

    		$desc = MyHelper::simpleReplace($desc,['product'=>$product, 'minmax'=>$minmax]);
    	}
    	elseif ($query['promo_type'] == 'Buy X Get Y')
    	{
    		$min_qty = 1;
    		$max_qty = 1;
    		foreach ($query[$source.'_buyxgety_rules'] as $key => $rule) {
				$min_req=$rule['min_qty_requirement'];
				$max_req=$rule['max_qty_requirement'];

				if($min_qty===null||$rule['min_qty_requirement']<$min_qty){
					$min_qty=$min_req;
				}
				if($max_qty===null||$rule['max_qty_requirement']>$max_qty){
					$max_qty=$max_req;
				}
    		}
    		$key = 'description_buyxgety_discount';
    		$key_null = 'Anda berhak mendapatkan potongan setelah melakukan pembelian %product% sebanyak %min% - %max%';
    		$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
    		$desc = Setting::where('key', '=', 'description_buyxgety_discount')->first()['value']??$key_null;

    		$desc = MyHelper::simpleReplace($desc,['product'=>$product, 'minmax'=>$minmax]);
        }
        elseif ($query['promo_type'] == 'Promo Product Category')
    	{
    		$min_qty = 1;
    		$max_qty = 1;

    		foreach ($query[$source.'_productcategory_rules'] as $key => $rule) {
				$min_req=$rule['min_qty_requirement'];
				$max_req=$rule['min_qty_requirement'];

				if($min_qty===null||$rule['min_qty_requirement']<$min_qty){
					$min_qty=$min_req;
				}
                if($max_qty===null||$rule['min_qty_requirement']>$max_qty){
					$max_qty=$max_req;
				}
    		}
            
    		$key = 'description_productcategory_discount';
    		$key_null = 'You get a discount after purchasing  %minmax% %product%';
    		$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
    		$desc = Setting::where('key', '=', 'description_productcategory_discount')->first()['value']??$key_null;

    		$desc = MyHelper::simpleReplace($desc,['product'=>$product, 'minmax'=>$minmax]);
    	}
        elseif ($query['promo_type'] == 'Referral')
    	{
            $desc = 'no description';
    		if($query[$source.'_referral']['referred_promo_type'] == 'Product Discount'){
                switch ($query[$source.'_referral']['referred_promo_unit']) {
                    case 'Percent':
                        $desc = 'You get '.$query[$source.'_referral']['referred_promo_value'].'% discount for all products';
                        if($query[$source.'_referral']['referred_promo_value_max'] > 0){
                            $desc = $desc.' with a maximum discount of '.MyHelper::requestNumber($query[$source.'_referral']['referred_promo_value_max'],'_CURRENCY').' for each product';
                        }
                    break;
                    case 'Nominal':
                        $desc = 'You get '.MyHelper::requestNumber($query[$source.'_referral']['referred_promo_value'],'_CURRENCY').' discount for all products';
                    break;
                }
            }else{
                switch ($query[$source.'_referral']['referred_promo_unit']) {
                    case 'Nominal':
                        $desc = 'You will get '.MyHelper::requestNumber($query[$source.'_referral']['referred_promo_value'],'_POINT').' points after transaction success';
                    break;
                    case 'Percent':
                        /*$desc = 'You will get '.$query[$source.'_referral']['referred_promo_value'].'%'.' cashback from total transactions';
                        if($query[$source.'_referral']['referred_promo_value_max'] > 0){
                            $desc = $desc.' with a maximum cashback of '.MyHelper::requestNumber($query[$source.'_referral']['referred_promo_value_max'],'_POINT').'.';
                        }*/
                        $desc = 'Voucher Applied! You will receive '.$query[$source.'_referral']['referred_promo_value'].'% cashback on your transaction';
                        if($query[$source.'_referral']['referred_promo_value_max'] > 0){
                            $desc = $desc.' with maximum cashback value of '.MyHelper::requestNumber($query[$source.'_referral']['referred_promo_value_max'],'_POINT').' points.';
                        }
                    break;
                }
            }
    	}
    	elseif ($query['promo_type'] == 'Discount delivery')
        {
        	if ($product == 'All Product') {
        		$product = 'this item';
        	}
        	elseif ($product == 'specified product') {
        		$product = 'these products';
        	}else {
        		$product = 'purchasing "'.$product.'"';
        	}

        	$discount = $query[$source.'_discount_delivery_rules']['discount_type']??'Nominal';

        	if ($discount == 'Percent') {
        		$discount = ( $query[$source.'_discount_delivery_rules']['discount_value']??0).'%';
        	}else{
        		$discount = 'IDR '.number_format( ($query[$source.'_discount_delivery_rules']['discount_value']??0),0,',','.');
        	}

			$desc = 'You are entitled to a %discount% discount on delivery costs';

			$desc = MyHelper::simpleReplace($desc,['discount'=>$discount]);
    	}
    	else
    	{
    		$key = null;
    		$desc = 'no description';
    	}

    	return $desc;
    }

    public function checkPromoCode($promo_code=null, $outlet=null, $product=null, $id_promo_campaign_promo_code=null)
    {
    	if (!empty($id_promo_campaign_promo_code))
    	{
    		$code = PromoCampaignPromoCode::where('id_promo_campaign_promo_code',$id_promo_campaign_promo_code);
    	}
    	else
    	{
    		$code = PromoCampaignPromoCode::where('promo_code',$promo_code);
    	}

        $code = $code->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
		            ->where('step_complete', '=', 1)
		            ->where( function($q){
		            	$q->where(function($q2) {
		            		$q2->where('code_type', 'Multiple')
			            		->where(function($q3) {
					            	$q3->whereColumn('usage','<','limitation_usage')
					            		->orWhere('limitation_usage',0);
			            		});

		            	}) 
		            	->orWhere(function($q2) {
		            		$q2->where('code_type','Single')
			            		->where(function($q3) {
					            	$q3->whereColumn('total_coupon','>','used_code')
					            		->orWhere('total_coupon',0);
			            		});
		            	});
		            });

	    if (!empty($outlet)) {
	    	$code = $code->with(['promo_campaign.promo_campaign_outlets']);
	    }

	    if (!empty($product)) {
		    $code = $code->with([
					'promo_campaign.promo_campaign_product_discount',
					'promo_campaign.promo_campaign_buyxgety_product_requirement',
					'promo_campaign.promo_campaign_productcategory_category_requirements',
					'promo_campaign.promo_campaign_tier_discount_product',
					'promo_campaign.promo_campaign_product_discount_rules',
					'promo_campaign.promo_campaign_tier_discount_rules',
                    'promo_campaign.promo_campaign_buyxgety_rules',
                    'promo_campaign.promo_campaign_productcategory_rules',
                    'promo_campaign.promo_campaign_referral',
                    'promo_campaign.promo_campaign_discount_delivery_rules',
                    'promo_campaign.promo_campaign_days'
				]);
	    }

	    $code = $code->first();

        return $code;
    }

	public function checkVoucher($id_deals_user=null, $outlet=null, $product=null)
    {
    	$deals = new DealsUser;

    	if (!empty($id_deals_user))
    	{
    		$deals = $deals->where('id_deals_user', '=', $id_deals_user)->where('id_user', '=', auth()->user()->id);
	        $deals = $deals->whereIn('paid_status', ['Free', 'Completed'])
	        			->whereNull('used_at')
	        			->where('voucher_expired_at','>=',date('Y-m-d H:i:s'))
	        			->where(function($q) {
	        				$q->where('voucher_active_at','<=',date('Y-m-d H:i:s'))
	        					->orWhereNull('voucher_active_at');
	        			});
    	}
    	else
    	{
    		$deals = $deals->where('id_user', '=', auth()->user()->id)->where('is_used','=',1);
    		$deals = $deals->with(['dealVoucher.deal']);
    	}




	    if (!empty($outlet)) {
        	$deals = $deals->with(['dealVoucher.deals.outlets_active']);
	    }

	    if (!empty($product)) {
        	$deals = $deals->with([
                    'dealVoucher.deals.outlets_active',
                    'dealVoucher.deals.deals_product_discount',
                    'dealVoucher.deals.deals_tier_discount_product',
                    'dealVoucher.deals.deals_buyxgety_product_requirement',
                    'dealVoucher.deals.deals_product_discount_rules',
                    'dealVoucher.deals.deals_tier_discount_rules',
                    'dealVoucher.deals.deals_buyxgety_rules',
                    'dealVoucher.deals.deals_discount_delivery_rules'
                ]);
	    }

	    $deals = $deals->first();

        return $deals;
    }

    public function promoError($source, $errore=null, $errors=null, $error_product=0)
    {
    	if ($source == 'transaction')
    	{
    		$setting = ['promo_error_title', 'promo_error_ok_button', 'promo_error_cancel_button', 'promo_warning_image'];
	    	$getData = Setting::whereIn('key',$setting)->get()->toArray();
	    	$data = [];
	    	foreach ($getData as $key => $value) {
	    		$data[$value['key']] = $value['value'];
	    	}

	    	$result['title'] 		 = $data['promo_error_title']??'Promo tidak berlaku';
	        $result['button_ok'] 	 = $data['promo_error_ok_button']??'Tambah item';
	        $result['button_cancel'] = $data['promo_error_cancel_button']??'Tidak';
	        $result['warning_image'] = $data['promo_warning_image']??'';
	        $result['remove'] 		 = 0;

	        if (!$error_product) {
	        	$result['remove'] 	 = 1;
	        }

    	}
    	else
    	{
    		return null;
    	}

    	$result['message'] = [];
    	if(isset($errore)){
			foreach ($errore as $key => $value) {
				array_push($result['message'], $value);
			}
		}
		if(isset($errors)){
			foreach ($errors as $key => $value) {
				array_push($result['message'], $value);
			}
		}

	    return $result;
    }

    public function addReport($id_promo_campaign, $id_promo_campaign_promo_code, $id_transaction, $id_outlet, $device_id, $device_type)
    {
    	$data = [
    		'id_promo_campaign_promo_code' 	=> $id_promo_campaign_promo_code,
    		'id_promo_campaign' => $id_promo_campaign,
    		'id_transaction' 	=> $id_transaction,
    		'id_outlet' 		=> $id_outlet,
    		'device_id' 		=> $device_id,
    		'device_type' 		=> $device_type,
    		'user_name'			=> Auth()->user()->name,
    		'user_phone'		=> Auth()->user()->phone,
    		'id_user' 			=> Auth()->user()->id
    	];

    	$insert = PromoCampaignReport::create($data);

    	if (!$insert) {
    		return false;
    	}

    	$used_code = PromoCampaignReport::where('id_promo_campaign',$id_promo_campaign)->count();
    	$update = PromoCampaign::where('id_promo_campaign', $id_promo_campaign)->update(['used_code' => $used_code]);

		if (!$update) {
    		return false;
    	}

    	$usage_code = PromoCampaignReport::where('id_promo_campaign_promo_code', $id_promo_campaign_promo_code)->count();
    	$update = PromoCampaignPromoCode::where('id_promo_campaign_promo_code', $id_promo_campaign_promo_code)->update(['usage' => $usage_code]);

		if (!$update) {
    		return false;
    	}

    	return true;
    }

    public function deleteReport($id_transaction, $id_promo_campaign_promo_code = null)
    {
    	$getReports = PromoCampaignReport::with('promo_campaign')
						// ->where('id_promo_campaign_promo_code', $id_promo_campaign_promo_code)
						->where('id_transaction','=',$id_transaction)
						->get();

    	// if ($getReport)
		foreach ($getReports ?? [] as $key => $getReport) 
    	{
	    	$delete = PromoCampaignReport::where('id_transaction', '=', $id_transaction)
	    				->where('id_promo_campaign_promo_code', $getReport->id_promo_campaign_promo_code)
	    				->delete();

	    	if ($delete)
	    	{
	    		$get_code = PromoCampaignPromoCode::where('id_promo_campaign_promo_code', $getReport->id_promo_campaign_promo_code)->first();
	    		$update = PromoCampaignPromoCode::where('id_promo_campaign_promo_code', $getReport->id_promo_campaign_promo_code)->update(['usage' => $get_code->usage-1]);

	    		if ($update) {
		    		$update = PromoCampaign::where('id_promo_campaign', '=', $getReport['id_promo_campaign'])->update(['used_code' => $getReport->promo_campaign->used_code-1]);

		    		if ($update)
		    		{
			    		continue;
		    		}
		    		else
		    		{
		    			return false;
		    		}
	    		}
	    		else
	    		{
	    			return false;
	    		}
	    	}
	    	else
	    	{
	    		return false;
	    	}
        }

        return true;
    }

    public function getAllVariant(){
        $product_variants = ProductVariant::with(['children'])->whereNull('parent')->get()->toArray();
        return MyHelper::checkGet($product_variants);
    }
}
