<?php

namespace Modules\PromoCampaign\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use \App\Lib\MyHelper;

use \Modules\PromoCampaign\Entities\PromoCampaignReferral;
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
}
