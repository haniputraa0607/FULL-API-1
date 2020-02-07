<?php

namespace Modules\PromoCampaign\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use \App\Lib\MyHelper;

use \Modules\PromoCampaign\Entities\PromoCampaignReferral;
use \Modules\PromoCampaign\Entities\UserReferralCode;

class ApiReferralController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
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
