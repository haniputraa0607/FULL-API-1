<?php

namespace Modules\PromoCampaign\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use \App\Lib\MyHelper;
use Modules\PromoCampaign\Lib\PromoCampaignTools;

use \Modules\PromoCampaign\Entities\PromoCampaignReferral;
use \Modules\PromoCampaign\Entities\PromoCampaign;
use \Modules\PromoCampaign\Entities\UserReferralCode;
use App\Http\Models\Setting;

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
