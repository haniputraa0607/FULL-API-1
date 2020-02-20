<?php

namespace Modules\PromoCampaign\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use \App\Lib\MyHelper;
use Modules\PromoCampaign\Lib\PromoCampaignTools;

use \App\Http\Models\User;
use \Modules\PromoCampaign\Entities\PromoCampaignReferral;
use \Modules\PromoCampaign\Entities\PromoCampaign;
use \Modules\PromoCampaign\Entities\UserReferralCode;
use App\Http\Models\Setting;
use Modules\PromoCampaign\Entities\PromoCampaignReferralTransaction;

class ApiReferralController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $setting = Setting::where('key', 'referral_messages')->first();
        if (!$setting) {
            $setting = [
                'key'           => 'referral_messages',
                'value_text'    => 'Get %value% discount on your first purchase. By using the %code% promo code'
            ];
            $setting = Setting::create($setting);
        }

        $referral = UserReferralCode::with(['promo_code', 'promo_code.promo_campaign_referral'])->where('id_user', $user->id)->get()->first();
        if (!$referral) {
            PromoCampaignTools::createReferralCode($user->id);
            $referral = UserReferralCode::with(['promo_code', 'promo_code.promo_campaign_referral'])->where('id_user', $user->id)->get()->first();
        }

        $value = implode('', [$referral->promo_code->promo_campaign_referral->referred_promo_value, $retVal = ($referral->promo_code->promo_campaign_referral->referred_promo_unit == 'Percent') ? '%' : ' point']);
        
        $data = [
            'status'    => 'success',
            'result'    => [
                'messages'      => str_replace(['%value%', '%code%'], [$value, $referral->promo_code->promo_code], $setting->value_text),
                'url_webview'   => env('API_URL') . 'api/referral/webview'
            ]
        ];

        return response()->json($data);
    }

    public function webview(Request $request)
    {
        $user = $request->user();
        $referral = UserReferralCode::with(['promo_code', 'promo_code.promo_campaign_referral'])->where('id_user', $user->id)->get()->first();

        $data = [
            'promo_code'    => $referral->promo_code->promo_code,
            'referral'      => $referral->promo_code->promo_campaign_referral
        ];
        
        return view('webview.referral', $data);
    }
    /**
     * Provide report data
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function report(Request $request)
    {
        $perpage = 20;
        $data['user'] = UserReferralCode::select('users.name','users.phone','user_referral_cashbacks.*')
            ->join('users','user_referral_cashbacks.id_user','=','users.id')
            ->paginate(20);
        $data['transaction'] = PromoCampaignReferralTransaction::join('transactions','promo_campaign_referral_transactions.id_transaction','=','transactions.id_transaction')
            ->join('users','users.id','=','transactions.id_user')
            ->paginate(20);
        return MyHelper::checkGet($data);
    }
    public function reportUser(Request $request)
    {
        $perpage = 20;
        $post = $request->json()->all();
        $select_user = ['id','name','phone'];
        $select_trx = ['id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal'];
        if($post['ajax']??false){
            $id_user = User::select('id')->where('phone',$post['phone'])->pluck('id')->first();
            $data = PromoCampaignReferralTransaction::with(['user'=>function($query) use ($select_user){
                    $query->select($select_user);
                },'transaction'=>function($query) use ($select_trx){
                    $query->select($select_trx);
                }])->where('id_referrer',$id_user)->paginate($perpage);
        }else{
            $data = User::select('id','name','phone','user_referral_cashbacks.referral_code','number_transaction','cashback_earned')
                ->join('user_referral_cashbacks','users.id','=','user_referral_cashbacks.id_user')
                ->where('phone',$post['phone'])
                ->with(['referred_transaction'=>function($query) use ($perpage){
                    $query->paginate($perpage);
                },'referred_transaction.user'=>function($query) use ($select_user){
                    $query->select($select_user);
                },'referred_transaction.transaction'=>function($query) use ($select_trx){
                    $query->select($select_trx);
                }])->first();
        }
        return MyHelper::checkGet($data);
    }


    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function setting(Request $request) {
        $referral = PromoCampaignReferral::with('promo_campaign')->first();
        return MyHelper::checkGet($referral);
    }

    public function settingUpdate(Request $request) {
        $post = $request->json()->all();
        $referral = PromoCampaignReferral::first();
        if(
            ($post['referred_promo_unit'] == 'Percent' && $post['referred_promo_value']>100) || 
            ($post['referrer_promo_unit'] == 'Percent' && $post['referrer_promo_value']>100)
        ){
            return MyHelper::checkGet([],'Percent value should lower or equal than 100');
        }
        $dataPromoCampaign = [
            'promo_title'=>$post['promo_title']??null,
            'date_end'=>$post['date_end']??null
        ];
        $dataPromoCampaignReferral = [
            'referred_promo_type'=>$post['referred_promo_type']??null,
            'referred_promo_unit'=>$post['referred_promo_unit']??null,
            'referred_promo_value'=>$post['referred_promo_value']??null,
            'referred_min_value'=>$post['referred_min_value']??null,
            'referred_promo_value_max'=>$post['referred_promo_value_max']??null,
            'referrer_promo_unit'=>$post['referrer_promo_unit']??null,
            'referrer_promo_value'=>$post['referrer_promo_value']??null,
            'referrer_promo_value_max'=>$post['referrer_promo_value_max']??null
        ];
        \DB::beginTransaction();
        $update = $referral->update($dataPromoCampaignReferral);
        $update2 = PromoCampaign::where('id_promo_campaign',$referral->id_promo_campaign)->update($dataPromoCampaign);
        if(!$update || !$update2){
            \DB::rollback();
            return MyHelper::checkUpdate([]);
        }
        \DB::commit();
        return MyHelper::checkUpdate($update);
    }
}
