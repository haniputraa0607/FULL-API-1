<?php

namespace Modules\POS\Http\Controllers;

use App\Jobs\FraudJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

use App\Http\Models\Membership;
use App\Http\Models\UsersMembership;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionDuplicate;
use App\Http\Models\TransactionDuplicatePayment;
use App\Http\Models\TransactionDuplicateProduct;
use App\Http\Models\TransactionProductModifier;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionPaymentOvo;
use App\Http\Models\TransactionPaymentCimb;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use App\Http\Models\TransactionVoucher;
use App\Http\Models\TransactionSetting;
use App\Http\Models\User;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\ProductPhoto;
use App\Http\Models\ProductModifier;
use App\Http\Models\ProductModifierPrice;
use App\Http\Models\ProductModifierProduct;
use App\Http\Models\Outlet;
use App\Http\Models\Setting;
use App\Http\Models\DealsUser;
use App\Http\Models\Deal;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\SpecialMembership;
use App\Http\Models\DealsVoucher;
use App\Http\Models\Configs;
use App\Http\Models\FraudSetting;
use App\Http\Models\LogBackendError;
use App\Http\Models\OutletSchedule;
use App\Http\Models\SyncTransactionFaileds;
use App\Http\Models\SyncTransactionQueues;
use App\Lib\MyHelper;
use Mail;

use Modules\POS\Http\Requests\reqMember;
use Modules\POS\Http\Requests\reqVoucher;
use Modules\POS\Http\Requests\voidVoucher;
use Modules\POS\Http\Requests\reqMenu;
use Modules\POS\Http\Requests\reqOutlet;
use Modules\POS\Http\Requests\reqTransaction;
use Modules\POS\Http\Requests\reqTransactionRefund;
use Modules\POS\Http\Requests\reqPreOrderDetail;
use Modules\POS\Http\Requests\reqBulkMenu;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandOutlet;
use Modules\Brand\Entities\BrandProduct;
use Modules\POS\Entities\SyncMenuRequest;
use Modules\POS\Entities\SyncMenuResult;
use Modules\IPay88\Entities\TransactionPaymentIpay88;

use Modules\POS\Http\Controllers\CheckVoucher;
use Exception;

use DB;
use DateTime;
use Illuminate\Support\Facades\Schema;
use Modules\Outlet\Entities\OutletOvo;
use Modules\POS\Jobs\SyncAddOnPrice;
use Modules\POS\Jobs\SyncProductPrice;
use App\Jobs\SyncProductPrice2;
use Modules\Product\Entities\ProductPricePeriode;
use Modules\ProductVariant\Entities\ProductGroup;
use Modules\ProductVariant\Entities\ProductProductVariant;
use Modules\ProductVariant\Entities\ProductVariant;

class ApiPOS extends Controller
{
    function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->balance    = "Modules\Balance\Http\Controllers\BalanceController";
        $this->membership = "Modules\Membership\Http\Controllers\ApiMembership";
        $this->balance    = "Modules\Balance\Http\Controllers\BalanceController";
        $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->setting_fraud = "Modules\SettingFraud\Http\Controllers\ApiFraud";

