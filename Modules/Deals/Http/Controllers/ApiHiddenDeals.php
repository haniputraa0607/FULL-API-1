<?php

namespace Modules\Deals\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;

use App\Http\Models\Deal;
use App\Http\Models\DealsOutlet;
use App\Http\Models\DealsPaymentManual;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsVoucher;
use App\Http\Models\User;

use DB;

use Modules\Deals\Http\Requests\Deals\Create;
use Modules\Deals\Http\Requests\Deals\Update;
use Modules\Deals\Http\Requests\Deals\Delete;

use Illuminate\Support\Facades\Schema;

class ApiHiddenDeals extends Controller
{

    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
        $this->deals        = "Modules\Deals\Http\Controllers\ApiDeals";
        $this->dealsVoucher = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->dealsClaim = "Modules\Deals\Http\Controllers\ApiDealsClaim";
    }

    public $saveImage = "img/deals/";


    /* CREATE REQUEST */
    function createReq(Create $request) {
        DB::beginTransaction();
        $post = $request->json()->all();        
        $to   = $this->cekUser($post['to']);

        $post['deals_total_voucher'] = count($to);
        $post['deals_total_claimed'] = count($to);

        $save = app($this->deals)->create($post);

        if ($save) {
            // PROCESS TO AUTO CLAIMED & ASSIGN

            if (!empty($to)) {
                $claim = $this->autoClaimedAssign($save, $to);

                if (!$claim) {
                    DB::rollback();
                    return response()->json(MyHelper::checkCreate($claim));
                }
            }
            else {
                DB::rollback();
                return response()->json([
                    'status' => 'fail', 
                    'messages' => ['empty recipient']
                ]);
            }
        }

        DB::commit();
        return response()->json(MyHelper::checkCreate($save));
    }

    /* AUTO CLAIMED & ASSIGN */
    function autoClaimedAssign($deals, $to) {
        foreach ($to as $key => $user) {
            $voucher = app($this->dealsVoucher)->generateVoucher($deals->id_deals, 1, 1);

            if ($voucher) {
                $userVoucher = app($this->dealsClaim)->createVoucherUser($user, $voucher->id_deals_voucher, $deals);

                if ($userVoucher) {
                    continue;
                }
                else {
                    return false;
                }
            }
            else {
                return false;
            }
        }
        return true;
    }

    /* CEK USER */
    function cekUser($phone, $id_deals=[]) {
        $phone = array_filter(explode(",", $phone));

        $user = User::select('id')->whereIn('phone', $phone)->get()->toArray();

        if (!empty($user)) {
            $user = array_pluck($user, 'id');

            if (!empty($id_deals)) {
                foreach ($user as $key => $value) {
                    $deals = DealsUser::join('deals_vouchers', 'deals_users.id_deals_voucher', '=', 'deals_vouchers.id_deals_voucher')->where('id_user', $value)->where('id_deals', $id_deals)->first();    

                    if (!empty($deals)) {
                        unset($user[$key]);
                    }
                }
            }
        }

        return array_values($user);
    }

    /* AUTO ASSIGN DEALS*/
    function autoAssign(Request $request) {
        DB::beginTransaction();

        $deals = Deal::where('id_deals', $request->json('id_deals'))->first();

        if (empty($deals)) {
            return response()->json(MyHelper::checkGet($deals));
        }
        else {
            $user = $this->cekUser($request->json('phone'), $request->json('id_deals'));

            if (!empty($user)) {
                // AUTO GENERATE
                if ($deals->deals_voucher_type == "Auto generated") {
                    // LIMIT USER
                    $user = $this->limitAvailableUser($deals, $user);

                    if ($user) {
                        // UPDATE DEALS
                        $updateDeals = Deal::where('id_deals', $deals->id_deals)->update([
                            'deals_total_claimed' => $deals->deals_total_claimed + count($user),
                            // 'deals_total_voucher' => count($user) + $deals->deals_total_voucher
                        ]);

                        if ($updateDeals) {
                            $claim = $this->autoClaimedAssign($deals, $user);

                            if (!$claim) {
                                DB::rollback();
                                return response()->json(MyHelper::checkUpdate($claim));
                            }
                        }
                        else {
                            DB::rollback();
                            return response()->json(MyHelper::checkUpdate($updateDeals));
                        }
                    }
                    else {
                        DB::rollback();
                        return response()->json([
                            'status'   => 'fail',
                            'messages' => ['User is empty']
                        ]);
                    }
                }
                // UNLIMITED
                elseif ($deals->deals_voucher_type == "Unlimited") {
                    // UPDATE DEALS
                    $updateDeals = Deal::where('id_deals', $deals->id_deals)->update([
                        'deals_total_claimed' => $deals->deals_total_claimed + 1,
                        'deals_total_voucher' => $deals->deals_total_voucher + 1
                    ]);

                    if ($updateDeals) {
                        $claim = $this->autoClaimedAssign($deals, $user);

                        if (!$claim) {
                            DB::rollback();
                            return response()->json(MyHelper::checkUpdate($claim));
                        }
                    }
                    else {
                        DB::rollback();
                        return response()->json(MyHelper::checkUpdate($updateDeals));
                    }
                }
                // WITH VOUCHER
                else {
                    DB::rollback();
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['Voucher is not free.']
                    ]);
                    // $voucher = $this->checkVoucherRegistered($deals->id_deals);

                    // if ($voucher) {
                    //     // BATAS
                    //     $batas = $this->limit($voucher, $user);

                    //     // UPDATE DEALS
                    //     $updateDeals = Deal::where('id_deals', $deals->id_deals)->update([
                    //         'deals_total_claimed' => $deals->deals_total_claimed + $batas,
                    //         // 'deals_total_voucher' => $batas + $deals->deals_total_voucher
                    //     ]);

                    //     if ($updateDeals) {
                    //         $claim = $this->claimedWithVoucher($deals, $user, $voucher);

                    //         if (!$claim) {
                    //             DB::rollback();
                    //             return response()->json(MyHelper::checkUpdate($claim));
                    //         }
                    //     }
                    //     else {
                    //         DB::rollback();
                    //         return response()->json(MyHelper::checkUpdate($updateDeals));
                    //     }
                    // }
                    // else {
                    //     DB::rollback();
                    //     return response()->json([
                    //         'status'   => 'fail',
                    //         'messages' => ['Voucher is empty']
                    //     ]);
                    // }                  
                }
            }
            else {
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['All user already claimed.']
                ]);
            }
            
            DB::commit();
            return response()->json(MyHelper::checkCreate($deals));
        }
    }

    /* LIMIT */
    function limit($voucher, $user) {
        $totalVoucher = count($voucher);
        $totalUser    = count($user);

        // SET LIMIT
        if ($totalVoucher > $totalUser) {
            $batas = $totalUser;
        }
        else {
            $batas = $totalVoucher;
        }

        return $batas;
    }

    /* LIMIT AVAILABLE USER */
    function limitAvailableUser($deals, $user) {
        if ($deals->deals_type == "Deals") {
            // CEK TOTAL VOUCHER SENT 
            $voucher = DealsVoucher::where('id_deals', $deals->id_deals)->count();

            if ($deals->deals_total_voucher < $voucher || $deals->deals_total_voucher == $voucher) {
                return false;
            }
            // IF DEALS MORE THAN USED
            else {
                // LIMITATION
                $limit = $deals->deals_total_voucher - $voucher;

                if (count($user) > $limit) {
                    $batas = $limit;
                }
                else {
                    $batas = count($user); 
                }

                // REGISTER USER
                $fixedUser = [];
                
                for ($i=0; $i < $batas; $i++) { 
                    array_push($fixedUser, $user[$i]);
                }

                // ASSIGN USERFIXED
                $user = $fixedUser;
            }
        }

        return array_values($user);
    }

    /* CLAIM WITH VOUCHER */
    function claimedWithVoucher($deals, $user, $voucher) {

        $batas = $this->limit($voucher, $user);

        for ($i=0; $i < $batas; $i++) { 
            // UPDATE STATUS VOUCHER
            $updateStatusVoucher = app($this->dealsVoucher)->update($voucher[$i], ['deals_voucher_status' => 'Sent']);

            if ($updateStatusVoucher) {
                // ASSIGN TO USER VOUCHER
                $claim = app($this->dealsClaim)->createVoucherUser($user[$i], $voucher[$i], $deals);

                if ($claim) {
                    continue;
                }
                else {
                    return false;    
                }
            }
            else {
                return false;
            }
        }

        return true;
    }

    /* CHECK VOUCHER */
    function checkVoucherRegistered($id_deals) {
        $voucher = DealsVoucher::where('id_deals', $id_deals)->where('deals_voucher_status', '=', 'Available')->get()->toArray();

        if (!empty($voucher)) {
            $voucher = array_pluck($voucher, 'id_deals_voucher');
        }

        return $voucher;
    }
}