        $this->pos = "Modules\POS\Http\Controllers\ApiPos";
    }

    public function transactionDetail(reqPreOrderDetail $request)
    {
        $post = $request->json()->all();

        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $outlet = Outlet::where('outlet_code', strtoupper($post['store_code']))->first();
        if (empty($outlet)) {
            return response()->json(['status' => 'fail', 'messages' => 'Store not found']);
        }

        $check = Transaction::join('transaction_pickups', 'transactions.id_transaction', '=', 'transaction_pickups.id_transaction')
            ->with(['modifiers', 'modifiers.product_modifier', 'products', 'products.product_group', 'products.product_variants' => function ($query) {
                $query->orderBy('parent');
            }, 'product_detail', 'vouchers', 'productTransaction.modifiers', 'promo_campaign_promo_code'])
            ->where('order_id', '=', $post['order_id'])
            ->where('id_outlet', '=', $outlet['id_outlet'])
            ->where('transactions.transaction_date', '>=', date("Y-m-d") . " 00:00:00")
            ->where('transactions.transaction_date', '<=', date("Y-m-d") . " 23:59:59")
            ->first();

        if ($check) {
            $check = $check->toArray();
            $user = User::where('id', '=', $check['id_user'])->first()->toArray();

            $qrCode = 'https://chart.googleapis.com/chart?chl=' . $check['order_id'] . '&chs=250x250&cht=qr&chld=H%7C0';
            $qrCode = html_entity_decode($qrCode);

            $expired = Setting::where('key', 'qrcode_expired')->first();
            if (!$expired || ($expired && $expired->value == null)) {
                $expired = '10';
            } else {
                $expired = $expired->value;
            }

            $trx_start_time = $check['pickup_at']?:$check['completed_at'];

            $timestamp = strtotime('+' . $expired . ' minutes');
            $memberUid = MyHelper::createQRV2($timestamp, $user['id']);
            $header['orderNumber'] = $check['transaction_receipt_number'];
            $header['outletId'] = $outlet['outlet_code'];
            // $header['order_id'] = $check['order_id'];
            $header['bookingCode'] = $check['order_id'];
            // $header['posting_date'] = date('Ymd', strtotime($check['transaction_date']));
            $header['businessDate'] = date('Ymd', strtotime($check['transaction_date']));
            $header['trxDate'] = date('Ymd', strtotime($check['transaction_date']));
            $header['trxStartTime'] = $trx_start_time?date('Ymd His', strtotime($trx_start_time)) : '';
            $header['trxEndTime'] = $check['completed_at'] ? date('Ymd His', strtotime($check['completed_at'])) : '';
            $header['pax'] = count($check['products']);
            $header['orderType'] = 'take away';
            $header['grandTotal'] = (float) $check['transaction_grandtotal'];
            $header['subTotal'] = (float) $check['transaction_subtotal'];
            $header['tax'] = (float) $check['transaction_tax'];
            $header['notes'] = '';
            $header['appliedPromo'] = $check['id_promo_campaign_promo_code'] ? 'MOBILE APPS PROMO' : '';
            // $header['process_at'] = $check['pickup_type'] ?? '';
            // $header['process_date_time'] = $check['pickup_at'] ?? '';
            // $header['status_order'] = '';
            // $header['accepted_date_time'] = date('Ymd His', strtotime($check['receive_at']));
            // $header['ready_date_time'] = date('Ymd His', strtotime($check['ready_at']));
            // $header['taken_date_time'] = date('Ymd His', strtotime($check['taken_at']));
            // $header['reject_date_time'] = date('Ymd His', strtotime($check['reject_at']));
            $header['pos'] = [
                'id'=> 1,
                'cashDrawer'=> 1,
                'cashierId'=> '',
                // 'id' => 1,
                // 'cash_drawer' => 2,
                // 'cashier_id' => 'M1907123'
            ];
            $header['customer'] = [
                'id' => $memberUid,
                'phone' => $user['phone'],
                'name' => $user['name'],
                'gender' => $user['gender'] ?? '',
                'age' => ($user['birthday'] ?? false) ? (date_diff(date_create($user['birthday']), date_create('now'))->y) : ''
            ];
            $payment = [];
            //cek di multi payment
            $multi = TransactionMultiplePayment::where('id_transaction', $check['id_transaction'])->get();
            if (!$multi) {
                //cek di balance
                $balance = TransactionPaymentBalance::where('id_transaction', $check['id_transaction'])->get();
                if ($balance) {
                    foreach ($balance as $payBalance) {
                        $pay = [
                            'number'        => 1,
                            'type'          => 'POINMOBILE',
                            'amount'        => (float) $payBalance['balance_nominal'],
                            'changeAmount' => 0,
                            'cardNumber'   => 0,
                            'cardOwner'    => $user['name']
                        ];
                        $payment[] = $pay;
                    }
                } else {
                    $midtrans = TransactionPaymentMidtran::where('id_transaction', $check['id_transaction'])->get();
                    if ($midtrans) {
                        foreach ($midtrans as $payMidtrans) {
                            $pay = [
                                'number'        => 1,
                                'type'          => 'GOPAY',
                                'amount'        => (float) $payMidtrans['gross_amount'],
                                'changeAmount' => 0,
                                'cardNumber'   => 0,
                                'cardOwner'    => $user['name']
                            ];
                            $payment[] = $pay;
                        }
                    }
                }
            } else {
                foreach ($multi as $key => $payMulti) {
                    if ($payMulti['type'] == 'Balance') {
                        $balance = TransactionPaymentBalance::find($payMulti['id_payment']);
                        if ($balance) {
                            $pay = [
                                'number'        => $key + 1,
                                'type'          => 'POINMOBILE',
                                'amount'        => (float) $balance['balance_nominal'],
                                'changeAmount' => 0,
                                'cardNumber'   => 0,
                                'cardOwner'    => $user['name']
                            ];
                            $payment[] = $pay;
                        }
                    } elseif ($payMulti['type'] == 'Midtrans') {
                        $midtrans = TransactionPaymentMidtran::find($payMulti['id_payment']);
                        if ($midtrans) {
                            $pay = [
                                'number'        => $key + 1,
                                'type'          => 'GOPAY',
                                'amount'        => (float) $midtrans['gross_amount'],
                                'changeAmount' => 0,
                                'cardNumber'   => '',
                                'cardOwner'    => ''
                            ];
                            $payment[] = $pay;
                        }
                    } elseif ($payMulti['type'] == 'Ovo') {
                        $ovo = TransactionPaymentOvo::find($payMulti['id_payment']);
                        if ($ovo) {
                            $pay = [
                                'number'            => $key + 1,
                                'type'              => 'OVO',
                                'amount'            => (float) $ovo['amount'],
                                'changeAmount'     => 0,
                                'cardNumber'       => $ovo['phone'],
                                'cardOwner'        => '',
                                'referenceNumber'  => $ovo['approval_code']
                            ];
                            $payment[] = $pay;
                        }
                    } elseif ($payMulti['type'] == 'Cimb') {
                        $cimb = TransactionPaymentCimb::find($payMulti['id_payment']);
                        if ($cimb) {
                            $pay = [
                                'number'            => $key + 1,
                                'type'              => 'Cimb',
                                'amount'            => (float) $cimb['amount'] ?? $check['transaction_grandtotal'],
                                'changeAmount'     => 0,
                                'cardNumber'       => '',
                                'cardOwner'        => ''
                            ];
                            $payment[] = $pay;
                        }
                    } elseif ($payMulti['type'] == 'IPay88') {
                        $ipay = TransactionPaymentIpay88::find($payMulti['id_payment']);
                        if ($ipay) {
                            $pay = [
                                'number'            => $key + 1,
                                'type'              => 'IPAY88',
                                'amount'            => (float) $ipay['amount'] / 100,
                                'changeAmount'     => 0,
                                'cardNumber'       => '',
                                'cardOwner'        => '',
                                'referenceNumber'  => $ipay['trans_id']
                            ];
                            $payment[] = $pay;
                        }
                    } elseif (strtolower($payMulti['type']) == 'shopeepay') {
                        $shopeepay = TransactionPaymentShopeePay::find($payMulti['id_payment']);
                        if ($shopeepay) {
                            $pay = [
                                'number'            => $key + 1,
                                'type'              => 'SHOPEEPAY',
                                'amount'            => (float) $shopeepay['amount']/100,
                                'changeAmount'     => 0,
                                'cardNumber'       => '',
                                'cardOwner'        => '',
                                'referenceNumber'  => $shopeepay['transaction_sn']
                            ];
                            $payment[] = $pay;
                        }
                    }
                }
            }

            // 			$transactions['payment_type'] = null;
            // 			$transactions['payment_code'] = null;
            // 			$transactions['payment_nominal'] = null;
            $transactions['menu'] = [];
            $transactions['tax'] = 0;
            $transactions['total'] = 0;
            $item = [];
            $last = 0;
            foreach ($check['products'] as $key => $menu) {
                $tax = 0;
                $val = [
                    'number' => $key + 1,
                    'menuId' => $menu['product_group']['product_group_code'],
                    'sapMatnr' => $menu['product_code'],
                    'categoryId' => $menu['category_id_pos'],
                    'qty' => (int) $menu['pivot']['transaction_product_qty'],
                    'price' => (float) $menu['pivot']['transaction_product_price'] - $menu['pivot']['transaction_modifier_subtotal'],
                    'discount' => (float) $menu['pivot']['transaction_product_discount'],
                    'grossAmount' => $menu['pivot']['transaction_product_subtotal'] - $menu['pivot']['transaction_modifier_subtotal'],
                    'netAmount' => $menu['pivot']['transaction_product_subtotal'] - $tax,
                    'tax' => $tax,
                    'type' => $menu['product_variants'][1]['product_variant_code'] == 'general_type' ? null : $menu['product_variants'][1]['product_variant_code'],
                    'size' => $menu['product_variants'][0]['product_variant_code'] == 'general_size' ? null : $menu['product_variants'][0]['product_variant_code'],
                    // 'promoNumber' => $check['id_promo_campaign_promo_code'] ? $check['promo_campaign_promo_code']['promo_code'] : '',
                    'promoNumber' => '01198',
                    'promoType' => $check['id_promo_campaign_promo_code'] ? '5' : null,
                    'status' => 'ACTIVE'
                    // 'amount' => (float) $menu['pivot']['transaction_product_subtotal']
                ];
                $item[] = $val;
                $last = $key + 1;
            }
            foreach ($check['modifiers'] as $key => $modifier) {
                $tax = 0;
                $val = [
                    'number' => $key + 1 + $last,
                    'menuId' => $modifier['product_modifier']['menu_id_pos'],
                    'sapMatnr' => $modifier['code'],
                    'categoryId' => $modifier['product_modifier']['category_id_pos'],
                    'qty' => (int) $modifier['qty'],
                    'price' => (float) $modifier['transaction_product_modifier_price'] / $modifier['qty'],
                    'discount' => 0,
                    'grossAmount' => $modifier['transaction_product_modifier_price'],
                    'netAmount' => $modifier['transaction_product_modifier_price'] - $tax,
                    'tax' => $tax,
                    'type' => null,
                    'size' => null,
                    // 'promoNumber' => $check['id_promo_campaign_promo_code'] ? $check['promo_campaign_promo_code']['promo_code'] : '',
                    'promoNumber' => '01198',
                    'promoType' => $check['id_promo_campaign_promo_code'] ? '5' : null,
                    'status' => 'ACTIVE'
                    // 'amount' => $modifier['transaction_product_modifier_price']
                ];
                $item[] = $val;
            }
            return [
                'header' => $header,
                'item' => $item,
                'payment' => $payment
            ];
            $transactions['tax'] = round($transactions['tax']);
            $transactions['total'] = round($transactions['total']);

            //update accepted_at
            $trxPickup = TransactionPickup::where('id_transaction', $check['id_transaction'])->first();
            if ($trxPickup && $trxPickup->reject_at == null) {
                $pick = TransactionPickup::where('id_transaction', $check['id_transaction'])->update(['receive_at' => date('Y-m-d H:i:s')]);
            }

            $send = app($this->autocrm)->SendAutoCRM('Order Accepted', $user['phone'], [
                "outletName" => $outlet['outlet_name'],
                "idReference" => $check['transaction_receipt_number'] . ',' . $outlet['id_outlet'],
                'idTransaction' => $check['id_transaction'],
                "transactionDate" => $check['transaction_date']
            ]);

            return response()->json(['status' => 'success', 'result' => $transactions]);
        } else {
            return response()->json(['status' => 'fail', 'messages' => 'Invalid Order ID']);
        }
        return response()->json(['status' => 'success', 'messages' => 'API is not ready yet. Stay tuned!', 'result' => $post]);
    }

    public function checkMember(Request $request)
    {
        $post = $request->json()->all();

        if (
            !empty($post['api_key']) && !empty($post['api_secret']) &&
            !empty($post['store_code']) && !empty($post['uid'])
        ) {

            $api = $this->checkApi($post['api_key'], $post['api_secret']);
            if ($api['status'] != 'success') {
                return response()->json($api);
            }

            $outlet = Outlet::where('outlet_code', strtoupper($post['store_code']))->first();
            if (empty($outlet)) {
                return response()->json(['status' => 'fail', 'messages' => 'Store not found']);
            }

            $qr = MyHelper::readQRV2($post['uid']);
            $timestamp = $qr['timestamp'];
            $iduserqr = $qr['id_user'];

            if (date('Y-m-d H:i:s') > date('Y-m-d H:i:s', $timestamp)) {
                return response()->json(['status' => 'fail', 'messages' => 'Please refresh qrcode and retry to scan member']);
            }

            $user = User::where('id', $iduserqr)->first();
            if (empty($user)) {
                return response()->json(['status' => 'fail', 'messages' => 'User not found']);
            }

            //suspend
            if (isset($user['is_suspended']) && $user['is_suspended'] == '1') {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => 'Maaf, akun Anda sedang di-suspend'
                ]);
            }

            $id_user = $user->id;
            if(strlen((string)$id_user) < 8){
                $id_user = "00000000".$id_user;
                $id_user = substr($id_user, -8);
            }

            $result['uid'] = $id_user;
            $result['name'] = $user->name;

            $voucher = DealsUser::with('dealVoucher', 'dealVoucher.deal')->where('id_user', $user->id)
                ->where(function ($query) use ($outlet) {
                    $query->where('id_outlet', $outlet->id_outlet)
                        ->orWhereNull('id_outlet');
                })
                ->whereDate('voucher_expired_at', '>=', date("Y-m-d"))
                ->where(function ($q) {
                    $q->where('paid_status', 'Completed')
                        ->orWhere('paid_status', 'Free');
                })
                ->get();
            if (count($voucher) <= 0) {
                $result['vouchers'] = [];
            } else {
                // $arr = [];
                $voucher_name = [];
                foreach ($voucher as $index => $vou) {
                    array_push($voucher_name, ['name' => $vou->dealVoucher->deal->deals_title]);

                    /* if($index > 0){
                        $voucher_name[0] = $voucher_name[0]."\n".$vou->dealVoucher->deal->deals_title;
                    }else{
                    $voucher_name[0] = $vou->dealVoucher->deal->deals_title;
                    }  */
                }


                // array_push($arr, $voucher_name);

                $result['vouchers'] = $voucher_name;
            }

            $membership = UsersMembership::with('users_membership_promo_id')->where('id_user', $user->id)->orderBy('id_log_membership', 'DESC')->first();
            if (empty($membership)) {
                $result['customer_level'] = "";
                $result['promo_id'] = [];
            } else {
                $result['customer_level'] = $membership->membership_name;
                if ($membership->users_membership_promo_id) {
                    $result['promo_id'] = [];
                    foreach ($membership->users_membership_promo_id as $promoid) {
                        if ($promoid['promo_id']) {
                            $result['promo_id'][] = $promoid['promo_id'];
                        }
                    }
                } else {
                    $result['promo_id'] = [];
                }
            }

            $result['saldo'] = $user->balance;

            return response()->json(['status' => 'success', 'result' => $result]);
        } else {
            return response()->json(['status' => 'fail', 'messages' => 'Input is incomplete']);
        }
    }

    public function checkVoucher(Request $request)
    {
        $post = $request->json()->all();

        if (
            !empty($post['api_key']) && !empty($post['api_secret']) && !empty($post['store_code']) &&
            (!empty($post['qrcode']) || !empty($post['code']))
        ) {

            $api = $this->checkApi($post['api_key'], $post['api_secret']);
            if ($api['status'] != 'success') {
                return response()->json($api);
            }

            return CheckVoucher::check($post);
        } else {
            return response()->json(['status' => 'fail', 'messages' => ['Input is incomplete']]);
        }
    }

    public function voidVoucher(Request $request)
    {
        $post = $request->json()->all();

        if (
            !empty($post['api_key']) && !empty($post['api_secret']) &&
            !empty($post['store_code']) && !empty($post['voucher_code'])
        ) {

            $api = $this->checkApi($post['api_key'], $post['api_secret']);
            if ($api['status'] != 'success') {
                return response()->json($api);
            }

            $outlet = Outlet::where('outlet_code', strtoupper($post['store_code']))->first();
            if (empty($outlet)) {
                return response()->json(['status' => 'fail', 'messages' => 'Store not found']);
            }

            DB::beginTransaction();

            $voucher = DealsVoucher::join('deals_users', 'deals_vouchers.id_deals_voucher', 'deals_users.id_deals_voucher')
                ->leftJoin('transaction_vouchers', 'deals_vouchers.id_deals_voucher', 'transaction_vouchers.id_deals_voucher')
                ->leftJoin('transaction_vouchers as transaction_vouchers2', 'deals_vouchers.voucher_code', 'transaction_vouchers2.deals_voucher_invalid')
                ->where('deals_vouchers.voucher_code', $post['voucher_code'])
                ->select('deals_vouchers.*', 'deals_users.id_outlet', 'transaction_vouchers.id_deals_voucher as id_deals_voucher_transaction', 'transaction_vouchers2.deals_voucher_invalid as voucher_code_transaction')
                ->first();

            if (!$voucher) {
                return response()->json(['status' => 'fail', 'messages' => 'Voucher not found']);
            } elseif ($voucher['id_deals_voucher_transaction'] || $voucher['voucher_code_transaction']) {
                return response()->json(['status' => 'fail', 'messages' => 'Void voucher failed, voucher has already been used.']);
            }

            if (isset($voucher['id_outlet']) && $voucher['id_outlet'] != $outlet['id_outlet']) {
                $outletDeals = Outlet::find($voucher['deals_user'][0]['id_outlet']);
                if ($outletDeals) {
                    return response()->json(['status' => 'fail', 'messages' => 'Void voucher  ' . $post['voucher_code'] . '. Void vouchers can only be done at ' . $outletDeals['outlet_name'] . ' outlets.']);
                }
                return response()->json(['status' => 'fail', 'messages' => 'Void voucher failed ' . $post['voucher_code'] . '. Void vouchers can only be done at ' . $outlet['outlet_name'] . ' outlets.']);
            }

            //update voucher redeem
            foreach ($voucher['deals_user'] as $dealsUser) {
                $dealsUser->redeemed_at = null;
                $dealsUser->used_at = null;
                $dealsUser->voucher_hash = null;
                $dealsUser->voucher_hash_code = null;
                $dealsUser->id_outlet = null;
                $dealsUser->update();

                if (!$dealsUser) {
                    DB::rollBack();
                    return response()->json(['status' => 'fail', 'messages' => 'Void voucher failed ' . $post['voucher_code'] . '. Please contact team support.']);
                }
            }

            //update count deals
            $deals = Deal::find($voucher['id_deals']);
            $deals->deals_total_redeemed = $deals->deals_total_redeemed - 1;
            $deals->update();
            if (!$deals) {
                DB::rollBack();
                return response()->json(['status' => 'fail', 'messages' => 'Void voucher failed ' . $post['voucher_code'] . '. Please contact team support.']);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'messages' => 'Voucher ' . $post['voucher_code'] . ' was successfully voided']);
        } else {
            return response()->json(['status' => 'fail', 'messages' => 'Input is incomplete']);
        }
    }

    public function syncOutlet(reqOutlet $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $getIdBrand = Brand::select('id_brand')->first();
        if (!$getIdBrand) {
            return [
                'status'    => 'fail',
                'messages'  => ['failed get brand']
            ];
        }

        $successOutlet = [];
        $failedOutlet = [];
        foreach ($post['store'] as $key => $value) {
            DB::beginTransaction();
            $cekOutlet = Outlet::where('outlet_code', strtoupper($value['store_code']))->first();
            if ($cekOutlet) {
                try {
                    $update = Outlet::updateOrCreate(['outlet_code' => strtoupper($value['store_code'])], [
                        'outlet_name'       => $value['store_name'],
                        'outlet_status'     => $value['store_status'],
                        'outlet_address'    => $value['store_address'],
                        'outlet_phone'      => $value['store_phone'],
                        'outlet_latitude'   => $value['store_latitude'],
                        'outlet_longitude'  => $value['store_longitude']
                    ]);
                } catch (\Exception $e) {
                    LogBackendError::logExceptionMessage("ApiPOS/syncOutlet=>" . $e->getMessage(), $e);
                    $failedOutlet[] = 'fail to sync, outlet ' . $value['store_name'];
                    continue;
                }
                foreach ($value['store_schedule'] as $valueSchedule) {
                    try {
                        $valueSchedule = $this->setTimezone($valueSchedule);
                        if (isset($valueSchedule['is_close'])) {
                            $valueSchedule['is_closed'] = $valueSchedule['is_close'];
                            unset($valueSchedule['is_close']);
                        }
                        OutletSchedule::updateOrCreate(['id_outlet' => $cekOutlet->id_outlet, 'day' => $valueSchedule['day']], $valueSchedule);
                    } catch (Exception $e) {
                        DB::rollBack();
                        LogBackendError::logExceptionMessage("ApiPOS/syncOutlet=>" . $e->getMessage(), $e);
                        $failedOutlet[] = 'fail to sync, outlet ' . $value['store_name'] . '. Error at store schedule ' . $valueSchedule['day'];
                        continue;
                    }
                }
            } else {
                try {
                    $save = Outlet::create([
                        'outlet_code'       => $value['store_code'],
                        'outlet_name'       => $value['store_name'],
                        'outlet_status'     => 'Inactive',
                        'outlet_address'    => $value['store_address'],
                        'outlet_phone'      => $value['store_phone'],
                        'outlet_latitude'   => $value['store_latitude'],
                        'outlet_longitude'  => $value['store_longitude']
                    ]);
                } catch (\Exception $e) {
                    LogBackendError::logExceptionMessage("ApiPOS/syncOutlet=>" . $e->getMessage(), $e);
                    $failedOutlet[] = 'fail to sync, outlet ' . $value['store_name'];
                    continue;
                }
                if (!empty($value['store_schedule'])) {
                    foreach ($value['store_schedule'] as $valueSchedule) {
                        $valueSchedule['id_outlet'] = $save->id_outlet;
                        try {
                            $valueSchedule = $this->setTimezone($valueSchedule);
                            $valueSchedule['is_closed'] = $valueSchedule['is_close'];
                            unset($valueSchedule['is_close']);
                            OutletSchedule::create($valueSchedule);
                        } catch (Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncOutlet=>" . $e->getMessage(), $e);
                            $failedOutlet[] = 'fail to sync, outlet ' . $value['store_name'] . '. Error at store schedule ' . $valueSchedule['day'];
                            continue;
                        }
                    }
                }
                $cekOutlet = $save;
            }

            //check brand outlet
            try {
                $brandOutlet = BrandOutlet::where('id_outlet', $cekOutlet->id_outlet)->first();
                if (!$brandOutlet) {
                    BrandOutlet::create([
                        'id_brand' => $getIdBrand->id_brand,
                        'id_outlet' => $cekOutlet->id_outlet
                    ]);
                }
            } catch (\Exception $e) {
                LogBackendError::logExceptionMessage("ApiPOS/syncOutlet=>" . $e->getMessage(), $e);
            }

            $successOutlet[] = $value['store_name'];
            DB::commit();
        }
        // return success
        return response()->json([
            'status' => 'success',
            'result' => [
                'success_outlet' => $successOutlet,
                'failed_outlet' => $failedOutlet
            ]
        ]);
    }

    public function syncOutletOvo(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $successOutlet = [];
        $failedOutlet = [];
        foreach ($post['store'] as $key => $value) {
            DB::beginTransaction();
            $cekOutlet = Outlet::where('outlet_code', strtoupper($value['store_code']))->first();
            if ($cekOutlet) {
                try {
                    OutletOvo::updateOrCreate([
                        'id_outlet'     => $cekOutlet->id_outlet
                    ], [
                        'id_outlet'     => $cekOutlet->id_outlet,
                        'store_code'    => $value['store_code_ovo'],
                        'tid'           => $value['tid'],
                        'mid'           => $value['mid']
                    ]);
                } catch (\Exception $e) {
                    LogBackendError::logExceptionMessage("ApiPOS/syncOutletOvo=>" . $e->getMessage(), $e);
                    $failedOutlet[] = 'fail to sync, outlet ' . $value['store_code'];
                    continue;
                }
            } else {
                $failedOutlet[] = 'fail to sync, outlet ' . $value['store_code'];
            }

            $successOutlet[] = $value['store_code'];
            DB::commit();
        }
        // return success
        return response()->json([
            'status' => 'success',
            'result' => [
                'success_outlet'    => $successOutlet,
                'failed_outlet'     => $failedOutlet
            ]
        ]);
    }
    /**
     * Synch menu for single outlet
     * @param  Request $request laravel Request object
     * @return array        status update
     */
    public function syncProduct(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $countInsert    = 0;
        $insertProduct  = [];
        $countUpdate    = 0;
        $updatedProduct = [];
        $failedProduct  = [];

        $getIdBrand = Brand::select('id_brand')->first();
        if (!$getIdBrand) {
            return [
                'status'    => 'fail',
                'messages'  => ['failed get brand']
            ];
        }

        foreach ($post['menu'] as $keyMenu => $menu) {
            if (!isset($menu['menu_id'])) {
                $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because menu_id not set';
                continue;
            }
            if (!isset($menu['category_id'])) {
                $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because category_id not set';
                continue;
            }
            if (!isset($menu['menu_variance'])) {
                $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because menu_variance not set';
                continue;
            }

            DB::beginTransaction();
            $checkGroup = ProductGroup::where('product_group_code', $menu['menu_id'])->first();
            if ($checkGroup) {

                foreach ($menu['menu_variance'] as $keyVariance => $variance) {
                    $size = $variance['size'];
                    //for variant null use general size
                    if ($variance['size'] == null) {
                        $size = 'general_size';
                    }
                    //for variant null use general type
                    $type = $variance['type'];
                    if ($variance['type'] == null) {
                        $type = 'general_type';
                    }
                    $variantSize = ProductVariant::where('product_variant_code', $size)->first();
                    if (!$variantSize) {
                        try {
                            $variantSize = ProductVariant::create([
                                'product_variant_code'      => $size,
                                'product_variant_subtitle'  => '',
                                'product_variant_name'      => $size,
                                'product_variant_position'  => 0,
                                'parent'                    => 1
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    }


                    $variantType = ProductVariant::where('product_variant_code', $type)->first();
                    if (!$variantType) {
                        try {
                            $variantType = ProductVariant::create([
                                'product_variant_code'       => $type,
                                'product_variant_subtitle'  => '',
                                'product_variant_name'      => $type,
                                'product_variant_position'  => 0,
                                'parent'                    => 2
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    }

                    $product = Product::where('product_code', $variance['sap_matnr'])->first();
                    if ($product) {
                        try {
                            Product::where('product_code', $variance['sap_matnr'])->update([
                                'product_name_pos'  => implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]),
                                'product_status'    => $variance['status'],
                                'id_product_group'  => $checkGroup->id_product_group,
                                'category_id_pos'   => $menu['category_id']
                            ]);

                            //check brand product
                            $checkBrand = BrandProduct::where('id_product', $product->id_product)->first();
                            if (!$checkBrand) {
                                $brandProduct = [
                                    'id_product' => $product->id_product,
                                    'id_brand'   => $getIdBrand->id_brand
                                ];
                                BrandProduct::create($brandProduct);
                            }

                            $countUpdate        = $countUpdate + 1;
                            $updatedProduct[]   = implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    } else {
                        try {
                            $product = Product::create([
                                'id_product_group'  => $checkGroup->id_product_group,
                                'product_code'      => $variance['sap_matnr'],
                                'product_name'      => implode(" ", [ucwords(strtolower($checkGroup->product_group_name)), $variance['size'], $variance['type']]),
                                'product_name_pos'  => implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]),
                                'product_status'    => $variance['status'],
                                'category_id_pos'   => $menu['category_id']
                            ]);

                            //insert brand
                            $brandProduct = [
                                'id_product' => $product->id_product,
                                'id_brand'   => $getIdBrand->id_brand
                            ];
                            BrandProduct::create($brandProduct);

                            $countInsert        = $countInsert + 1;
                            $insertProduct[]    = implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    }

                    try {
                        ProductProductVariant::updateOrCreate([
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantSize->id_product_variant
                        ], [
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantSize->id_product_variant
                        ]);
                        ProductProductVariant::updateOrCreate([
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantType->id_product_variant
                        ], [
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantType->id_product_variant
                        ]);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                        $failedProduct[] = 'fail to sync, product ' . implode(" ", [$checkGroup->product_group_name, $variance['size'], $variance['type']]);
                        continue;
                    }
                }
            } else {
                try {
                    $createGroup = ProductGroup::create([
                        'product_group_code'    => $menu['menu_id'],
                        'product_group_name'    => ucwords(strtolower($menu['menu_name']))
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                    $failedProduct[] = 'fail to sync, product ' . $menu['menu_name'];
                    continue;
                }

                foreach ($menu['menu_variance'] as $keyVariance => $variance) {
                    $size = $variance['size'];
                    //for variant null use general size
                    if ($variance['size'] == null) {
                        $size = 'general_size';
                    }
                    $type = $variance['type'];
                    //for variant null use general type
                    if ($variance['type'] == null) {
                        $type = 'general_type';
                    }
                    $variantSize = ProductVariant::where('product_variant_code', $size)->first();
                    if (!$variantSize) {
                        try {
                            $variantSize = ProductVariant::create([
                                'product_variant_code'       => $size,
                                'product_variant_subtitle'  => '',
                                'product_variant_name'      => $size,
                                'product_variant_position'  => 0,
                                'parent'                    => 1
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    }


                    $variantType = ProductVariant::where('product_variant_code', $type)->first();
                    if (!$variantType) {
                        try {
                            $variantType = ProductVariant::create([
                                'product_variant_code'       => $type,
                                'product_variant_subtitle'  => '',
                                'product_variant_name'      => $type,
                                'product_variant_position'  => 0,
                                'parent'                    => 2
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    }

                    $product = Product::where('product_code', $variance['sap_matnr'])->first();
                    if ($product) {
                        try {
                            Product::where('product_code', $variance['sap_matnr'])->update([
                                'product_name_pos'  => implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]),
                                'product_status'    => $variance['status'],
                                'category_id_pos'    => $menu['category_id'],
                                'id_product_group'  => $createGroup->id_product_group
                            ]);

                            //check brand product
                            $checkBrand = BrandProduct::where('id_product', $product->id_product)->first();
                            if (!$checkBrand) {
                                $brandProduct = [
                                    'id_product' => $product->id_product,
                                    'id_brand'   => $getIdBrand->id_brand
                                ];
                                BrandProduct::create($brandProduct);
                            }

                            $countUpdate        = $countUpdate + 1;
                            $updatedProduct[]   = implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    } else {
                        try {
                            $product = Product::create([
                                'id_product_group'  => $createGroup->id_product_group,
                                'product_code'      => $variance['sap_matnr'],
                                'product_name'      => implode(" ", [ucwords(strtolower($createGroup->product_group_name)), $variance['size'], $variance['type']]),
                                'product_name_pos'  => implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]),
                                'product_status'    => $variance['status'],
                                'category_id_pos'    => $menu['category_id']
                            ]);

                            //insert brand
                            $brandProduct = [
                                'id_product' => $product->id_product,
                                'id_brand' => $getIdBrand->id_brand
                            ];
                            BrandProduct::create($brandProduct);

                            $countInsert        = $countInsert + 1;
                            $insertProduct[]    = implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                            $failedProduct[] = 'fail to sync, product ' . implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]);
                            continue;
                        }
                    }

                    try {
                        ProductProductVariant::updateOrCreate([
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantSize->id_product_variant
                        ], [
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantSize->id_product_variant
                        ]);
                        ProductProductVariant::updateOrCreate([
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantType->id_product_variant
                        ], [
                            'id_product'            => $product->id_product,
                            'id_product_variant'    => $variantType->id_product_variant
                        ]);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                        $failedProduct[] = 'fail to sync, product ' . implode(" ", [$createGroup->product_group_name, $variance['size'], $variance['type']]);;
                    }
                }
            }
            DB::commit();
        }
        $hasil['inserted']  = $countInsert;
        $hasil['updated']   = $countUpdate;
        $hasil['failed']   = $failedProduct;
        return [
            'status'    => 'success',
            'result'    => $hasil,
        ];
    }

    public function syncProductPrice(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $countInsert    = 0;
        $insertProduct  = [];
        $countfailed    = 0;
        $failedProduct  = [];
        $dataJob        = [];
        foreach ($post['menu'] as $keyMenu => $menu) {
            $checkProduct = Product::where('product_code', $menu['sap_matnr'])->first();
            if ($checkProduct) {
                foreach ($menu['price_detail'] as $keyPrice => $price) {
                    if ($price['start_date'] < date('Y-m-d')) {
                        $price['start_date'] = date('Y-m-d');
                    }
                    if ($price['end_date'] < $price['start_date']) {
                        $countfailed     = $countfailed + 1;
                        $failedProduct[] = 'Fail to sync, product ' . $menu['sap_matnr'] . ', recheck this date';
                        continue;
                    }

                    $checkOutlet = Outlet::where('outlet_code', $price['store_code'])->first();
                    if (!$checkOutlet) {
                        $countfailed     = $countfailed + 1;
                        $failedProduct[] = 'Fail to sync, product ' . $menu['sap_matnr'] . ', no outlet';
                        continue;
                    }

                    $dataJob[$keyMenu]['price_detail'][$keyPrice] = [
                        'id_product'    => $checkProduct->id_product,
                        'id_outlet'     => $checkOutlet->id_outlet,
                        'price'         => $price['price'],
                        'start_date'    => $price['start_date'],
                        'end_date'      => $price['end_date'],
                        'created_at'    => date('Y-m-d H:i:s'),
                        'updated_at'    => date('Y-m-d H:i:s')
                    ];

                    $countInsert     = $countInsert + 1;
                    $insertProduct[] = 'Success to sync price, product ' . $menu['sap_matnr'] . ' outlet ' . $price['store_code'];
                }
            } else {
                $countfailed     = $countfailed + 1;
                $failedProduct[] = 'Fail to sync, product ' . $menu['sap_matnr'] . ', product not found';
                continue;
            }
            if (isset($dataJob[$keyMenu]['price_detail'])) {
                SyncProductPrice::dispatch(json_encode($dataJob[$keyMenu]));
            }
        }

        $hasil['success_menu']['total']         = $countInsert;
        $hasil['success_menu']['list_menu']     = $insertProduct;
        $hasil['failed_product']['total']       = $countfailed;
        $hasil['failed_product']['list_menu']   = $failedProduct;
        return [
            'status'    => 'success',
            'result'    => $hasil,
        ];
    }

    /**
     * Save product price to temporary table and create queue
     * @param  Request $request [description]
     * {
     *     "api_key": "xxxxxxx",
     *     "api_secret": "xxxxxxx",
     *     "menu": [
     *         {
     *             "sap_matnr": "50000064",
     *             "price_detail": [
     *                 {
     *                     "store_code": "M001",
     *                     "price": 20000,
     *                     "start_date": "2020-04-01",
     *                     "end_date": "2020-04-30"
     *                 }
     *             ]
     *         }
     *     ]
     * }
     * @return [type]           [description]
     */
    public function syncProductPrice2(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $countInsert    = 0;
        $insertProduct  = [];
        $countfailed    = 0;
        $failedProduct  = [];
        $dataJob        = [];
        $dataOutlet     = [];
        $outlets        = [];

        // meminimalisir query ke tabel produk
        $products = [];
        $all_products = Product::select('id_product','product_code')->get();
        foreach ($all_products as $product) {
            $products[$product->product_code] = $product;
        }

        foreach ($post['menu'] as $keyMenu => $menu) {
            $checkProduct = $products[$menu['sap_matnr']]??null;
            if ($checkProduct) {
                foreach ($menu['price_detail'] as $keyPrice => $price) {
                    if ($price['start_date'] < date('Y-m-d')) {
                        $price['start_date'] = date('Y-m-d');
                    }
                    if ($price['end_date'] < $price['start_date']) {
                        $countfailed     = $countfailed + 1;
                        $failedProduct[] = 'Fail to sync, product ' . $menu['sap_matnr'] . ', recheck this date';
                        continue;
                    }

                    // meminimalisir query ke tabel outlet
                    if(!($outlets[$price['store_code']]??false)) {
                        $outlets[$price['store_code']] = Outlet::select('id_outlet')->where('outlet_code', $price['store_code'])->first();
                    }

                    $checkOutlet = $outlets[$price['store_code']];

                    if (!$checkOutlet) {
                        $countfailed     = $countfailed + 1;
                        $failedProduct[] = 'Fail to sync, product ' . $menu['sap_matnr'] . ', no outlet';
                        continue;
                    }
                    $dataOutlet[$checkOutlet->id_outlet] = null;
                    $dataJob[] = [
                        'id_product'    => $checkProduct->id_product,
                        'id_outlet'     => $checkOutlet->id_outlet,
                        'price'         => $price['price'],
                        'start_date'    => $price['start_date'],
                        'end_date'      => $price['end_date'],
                        'created_at'    => date('Y-m-d H:i:s')
                    ];

                    $countInsert     = $countInsert + 1;
                    $insertProduct[] = 'Success to sync price, product ' . $menu['sap_matnr'] . ' outlet ' . $price['store_code'];
                }
            } else {
                $countfailed     = $countfailed + 1;
                $failedProduct[] = 'Fail to sync, product ' . $menu['sap_matnr'] . ', product not found';
                continue;
            }
        }

        $insert = DB::connection('mysql')->table('outlet_product_price_periode_temps')->insert($dataJob);   
        
        if ($dataJob && $insert) {
            SyncProductPrice2::dispatch(array_keys($dataOutlet))->allOnConnection('database');
        } else {
            return [
                'status' => 'fail',
                'result' => ['Failed insert to database']
            ];
        }

        $hasil['success_menu']['total']         = $countInsert;
        $hasil['success_menu']['list_menu']     = $insertProduct;
        $hasil['failed_product']['total']       = $countfailed;
        $hasil['failed_product']['list_menu']   = $failedProduct;
        return [
            'status'    => 'success',
            'result'    => $hasil,
        ];
    }

    public function cronResetProductPriceTemp()
    {
        $log = MyHelper::logCron('Reset Temporary Product Price Period');
        try{
            DB::connection('mysql')->table('outlet_product_price_periode_temps')->whereDate('created_at', '<', date('Y-m-d', time()))->delete();
            $log->success();
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function syncProductDeactive(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $countDeleted   = 0;
        $deletedProduct = [];
        $failedProduct  = [];

        foreach ($post['menu'] as $keyMenu => $menu) {
            if (!isset($menu['sap_matnr'])) {
                $failedProduct[] = 'fail to sync deactive because sap_matnr not set';
                continue;
            }

            $getProduct = Product::select('id_product')->where('product_code', $menu['sap_matnr'])->first();
            if (!$getProduct) {
                $failedProduct[] = 'fail to sync deactive because sap_matnr ' . $menu['sap_matnr'] . ' not availabe';
                continue;
            } else {
                foreach ($menu['store_code'] as $keyOutlet => $outlet) {
                    $getOutlet = Outlet::select('id_outlet')->where('outlet_code', $outlet)->first();
                    if (!$getOutlet) {
                        $failedProduct[] = 'fail to sync deactive because store_code ' . $outlet . ' not availabe';
                        continue;
                    } else {
                        DB::beginTransaction();

                        ProductPrice::where([
                            'id_product' => $getProduct->id_product,
                            'id_outlet' => $getOutlet->id_outlet
                        ])->update(['product_status' => 'Inactive']);

                        DB::commit();
                        $countDeleted       = $countDeleted + 1;
                        $deletedProduct[]   = 'Success to sync deactive, product ' . $menu['sap_matnr'] . ' outlet ' . $outlet;
                    }
                }
            }
        }

        $hasil['success_menu']['total']         = $countDeleted;
        $hasil['success_menu']['list_menu']     = $deletedProduct;
        $hasil['failed_product']['list_menu']   = $failedProduct;
        return [
            'status'    => 'success',
            'result'    => $hasil,
        ];
    }

    public function syncAddOnDeactive(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $countDeleted   = 0;
        $deletedAddOn = [];
        $failedAddOn  = [];

        foreach ($post['add-on'] as $menu) {
            if (!isset($menu['sap_matnr'])) {
                $failedAddOn[] = 'fail to sync deactive because sap_matnr not set';
                continue;
            }

            $getProductModifier = ProductModifier::select('id_product_modifier')->where('code', $menu['sap_matnr'])->first();
            if (!$getProductModifier) {
                $failedAddOn[] = 'fail to sync deactive because sap_matnr ' . $menu['sap_matnr'] . ' not availabe';
                continue;
            } else {
                foreach ($menu['store_code'] as $outlet) {
                    $getOutlet = Outlet::select('id_outlet')->where('outlet_code', $outlet)->first();
                    if (!$getOutlet) {
                        $failedAddOn[] = 'fail to sync deactive because store_code ' . $outlet . ' not availabe';
                        continue;
                    } else {
                        DB::beginTransaction();

                        ProductModifierPrice::where([
                            'id_product_modifier' => $getProductModifier->id_product_modifier,
                            'id_outlet' => $getOutlet->id_outlet
                        ])->update(['product_modifier_status' => 'Inactive']);

                        DB::commit();
                        $countDeleted       = $countDeleted + 1;
                        $deletedAddOn[]   = 'Success to sync deactive, product modifier ' . $menu['sap_matnr'] . ' outlet ' . $outlet;
                    }
                }
            }
        }

        $hasil['success_menu']['total']         = $countDeleted;
        $hasil['success_menu']['list_menu']     = $deletedAddOn;
        $hasil['failed_product']['list_menu']   = $failedAddOn;
        return [
            'status'    => 'success',
            'result'    => $hasil,
        ];
    }

    public function syncAddOn(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $countInsert    = 0;
        $insertProduct  = [];
        $countUpdate    = 0;
        $updatedProduct = [];
        $failedProduct  = [];

        foreach ($post['add_on'] as $keyMenu => $menu) {
            if (!isset($menu['sap_matnr'])) {
                $failedProduct[] = 'fail to sync add on ' . $menu['name'] . ', because sap_matnr not set';
                continue;
            }
            if (!isset($menu['menu_id'])) {
                $failedProduct[] = 'fail to sync add on ' . $menu['name'] . ', because menu_id not set';
                continue;
            }
            if (!isset($menu['category_id'])) {
                $failedProduct[] = 'fail to sync add on ' . $menu['name'] . ', because category_id not set';
                continue;
            }
            if (!isset($menu['menu'])) {
                $failedProduct[] = 'fail to sync add on ' . $menu['name'] . ', because menu not set';
                continue;
            }

            DB::beginTransaction();
            $productModifier = ProductModifier::where('code', $menu['sap_matnr'])->first();
            if ($productModifier) {
                try {
                    ProductModifier::where('code', $menu['sap_matnr'])->update([
                        'text'      => $menu['name'],
                        'type'      => $menu['group'],
                        'modifier_type' => 'Specific',
                        'status'    => $menu['status'] = ($menu['status'] == 'Active') ? 1 : 0,
                        'menu_id_pos'          => $menu['menu_id'],
                        'category_id_pos'      => $menu['category_id']
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    LogBackendError::logExceptionMessage("ApiPOS/syncAddOn=>" . $e->getMessage(), $e);
                    $failedProduct[] = 'fail to sync, add on ' . $menu['name'];
                    continue;
                }

                foreach ($menu['menu'] as $keyVariance => $variance) {
                    $productGroup = ProductGroup::with('products')->where('product_group_code', $variance)->first();
                    if (!$productGroup) {
                        $failedProduct[] = 'fail to sync, product modifier ' . $productModifier->text;
                        continue;
                    }

                    if (isset($productGroup['products'])) {
                        foreach ($productGroup['products'] as $product) {
                            try {
                                ProductModifierProduct::updateOrCreate([
                                    'id_product'            => $product->id_product,
                                    'id_product_modifier'   => $productModifier->id_product_modifier
                                ], [
                                    'id_product'            => $product->id_product,
                                    'id_product_modifier'   => $productModifier->id_product_modifier
                                ]);
                                $countUpdate = $countUpdate + 1;
                            } catch (\Exception $e) {
                                DB::rollBack();
                                LogBackendError::logExceptionMessage("ApiPOS/syncAddOn=>" . $e->getMessage(), $e);
                                $failedProduct[] = 'fail to sync, product ' . $productModifier->text;
                                continue;
                            }
                        }
                    }
                }
            } else {
                try {
                    $productModifier = ProductModifier::create([
                        'code'      => $menu['sap_matnr'],
                        'text'      => $menu['name'],
                        'type'      => $menu['group'],
                        'modifier_type' => 'Specific',
                        'status'    => $menu['status'] = ($menu['status'] == 'Active') ? 1 : 0,
                        'menu_id_pos'          => $menu['menu_id'],
                        'category_id_pos'      => $menu['category_id']
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                    $failedProduct[] = 'fail to sync, product modifier ' . $menu['sap_matnr'];
                    continue;
                }

                foreach ($menu['menu'] as $keyVariance => $variance) {
                    $productGroup = ProductGroup::with('products')->where('product_group_code', $variance)->first();
                    if (!$productGroup) {
                        $failedProduct[] = 'fail to sync, product modifier ' . $productModifier->text;
                        continue;
                    }

                    if (isset($productGroup['products'])) {
                        foreach ($productGroup['products'] as $product) {
                            try {
                                ProductModifierProduct::updateOrCreate([
                                    'id_product'            => $product->id_product,
                                    'id_product_modifier'   => $productModifier->id_product_modifier
                                ], [
                                    'id_product'            => $product->id_product,
                                    'id_product_modifier'   => $productModifier->id_product_modifier
                                ]);
                                $countUpdate = $countUpdate + 1;
                            } catch (\Exception $e) {
                                DB::rollBack();
                                LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                                $failedProduct[] = 'fail to sync, product ' . $productModifier->text;
                                continue;
                            }
                        }
                    }
                }
            }
            DB::commit();
        }
        $hasil['inserted']  = $countInsert;
        $hasil['updated']   = $countUpdate;
        $hasil['failed']   = $failedProduct;
        return [
            'status'    => 'success',
            'result'    => $hasil,
        ];
    }

    public function syncAddOnPrice(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $countInsert    = 0;
        $insertProduct  = [];
        $countfailed    = 0;
        $failedProduct  = [];
        $dataJob        = [];
        foreach ($post['add_on'] as $keyMenu => $menu) {
            $checkProduct = ProductModifier::where('code', $menu['sap_matnr'])->first();
            if ($checkProduct) {
                foreach ($menu['price_detail'] as $keyPrice => $price) {
                    if ($price['start_date'] < date('Y-m-d')) {
                        $price['start_date'] = date('Y-m-d');
                    }
                    if ($price['end_date'] < $price['start_date']) {
                        $countfailed     = $countfailed + 1;
                        $failedProduct[] = 'Fail to sync, product modiffier ' . $menu['sap_matnr'] . ', recheck this date';
                        continue;
                    }

                    $checkOutlet = Outlet::where('outlet_code', $price['store_code'])->first();
                    if (!$checkOutlet) {
                        $countfailed     = $countfailed + 1;
                        $failedProduct[] = 'Fail to sync, product modiffier ' . $menu['sap_matnr'] . ', no outlet';
                        continue;
                    }

                    $dataJob[$keyMenu]['price_detail'][$keyPrice] = [
                        'id_product_modifier'   => $checkProduct->id_product_modifier,
                        'id_outlet'             => $checkOutlet->id_outlet,
                        'price'                 => $price['price'],
                        'start_date'            => $price['start_date'],
                        'end_date'              => $price['end_date'],
                        'created_at'            => date('Y-m-d H:i:s'),
                        'updated_at'            => date('Y-m-d H:i:s')
                    ];

                    $countInsert     = $countInsert + 1;
                    $insertProduct[] = 'Success to sync price, product modiffier ' . $menu['sap_matnr'] . ' outlet ' . $price['store_code'];
                }
            } else {
                $countfailed     = $countfailed + 1;
                $failedProduct[] = 'Fail to sync, menu ' . $menu['sap_matnr'] . ', menu not found';
                continue;
            }
            if (isset($dataJob[$keyMenu]['price_detail'])) {
                SyncAddOnPrice::dispatch(json_encode($dataJob[$keyMenu]));
            }
        }

        $hasil['success_menu']['total']         = $countInsert;
        $hasil['success_menu']['list_menu']     = $insertProduct;
        $hasil['failed_product']['total']       = $countfailed;
        $hasil['failed_product']['list_menu']   = $failedProduct;
        return [
            'status'    => 'success',
            'result'    => $hasil,
        ];
    }

    public function cronProductPricePriority()
    {
        $log = MyHelper::logCron('Sync Product Price Priority');
        try {
            $update = ProductPrice::join('outlet_product_price_periodes', function($join) {
                $join->on('product_prices.id_product', '=', 'outlet_product_price_periodes.id_product')
                    ->whereColumn('product_prices.id_outlet', '=', 'outlet_product_price_periodes.id_outlet')
                    ->where(function($query) {
                        $query->whereColumn('outlet_product_price_periodes.price', '<>', 'product_prices.product_price')
                            ->orWhereNull('product_prices.product_price');
                    });
            })->join('outlets', function($join) {
                $join->on('product_prices.id_outlet', '=', 'outlets.id_outlet')
                    ->where('is_24h', 1);
            })->where([
                ['start_date', '<=', date('Y-m-d')],
                ['end_date', '>=', date('Y-m-d')],
            ])->update([
                'product_price' => \DB::raw('outlet_product_price_periodes.price'),
                'updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
            ]);

            // create not existing product
            $toCreate = [];
            $products = \DB::connection('mysql')
                ->table('outlet_product_price_periodes')->join('outlets', function($join) {
                    $join->on('outlet_product_price_periodes.id_outlet', '=', 'outlets.id_outlet')
                        ->where('is_24h', 1);
                })
                ->join('products', 'outlet_product_price_periodes.id_product', '=', 'products.id_product')
                ->leftJoin('product_prices', function ($join) {
                    $join->on('outlet_product_price_periodes.id_outlet', '=', 'product_prices.id_outlet')
                        ->whereColumn('outlet_product_price_periodes.id_product', '=', 'product_prices.id_product');
                })
                ->whereNull('product_prices.id_product_price')
                ->where([
                    ['start_date', '<=', date('Y-m-d')],
                    ['end_date', '>=', date('Y-m-d')],
                ])
                ->select(
                    'outlet_product_price_periodes.id_product',
                    'outlet_product_price_periodes.id_outlet',
                    'outlet_product_price_periodes.price',
                    'outlet_product_price_periodes.start_date','outlet_product_price_periodes.end_date'
                )
                ->get();

            foreach ($products as $product) {
                $kwd = $product->id_product.'.'.$product->id_outlet;
                $toCreate[$kwd] = [
                    'id_product' => $product->id_product,
                    'id_outlet' => $product->id_outlet,
                    'product_price' => $product->price,
                    'created_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                    'updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                ];
            }
            if ($toCreate) {
                \DB::connection('mysql')->table('product_prices')->insert(array_values($toCreate));
            }

            $log->success(['update'=>$update,'create'=>count($toCreate)]);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function cronProductPrice()
    {
        $log = MyHelper::logCron('Sync Product Price');
        try {
            $update = ProductPrice::join('outlet_product_price_periodes', function($join) {
                $join->on('product_prices.id_product', '=', 'outlet_product_price_periodes.id_product')
                    ->whereColumn('product_prices.id_outlet', '=', 'outlet_product_price_periodes.id_outlet')
                    ->where(function($query) {
                        $query->whereColumn('outlet_product_price_periodes.price', '<>', 'product_prices.product_price')
                            ->orWhereNull('product_prices.product_price');
                    });
            })->join('outlets', function($join) {
                $join->on('product_prices.id_outlet', '=', 'outlets.id_outlet')
                    ->where('is_24h', 0);
            })->where([
                ['start_date', '<=', date('Y-m-d')],
                ['end_date', '>=', date('Y-m-d')],
            ])->update([
                'product_price' => \DB::raw('outlet_product_price_periodes.price'),
                'updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
            ]);

            // create not existing product
            $toCreate = [];
            $products = \DB::connection('mysql')
                ->table('outlet_product_price_periodes')->join('outlets', function($join) {
                    $join->on('outlet_product_price_periodes.id_outlet', '=', 'outlets.id_outlet')
                        ->where('is_24h', 0);
                })
                ->join('products', 'outlet_product_price_periodes.id_product', '=', 'products.id_product')
                ->leftJoin('product_prices', function ($join) {
                    $join->on('outlet_product_price_periodes.id_outlet', '=', 'product_prices.id_outlet')
                        ->whereColumn('outlet_product_price_periodes.id_product', '=', 'product_prices.id_product');
                })
                ->whereNull('product_prices.id_product_price')
                ->where([
                    ['start_date', '<=', date('Y-m-d')],
                    ['end_date', '>=', date('Y-m-d')],
                ])
                ->select(
                    'outlet_product_price_periodes.id_product',
                    'outlet_product_price_periodes.id_outlet',
                    'outlet_product_price_periodes.price',
                    'outlet_product_price_periodes.start_date','outlet_product_price_periodes.end_date'
                )
                ->get();

            foreach ($products as $product) {
                $kwd = $product->id_product.'.'.$product->id_outlet;
                $toCreate[$kwd] = [
                    'id_product' => $product->id_product,
                    'id_outlet' => $product->id_outlet,
                    'product_price' => $product->price,
                    'created_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                    'updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                ];
            }
            if ($toCreate) {
                \DB::connection('mysql')->table('product_prices')->insert(array_values($toCreate));
            }

            $log->success(['update'=>$update,'create'=>count($toCreate)]);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function cronAddOnPrice()
    {
        $log = MyHelper::logCron('Sync Add On Price');
        try {
            $update = ProductModifierPrice::join('outlet_product_modifier_price_periodes', function($join) {
                $join->on('product_modifier_prices.id_product_modifier', '=', 'outlet_product_modifier_price_periodes.id_product_modifier')
                    ->whereColumn('product_modifier_prices.id_outlet', '=', 'outlet_product_modifier_price_periodes.id_outlet')
                    ->where(function($query) {
                        $query->whereColumn('outlet_product_modifier_price_periodes.price', '<>', 'product_modifier_prices.product_modifier_price')
                            ->orWhereNull('product_modifier_prices.product_modifier_price');
                    });
            })->where([
                ['start_date', '<=', date('Y-m-d')],
                ['end_date', '>=', date('Y-m-d')],
            ])->update([
                'product_modifier_price' => \DB::raw('outlet_product_modifier_price_periodes.price'),
                'updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
            ]);

            // create not existing product
            $toCreate = [];
            $products = \DB::connection('mysql')
                ->table('outlet_product_modifier_price_periodes')->join('outlets', 'outlets.id_outlet', '=', 'outlet_product_modifier_price_periodes.id_outlet')
                ->join('product_modifiers', 'outlet_product_modifier_price_periodes.id_product_modifier', '=', 'product_modifiers.id_product_modifier')
                ->leftJoin('product_modifier_prices', function ($join) {
                    $join->on('outlet_product_modifier_price_periodes.id_outlet', '=', 'product_modifier_prices.id_outlet')
                        ->whereColumn('outlet_product_modifier_price_periodes.id_product_modifier', '=', 'product_modifier_prices.id_product_modifier');
                })
                ->whereNull('product_modifier_prices.id_product_modifier_price')
                ->where([
                    ['start_date', '<=', date('Y-m-d')],
                    ['end_date', '>=', date('Y-m-d')],
                ])
                ->select(
                    'outlet_product_modifier_price_periodes.id_product_modifier',
                    'outlet_product_modifier_price_periodes.id_outlet',
                    'outlet_product_modifier_price_periodes.price',
                )
                ->get();

            foreach ($products as $product) {
                $kwd = $product->id_product_modifier.'.'.$product->id_outlet;
                $toCreate[$kwd] = [
                    'id_product_modifier' => $product->id_product_modifier,
                    'id_outlet' => $product->id_outlet,
                    'product_modifier_price' => $product->price,
                    'created_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                    'updated_at' => \DB::raw('CURRENT_TIMESTAMP()'),
                ];
            }
            if ($toCreate) {
                \DB::connection('mysql')->table('product_modifier_prices')->insert(array_values($toCreate));
            }

            $log->success(['update'=>$update,'create'=>count($toCreate)]);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function syncMenu(Request $request)
    {
        $post = $request->json()->all();
        return $this->syncMenuProcess($post, 'partial');
    }

    public function syncMenuProcess($data, $flag)
    {
        $syncDatetime = date('d F Y h:i');
        $getBrand = Brand::pluck('code_brand')->toArray();
        $getBrandList = Brand::select('id_brand', 'code_brand')->get()->toArray();
        $outlet = Outlet::where('outlet_code', strtoupper($data['store_code']))->first();
        if ($outlet) {
            $countInsert = 0;
            $countUpdate = 0;
            $rejectedProduct = [];
            $updatedProduct = [];
            $insertedProduct = [];
            $failedProduct = [];
            foreach ($data['menu'] as $key => $menu) {
                if (empty($menu['brand_code'])) {
                    $brand = Brand::first();
                    $value['brand_code'] = [$brand->code_brand];
                }
                if (!isset($menu['brand_code'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because brand_code not set';
                    continue;
                }
                if (!isset($menu['plu_id'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because plu_id not set';
                    continue;
                }
                if (!isset($menu['name'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because name not set';
                    continue;
                }
                if (!isset($menu['category'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because category not set';
                    continue;
                }
                if (!isset($menu['price'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because price not set';
                    continue;
                }
                if (!isset($menu['price_base'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because price_base not set';
                    continue;
                }
                if (!isset($menu['price_tax'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because price_tax not set';
                    continue;
                }
                if (!isset($menu['status'])) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because status not set';
                    continue;
                }
                $diffBrand = array_diff($menu['brand_code'], $getBrand);
                if (!empty($diffBrand)) {
                    $failedProduct[] = 'fail to sync product ' . $menu['name'] . ', because code brand ' . implode(', ', $diffBrand) . ' not found';
                    continue;
                }
                if (isset($menu['plu_id']) && isset($menu['name'])) {
                    DB::beginTransaction();
                    $product = Product::where('product_code', $menu['plu_id'])->first();
                    // update product
                    if ($product) {
                        // cek allow sync, jika 0 product tidak di update
                        if ($product->product_allow_sync == '1') {
                            $cekBrandProduct = BrandProduct::join('brands', 'brands.id_brand', 'brand_product.id_brand')->where('id_product', $product->id_product)->pluck('code_brand')->toArray();
                            // delete diff brand
                            $deleteDiffBrand = array_diff($cekBrandProduct, $menu['brand_code']);
                            if (!empty($deleteDiffBrand)) {
                                try {
                                    BrandProduct::join('brands', 'brands.id_brand', 'brand_product.id_brand')->where('id_product', $product->id_product)->whereIn('brand_product.id_brand', $deleteDiffBrand)->delete();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                                    $failedProduct[] = 'fail to sync, product ' . $menu['name'];
                                    continue;
                                }
                            }
                            $createDiffBrand = array_diff($menu['brand_code'], $cekBrandProduct);
                            if (!empty($createDiffBrand)) {
                                try {
                                    $brandProduct = [];
                                    foreach ($createDiffBrand as $menuBrand) {
                                        $getIdBrand = $getBrandList[array_search($menuBrand, $getBrand)]['id_brand'];
                                        $brandProduct[] = [
                                            'id_product' => $product->id_product,
                                            'id_brand' => $getIdBrand,
                                            'created_at' => date('Y-m-d H:i:s'),
                                            'updated_at' => date('Y-m-d H:i:s'),
                                        ];
                                    }
                                    BrandProduct::insert($brandProduct);
                                } catch (Exception $e) {
                                    DB::rollBack();
                                    LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                                    $failedProduct[] = 'fail to sync, product ' . $menu['name'];
                                    continue;
                                }
                            }
                            // cek name pos, jika beda product tidak di update
                            if (empty($product->product_name_pos) || $product->product_name_pos == $menu['name']) {
                                // update modifiers
                                if (isset($menu['modifiers'])) {
                                    ProductModifierProduct::where('id_product', $product['id_product'])->delete();
                                    foreach ($menu['modifiers'] as $mod) {
                                        $dataProductMod['type'] = $mod['type'];
                                        if (isset($mod['text']))
                                            $dataProductMod['text'] = $mod['text'];
                                        else
                                            $dataProductMod['text'] = null;
                                        $dataProductMod['modifier_type'] = 'Specific';
                                        $updateProductMod = ProductModifier::updateOrCreate([
                                            'code'  => $mod['code']
                                        ], $dataProductMod);
                                        $id_product_modifier = $updateProductMod['id_product_modifier'];
                                        ProductModifierProduct::create([
                                            'id_product_modifier' => $id_product_modifier,
                                            'id_product' => $product['id_product']
                                        ]);
                                    }
                                }
                                // update price
                                $productPrice = ProductPrice::where('id_product', $product->id_product)->where('id_outlet', $outlet->id_outlet)->first();
                                if ($productPrice) {
                                    $oldPrice =  $productPrice->product_price;
                                    $oldUpdatedAt =  $productPrice->updated_at;
                                } else {
                                    $oldPrice = null;
                                    $oldUpdatedAt = null;
                                }
                                $dataProductPrice['product_price'] = (int) round($menu['price']);
                                $dataProductPrice['product_price_base'] = round($menu['price_base'], 2);
                                $dataProductPrice['product_price_tax'] = round($menu['price_tax'], 2);
                                $dataProductPrice['product_status'] = $menu['status'];
                                try {
                                    $updateProductPrice = ProductPrice::updateOrCreate([
                                        'id_product' => $product->id_product,
                                        'id_outlet'  => $outlet->id_outlet
                                    ], $dataProductPrice);
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    LogBackendError::logExceptionMessage("ApiPOS/syncMenu=>" . $e->getMessage(), $e);
                                    $failedProduct[] = ['product_code' => $menu['plu_id'], 'product_name' => $menu['name']];
                                }
                                //upload photo
                                // $imageUpload = [];
                                // if (isset($menu['photo'])) {
                                //     foreach ($menu['photo'] as $photo) {
                                //         $image = file_get_contents($photo['url']);
                                //         $img = base64_encode($image);
                                //         if (!file_exists('img/product/item/')) {
                                //             mkdir('img/product/item/', 0777, true);
                                //         }
                                //         $upload = MyHelper::uploadPhotoStrict($img, 'img/product/item/', 300, 300);
                                //         if (isset($upload['status']) && $upload['status'] == "success") {
                                //             $orderPhoto = ProductPhoto::where('id_product', $product->id_product)->orderBy('product_photo_order', 'desc')->first();
                                //             if ($orderPhoto) {
                                //                 $orderPhoto = $orderPhoto->product_photo_order + 1;
                                //             } else {
                                //                 $orderPhoto = 1;
                                //             }
                                //             $dataPhoto['id_product'] = $product->id_product;
                                //             $dataPhoto['product_photo'] = $upload['path'];
                                //             $dataPhoto['product_photo_order'] = $orderPhoto;
                                //             try {
                                //                 $photo = ProductPhoto::create($dataPhoto);
                                //             } catch (\Exception $e) {
                                //                 DB::rollBack();
                                //                 LogBackendError::logExceptionMessage("ApiPOS/syncMenu=>" . $e->getMessage(), $e);
                                //                 $failedProduct[] = ['product_code' => $menu['plu_id'], 'product_name' => $menu['name']];
                                //             }
                                //             //add in array photo
                                //             $imageUpload[] = $photo['product_photo'];
                                //         } else {
                                //             DB::rollBack();
                                //             $failedProduct[] = ['product_code' => $menu['plu_id'], 'product_name' => $menu['name']];
                                //         }
                                //     }
                                // }
                                $countUpdate++;
                                // list updated product utk data log
                                $newProductPrice = ProductPrice::where('id_product', $product->id_product)->where('id_outlet', $outlet->id_outlet)->first();
                                $newUpdatedAt =  $newProductPrice->updated_at;
                                $updateProd['id_product'] = $product['id_product'];
                                $updateProd['plu_id'] = $product['product_code'];
                                $updateProd['product_name'] = $product['product_name'];
                                $updateProd['old_price'] = $oldPrice;
                                $updateProd['new_price'] = (int) round($menu['price']);
                                $updateProd['old_updated_at'] = $oldUpdatedAt;
                                $updateProd['new_updated_at'] = $newUpdatedAt;
                                // if (count($imageUpload) > 0) {
                                //     $updateProd['new_photo'] = $imageUpload;
                                // }
                                $updatedProduct[] = $updateProd;
                            } else {
                                // Add product to rejected product
                                $productPrice = ProductPrice::where('id_outlet', $outlet->id_outlet)->where('id_product', $product->id_product)->first();
                                $dataBackend['plu_id'] = $product->product_code;
                                $dataBackend['name'] = $product->product_name_pos;
                                if (empty($productPrice)) {
                                    $dataBackend['price'] = '';
                                } else {
                                    $dataBackend['price'] = number_format($productPrice->product_price, 0, ',', '.');
                                }
                                $dataRaptor['plu_id'] = $menu['plu_id'];
                                $dataRaptor['name'] = $menu['name'];
                                $dataRaptor['price'] = number_format($menu['price'], 0, ',', '.');
                                array_push($rejectedProduct, ['backend' => $dataBackend, 'raptor' => $dataRaptor]);
                            }
                        }
                    }
                    // insert product
                    else {
                        $create = Product::create(['product_code' => $menu['plu_id'], 'product_name_pos' => $menu['name'], 'product_name' => $menu['name']]);
                        if ($create) {
                            // update price
                            $dataProductPrice['product_price'] = (int) round($menu['price']);
                            $dataProductPrice['product_price_base'] = round($menu['price_base'], 2);
                            $dataProductPrice['product_price_tax'] = round($menu['price_tax'], 2);
                            $dataProductPrice['product_status'] = $menu['status'];
                            try {
                                $updateProductPrice = ProductPrice::updateOrCreate([
                                    'id_product' => $create['id_product'],
                                    'id_outlet'  => $outlet->id_outlet
                                ], $dataProductPrice);
                            } catch (\Exception $e) {
                                DB::rollBack();
                                LogBackendError::logExceptionMessage("ApiPOS/syncMenu=>" . $e->getMessage(), $e);
                                $failedProduct[] = ['product_code' => $menu['plu_id'], 'product_name' => $menu['name']];
                            }
                            try {
                                $brandProduct = [];
                                foreach ($menu['brand_code'] as $valueBrand) {
                                    $getIdBrand = $getBrandList[array_search($valueBrand, $getBrand)]['id_brand'];
                                    $brandProduct[] = [
                                        'id_product' => $create['id_product'],
                                        'id_brand' => $getIdBrand,
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'updated_at' => date('Y-m-d H:i:s'),
                                    ];
                                }
                                BrandProduct::insert($brandProduct);
                            } catch (Exception $e) {
                                DB::rollBack();
                                LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                                $failedProduct[] = 'fail to sync, brand ' . $menu['name'];
                                continue;
                            }
                            // $imageUpload = [];
                            // if (isset($menu['photo'])) {
                            //     foreach ($menu['photo'] as $photo) {
                            //         $image = file_get_contents($photo['url']);
                            //         $img = base64_encode($image);
                            //         if (!file_exists('img/product/item/')) {
                            //             mkdir('img/product/item/', 0777, true);
                            //         }
                            //         $upload = MyHelper::uploadPhotoStrict($img, 'img/product/item/', 300, 300);
                            //         if (isset($upload['status']) && $upload['status'] == "success") {
                            //             $dataPhoto['id_product'] = $product->id_product;
                            //             $dataPhoto['product_photo'] = $upload['path'];
                            //             $dataPhoto['product_photo_order'] = 1;
                            //             try {
                            //                 $photo = ProductPhoto::create($dataPhoto);
                            //             } catch (\Exception $e) {
                            //                 DB::rollBack();
                            //                 LogBackendError::logExceptionMessage("ApiPOS/syncMenu=>" . $e->getMessage(), $e);
                            //                 $failedProduct[] = ['product_code' => $menu['plu_id'], 'product_name' => $menu['name']];
                            //             }
                            //             //add in array photo
                            //             $imageUpload[] = $photo['product_photo'];
                            //         } else {
                            //             DB::rollBack();
                            //             $failedProduct[] = ['product_code' => $menu['plu_id'], 'product_name' => $menu['name']];
                            //         }
                            //     }
                            // }
                            $countInsert++;
                            // list new product utk data log
                            $insertProd['id_product'] = $create['id_product'];
                            $insertProd['plu_id'] = $create['product_code'];
                            $insertProd['product_name'] = $create['product_name'];
                            $insertProd['price'] = (int) round($menu['price']);
                            // if (count($imageUpload) > 0) {
                            //     $updateProd['new_photo'] = $imageUpload;
                            // }
                            $insertedProduct[] = $insertProd;
                        }
                    }
                    DB::commit();
                }
            }
            if ($modifier_prices = ($data['modifier'] ?? false)) {
                foreach ($modifier_prices as $modifier) {
                    $promod = ProductModifier::select('id_product_modifier')->where('code', $modifier['code'])->first();
                    if (!$promod) {
                        continue;
                    }
                    $data_key = [
                        'id_outlet' => $outlet->id_outlet,
                        'id_product_modifier' => $promod->id_product_modifier
                    ];
                    $data_price = [];
                    if (isset($modifier['price'])) {
                        $data_price['product_modifier_price'] = $modifier['price'];
                    }
                    if ($modifier['status'] ?? false) {
                        $data_price['product_modifier_status'] = $modifier['status'];
                    }
                    ProductModifierPrice::updateOrCreate($data_key, $data_price);
                }
            }
            if ($flag == 'partial') {
                if (count($rejectedProduct) > 0) {
                    $this->syncSendEmail($syncDatetime, $outlet->outlet_code, $outlet->outlet_name, $rejectedProduct, null);
                }
                if (count($failedProduct) > 0) {
                    $this->syncSendEmail($syncDatetime, $outlet->outlet_code, $outlet->outlet_name, null, $failedProduct);
                }
            }
            $hasil['new_product']['total'] = (string) $countInsert;
            $hasil['new_product']['list_product'] = $insertedProduct;
            $hasil['updated_product']['total'] = (string) $countUpdate;
            $hasil['updated_product']['list_product'] = $updatedProduct;
            $hasil['rejected_product']['list_product'] = $rejectedProduct;
            $hasil['failed_product']['list_product'] = $failedProduct;
            return [
                'status'    => 'success',
                'result'  => $hasil,
            ];
        } else {
            return [
                'status'    => 'fail',
                'messages'  => ['store_code ' . $data['store_code'] . ' isn\'t match']
            ];
        }
    }

    public function syncSendEmail($syncDatetime, $outlet_code, $outlet_name, $rejectedProduct = null, $failedProduct = null)
    {
        $emailSync = Setting::where('key', 'email_sync_menu')->first();
        if (!empty($emailSync) && $emailSync->value != null) {
            $emailSync = explode(',', $emailSync->value);
            foreach ($emailSync as $key => $to) {
                $subject = 'Rejected product from menu sync raptor';
                $content['sync_datetime'] = $syncDatetime;
                $content['outlet_code'] = $outlet_code;
                $content['outlet_name'] = $outlet_name;
                if ($rejectedProduct != null) {
                    $content['total_rejected'] = count($rejectedProduct);
                    $content['rejected_menu'] = $rejectedProduct;
                }
                if ($failedProduct != null) {
                    $content['total_failed'] = count($failedProduct);
                    $content['failed_menu'] = $failedProduct;
                }
                // get setting email
                $setting = array();
                $set = Setting::where('key', 'email_from')->first();
                if (!empty($set)) {
                    $setting['email_from'] = $set['value'];
                } else {
                    $setting['email_from'] = null;
                }
                $set = Setting::where('key', 'email_sender')->first();
                if (!empty($set)) {
                    $setting['email_sender'] = $set['value'];
                } else {
                    $setting['email_sender'] = null;
                }
                $set = Setting::where('key', 'email_reply_to')->first();
                if (!empty($set)) {
                    $setting['email_reply_to'] = $set['value'];
                } else {
                    $setting['email_reply_to'] = null;
                }
                $set = Setting::where('key', 'email_reply_to_name')->first();
                if (!empty($set)) {
                    $setting['email_reply_to_name'] = $set['value'];
                } else {
                    $setting['email_reply_to_name'] = null;
                }
                $set = Setting::where('key', 'email_cc')->first();
                if (!empty($set)) {
                    $setting['email_cc'] = $set['value'];
                } else {
                    $setting['email_cc'] = null;
                }
                $set = Setting::where('key', 'email_cc_name')->first();
                if (!empty($set)) {
                    $setting['email_cc_name'] = $set['value'];
                } else {
                    $setting['email_cc_name'] = null;
                }
                $set = Setting::where('key', 'email_bcc')->first();
                if (!empty($set)) {
                    $setting['email_bcc'] = $set['value'];
                } else {
                    $setting['email_bcc'] = null;
                }
                $set = Setting::where('key', 'email_bcc_name')->first();
                if (!empty($set)) {
                    $setting['email_bcc_name'] = $set['value'];
                } else {
                    $setting['email_bcc_name'] = null;
                }
                $set = Setting::where('key', 'email_logo')->first();
                if (!empty($set)) {
                    $setting['email_logo'] = $set['value'];
                } else {
                    $setting['email_logo'] = null;
                }
                $set = Setting::where('key', 'email_logo_position')->first();
                if (!empty($set)) {
                    $setting['email_logo_position'] = $set['value'];
                } else {
                    $setting['email_logo_position'] = null;
                }
                $set = Setting::where('key', 'email_copyright')->first();
                if (!empty($set)) {
                    $setting['email_copyright'] = $set['value'];
                } else {
                    $setting['email_copyright'] = null;
                }
                $set = Setting::where('key', 'email_disclaimer')->first();
                if (!empty($set)) {
                    $setting['email_disclaimer'] = $set['value'];
                } else {
                    $setting['email_disclaimer'] = null;
                }
                $set = Setting::where('key', 'email_contact')->first();
                if (!empty($set)) {
                    $setting['email_contact'] = $set['value'];
                } else {
                    $setting['email_contact'] = null;
                }
                $data = array(
                    'content' => $content,
                    'setting' => $setting
                );
                Mail::send('pos::email_sync_menu', $data, function ($message) use ($to, $subject, $setting) {
                    $message->to($to)->subject($subject);
                    if (env('MAIL_DRIVER') == 'mailgun') {
                        $message->trackClicks(true)
                            ->trackOpens(true);
                    }
                    if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                        $message->from($setting['email_sender'], $setting['email_from']);
                    } else if (!empty($setting['email_sender'])) {
                        $message->from($setting['email_sender']);
                    }

                    if (!empty($setting['email_reply_to'])) {
                        $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                    }
                    if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                        $message->cc($setting['email_cc'], $setting['email_cc_name']);
                    }
                    if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                        $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                    }
                });
            }
        }
    }

    public function syncMenuReturn(reqMenu $request)
    {
        // call function syncMenu
        $url = env('API_URL') . 'api/v1/pos/menu/sync';
        $syncMenu = MyHelper::post($url, MyHelper::getBearerToken(), $request->json()->all());

        // return sesuai api raptor
        if (isset($syncMenu['status']) && $syncMenu['status'] == 'success') {
            $hasil['inserted'] = $syncMenu['result']['new_product']['total'];
            $hasil['updated'] = $syncMenu['result']['updated_product']['total'];
            return response()->json([
                'status'    => 'success',
                'result'  => [$hasil]
            ]);
        }
        return $syncMenu;
    }

    public function transaction(Request $request, $post = null, $cek = 1)
    {
        if (!$post) {
            $post = $request->json()->all();
        }

        if (($cek != 1 &&
                !empty($post['store_code']) && !empty($post['transactions'])) ||
            ($cek == 1 && !empty($post['api_key']) && !empty($post['api_secret']) &&
                !empty($post['store_code']) && !empty($post['transactions']))
        ) {

            if ($cek == 1) {
                $api = $this->checkApi($post['api_key'], $post['api_secret']);
                if ($api['status'] != 'success') {
                    return response()->json($api);
                }
            }

            $checkOutlet = Outlet::where('outlet_code', strtoupper($post['store_code']))->first();
            if (empty($checkOutlet)) {
                return response()->json(['status' => 'fail', 'messages' => 'Store not found']);
            }

            $countTransaction = count($post['transactions']);
            $x = 10;

            $countTransactionFail = 0;
            $countTransactionSuccess = 0;
            $countTransactionDuplicate = 0;
            $detailTransactionFail = [];

            if ($countTransaction <= $x) {

                $getIdBrand = Brand::select('id_brand')->first();
                if (!$getIdBrand) {
                    return [
                        'status'    => 'fail',
                        'messages'  => ['failed get brand']
                    ];
                }

                $config['point']    = Configs::where('config_name', 'point')->first()->is_active;
                $config['balance']  = Configs::where('config_name', 'balance')->first()->is_active;
                $config['unique_receipt_outlet'] = Configs::where('config_name', 'unique receipt outlet')->first()->is_active;
                $config['fraud_use_queue'] = Configs::where('config_name', 'fraud use queue')->first()->is_active;
                $settingPoint       = Setting::where('key', 'point_conversion_value')->first()->value;
                $transOriginal      = $post['transactions'];

                $result = array();

                $receipt = array_column($post['transactions'], 'trx_id');
                //exclude receipt number when already exist in outlet
                $checkReceipt = Transaction::select('transaction_receipt_number', 'id_transaction');
                if ($config['unique_receipt_outlet'] == '1') {
                    $checkReceipt = $checkReceipt->where('id_outlet', $checkOutlet['id_outlet']);
                }
                $checkReceipt = $checkReceipt->whereIn('transaction_receipt_number', $receipt)
                    ->where('trasaction_type', 'Offline')
                    ->get();
                $convertTranscToArray = $checkReceipt->toArray();
                $receiptExist = $checkReceipt->pluck('transaction_receipt_number')->toArray();

                $invalidReceipt = array_intersect($receipt, $receiptExist);
                foreach ($invalidReceipt as $key => $invalid) {
                    $countTransactionDuplicate++;
                    unset($post['transactions'][$key]);
                }

                //check possibility duplicate when receipt number unique per outlet
                if ($config['unique_receipt_outlet'] == '1') {
                    $validReceipt = array_diff($receipt, $receiptExist);

                    $receiptDuplicate = Transaction::where('id_outlet', '!=', $checkOutlet['id_outlet'])
                        ->whereIn('transaction_receipt_number', $validReceipt)
                        ->where('trasaction_type', 'Offline')
                        ->select('transaction_receipt_number')
                        ->get()->pluck('transaction_receipt_number')->toArray();

                    $transactionDuplicate = TransactionDuplicate::where('id_outlet', '=', $checkOutlet['id_outlet'])
                        ->whereIn('transaction_receipt_number', $validReceipt)
                        ->select('transaction_receipt_number')
                        ->get()->pluck('transaction_receipt_number')->toArray();

                    $receiptDuplicate = array_intersect($receipt, $receiptDuplicate);

                    $contentDuplicate = [];
                    foreach ($receiptDuplicate as $key => $receipt) {
                        if (in_array($receipt, $transactionDuplicate)) {
                            $countTransactionDuplicate++;
                            unset($post['transactions'][$key]);
                        } else {
                            $duplicate = $this->processDuplicate($post['transactions'][$key], $checkOutlet);
                            if (isset($duplicate['status']) && $duplicate['status'] == 'duplicate') {
                                $countTransactionDuplicate++;
                                $data = [
                                    'trx' => $duplicate['trx'],
                                    'duplicate' => $duplicate['duplicate']
                                ];
                                $contentDuplicate[] = $data;
                                unset($post['transactions'][$key]);
                            }
                        }
                    }
                }

                $productList = Product::select('id_product', 'product_code')->get()->toArray();
                $allProductCode = array_column($productList, 'product_code');
                $groupList = ProductGroup::select('id_product_group', 'product_group_code', 'product_group_name')->get()->toArray();
                $allGroupCode = array_column($groupList, 'product_group_code');
                $variantList = ProductVariant::select('id_product_variant', 'product_variant_code')->get()->toArray();
                $allVariantCode = array_column($variantList, 'product_variant_code');
                $modList = ProductModifier::select('id_product_modifier', 'code', 'type', 'text')->get()->toArray();
                $allModCode = array_column($modList, 'code');

                $countSettingCashback = TransactionSetting::get();
                $fraudTrxDay = FraudSetting::where('parameter', 'LIKE', '%transactions in 1 day%')->where('fraud_settings_status', 'Active')->first();
                $fraudTrxWeek = FraudSetting::where('parameter', 'LIKE', '%transactions in 1 week%')->where('fraud_settings_status', 'Active')->first();
                foreach ($post['transactions'] as $key => $trx) {
                    if (
                        !empty($trx['date_time']) &&
                        isset($trx['total']) &&
                        isset($trx['service']) && isset($trx['tax']) &&
                        isset($trx['discount']) && isset($trx['grand_total']) &&
                        isset($trx['menu'])
                    ) {

                        $insertTrx = $this->insertTransaction($getIdBrand, $checkOutlet, $productList, $allProductCode, $groupList, $allGroupCode, $variantList, $allVariantCode, $modList, $allModCode, $trx, $config, $settingPoint, $countSettingCashback, $fraudTrxDay, $fraudTrxWeek);

                        if (isset($insertTrx['id_transaction'])) {
                            $countTransactionSuccess++;
                            $result[] = $insertTrx;
                        } else {
                            $countTransactionFail++;
                            if (isset($trx['trx_id'])) {
                                $id = 'failed save trx_id : ' . $trx['trx_id'];
                                if (isset($insertTrx['message'])) {
                                    $id = $id . ', ' . $insertTrx['message'];
                                }
                            } else {
                                $id = 'trx_id does not exist';
                            }
                            array_push($detailTransactionFail, $id);
                            $data = [
                                'outlet_code' => $post['store_code'],
                                'request' => json_encode($trx),
                                'message_failed' => $insertTrx['messages'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            SyncTransactionFaileds::create($data);
                        }
                    } else {
                        $countTransactionFail++;
                        if (isset($trx['trx_id'])) {
                            $id = 'failed save trx_id : ' . $trx['trx_id'] . ', There is an incomplete input in the transaction list';
                        } else {
                            $id = 'trx_id does not exist';
                        }

                        array_push($detailTransactionFail, $id);
                        $data = [
                            'outlet_code' => $post['store_code'],
                            'request' => json_encode($trx),
                            'message_failed' => 'There is an incomplete input in the transaction list',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        SyncTransactionFaileds::create($data);
                    }
                }

                return response()->json([
                    'status'    => 'success',
                    'result'    => [
                        'transaction_success' => $countTransactionSuccess,
                        'transaction_failed' => $countTransactionFail,
                        'transaction_duplicate' => $countTransactionDuplicate,
                        'detail_transaction_failed' => $detailTransactionFail
                    ]
                ]);
            } else {
                $countDataTransToSave = $countTransaction / $x;
                $checkFloat = is_float($countDataTransToSave);
                $getDataFrom = 0;

                if ($checkFloat === true) {
                    $countDataTransToSave = (int) $countDataTransToSave + 1;
                }

                for ($i = 0; $i < $countDataTransToSave; $i++) {
                    $dataTransToSave = array_slice($post['transactions'], $getDataFrom, $x);
                    $data = [
                        'type' => 'Transaction',
                        'outlet_code' => $post['store_code'],
                        'request_transaction' => json_encode($dataTransToSave),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    try {
                        $insertTransactionQueue = SyncTransactionQueues::create($data);

                        if (!$insertTransactionQueue) {
                            $countTransactionFail = $countTransactionFail + count($dataTransToSave);
                            array_push($detailTransactionFail, array_column($dataTransToSave, 'trx_id'));
                        } else {
                            $countTransactionSuccess = $countTransactionSuccess + count($dataTransToSave);
                        }
                    } catch (Exception $e) {
                        $countTransactionFail = $countTransactionFail + count($dataTransToSave);
                        array_push($detailTransactionFail, array_column($dataTransToSave, 'trx_id'));
                    }

                    $getDataFrom = $getDataFrom + $countDataTransToSave;
                }

                return response()->json([
                    'status'    => 'success',
                    'result'    => [
                        'transaction_success' => $countTransactionSuccess,
                        'transaction_failed' => $countTransactionFail,
                        'transaction_duplicate' => $countTransactionDuplicate,
                        'detail_transaction_failed' => $detailTransactionFail
                    ]
                ]);
            }
        } else {
            return response()->json(['status' => 'fail', 'messages' => 'Input is incomplete']);
        }
    }

    function insertTransaction($getIdBrand, $outlet, &$productList, &$allProductCode, &$groupList, &$allGroupCode, &$variantList, &$allVariantCode, &$modList, &$allModCode, $trx, $config, $settingPoint, $countSettingCashback, $fraudTrxDay, $fraudTrxWeek)
    {
        DB::beginTransaction();
        try {
            if (count($trx['menu']) >= 0 && isset($trx['trx_id'])) {
                $countTrxDay = 0;
                $countTrxWeek = 0;

                $dataTrx = [
                    'id_outlet'                   => $outlet['id_outlet'],
                    'transaction_date'            => date('Y-m-d H:i:s', strtotime($trx['date_time'])),
                    'transaction_receipt_number'  => $trx['trx_id'],
                    'trasaction_type'             => 'Offline',
                    'transaction_subtotal'        => $trx['total'],
                    'transaction_service'         => $trx['service'],
                    'transaction_discount'        => $trx['discount'],
                    'transaction_tax'             => $trx['tax'],
                    'transaction_grandtotal'      => $trx['grand_total'],
                    'transaction_point_earned'    => null,
                    'transaction_cashback_earned' => null,
                    'trasaction_payment_type'     => 'Offline',
                    'transaction_payment_status'  => 'Completed'
                ];

                if (!empty($trx['sales_type'])) {
                    $dataTrx['sales_type']  = $trx['sales_type'];
                }

                $trxVoucher = [];
                $pointBefore = 0;
                $pointValue = 0;

                if (isset($trx['member_uid'])) {
                    $qr = [];

                    $trx['member_uid'] = ltrim($trx['member_uid'], '0');
                    $user = User::where('id', $trx['member_uid'])->with('memberships')->first();

                    if (empty($user)) {
                        $user['id'] = null;
                    } elseif (isset($user['is_suspended']) && $user['is_suspended'] == '1') {
                        $user['id'] = null;
                        $dataTrx['membership_level']    = null;
                        $dataTrx['membership_promo_id'] = null;
                    } else {
                        if($config['fraud_use_queue'] == 1){
                            FraudJob::dispatch($user, $trx, 'transaction')->onConnection('fraudqueue');
                        }else{
                            //========= This process to check if user have fraud ============//
                            $geCountTrxDay = Transaction::leftJoin('transaction_pickups', 'transaction_pickups.id_transaction', '=', 'transactions.id_transaction')
                                ->where('transactions.id_user', $user['id'])
                                ->whereRaw('DATE(transactions.transaction_date) = "' . date('Y-m-d', strtotime($trx['date_time'])) . '"')
                                ->where('transactions.transaction_payment_status', 'Completed')
                                ->whereNull('transaction_pickups.reject_at')
                                ->count();

                            $currentWeekNumber = date('W', strtotime($trx['date_time']));
                            $currentYear = date('Y', strtotime($trx['date_time']));
                            $dto = new DateTime();
                            $dto->setISODate($currentYear, $currentWeekNumber);
                            $start = $dto->format('Y-m-d');
                            $dto->modify('+6 days');
                            $end = $dto->format('Y-m-d');

                            $geCountTrxWeek = Transaction::leftJoin('transaction_pickups', 'transaction_pickups.id_transaction', '=', 'transactions.id_transaction')
                                ->where('id_user', $user['id'])
                                ->where('transactions.transaction_payment_status', 'Completed')
                                ->whereNull('transaction_pickups.reject_at')
                                ->whereRaw('Date(transactions.transaction_date) BETWEEN "' . $start . '" AND "' . $end . '"')
                                ->count();

                            $countTrxDay = $geCountTrxDay + 1;
                            $countTrxWeek = $geCountTrxWeek + 1;
                            //================================ End ================================//
                        }

                        if (count($user['memberships']) > 0) {
                            $dataTrx['membership_level']    = $user['memberships'][0]['membership_name'];
                            $dataTrx['membership_promo_id'] = $user['memberships'][0]['benefit_promo_id'];
                        }

                        //using voucher
                        if (!empty($trx['voucher'])) {
                            foreach ($trx['voucher'] as $keyV => $valueV) {
                                $checkVoucher = DealsVoucher::join('deals_users', 'deals_vouchers.id_deals_voucher', 'deals_users.id_deals_voucher')
                                    ->leftJoin('transaction_vouchers', 'deals_vouchers.id_deals_voucher', 'transaction_vouchers.id_deals_voucher')
                                    ->where('voucher_code', $valueV['voucher_code'])
                                    ->where('deals_users.id_outlet', $outlet['id_outlet'])
                                    ->where('deals_users.id_user', $user['id'])
                                    ->whereNotNull('deals_users.used_at')
                                    ->whereNull('transaction_vouchers.id_transaction_voucher')
                                    ->select('deals_vouchers.*')
                                    ->first();

                                if (empty($checkVoucher)) {
                                    // for invalid voucher
                                    $dataVoucher['deals_voucher_invalid'] = $valueV['voucher_code'];
                                } else {
                                    $dataVoucher['id_deals_voucher'] =  $checkVoucher['id_deals_voucher'];
                                }
                                $trxVoucher[] = $dataVoucher;
                            }
                        } else {
                            if ($config['point'] == '1') {
                                if (isset($user['memberships'][0]['membership_name'])) {
                                    $level = $user['memberships'][0]['membership_name'];
                                    $percentageP = $user['memberships'][0]['benefit_point_multiplier'] / 100;
                                } else {
                                    $level = null;
                                    $percentageP = 0;
                                }

                                $point = floor(app($this->pos)->count('point', $trx) * $percentageP);
                                $dataTrx['transaction_point_earned'] = $point;
                            }

                            if ($config['balance'] == '1') {
                                if (isset($user['memberships'][0]['membership_name'])) {
                                    $level = $user['memberships'][0]['membership_name'];
                                    $percentageB = $user['memberships'][0]['benefit_cashback_multiplier'] / 100;
                                    $cashMax = $user['memberships'][0]['cashback_maximum'];
                                } else {
                                    $level = null;
                                    $percentageB = 0;
                                }

                                $data = $trx;
                                $data['total'] = $trx['grand_total'];
                                $cashback = floor(app($this->pos)->count('cashback', $data) * $percentageB);

                                //count some trx user
                                $countUserTrx = Transaction::leftJoin('transaction_pickups', 'transaction_pickups.id_transaction', '=', 'transactions.id_transaction')
                                    ->where('id_user', $user['id'])
                                    ->where('transactions.transaction_payment_status', 'Completed')
                                    ->whereNull('transaction_pickups.reject_at')
                                    ->count();
                                if ($countUserTrx < count($countSettingCashback)) {
                                    $cashback = $cashback * $countSettingCashback[$countUserTrx]['cashback_percent'] / 100;
                                    if ($cashback > $countSettingCashback[$countUserTrx]['cashback_maximum']) {
                                        $cashback = $countSettingCashback[$countUserTrx]['cashback_maximum'];
                                    }
                                } else {
                                    if (isset($cashMax) && $cashback > $cashMax) {
                                        $cashback = $cashMax;
                                    }
                                }
                                $dataTrx['transaction_cashback_earned'] = $cashback;
                            }
                        }
                    }
                    $dataTrx['id_user'] = $user['id'];
                }

                if (isset($qr['device'])) {
                    $dataTrx['transaction_device_type'] = $qr['device'];
                }
                if (isset($trx['cashier'])) {
                    $dataTrx['transaction_cashier'] = $trx['cashier'];
                }

                $dataTrx['show_rate_popup'] = 1;

                $createTrx = Transaction::create($dataTrx);
                if (!$createTrx) {
                    DB::rollBack();
                    return ['status' => 'fail', 'messages' => 'Transaction sync failed'];
                }

                $dataPayments = [];
                if (!empty($trx['payments'])) {
                    foreach ($trx['payments'] as $col => $pay) {
                        if (
                            isset($pay['type']) && isset($pay['name'])
                            && isset($pay['nominal'])
                        ) {
                            $dataPay = [
                                'id_transaction' => $createTrx['id_transaction'],
                                'payment_type'   => $pay['type'],
                                'payment_bank'   => $pay['name'],
                                'payment_amount' => $pay['nominal'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            array_push($dataPayments, $dataPay);
                        } else {
                            DB::rollBack();
                            return ['status' => 'fail', 'messages' => 'There is an incomplete input in the payment list'];
                        }
                    }
                } else {
                    $dataPayments = [
                        'id_transaction' => $createTrx['id_transaction'],
                        'payment_type'   => 'offline',
                        'payment_bank'   => null,
                        'payment_amount' => $trx['grand_total'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }

                $insertPayments = TransactionPaymentOffline::insert($dataPayments);
                if (!$insertPayments) {
                    DB::rollBack();
                    return ['status' => 'fail', 'messages' => 'Failed insert transaction payments'];
                }

                $userTrxProduct = [];
                foreach ($trx['menu'] as $row => $menu) {
                    //only for product not add on
                    if ($menu['category_id'] != '1') {
                        if (
                            isset($menu['menu_variance']['sap_matnr']) && isset($menu['menu_id']) && isset($menu['menu_name']) && isset($menu['category_id'])
                            && isset($menu['menu_variance']['status']) && isset($menu['price']) && isset($menu['qty'])
                        ) {

                            $getIndexProduct = array_search($menu['menu_variance']['sap_matnr'], $allProductCode);

                            if ($getIndexProduct === false) {
                                //check product group
                                $getIndexProductGroup = array_search($menu['menu_id'], $allGroupCode);
                                if ($getIndexProductGroup === false) {
                                    try {
                                        $productGroup = ProductGroup::create([
                                            'product_group_code'    => $menu['menu_id'],
                                            'product_group_name'    => ucwords(strtolower($menu['menu_name']))
                                        ]);
                                    } catch (\Exception $e) {
                                        DB::rollBack();
                                        LogBackendError::logExceptionMessage("ApiPOS/transactionSync=>" . $e->getMessage(), $e);
                                        return ['status' => 'fail', 'messages' => 'Failed create new product group'];
                                    }

                                    $groupList[] = [
                                        'id_product_group' => $productGroup->id_product_group,
                                        'product_group_code' => $productGroup->product_group_code
                                    ];
                                    $allGroupCode[] = $productGroup->product_group_code;

                                    $getIndexProductGroup = count($allProductCode);
                                } else {
                                    $productGroup = $groupList[$getIndexProductGroup];
                                }

                                $variance = $menu['menu_variance'];
                                $size = $variance['size'];
                                //for variant null use general size
                                if ($variance['size'] == null) {
                                    $size = 'general_size';
                                }
                                $type = $variance['type'];
                                //for variant null use general type
                                if ($variance['type'] == null) {
                                    $type = 'general_type';
                                }

                                $getIndexVariantSize = array_search($size, $allVariantCode);
                                if ($getIndexVariantSize === false) {
                                    try {
                                        $variantSize = ProductVariant::create([
                                            'product_variant_code'       => $size,
                                            'product_variant_subtitle'  => '',
                                            'product_variant_name'      => $size,
                                            'product_variant_position'  => 0,
                                            'parent'                    => 1
                                        ]);
                                    } catch (\Exception $e) {
                                        DB::rollBack();
                                        LogBackendError::logExceptionMessage("ApiPOS/transactionSync=>" . $e->getMessage(), $e);
                                        return ['status' => 'fail', 'messages' => 'Failed create new product variant size'];
                                    }

                                    $variantList[] = [
                                        'id_product_variant' => $variantSize->id_product_variant,
                                        'product_variant_code' => $variantSize->product_variant_code
                                    ];
                                    $allvariantCode[] = $variantSize->product_variant_code;
                                } else {
                                    $variantSize = $variantList[$getIndexVariantSize];
                                }

                                $getIndexVariantType = array_search($type, $allVariantCode);
                                if ($getIndexVariantType === false) {
                                    try {
                                        $variantType = ProductVariant::create([
                                            'product_variant_code'       => $type,
                                            'product_variant_subtitle'  => '',
                                            'product_variant_name'      => $type,
                                            'product_variant_position'  => 0,
                                            'parent'                    => 2
                                        ]);
                                    } catch (\Exception $e) {
                                        DB::rollBack();
                                        LogBackendError::logExceptionMessage("ApiPOS/transactionSync=>" . $e->getMessage(), $e);
                                        return ['status' => 'fail', 'messages' => 'Failed create new product variant type'];
                                    }

                                    $variantList[] = [
                                        'id_product_variant' => $variantType->id_product_variant,
                                        'product_variant_code' => $variantType->product_variant_code
                                    ];
                                    $allvariantCode[] = $variantType->product_variant_code;
                                } else {
                                    $variantType = $variantList[$getIndexVariantType];
                                }


                                try {
                                    $product = Product::create([
                                        'product_code'    => $menu['menu_variance']['sap_matnr'],
                                        'product_name_pos'  => implode(" ", [$productGroup['product_group_name'], $variance['size'], $variance['type']]),
                                        'product_name'  => implode(" ", [ucwords(strtolower($productGroup['product_group_name'])), $variance['size'], $variance['type']]),
                                        'product_status'    => $variance['status'],
                                        'id_product_group'  => $productGroup['id_product_group']
                                    ]);

                                    $brandProduct = [
                                        'id_product' => $product->id_product,
                                        'id_brand'   => $getIdBrand->id_brand
                                    ];
                                    BrandProduct::create($brandProduct);
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    LogBackendError::logExceptionMessage("ApiPOS/transactionSync=>" . $e->getMessage(), $e);
                                    return ['status' => 'fail', 'messages' => 'Failed create new product'];
                                }

                                try {
                                    ProductProductVariant::updateOrCreate([
                                        'id_product'            => $product->id_product,
                                        'id_product_variant'    => $variantSize['id_product_variant']
                                    ], [
                                        'id_product'            => $product->id_product,
                                        'id_product_variant'    => $variantSize['id_product_variant']
                                    ]);
                                    ProductProductVariant::updateOrCreate([
                                        'id_product'            => $product->id_product,
                                        'id_product_variant'    => $variantType['id_product_variant']
                                    ], [
                                        'id_product'            => $product->id_product,
                                        'id_product_variant'    => $variantType['id_product_variant']
                                    ]);
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    LogBackendError::logExceptionMessage("ApiPOS/syncProduct=>" . $e->getMessage(), $e);
                                    return ['status' => 'fail', 'messages' => 'Failed create product variant detail'];
                                }

                                $productPriceData['id_product']         = $product['id_product'];
                                $productPriceData['id_outlet']             = $outlet['id_outlet'];
                                $productPriceData['product_price_base'] = $menu['price'];
                                $newProductPrice = ProductPrice::create($productPriceData);
                                if (!$newProductPrice) {
                                    DB::rollBack();
                                    return ['status' => 'fail', 'messages' => 'Failed create new product price'];
                                }
                            } else {
                                $product = $productList[$getIndexProduct];
                            }
                            $dataProduct = [
                                'id_transaction'               => $createTrx['id_transaction'],
                                'id_product'                   => $product['id_product'],
                                'id_outlet'                    => $outlet['id_outlet'],
                                'id_user'                      => $createTrx['id_user'],
                                'transaction_product_qty'      => $menu['qty'],
                                'transaction_product_price'    => round($menu['price'], 2),
                                'transaction_product_subtotal' => $menu['qty'] * round($menu['price'], 2)
                            ];
                            if (isset($menu['open_modifier'])) {
                                $dataProduct['transaction_product_note'] = $menu['open_modifier'];
                            }

                            $createProduct = TransactionProduct::create($dataProduct);

                            if (!$createProduct) {
                                DB::rollBack();
                                return ['status' => 'fail', 'messages' => 'Failed create transaction product'];
                            }
                            $modSubtotal = 0;
                            // add modifiers
                            $number = $row + 1;
                            while (isset($trx['menu'][$number]['category_id']) && $trx['menu'][$number]['category_id'] == '1') {

                                if (
                                    isset($trx['menu'][$number]['menu_variance']['sap_matnr']) && isset($trx['menu'][$number]['menu_name'])
                                    && isset($trx['menu'][$number]['price']) && isset($trx['menu'][$number]['qty'])
                                ) {

                                    $getIndexMod = array_search($trx['menu'][$number]['menu_variance']['sap_matnr'], $allModCode);

                                    if ($getIndexMod !== false) {
                                        $productModifier = $modList[$getIndexMod];
                                    } else {
                                        try {
                                            $productModifier = ProductModifier::create([
                                                'code'      => $trx['menu'][$number]['menu_variance']['sap_matnr'],
                                                'text'      => $trx['menu'][$number]['menu_name'],
                                                'type'      => isset($trx['menu'][$number]['group']) ?? "",
                                                'modifier_type' => 'Specific',
                                                'status'    => $trx['menu'][$number]['menu_variance']['status'],
                                            ]);
                                        } catch (\Exception $e) {
                                            DB::rollBack();
                                            LogBackendError::logExceptionMessage("ApiPOS/transactionSync=>" . $e->getMessage(), $e);
                                            return ['status' => 'fail', 'messages' => 'Failed create new add on'];
                                        }

                                        $modList[] = [
                                            'id_product_modifier' => $productModifier->id_product_modifier,
                                            'code' => $productModifier->code,
                                            'text' => $productModifier->text,
                                            'type' => $productModifier->type,
                                        ];
                                        $allModCode[] = $productModifier->code;

                                        try {
                                            ProductModifierProduct::create([
                                                'id_product'            => $product->id_product,
                                                'id_product_modifier'   => $productModifier->id_product_modifier
                                            ]);
                                        } catch (\Exception $e) {
                                            DB::rollBack();
                                            LogBackendError::logExceptionMessage("ApiPOS/transactionSync=>" . $e->getMessage(), $e);
                                            return ['status' => 'fail', 'messages' => 'Failed create add on product'];
                                        }
                                    }

                                    $dataProductMod['id_transaction_product'] = $createProduct['id_transaction_product'];
                                    $dataProductMod['id_transaction'] = $createTrx['id_transaction'];
                                    $dataProductMod['id_product'] = $product['id_product'];
                                    $dataProductMod['id_product_modifier'] = $productModifier['id_product_modifier'];
                                    $dataProductMod['id_outlet'] = $outlet['id_outlet'];
                                    $dataProductMod['id_user'] = $createTrx['id_user'];
                                    $dataProductMod['type'] = $productModifier['type'];
                                    $dataProductMod['code'] = $productModifier['code'];
                                    $dataProductMod['text'] = $productModifier['text'];
                                    $dataProductMod['qty'] = $trx['menu'][$number]['qty'];
                                    $dataProductMod['datetime'] = $createTrx['created_at'];
                                    $dataProductMod['trx_type'] = $createTrx['trasaction_type'];
                                    $dataProductMod['sales_type'] = $createTrx['sales_type'];
                                    $dataProductMod['transaction_product_modifier_price'] = $trx['menu'][$number]['qty'] * $trx['menu'][$number]['price'];

                                    $modSubtotal += $dataProductMod['transaction_product_modifier_price'];

                                    try {
                                        TransactionProductModifier::create($dataProductMod);
                                    } catch (\Exception $e) {
                                        DB::rollBack();
                                        LogBackendError::logExceptionMessage("ApiPOS/transactionSync=>" . $e->getMessage(), $e);
                                        return ['status' => 'fail', 'messages' => 'Failed create transaction product add on'];
                                    }
                                }

                                $number++;
                            }

                            if ($modSubtotal > 0) {
                                TransactionProduct::where('id_transaction_product', $createProduct['id_transction_product'])->update(['transaction_modifier_subtotal' => $modSubtotal]);
                            }
                        } else {
                            DB::rollBack();
                            return ['status' => 'fail', 'messages' => 'There is an incomplete input in the menu list'];
                        }
                    }
                }

                if (!empty($createTrx['id_user']) && $config['fraud_use_queue'] != 1) {
                    if ((($fraudTrxDay && $countTrxDay <= $fraudTrxDay['parameter_detail']) && ($fraudTrxWeek && $countTrxWeek <= $fraudTrxWeek['parameter_detail']))
                        || (!$fraudTrxDay && !$fraudTrxWeek)
                    ) {

                        if ($createTrx['transaction_point_earned']) {
                            $dataLog = [
                                'id_user'                     => $createTrx['id_user'],
                                'point'                       => $createTrx['transaction_point_earned'],
                                'id_reference'                => $createTrx['id_transaction'],
                                'source'                      => 'Transaction',
                                'grand_total'                 => $createTrx['transaction_grandtotal'],
                                'point_conversion'            => $settingPoint,
                                'membership_level'            => $level,
                                'membership_point_percentage' => $percentageP * 100
                            ];

                            $insertDataLog = LogPoint::updateOrCreate(['id_user' => $createTrx['id_user'], 'id_reference' => $createTrx['id_transaction']], $dataLog);
                            if (!$insertDataLog) {
                                DB::rollBack();
                                return [
                                    'status'    => 'fail',
                                    'messages'  => 'Insert Point Failed'
                                ];
                            }

                            $pointValue = $insertDataLog->point;

                            //update user point
                            $user->points = $pointBefore + $pointValue;
                            $user->update();
                            if (!$user) {
                                DB::rollBack();
                                return [
                                    'status'    => 'fail',
                                    'messages'  => 'Insert Point Failed'
                                ];
                            }
                        }

                        if ($createTrx['transaction_cashback_earned']) {

                            $insertDataLogCash = app($this->balance)->addLogBalance($createTrx['id_user'], $createTrx['transaction_cashback_earned'], $createTrx['id_transaction'], 'Transaction', $createTrx['transaction_grandtotal']);
                            if (!$insertDataLogCash) {
                                DB::rollBack();
                                return [
                                    'status'    => 'fail',
                                    'messages'  => 'Insert Cashback Failed'
                                ];
                            }
                            $usere = User::where('id', $createTrx['id_user'])->first();
                            $send = app($this->autocrm)->SendAutoCRM(
                                'Transaction Point Achievement',
                                $usere->phone,
                                [
                                    "outlet_name"       => $outlet['outlet_name'],
                                    "transaction_date"  => $createTrx['transaction_date'],
                                    'id_transaction'    => $createTrx['id_transaction'],
                                    'receipt_number'    => $createTrx['transaction_receipt_number'],
                                    'received_point'    => (string) $createTrx['transaction_cashback_earned']
                                ]
                            );
                            if ($send != true) {
                                DB::rollBack();
                                return response()->json([
                                    'status' => 'fail',
                                    'messages' => 'Failed Send notification to customer'
                                ]);
                            }
                            $pointValue = $insertDataLogCash->balance;
                        }
                    } else {
                        if ($countTrxDay > $fraudTrxDay['parameter_detail'] && $fraudTrxDay) {
                            $fraudFlag = 'transaction day';
                        } elseif ($countTrxWeek > $fraudTrxWeek['parameter_detail'] && $fraudTrxWeek) {
                            $fraudFlag = 'transaction week';
                        } else {
                            $fraudFlag = NULL;
                        }

                        $updatePointCashback = Transaction::where('id_transaction', $createTrx['id_transaction'])
                            ->update([
                                'transaction_point_earned' => NULL,
                                'transaction_cashback_earned' => NULL,
                                'fraud_flag' => $fraudFlag
                            ]);

                        if (!$updatePointCashback) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'fail',
                                'messages' => ['Failed update Point and Cashback']
                            ]);
                        }
                    }
                }

                //insert voucher
                foreach ($trxVoucher as $dataTrxVoucher) {
                    $dataTrxVoucher['id_transaction'] = $createTrx['id_transaction'];
                    $create = TransactionVoucher::create($dataTrxVoucher);
                }

                if (isset($user['phone']) && $config['fraud_use_queue'] != 1) {
                    $checkMembership = app($this->membership)->calculateMembership($user['phone']);
                    $userData = User::find($user['id']);
                    //cek fraud detection transaction per day
                    if ($fraudTrxDay) {
                        $checkFraud = app($this->setting_fraud)->checkFraud($fraudTrxDay, $userData, null, $countTrxDay, $countTrxWeek, $trx['date_time'], 0, $trx['trx_id']);
                    }
                    //cek fraud detection transaction per week
                    if ($fraudTrxWeek) {
                        $checkFraud = app($this->setting_fraud)->checkFraud($fraudTrxWeek, $userData, null, $countTrxDay, $countTrxWeek, $trx['date_time'], 0, $trx['trx_id']);
                    }
                }

                DB::commit();
                return [
                    'id_transaction'    => $createTrx->id_transaction,
                    'point_before'      => (int) $pointBefore,
                    'point_after'       => $pointBefore + $pointValue,
                    'point_value'       => $pointValue
                ];
            } else {
                DB::rollBack();
                return ['status' => 'fail', 'messages' => 'trx_id does not exist'];
            }
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => 'fail', 'messages' => $e];
        }
    }

    function processDuplicate($trx, $outlet)
    {
        DB::beginTransaction();
        try {
            $trxDuplicate = Transaction::where('transaction_receipt_number',  $trx['trx_id'])
                ->with('user', 'outlet', 'productTransaction.product')
                ->whereNotIn('transactions.id_outlet', [$outlet['id_outlet']])
                ->where('transaction_date', date('Y-m-d H:i:s', strtotime($trx['date_time'])))
                ->where('transaction_grandtotal', $trx['grand_total'])
                ->where('transaction_subtotal', $trx['total'])
                ->where('trasaction_type', 'Offline');

            if (isset($trx['cashier'])) {
                $trxDuplicate = $trxDuplicate->where('transaction_cashier', $trx['cashier']);
            }

            $trxDuplicate = $trxDuplicate->first();
            if ($trxDuplicate) {
                //cek detail productnya
                $statusDuplicate = true;

                $trx['product'] = [];
                $detailproduct = [];

                foreach ($trx['menu'] as $row => $menu) {
                    $productDuplicate = false;
                    foreach ($trxDuplicate['productTransaction'] as $i => $dataProduct) {
                        if ($menu['menu_variance']['sap_matnr'] == $dataProduct['product']['product_code']) {
                            //cek jumlah quantity
                            if ($menu['qty'] == $dataProduct['transaction_product_qty']) {
                                //set status product duplicate true
                                $productDuplicate = true;
                                $menu['id_product'] = $dataProduct['id_product'];
                                $menu['product_name'] = $dataProduct['product']['product_name'];
                                $trx['product'][] = $menu;
                                $detailproduct[] = $dataProduct;
                                unset($trxDuplicate['productTransaction'][$i]);
                            }
                        }
                    }

                    //jika status product duplicate false maka detail product ada yg berbeda
                    if ($productDuplicate == false) {
                        $statusDuplicate = false;
                        break;
                    }
                }

                $trxDuplicate['product'] = $detailproduct;

                if ($statusDuplicate == true) {
                    //insert into table transaction_duplicates
                    if (isset($trx['member_uid'])) {
                        $qr = [];
                        $trx['member_uid'] = ltrim($trx['member_uid'], '0');
                        $user      = User::where('id', $trx['member_uid'])->with('memberships')->first();
                        if ($user) {
                            $dataDuplicate['id_user'] = $user['id'];
                        }
                    }

                    $dataDuplicate['id_transaction'] = $trxDuplicate['id_transaction'];
                    $dataDuplicate['id_outlet_duplicate'] = $trxDuplicate['outlet']['id_outlet'];
                    $dataDuplicate['id_outlet'] = $outlet['id_outlet'];
                    $dataDuplicate['transaction_receipt_number'] = $trx['trx_id'];
                    $dataDuplicate['outlet_code_duplicate'] = $trxDuplicate['outlet']['outlet_code'];
                    $dataDuplicate['outlet_code'] = $outlet['outlet_code'];
                    $dataDuplicate['outlet_name_duplicate'] = $trxDuplicate['outlet']['outlet_name'];
                    $dataDuplicate['outlet_name'] = $outlet['outlet_name'];

                    if (isset($user['name'])) {
                        $dataDuplicate['user_name'] = $user['name'];
                    }

                    if (isset($user['phone'])) {
                        $dataDuplicate['user_phone'] = $user['phone'];
                    }

                    $dataDuplicate['transaction_cashier'] = $trx['cashier'];
                    $dataDuplicate['transaction_date'] = date('Y-m-d H:i:s', strtotime($trx['date_time']));
                    $dataDuplicate['transaction_subtotal'] = $trx['total'];
                    $dataDuplicate['transaction_tax'] = $trx['tax'];
                    $dataDuplicate['transaction_service'] = $trx['service'];
                    $dataDuplicate['transaction_grandtotal'] = $trx['grand_total'];
                    $dataDuplicate['sync_datetime_duplicate'] = $trxDuplicate['created_at'];
                    $dataDuplicate['sync_datetime'] = date('Y-m-d H:i:s');
                    $insertDuplicate = TransactionDuplicate::create($dataDuplicate);
                    if (!$insertDuplicate) {
                        DB::rollBack();
                        return ['status' => 'Transaction sync failed'];
                    }

                    //insert transaction duplicate product
                    $prodDuplicate = [];
                    foreach ($trx['product'] as $row => $menu) {
                        $dataTrxDuplicateProd['id_transaction_duplicate'] = $insertDuplicate['id_transaction_duplicate'];

                        $dataTrxDuplicateProd['id_product'] = $menu['id_product'];
                        $dataTrxDuplicateProd['transaction_product_code'] = $menu['menu_variance']['sap_matnr'];
                        $dataTrxDuplicateProd['transaction_product_name'] = $menu['product_name'];
                        $dataTrxDuplicateProd['transaction_product_qty'] = $menu['qty'];
                        $dataTrxDuplicateProd['transaction_product_price'] = $menu['price'];
                        $dataTrxDuplicateProd['transaction_product_subtotal'] = $menu['qty'] * $menu['price'];
                        if (isset($menu['open_modifier'])) {
                            $dataTrxDuplicateProd['transaction_product_note'] = $menu['open_modifier'];
                        }
                        $dataTrxDuplicateProd['created_at'] = date('Y-m-d H:i:s');
                        $dataTrxDuplicateProd['updated_at'] = date('Y-m-d H:i:s');

                        $prodDuplicate[] = $dataTrxDuplicateProd;
                    }

                    $insertTrxDuplicateProd = TransactionDuplicateProduct::insert($prodDuplicate);
                    if (!$insertTrxDuplicateProd) {
                        DB::rollBack();
                        return ['status' => 'Transaction sync failed'];
                    }

                    //insert payment
                    $payDuplicate = [];
                    if (!empty($trx['payments'])) {
                        foreach ($trx['payments'] as $pay) {
                            $dataTrxDuplicatePay['id_transaction_duplicate'] = $insertDuplicate['id_transaction_duplicate'];
                            $dataTrxDuplicatePay['payment_name'] = $pay['name'];
                            $dataTrxDuplicatePay['payment_type'] = $pay['type'];
                            $dataTrxDuplicatePay['payment_amount'] = $pay['nominal'];
                            $dataTrxDuplicatePay['created_at'] = date('Y-m-d H:i:s');
                            $dataTrxDuplicatePay['updated_at'] = date('Y-m-d H:i:s');
                            $payDuplicate[] = $dataTrxDuplicatePay;
                        }
                    } else {
                        $dataTrxDuplicatePay = [
                            'id_transaction_duplicate' => $insertDuplicate['id_transaction_duplicate'],
                            'payment_name' => 'Offline',
                            'payment_type' => 'offline',
                            'payment_amount' => 10000,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        $payDuplicate[] = $dataTrxDuplicatePay;
                    }

                    $insertTrxDuplicatePay = TransactionDuplicatePayment::create($dataTrxDuplicatePay);
                    if (!$insertTrxDuplicatePay) {
                        DB::rollBack();
                        return ['status' => 'Transaction sync failed'];
                    }

                    $trx['outlet_name'] = $outlet['outlet_name'];
                    $trx['outlet_code'] = $outlet['outlet_code'];
                    $trx['sync_datetime'] = $dataDuplicate['sync_datetime'];

                    DB::commit();
                    return [
                        'status' => 'duplicate',
                        'duplicate' => $trxDuplicate,
                        'trx' => $trx,
                    ];
                }
            }

            return ['status' => 'not duplicate'];
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => 'fail', 'messages' => $e];
        }
    }

    public function transactionRefund(Request $request, $post = null, $cek = 1)
    {
        if (!$post) {
            $post = $request->json()->all();
        }

        if ($cek == 1) {
            $api = $this->checkApi($post['api_key'], $post['api_secret']);
            if ($api['status'] != 'success') {
                return response()->json($api);
            }
        }

        $countSuccess = 0;
        $countFailed = 0;
        $successRefund = [];
        $failedRefund = [];

        if (!isset($post['data'])) {
            return response()->json(['status' => 'fail', 'messages' => 'field data is required']);
        }

        $checkQueueSyncTrx = SyncTransactionQueues::count();

        if ($checkQueueSyncTrx <= 0 || $cek == 0) {
            foreach ($post['data'] as $trx) {
                $outlet = Outlet::where('outlet_code', strtoupper($trx['store_code']))->first();
                if (empty($outlet)) {
                    return response()->json(['status' => 'fail', 'messages' => 'Store not found']);
                }


                // if(!isset($trx['trx_id']) || !isset($trx['reason'])){
                //     $countFailed += 1;
                //     $failedRefund[] = 'fail to refund, trx_id and reason is required';
                //     continue;
                // }

                DB::beginTransaction();
                $checkTrx = Transaction::where('transaction_receipt_number', $trx['trx_id'])->where('id_outlet', $outlet->id_outlet)->first();
                if (empty($checkTrx)) {
                    $countFailed += 1;
                    $failedRefund[] = 'fail to refund trx_id ' . $trx['trx_id'] . ', transaction not found';
                    continue;
                }

                //if use voucher, cannot refund
                $trxVou = TransactionVoucher::where('id_transaction', $checkTrx->id_transaction)->first();
                if ($trxVou) {
                    $countFailed += 1;
                    $failedRefund[] = 'fail to refund trx_id ' . $trx['trx_id'] . ', This transaction use voucher';
                    continue;
                }

                MyHelper::updateFlagTransactionOnline($checkTrx, 'cancel');

                $checkTrx->transaction_payment_status = 'Cancelled';
                $checkTrx->void_date = date('Y-m-d H:i:s');
                $checkTrx->transaction_notes = $trx['reason'];
                $checkTrx->update();
                if (!$checkTrx) {
                    DB::rollBack();
                    $countFailed += 1;
                    $failedRefund[] = 'fail to refund trx_id ' . $trx['trx_id'] . ', Failed update transaction status';
                    continue;
                }

                if ($checkTrx->id_user) {

                    $user = User::where('id', $checkTrx->id_user)->first();
                    if ($user) {
                        $point = LogPoint::where('id_reference', $checkTrx->id_transaction)->where('source', 'Transaction')->first();
                        if (!empty($point)) {
                            $point->delete();
                            if (!$point) {
                                DB::rollBack();
                                $countFailed += 1;
                                $failedRefund[] = 'fail to refund trx_id ' . $trx['trx_id'] . ', Failed delete point';
                                continue;
                            }

                            //update user point
                            $sumPoint = LogPoint::where('id_user', $user['id'])->sum('point');
                            $user->points = $sumPoint;
                            $user->update();
                            if (!$user) {
                                DB::rollBack();
                                $countFailed += 1;
                                $failedRefund[] = 'fail to refund trx_id ' . $trx['trx_id'] . ', Failed update point';
                                continue;
                            }
                        }

                        $balance = LogBalance::where('id_reference', $checkTrx->id_transaction)->where('source', 'Transaction')->first();
                        $balanceVoid = $balance['balance'];//get balance before delete
                        $receivedBalance = $balance['created_at'];//get date balance received before delete
                        if (!empty($balance)) {
                            $balance->delete();
                            if (!$balance) {
                                $countFailed += 1;
                                $failedRefund[] = 'fail to refund trx_id ' . $trx['trx_id'] . ', Failed delete point';
                                continue;
                            }

                            //update user balance
                            $sumBalance = LogBalance::where('id_user', $user['id'])->sum('balance');
                            $user->balance = $sumBalance;
                            $user->update();
                            if (!$user) {
                                DB::rollBack();
                                $countFailed += 1;
                                $failedRefund[] = 'fail to refund trx_id ' . $trx['trx_id'] . ', Failed update point';
                                continue;
                            }

                            //send notification to customer
                            $sendCRM = app($this->autocrm)->SendAutoCRM('Void Point', $user['phone'], [
                                'point' => number_format($balanceVoid),
                                'received_date' =>  date('d M Y H:i', strtotime($receivedBalance)),
                                'void_date' => date('d M Y H:i'),
                                'receipt_number' => $checkTrx['transaction_receipt_number']
                            ]);
                        }
                        $checkMembership = app($this->membership)->calculateMembership($user['phone']);
                        $countSuccess += 1;
                        $successRefund[] = 'success to refund ' . $trx['trx_id'];
                    }
                }

                DB::commit();
            }

            return response()->json(['status' => 'success', 'result' => [
                'count_success' => $countSuccess,
                'success' => $successRefund,
                'count_failed' => $countFailed,
                'failed' => $failedRefund
            ]]);
        } else {
            $dataToInsert = [
                'type' => 'Transaction Refund',
                'outlet_code' => NULL,
                'request_transaction' => json_encode($post),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $insertTransactionRefundQueue = SyncTransactionQueues::create($dataToInsert);

            if ($insertTransactionRefundQueue) {
                $countSuccess = count($post['data']);
                $successRefund[] = 'success insert transaction refund to queue, ' . count($post['data']) . ' data';
            } else {
                $countFailed = count($post['data']);
                $failedRefund[] = 'fail insert transaction refund to queue, ' . count($post['data']) . ' data';
            }

            return response()->json(['status' => 'success', 'result' => [
                'count_success' => $countSuccess,
                'success' => $successRefund,
                'count_failed' => $countFailed,
                'failed' => $failedRefund
            ]]);
        }
    }

    public static function checkApi($key, $secret)
    {
        $api_key = Setting::where('key', 'api_key')->first();
        if (empty($api_key)) {
            return ['status' => 'fail', 'messages' => 'api_key not found'];
        }

        $api_key = $api_key['value'];
        if ($api_key != $key) {
            return ['status' => 'fail', 'messages' => 'api_key isn\’t match'];
        }

        $api_secret = Setting::where('key', 'api_secret')->first();
        if (empty($api_secret)) {
            return ['status' => 'fail', 'messages' => 'api_secret not found'];
        }

        $api_secret = $api_secret['value'];
        if ($api_secret != $secret) {
            return ['status' => 'fail', 'messages' => 'api_secret isn\’t match'];
        }

        return ['status' => 'success'];
    }

    function count($value, $data)
    {
        if ($value == 'point') {
            $subtotal     = $data['total'];
            $service      = $data['service'];
            $discount     = $data['discount'];
            $tax          = $data['tax'];
            $pointFormula = $this->convertFormula('point');
            $value        = $this->pointValue();

            $count = floor(eval('return ' . preg_replace('/([a-zA-Z0-9]+)/', '\$$1', $pointFormula) . ';'));
            return $count;
        }

        if ($value == 'cashback') {
            $subtotal        = $data['total'];
            $service         = $data['service'];
            $discount        = $data['discount'];
            $tax             = $data['tax'];
            $cashbackFormula = $this->convertFormula('cashback');
            $value           = $this->cashbackValue();
            // $max             = $this->cashbackValueMax();

            $count = floor(eval('return ' . preg_replace('/([a-zA-Z0-9]+)/', '\$$1', $cashbackFormula) . ';'));

            // if ($count >= $max) {
            //     return $max;
            // } else {
            return $count;
            // }

        }
    }

    public function convertFormula($value)
    {
        $convert = $this->$value();
        return $convert;
    }

    public function point()
    {
        $point = $this->setting('point_acquisition_formula');

        $point = preg_replace('/\s+/', '', $point);
        return $point;
    }

    public function cashback()
    {
        $cashback = $this->setting('cashback_acquisition_formula');

        $cashback = preg_replace('/\s+/', '', $cashback);
        return $cashback;
    }

    public function setting($value)
    {
        $setting = Setting::where('key', $value)->first();

        if (empty($setting->value)) {
            return response()->json(['Setting Not Found']);
        }

        return $setting->value;
    }

    public function pointCount()
    {
        $point = $this->setting('point_acquisition_formula');
        return $point;
    }

    public function cashbackCount()
    {
        $cashback = $this->setting('cashback_acquisition_formula');
        return $cashback;
    }

    public function pointValue()
    {
        $point = $this->setting('point_conversion_value');
        return $point;
    }

    public function cashbackValue()
    {
        $cashback = $this->setting('cashback_conversion_value');
        return $cashback;
    }

    public function cashbackValueMax()
    {
        $cashback = $this->setting('cashback_maximum');
        return $cashback;
    }

    public function getLastTransaction(Request $request)
    {
        $post = $request->json()->all();

        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }

        $checkOutlet = Outlet::where('outlet_code', strtoupper($post['store_code']))->first();
        if (empty($checkOutlet)) {
            return response()->json(['status' => 'fail', 'messages' => 'Store not found']);
        }

        $trx = TransactionPickup::join('transactions', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
            ->select('transactions.id_transaction', 'transaction_date', 'transaction_receipt_number', 'order_id')
            ->where('id_outlet', $checkOutlet['id_outlet'])
            ->whereDate('transaction_date', date('Y-m-d'))
            ->orderBy('transactions.id_transaction', 'DESC')
            ->limit(10)->get();

        foreach ($trx as $key => $dataTrx) {
            $qrCode = 'https://chart.googleapis.com/chart?chl=' . $dataTrx['order_id'] . '&chs=250x250&cht=qr&chld=H%7C0';
            $qrCode = html_entity_decode($qrCode);

            $trx[$key]['qrcode'] = $qrCode;
        }

        return response()->json(MyHelper::checkGet($trx));
    }

    public function syncOutletMenu(Request $request)
    {
        $post = $request->json()->all();
        $api = $this->checkApi($post['api_key'], $post['api_secret']);
        if ($api['status'] != 'success') {
            return response()->json($api);
        }
        $lastData = end($post['store']);
        foreach ($post['store'] as $key => $value) {
            $data[$key]['store_code']   = $value['store_code'];
            if ($value == $lastData) {
                $data[$key]['is_end']   = 1;
            } else {
                $data[$key]['is_end']   = 0;
            }
            $data[$key]['request']      = json_encode($value);
            $data[$key]['created_at']   = date('Y-m-d H:i:s');
            $data[$key]['updated_at']   = date('Y-m-d H:i:s');
        }
        DB::beginTransaction();
        try {
            $insertRequest = SyncMenuRequest::insert($data);
        } catch (\Exception $e) {
            DB::rollBack();
            LogBackendError::logExceptionMessage("ApiPOS/syncOutletMenu=>" . $e->getMessage(), $e);
        }
        DB::commit();
        return response()->json(MyHelper::checkGet($insertRequest));
    }

    public function syncOutletMenuCron(Request $request)
    {
        $log = MyHelper::logCron('Sync Outlet Menu');
        try {
            $syncDatetime = date('d F Y h:i');
            $getRequest = SyncMenuRequest::get()->first();
            // is $getRequest null
            if (!$getRequest) {
                $log->success('empty');
                return '';
            }
            $getRequest = $getRequest->toArray();
            $getRequest['request'] = json_decode($getRequest['request'], true);
            $syncMenu = $this->syncMenuProcess($getRequest['request'], 'bulk');
            if ($syncMenu['status'] == 'success') {
                SyncMenuResult::create(['result' => json_encode($syncMenu['result'])]);
            } else {
                SyncMenuResult::create(['result' => json_encode($syncMenu['messages'])]);
            }
            if ($getRequest['is_end'] == 1) {
                $getResult = SyncMenuResult::pluck('result');
                $totalReject    = 0;
                $totalFailed    = 0;
                $listFailed     = [];
                $listRejected     = [];
                foreach ($getResult as $value) {
                    $data[] = json_decode($value, true);
                    if (isset(json_decode($value, true)[0])) {
                        $result['fail'][] = json_decode($value, true)[0];
                    }
                    if (isset(json_decode($value, true)['rejected_product'])) {
                        $totalReject    = $totalReject + count(json_decode($value, true)['rejected_product']['list_product']);
                        foreach (json_decode($value, true)['rejected_product']['list_product'] as $valueRejected) {
                            array_push($listRejected, $valueRejected);
                        }
                    }
                    if (isset(json_decode($value, true)['failed_product'])) {
                        $totalFailed    = $totalFailed + count(json_decode($value, true)['failed_product']['list_product']);
                        foreach (json_decode($value, true)['failed_product']['list_product'] as $valueFailed) {
                            array_push($listFailed, $valueFailed);
                        }
                    }
                }

                // if (count($listRejected) > 0) {
                //     $this->syncSendEmail($syncDatetime, $outlet->outlet_code, $outlet->outlet_name, $rejectedProduct, null);
                // }
                // if (count($listFailed) > 0) {
                //     $this->syncSendEmail($syncDatetime, $outlet->outlet_code, $outlet->outlet_name, null, $failedProduct);
                // }
            }
            SyncMenuRequest::where('id', $getRequest['id'])->delete();
            $log->success();
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function setTimezone($data)
    {
        switch ($data['time_zone']) {
            case 'Asia/Makassar':
                $data['open'] = date('H:i', strtotime('-1 hour', strtotime($data['open'])));
                $data['close'] = date('H:i', strtotime('-1 hour', strtotime($data['close'])));
                break;
            case 'Asia/Jayapura':
                $data['open'] = date('H:i', strtotime('-2 hours', strtotime($data['open'])));
                $data['close'] = date('H:i', strtotime('-2 hours', strtotime($data['close'])));
                break;
            case 'Asia/Singapore':
                $data['open'] = date('H:i', strtotime('-1 hour', strtotime($data['open'])));
                $data['close'] = date('H:i', strtotime('-1 hour', strtotime($data['close'])));
                break;
        }
        return $data;
    }
}
