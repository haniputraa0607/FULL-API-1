<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Pagination\Paginator;

use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionPayment;
use App\Http\Models\Province;
use App\Http\Models\City;
use App\Http\Models\User;
use App\Http\Models\Courier;
use App\Http\Models\Product;
use App\Http\Models\ProductModifierPrice;
use App\Http\Models\Setting;
use App\Http\Models\StockLog;
use App\Http\Models\UserAddress;
use App\Http\Models\ManualPayment;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\ManualPaymentTutorial;
use App\Http\Models\TransactionPaymentManual;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\Outlet;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentOvo;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsPaymentManual;
use App\Http\Models\DealsPaymentOvo;
use App\Http\Models\TransactionPaymentNobu;
use Modules\IPay88\Entities\DealsPaymentIpay88;
use Modules\ShopeePay\Entities\DealsPaymentShopeePay;
use App\Http\Models\UserTrxProduct;
use Modules\Brand\Entities\Brand;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Transaction\Http\Requests\RuleUpdate;

use Modules\Transaction\Http\Requests\TransactionDetail;
use Modules\Transaction\Http\Requests\TransactionHistory;
use Modules\Transaction\Http\Requests\TransactionFilter;
use Modules\Transaction\Http\Requests\TransactionNew;
use Modules\Transaction\Http\Requests\TransactionShipping;
use Modules\Transaction\Http\Requests\GetProvince;
use Modules\Transaction\Http\Requests\GetCity;
use Modules\Transaction\Http\Requests\GetSub;
use Modules\Transaction\Http\Requests\GetAddress;
use Modules\Transaction\Http\Requests\GetNearbyAddress;
use Modules\Transaction\Http\Requests\AddAddress;
use Modules\Transaction\Http\Requests\UpdateAddress;
use Modules\Transaction\Http\Requests\DeleteAddress;
use Modules\Transaction\Http\Requests\ManualPaymentCreate;
use Modules\Transaction\Http\Requests\ManualPaymentEdit;
use Modules\Transaction\Http\Requests\ManualPaymentUpdate;
use Modules\Transaction\Http\Requests\ManualPaymentDetail;
use Modules\Transaction\Http\Requests\ManualPaymentDelete;
use Modules\Transaction\Http\Requests\MethodSave;
use Modules\Transaction\Http\Requests\MethodDelete;
use Modules\Transaction\Http\Requests\ManualPaymentConfirm;
use Modules\Transaction\Http\Requests\ShippingGoSend;
use Modules\PromoCampaign\Entities\PromoCampaignReferral;
use App\Http\Models\TransactionPickupGoSend;

use App\Lib\MyHelper;
use App\Lib\GoSend;
use Validator;
use Hash;
use DB;
use App\Lib\MailQueue as Mail;
use Image;
use Modules\UserRating\Entities\UserRating;

class ApiTransaction extends Controller
{
    public $saveImage = "img/transaction/manual-payment/";

    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
    }

    public function transactionRule() {
        $settingTotal = Setting::where('key', 'transaction_grand_total_order')->first();
        $settingService = Setting::where('key', 'transaction_service_formula')->first();
        $settingServiceValue = Setting::where('key', 'service')->first();

        $settingDiscount = Setting::where('key', 'transaction_discount_formula')->first();
        $settingPercent = Setting::where('key', 'discount_percent')->first();
        $settingNom = Setting::where('key', 'discount_nominal')->first();

        $settingTax = Setting::where('key', 'transaction_tax_formula')->first();
        $settingTaxValue = Setting::where('key', 'tax')->first();

        $settingPoint = Setting::where('key', 'point_acquisition_formula')->first();
        $settingPointValue = Setting::where('key', 'point_conversion_value')->first();

        $settingCashback = Setting::where('key', 'cashback_acquisition_formula')->first();
        $settingCashbackValue = Setting::where('key', 'cashback_conversion_value')->first();
        $settingCashbackMax = Setting::where('key', 'cashback_maximum')->first();

        $settingOutlet = Setting::where('key', 'default_outlet')->first();

        $outlet = Outlet::get()->toArray();

        if (!$settingTotal || !$settingService || !$settingServiceValue || !$settingDiscount || !$settingTax || !$settingTaxValue || !$settingPoint || !$settingPointValue || !$settingCashback || !$settingCashbackValue || !$settingOutlet) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Data setting not found']
            ]);
        }

        $data = [
            'grand_total'   => explode(',', $settingTotal['value']),
            'service'       => [
                'data'  => explode(' ', $settingService['value']),
                'value' => $settingServiceValue['value']
            ],
            'discount'      => [
                'data'    => explode(' ', $settingDiscount['value']),
                'percent' => $settingPercent['value'],
                'nominal' => $settingNom['value'],
            ],
            'tax'       => [
                'data'  => explode(' ', $settingTax['value']),
                'value' => $settingTaxValue['value']
            ],
            'point'       => [
                'data'  => explode(' ', $settingPoint['value']),
                'value' => $settingPointValue['value']
            ],
            'cashback'       => [
                'data'  => explode(' ', $settingCashback['value']),
                'value' => $settingCashbackValue['value'],
                'max' => $settingCashbackMax['value'],
            ],
            'outlet'        => $outlet,
            'default_outlet' => $settingOutlet,
        ];

        return response()->json(MyHelper::checkGet($data));
    }

    public function transactionRuleUpdate(RuleUpdate $request) {
        $post = $request->json()->all();
        DB::beginTransaction();
        if ($post['key'] == 'grand_total') {
            $merge = implode(',', $post['item']);

            $save = Setting::where('key', 'transaction_grand_total_order')->first();
            if (!$save) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $save->value = $merge;
            $save->save();

            DB::commit();
            return response()->json(MyHelper::checkUpdate($save));
        } elseif ($post['key'] == 'service') {
            // return $post;
            $dataResult = [];

            array_push($dataResult, '(');

            if (isset($post['item'])) {
                foreach ($post['item'] as $key => $item) {
                    if ($item == 'resultsubtotal') {
                        $dataItem = 'subtotal';
                    } elseif ($item == 'resultservice') {
                        $dataItem = 'service';
                    } elseif ($item == 'resultdiscount') {
                        $dataItem = 'discount';
                    } elseif ($item == 'resultshipping') {
                        $dataItem = 'shipping';
                    } elseif ($item == 'resulttax') {
                        $dataItem = 'tax';
                    } elseif ($item == 'resultkali') {
                        $dataItem = '*';
                    } elseif ($item == 'resultbagi') {
                        $dataItem = '/';
                    } elseif ($item == 'resulttambah') {
                        $dataItem = '+';
                    } elseif ($item == 'resultkurang') {
                        $dataItem = '-';
                    } elseif ($item == 'resultkbuka') {
                        $dataItem = '(';
                    } elseif ($item == 'resultktutup') {
                        $dataItem = ')';
                    }

                    array_push($dataResult, $dataItem);
                }
            } else {
                array_push($dataResult, '');
            }
            array_push($dataResult, ')');
            array_push($dataResult, '*');
            array_push($dataResult, 'value');

            $join = implode(' ', $dataResult);

            $update = Setting::where('key', 'transaction_service_formula')->first();

            if (!$update) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $join;
            $update->save();

            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            $updateService = Setting::where('key', 'service')->first();
            if (!$updateService) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $updateService->value = $post['value']/100;
            $updateService->save();
            if (!$updateService) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($updateService));

        } elseif ($post['key'] == 'courier') {
            $dataResult = [];

            if (isset($post['item'])) {
                foreach ($post['item'] as $key => $item) {
                    if ($item == 'resultsubtotal') {
                        $dataItem = 'subtotal';
                    } elseif ($item == 'resultservice') {
                        $dataItem = 'service';
                    } elseif ($item == 'resultdiscount') {
                        $dataItem = 'discount';
                    } elseif ($item == 'resultshipping') {
                        $dataItem = 'shipping';
                    } elseif ($item == 'resulttax') {
                        $dataItem = 'tax';
                    } elseif ($item == 'resultkali') {
                        $dataItem = '*';
                    } elseif ($item == 'resultbagi') {
                        $dataItem = '/';
                    } elseif ($item == 'resulttambah') {
                        $dataItem = '+';
                    } elseif ($item == 'resultkurang') {
                        $dataItem = '-';
                    } elseif ($item == 'resultkbuka') {
                        $dataItem = '(';
                    } elseif ($item == 'resultktutup') {
                        $dataItem = ')';
                    }

                    array_push($dataResult, $dataItem);
                }
            } else {
                array_push($dataResult, '');
            }

            $join = implode(' ', $dataResult);

            $update = Setting::where('key', 'transaction_delivery_standard')->first();

            if (!$update) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $join;
            $update->save();

            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($update));

        } elseif ($post['key'] == 'delivery') {
            $updateMinValue = Setting::where('key', 'transaction_delivery_min_value')->first();
            $updateMaxDis = Setting::where('key', 'transaction_delivery_max_distance')->first();
            $updateDelPrice = Setting::where('key', 'transaction_delivery_price')->first();
            $updateDelPricing = Setting::where('key', 'transaction_delivery_pricing')->first();

            if (!$updateMinValue || !$updateMaxDis || !$updateDelPrice || !$updateDelPricing) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $updateMinValue->value = $post['min_value'];
            $updateMaxDis->value = $post['max_distance'];
            $updateDelPrice->value = $post['delivery_price'];
            $updateDelPricing->value = $post['delivery_pricing'];

            $updateMinValue->save();
            $updateMaxDis->save();
            $updateDelPrice->save();
            $updateDelPricing->save();

            if (!$updateMinValue || !$updateMaxDis || !$updateDelPrice || !$updateDelPricing) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($updateMinValue));

        } elseif ($post['key'] == 'discount') {
            $dataResult = [];

            array_push($dataResult, '(');

            if (isset($post['item'])) {
                foreach ($post['item'] as $key => $item) {
                    if ($item == 'resultsubtotal') {
                        $dataItem = 'subtotal';
                    } elseif ($item == 'resultservice') {
                        $dataItem = 'service';
                    } elseif ($item == 'resultdiscount') {
                        $dataItem = 'discount';
                    } elseif ($item == 'resultshipping') {
                        $dataItem = 'shipping';
                    } elseif ($item == 'resulttax') {
                        $dataItem = 'tax';
                    } elseif ($item == 'resultkali') {
                        $dataItem = '*';
                    } elseif ($item == 'resultbagi') {
                        $dataItem = '/';
                    } elseif ($item == 'resulttambah') {
                        $dataItem = '+';
                    } elseif ($item == 'resultkurang') {
                        $dataItem = '-';
                    } elseif ($item == 'resultkbuka') {
                        $dataItem = '(';
                    } elseif ($item == 'resultktutup') {
                        $dataItem = ')';
                    }

                    array_push($dataResult, $dataItem);
                }
            } else {
                array_push($dataResult, '');
            }
            array_push($dataResult, ')');
            array_push($dataResult, '*');
            array_push($dataResult, 'value');

            $join = implode(' ', $dataResult);

            $update = Setting::where('key', 'transaction_discount_formula')->first();

            if (!$update) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $join;
            $update->save();

            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            $checkPercent = Setting::where('key', 'discount_percent')->first();
            if (!$checkPercent) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $checkNominal = Setting::where('key', 'discount_nominal')->first();
            if (!$checkNominal) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $checkPercent->value = $post['percent'];
            $checkPercent->save();
            if (!$checkPercent) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }


            $checkNominal->value = $post['nominal'];
            $checkNominal->save();
            if (!$checkNominal) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($update));

        } elseif ($post['key'] == 'tax') {
            // return $post;
            $dataResult = [];

            array_push($dataResult, '(');

            if (isset($post['item'])) {
                foreach ($post['item'] as $key => $item) {
                    if ($item == 'resultsubtotal') {
                        $dataItem = 'subtotal';
                    } elseif ($item == 'resultservice') {
                        $dataItem = 'service';
                    } elseif ($item == 'resultdiscount') {
                        $dataItem = 'discount';
                    } elseif ($item == 'resultshipping') {
                        $dataItem = 'shipping';
                    } elseif ($item == 'resulttax') {
                        $dataItem = 'tax';
                    } elseif ($item == 'resultkali') {
                        $dataItem = '*';
                    } elseif ($item == 'resultbagi') {
                        $dataItem = '/';
                    } elseif ($item == 'resulttambah') {
                        $dataItem = '+';
                    } elseif ($item == 'resultkurang') {
                        $dataItem = '-';
                    } elseif ($item == 'resultkbuka') {
                        $dataItem = '(';
                    } elseif ($item == 'resultktutup') {
                        $dataItem = ')';
                    }

                    array_push($dataResult, $dataItem);
                }
            } else {
                array_push($dataResult, '');
            }
            array_push($dataResult, ')');
            array_push($dataResult, '*');
            array_push($dataResult, 'value');

            $join = implode(' ', $dataResult);

            $update = Setting::where('key', 'transaction_tax_formula')->first();

            if (!$update) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $join;
            $update->save();

            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            $updateTax = Setting::where('key', 'tax')->first();
            if (!$updateTax) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $updateTax->value = $post['value']/100;
            $updateTax->save();
            if (!$updateTax) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($updateTax));

        } elseif ($post['key'] == 'point') {
            // return $post;
            $dataResult = [];

            array_push($dataResult, '(');

            if (isset($post['item'])) {
                foreach ($post['item'] as $key => $item) {
                    if ($item == 'resultsubtotal') {
                        $dataItem = 'subtotal';
                    } elseif ($item == 'resultservice') {
                        $dataItem = 'service';
                    } elseif ($item == 'resultdiscount') {
                        $dataItem = 'discount';
                    } elseif ($item == 'resultshipping') {
                        $dataItem = 'shipping';
                    } elseif ($item == 'resulttax') {
                        $dataItem = 'tax';
                    } elseif ($item == 'resultkali') {
                        $dataItem = '*';
                    } elseif ($item == 'resultbagi') {
                        $dataItem = '/';
                    } elseif ($item == 'resulttambah') {
                        $dataItem = '+';
                    } elseif ($item == 'resultkurang') {
                        $dataItem = '-';
                    } elseif ($item == 'resultkbuka') {
                        $dataItem = '(';
                    } elseif ($item == 'resultktutup') {
                        $dataItem = ')';
                    }

                    array_push($dataResult, $dataItem);
                }
            } else {
                array_push($dataResult, '');
            }
            array_push($dataResult, ')');
            array_push($dataResult, '*');
            array_push($dataResult, 'value');

            $join = implode(' ', $dataResult);

            $update = Setting::where('key', 'point_acquisition_formula')->first();

            if (!$update) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $join;
            $update->save();

            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            $updatePoint = Setting::where('key', 'point_conversion_value')->first();
            if (!$updatePoint) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $updatePoint->value = $post['value'];
            $updatePoint->save();
            if (!$updatePoint) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($updatePoint));

        } elseif ($post['key'] == 'cashback') {
            // return $post;
            $dataResult = [];

            array_push($dataResult, '(');

            if (isset($post['item'])) {
                foreach ($post['item'] as $key => $item) {
                    if ($item == 'resultsubtotal') {
                        $dataItem = 'subtotal';
                    } elseif ($item == 'resultservice') {
                        $dataItem = 'service';
                    } elseif ($item == 'resultdiscount') {
                        $dataItem = 'discount';
                    } elseif ($item == 'resultshipping') {
                        $dataItem = 'shipping';
                    } elseif ($item == 'resulttax') {
                        $dataItem = 'tax';
                    } elseif ($item == 'resultkali') {
                        $dataItem = '*';
                    } elseif ($item == 'resultbagi') {
                        $dataItem = '/';
                    } elseif ($item == 'resulttambah') {
                        $dataItem = '+';
                    } elseif ($item == 'resultkurang') {
                        $dataItem = '-';
                    } elseif ($item == 'resultkbuka') {
                        $dataItem = '(';
                    } elseif ($item == 'resultktutup') {
                        $dataItem = ')';
                    }

                    array_push($dataResult, $dataItem);
                }
            } else {
                array_push($dataResult, '');
            }
            array_push($dataResult, ')');
            array_push($dataResult, '*');
            array_push($dataResult, 'value');

            $join = implode(' ', $dataResult);

            $update = Setting::where('key', 'cashback_acquisition_formula')->first();

            if (!$update) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $join;
            $update->save();

            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            $updateCashback = Setting::where('key', 'cashback_conversion_value')->first();
            if (!$updateCashback) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $updateCashback->value = $post['value']/100;
            $updateCashback->save();
            if (!$updateCashback) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            $updateCashbackMax = Setting::where('key', 'cashback_maximum')->first();
            if (!$updateCashbackMax) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $updateCashbackMax->value = $post['max'];
            $updateCashbackMax->save();
            if (!$updateCashbackMax) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($updateCashback));

        } elseif ($post['key'] == 'outlet') {
            $update = Setting::where('key', 'default_outlet')->first();
            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $post['value'];
            $update->save();

            if (!$update) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting update failed']
                ]);
            }

            DB::commit();
            return response()->json(MyHelper::checkUpdate($update));
        }

    }

    public function internalCourier() {
        $setting = Setting::where('key', 'transaction_delivery_standard')->orWhere('key', 'transaction_delivery_min_value')->orWhere('key', 'transaction_delivery_max_distance')->orWhere('key', 'transaction_delivery_pricing')->orWhere('key', 'transaction_delivery_price')->get()->toArray();

        return response()->json(MyHelper::checkGet($setting));
    }

    public function manualPaymentList() {
        $list = ManualPayment::with('manual_payment_methods')->get()->toArray();

        return response()->json(MyHelper::checkGet($list));
    }

    public function manualPaymentCreate(ManualPaymentCreate $request) {
        $post = $request->json()->all();

        DB::beginTransaction();
        if (isset($post['manual_payment_logo'])) {
            $decoded = base64_decode($post['manual_payment_logo']);

            // cek extension
            $ext = MyHelper::checkExtensionImageBase64($decoded);

            // set picture name
            $pictName = mt_rand(0, 1000).''.time().''.$ext;

            // path
            $upload = $this->saveImage.$pictName;

            $img = Image::make($decoded);
            $img->save($upload);

            if ($img) {
                $data['manual_payment_logo'] = $upload;
            } else {
                DB::rollBack();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ]);
            }

            // $save = MyHelper::uploadPhotoStrict($post['manual_payment_logo'], $this->saveImage, 300, 300);

            // if (isset($save['status']) && $save['status'] == "success") {
            //     $data['manual_payment_logo'] = $save['path'];
            // }
            // else {
            //     DB::rollBack();
            //     return response()->json([
            //         'status'   => 'fail',
            //         'messages' => ['fail upload image']
            //     ]);
            // }
        }

        if (isset($post['is_virtual_account'])) {
            $data['is_virtual_account'] = $post['is_virtual_account'];
        }

        if (isset($post['manual_payment_name'])) {
            $data['manual_payment_name'] = $post['manual_payment_name'];
        }

        if (isset($post['account_number'])) {
            $data['account_number'] = $post['account_number'];
        }

        if (isset($post['account_name'])) {
            $data['account_name'] = $post['account_name'];
        }

        $save = ManualPayment::create($data);

        if (!$save) {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Create manual payment failed']
            ]);
        }

        DB::commit();
        return response()->json(MyHelper::checkCreate($save));
    }

    public function manualPaymentEdit(ManualPaymentEdit $request) {
        $id = $request->json('id');

        $list = ManualPayment::with('manual_payment_methods')->where('id_manual_payment', $id)->first();

        if (count($list['manual_payment_methods']) > 0) {
            $method = [];

            foreach ($list['manual_payment_methods'] as $value) {
                array_push($method, $value['payment_method_name']);
            }

            $list['method'] = implode(',', $method);
        }

        return response()->json(MyHelper::checkGet($list));
    }

    public function manualPaymentUpdate(ManualPaymentUpdate $request) {
        $post = $request->json()->all();

        DB::beginTransaction();
        if (isset($post['post']['manual_payment_logo'])) {
            $decoded = base64_decode($post['post']['manual_payment_logo']);

            // cek extension
            $ext = MyHelper::checkExtensionImageBase64($decoded);

            // set picture name
            $pictName = mt_rand(0, 1000).''.time().''.$ext;

            // path
            $upload = $this->saveImage.$pictName;

            $img = Image::make($decoded);
            $img->save($upload);

            if ($img) {
                $data['manual_payment_logo'] = $upload;
            } else {
                DB::rollBack();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ]);
            }
            // $save = MyHelper::uploadPhotoStrict($post['post']['manual_payment_logo'], $this->saveImage, 300, 300);

            // if (isset($save['status']) && $save['status'] == "success") {
            //     $data['manual_payment_logo'] = $save['path'];
            // }
            // else {
            //     DB::rollBack();
            //     return response()->json([
            //         'status'   => 'fail',
            //         'messages' => ['fail upload image']
            //     ]);
            // }
        }

        if (isset($post['post']['is_virtual_account'])) {
            $data['is_virtual_account'] = $post['post']['is_virtual_account'];
        }

        if (isset($post['post']['manual_payment_name'])) {
            $data['manual_payment_name'] = $post['post']['manual_payment_name'];
        }

        if (isset($post['post']['account_number'])) {
            $data['account_number'] = $post['post']['account_number'];
        }

        if (isset($post['post']['account_name'])) {
            $data['account_name'] = $post['post']['account_name'];
        }

        $save = ManualPayment::where('id_manual_payment', $post['id'])->update($data);
        // return $save;
        if (!$save) {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Update manual payment failed']
            ]);
        }

        // $old = explode(',', $post['post']['method_name_old']);
        // $new = explode(',', $post['post']['method_name_new']);
        // // return $old;
        // // return response()->json($old[0]);

        // foreach ($old as $key => $o) {
        //     if (!in_array($o, $new)) {
        //         $delete = ManualPaymentMethod::where('payment_method_name', $o)->delete();
        //         // return $delete;

        //         if (!$delete) {
        //             DB::rollBack();
        //             return response()->json([
        //                 'status'    => 'fail',
        //                 'messages'  => ['Update manual payment failed1']
        //             ]);
        //         }
        //     }
        // }

        // foreach ($new as $row => $n) {
        //     if (!in_array($n, $old)) {
        //         $data = [
        //             'id_manual_payment' => $post['id'],
        //             'payment_method_name'   => $n
        //         ];

        //         $insert = ManualPaymentMethod::create($data);

        //         if (!$insert) {
        //             DB::rollBack();
        //             return response()->json([
        //                 'status'    => 'fail',
        //                 'messages'  => ['Update manual payment failed']
        //             ]);
        //         }
        //     }
        // }

        DB::commit();
        return response()->json(MyHelper::checkCreate($save));
    }

    public function manualPaymentDetail(ManualPaymentDetail $request) {
        $id = $request->json('id');

        $detail = ManualPayment::with('manual_payment_methods.manual_payment_tutorials')->where('id_manual_payment', $id)->first();

        if (!empty($detail['manual_payment_methods'])) {
            $detail['old_id'] = implode(',', array_column($detail['manual_payment_methods']->toArray(), 'id_manual_payment_method'));
        }
        // return $detail;
        return response()->json(MyHelper::checkGet($detail));
    }

    public function manualPaymentDelete(ManualPaymentDelete $request) {
        $id = $request->json('id');
        $check = ManualPayment::with('manual_payment_methods.transaction_payment_manuals.transaction')->where('id_manual_payment', $id)->first();
        if (empty($check)) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Delete manual payment failed']
            ]);
        }

        foreach ($check['manual_payment_methods'] as $key => $value) {
            if (count($value['transaction_payment_manuals']) > 0) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['This payment is already in use']
                ]);
            }
        }

        $check->delete();

        return response()->json(MyHelper::checkDelete($check));
    }

    public function manualPaymentMethod(MethodSave $request) {
        $post = $request->json()->all();
        // return $post;
        $check = explode(',', $post['old_id']);

        DB::beginTransaction();
        if (!isset($post['method_name'])) {
            $delete = ManualPaymentMethod::where('id_manual_payment', $post['id'])->delete();

            if (!$delete) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Failed']
                ]);
            }
        } else {
            foreach ($post['method_name'] as $key => $value) {
                $data = [
                    'id_manual_payment'   => $post['id'],
                    'payment_method_name' => $value,
                ];

                if (in_array($post['id_method'][$key], $check)) {
                    $method = ManualPaymentMethod::with('manual_payment_tutorials')->where('id_manual_payment_method', $post['id_method'][$key])->first();
                    $insert = $method->update($data);

                    if (count($method['manual_payment_tutorials']) > 0) {
                        $delete = ManualPaymentTutorial::where('id_manual_payment_method', $post['id_method'][$key])->delete();

                        if (!$delete) {
                            DB::rollBack();
                            return response()->json([
                                'status'    => 'fail',
                                'messages'  => ['Failed']
                            ]);
                        }
                    }

                    $id = $method['id_manual_payment_method'];
                } else {
                    $insert = ManualPaymentMethod::create($data);
                    $id = $insert['id_manual_payment_method'];
                }

                if (!$insert) {
                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Failed']
                    ]);
                }

                if (isset($post['tutorial_'.$key.''])) {
                    foreach ($post['tutorial_'.$key.''] as $row => $tutorial) {
                        $dataTutor = [
                            'id_manual_payment_method' => $id,
                            'payment_tutorial'         => $tutorial,
                            'payment_tutorial_no'      => $row+1
                        ];

                        $insert = ManualPaymentTutorial::create($dataTutor);

                        if (!$insert) {
                            DB::rollBack();
                            return response()->json([
                                'status'    => 'fail',
                                'messages'  => ['Insert Failed']
                            ]);
                        }
                    }
                }
            }

            if (isset($post['old_id'])) {
                foreach ($check as $tes => $value) {
                    if (!in_array($value, $post['id_method'])) {
                        $delete = ManualPaymentMethod::where('id_manual_payment_method', $value)->delete();

                        if (!$delete) {
                            DB::rollBack();
                            return response()->json([
                                'status'    => 'fail',
                                'messages'  => ['Insert Failed']
                            ]);
                        }
                    }
                }
            }
        }

        DB::commit();

        return response()->json([
            'status'    => 'success',
            'messages'  => ['Success']
        ]);
    }

    public function manualPaymentMethodDelete(MethodDelete $request) {
        $id = $request->json('id');

        $check = ManualPaymentTutorial::where('id_manual_payment_tutorial', $id)->first();

        if (empty($check)) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Tutorial Not Found']
            ]);
        }

        $check->delete();

        return response()->json(MyHelper::checkDelete($check));

    }

    public function pointUser(Request $request) {
        $point = LogPoint::with('user')->paginate(10);
        return response()->json(MyHelper::checkGet($point));
    }

    public function pointUserFilter(Request $request) {
        $post = $request->json()->all();

        $conditions = [];
        $rule = '';
        $search = '';
        // return $post;
        $start = date('Y-m-d', strtotime($post['date_start']));
        $end = date('Y-m-d', strtotime($post['date_end']));
        $query = LogPoint::select('log_points.*',
                              'users.*')
                    ->leftJoin('users','log_points.id_user','=','users.id')
                    ->where('log_points.created_at', '>=', $start)
                    ->where('log_points.created_at', '<=', $end)
                    ->orderBy('log_points.id_log_point', 'DESC')
                    ->groupBy('log_points.id_log_point');
                    // ->orderBy('transactions.id_transaction', 'DESC');

        // return response()->json($query->get());
        if (isset($post['conditions'])) {
            foreach ($post['conditions'] as $key => $con) {
                if(isset($con['subject'])){
                    $var = $con['subject'];
                    if ($post['rule'] == 'and') {
                        if ($con['operator'] == 'like') {
                            $query = $query->where($var, $con['operator'], '%'.$con['parameter'].'%');
                        } else {
                            $query = $query->where($var, $con['operator'], $con['parameter']);
                        }
                    } else {
                        if ($con['operator'] == 'like') {
                            $query = $query->orWhere($var, $con['operator'], '%'.$con['parameter'].'%');
                        } else {
                            $query = $query->orWhere($var, $con['operator'], $con['parameter']);
                        }
                    }
                }

            }

            $conditions = $post['conditions'];
            $rule       = $post['rule'];
            $search     = '1';
        }

        $akhir = $query->paginate(10);

        if ($akhir) {
            $result = [
                'status'     => 'success',
                'data'       => $akhir,
                'count'      => count($akhir),
                'conditions' => $conditions,
                'rule'       => $rule,
                'search'     => $search
            ];
        } else {
            $result = [
                'status'     => 'fail',
                'data'       => $akhir,
                'count'      => count($akhir),
                'conditions' => $conditions,
                'rule'       => $rule,
                'search'     => $search
            ];
        }

        return response()->json($result);
    }

    public function balanceUserFilter(Request $request) {
        $post = $request->json()->all();

        $conditions = [];
        $rule = '';
        $search = '';
        // return $post;
        $start = date('Y-m-d', strtotime($post['date_start']));
        $end = date('Y-m-d', strtotime($post['date_end']));
        $query = LogBalance::select(
        				'log_balances.*',
                        'users.name',
                        'users.phone'
                    )
                    ->leftJoin('users','log_balances.id_user','=','users.id')
                    ->where('log_balances.created_at', '>=', $start)
                    ->where('log_balances.created_at', '<=', $end)
                    ->orderBy('log_balances.id_log_balance', 'DESC')
                    ->groupBy('log_balances.id_log_balance');
                    // ->orderBy('transactions.id_transaction', 'DESC');

        // return response()->json($query->get());
        if (isset($post['conditions'])) {
            foreach ($post['conditions'] as $key => $con) {
                if(isset($con['subject'])){
                    if ($con['subject'] == 'balance') {
                        $var = 'log_balances.balance';
                    } else {
                        $var = $con['subject'];
                    }

                    if ($post['rule'] == 'and') {
                        if ($con['operator'] == 'like') {
                            $query = $query->where($var, $con['operator'], '%'.$con['parameter'].'%');
                        } else {
                            $query = $query->where($var, $con['operator'], $con['parameter']);
                        }
                    } else {
                        if ($con['operator'] == 'like') {
                            $query = $query->orWhere($var, $con['operator'], '%'.$con['parameter'].'%');
                        } else {
                            $query = $query->orWhere($var, $con['operator'], $con['parameter']);
                        }
                    }
                }

            }

            $conditions = $post['conditions'];
            $rule       = $post['rule'];
            $search     = '1';
        }

        $akhir = $query->paginate(10)->toArray();
        if ($akhir) {

	        $akhir['data'] = $query->paginate(10)
			        				->each(function($q){
									    $q->setAppends([
									        'get_reference'
									    ]);
									})
				        			->toArray();
            $result = [
                'status'     => 'success',
                'data'       => $akhir,
                'count'      => count($akhir),
                'conditions' => $conditions,
                'rule'       => $rule,
                'search'     => $search
            ];
        } else {
            $result = [
                'status'     => 'fail',
                'data'       => $akhir,
                'count'      => count($akhir),
                'conditions' => $conditions,
                'rule'       => $rule,
                'search'     => $search
            ];
        }

        return response()->json($result);
    }

    public function balanceUser(Request $request) {
    	$balance = LogBalance::with('user')
        			->orderBy('id_log_balance', 'desc')
        			->paginate(10)
        			->toArray();

        if ($balance) {
	        $balance['data'] = LogBalance::with('user')
			        			->orderBy('id_log_balance', 'desc')
			        			->paginate(10)
			        			->each(function($q){
								    $q->setAppends([
								        'get_reference'
								    ]);
								})
			        			->toArray();
        }
        return response()->json(MyHelper::checkGet($balance));
    }

    public function manualPaymentListUnpay(Request $request) {
        $list = TransactionPaymentManual::with('transaction', 'manual_payment_method')->get()->toArray();
        return response()->json(MyHelper::checkGet($list));
    }

    public function transactionList($key){
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-d 23:59:59');
        $list = Transaction::leftJoin('outlets','outlets.id_outlet','=','transactions.id_outlet')->orderBy('id_transaction', 'DESC')->with('user', 'productTransaction.product.product_category');

        if (strtolower($key) == 'delivery') {
            $list->where('trasaction_type', 'Pickup Order')->whereNotNull('transaction_shipping_method');
        } elseif (strtolower($key) == 'pickup order') {
            $list->where('trasaction_type', 'Pickup Order')->whereNull('transaction_shipping_method');
        } else {
            $list->where('trasaction_type', ucwords($key));
        }

        $list = $list->where('transactions.created_at', '>=', $start)->where('transactions.created_at', '<=', $end)->paginate(10);

        return response()->json(MyHelper::checkGet($list));
    }

    public function transactionFilter(TransactionFilter $request) {
        $post = $request->json()->all();
        // return $post;
        $conditions = [];
        $rule = '';
        $search = '';
        // return $post;
        $start = date('Y-m-d', strtotime($post['date_start']));
        $end = date('Y-m-d', strtotime($post['date_end']));
        $query = Transaction::select('transactions.*',
                              'transaction_pickups.*',
                              'transaction_products.*',
                              'users.*',
                              'products.*',
                              'product_categories.*',
                              'outlets.outlet_code', 'outlets.outlet_name')
                    ->leftJoin('outlets','outlets.id_outlet','=','transactions.id_outlet')
                    ->leftJoin('transaction_pickups','transactions.id_transaction','=','transaction_pickups.id_transaction')
                    ->leftJoin('transaction_products','transactions.id_transaction','=','transaction_products.id_transaction')
                    ->leftJoin('users','transactions.id_user','=','users.id')
                    ->leftJoin('products','products.id_product','=','transaction_products.id_product')
                    ->leftJoin('product_categories','products.id_product_category','=','product_categories.id_product_category')
                    ->where('trasaction_type', $post['key'])
                    ->whereDate('transactions.transaction_date', '>=', $start)
                    ->whereDate('transactions.transaction_date', '<=', $end)
                    ->with('user')
                    ->orderBy('transactions.id_transaction', 'DESC')
                    ->groupBy('transactions.id_transaction');
                    // ->orderBy('transactions.id_transaction', 'DESC');

        // return response()->json($query->get());
        if (isset($post['conditions'])) {
            foreach ($post['conditions'] as $key => $con) {
                if (isset($con['subject'])) {
                    if ($con['subject'] == 'receipt') {
                        $var = 'transactions.transaction_receipt_number';
                    } elseif ($con['subject'] == 'name' || $con['subject'] == 'phone' || $con['subject'] == 'email') {
                        $var = 'users.'.$con['subject'];
                    } elseif ($con['subject'] == 'product_name' || $con['subject'] == 'product_code') {
                        $var = 'products.'.$con['subject'];
                    } elseif ($con['subject'] == 'product_category') {
                        $var = 'product_categories.product_category_name';
                    } elseif ($con['subject'] == 'order_id') {
                        $var = 'transaction_pickups.order_id';
                    }

                    if ($con['subject'] == 'outlet_code' || $con['subject'] == 'outlet_name') {
                        $var = 'outlets.'.$con['subject'];
                        if ($post['rule'] == 'and') {
                            $query = $query->where($var, $con['operator'], $con['parameter']);
                        } else {
                            $query = $query->orWhere($var, $con['operator'], $con['parameter']);
                        }
                    }

                    if ($con['subject'] == 'receipt' || $con['subject'] == 'name' || $con['subject'] == 'phone' || $con['subject'] == 'email' || $con['subject'] == 'product_name' || $con['subject'] == 'product_code' || $con['subject'] == 'product_category' || $con['subject'] == 'order_id') {
                        if ($post['rule'] == 'and') {
                            if ($con['operator'] == 'like') {
                                $query = $query->where($var, 'like', '%'.$con['parameter'].'%');
                            } else {
                                $query = $query->where($var, '=', $con['parameter']);
                            }
                        } else {
                            if ($con['operator'] == 'like') {
                                $query = $query->orWhere($var, 'like', '%'.$con['parameter'].'%');
                            } else {
                                $query = $query->orWhere($var, '=', $con['parameter']);
                            }
                        }
                    }

                    if ($con['subject'] == 'product_name' || $con['subject'] == 'product_code' || $con['subject'] == 'product_weight' || $con['subject'] == 'product_price') {
                        $var = 'products.'.$con['subject'];
                        if ($post['rule'] == 'and') {
                            $query = $query->where($var, $con['operator'], $con['parameter']);
                        } else {
                            $query = $query->orWhere($var, $con['operator'], $con['parameter']);
                        }
                    }

                    if ($con['subject'] == 'grand_total' || $con['subject'] == 'product_tax') {
                        if ($con['subject'] == 'grand_total') {
                            $var = 'transactions.transaction_grandtotal';
                        } else {
                            $var = 'transactions.transaction_tax';
                        }

                        if ($post['rule'] == 'and') {
                            $query = $query->where($var, $con['operator'], $con['parameter']);
                        } else {
                            $query = $query->orWhere($var, $con['operator'], $con['parameter']);
                        }
                    }

                    if ($con['subject'] == 'status' || $con['subject'] == 'courier') {
                        if ($con['subject'] == 'status') {
                            $var = 'transactions.transaction_payment_status';
                        } else {
                            $var = 'transactions.transaction_courier';
                        }

                        if ($post['rule'] == 'and') {
                            $query = $query->where($var, '=', $con['operator']);
                        } else {
                            $query = $query->orWhere($var, '=', $con['operator']);
                        }
                    }
                }

            }

            $conditions = $post['conditions'];
            $rule       = $post['rule'];
            $search     = '1';
        }

        $akhir = $query->paginate(10);
        // return $akhir;
        if ($akhir) {
            $result = [
                'status'     => 'success',
                'data'       => $akhir,
                'count'      => count($akhir),
                'conditions' => $conditions,
                'rule'       => $rule,
                'search'     => $search
            ];
        } else {
            $result = [
                'status'     => 'fail',
                'data'       => $akhir,
                'count'      => count($akhir),
                'conditions' => $conditions,
                'rule'       => $rule,
                'search'     => $search
            ];
        }

        return response()->json($result);
    }

    public function transactionDetail(TransactionDetail $request){
        if ($request->json('transaction_receipt_number') !== null) {
            $trx = Transaction::where(['transaction_receipt_number' => $request->json('transaction_receipt_number')])->first();
            if($trx) {
                $id = $trx->id_transaction;
            } else {
                return MyHelper::checkGet([]);
            }
        } else {
            $id = $request->json('id_transaction');
        }
        $type = $request->json('type');

        $use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();

        if ($type == 'trx') {
            if($request->json('admin')){
                $list = Transaction::where(['transactions.id_transaction' => $id])->with('user')->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction');
            }else{
                $list = Transaction::join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')->where(['transactions.id_transaction' => $id, 'id_user' => $request->user()->id]);
            }
            if($use_product_variant){
                $list = $list->with(
                    // 'user.city.province',
                    'productTransaction.product.product_group',
                    'productTransaction.product.product_variants',
                    'productTransaction.product.product_group.product_category',
                    'productTransaction.modifiers',
                    'productTransaction.product.product_photos',
                    'productTransaction.product.product_discounts',
                    'transaction_payment_offlines',
                    'transaction_vouchers.deals_voucher.deal',
                    'promo_campaign_promo_code.promo_campaign',
                    'promo_campaign_promo_code_delivery.promo_campaign',
                    'promo_campaign_referral_transaction',
                    'transaction_pickup_go_send.transaction_pickup_update',
                    'transaction_pickup_outlet',
                    'outlet.city')->first();
            }else{
                $list = $list->with(
                    // 'user.city.province',
                    'productTransaction.product.product_category',
                    'productTransaction.modifiers',
                    'productTransaction.product.product_photos',
                    'productTransaction.product.product_group',
                    'productTransaction.product.product_discounts',
                    'transaction_payment_offlines',
                    'transaction_vouchers.deals_voucher.deal',
                    'promo_campaign_promo_code.promo_campaign',
                    'promo_campaign_promo_code_delivery.promo_campaign',
                    'promo_campaign_referral_transaction',
                    'outlet.city')->first();
            }
            if(!$list){
                return MyHelper::checkGet([],'empty');
            }
            $list = $list->toArray();
            $label = [];
            $label2 = [];
            $product_count=0;
            if(!$use_product_variant){
                $list['product_transaction'] = MyHelper::groupIt($list['product_transaction'],'id_brand',null,function($key,&$val) use (&$product_count){
                    $product_count += array_sum(array_column($val,'transaction_product_qty'));
                    $brand = Brand::select('name_brand')->find($key);
                    if(!$brand){
                        return 'No Brand';
                    }
                    return $brand->name_brand;
                });
            }
            $cart = $list['transaction_subtotal'] + $list['transaction_shipment'] + $list['transaction_service'] + $list['transaction_tax'] - $list['transaction_discount'];

            $list['transaction_carttotal'] = $cart;
            $list['transaction_item_total'] = $product_count;

            $order = Setting::where('key', 'transaction_grand_total_order')->value('value');
            $exp   = explode(',', $order);
            $exp2   = explode(',', $order);

            foreach ($exp as $i => $value) {
                if ($exp[$i] == 'subtotal') {
                    unset($exp[$i]);
                    unset($exp2[$i]);
                    continue;
                }

                if ($exp[$i] == 'tax') {
                    $exp[$i] = 'transaction_tax';
                    $exp2[$i] = 'transaction_tax';
                    array_push($label, 'Tax');
                    array_push($label2, 'Tax');
                }

                if ($exp[$i] == 'service') {
                    $exp[$i] = 'transaction_service';
                    $exp2[$i] = 'transaction_service';
                    array_push($label, 'Service Fee');
                    array_push($label2, 'Service Fee');
                }

                if ($exp[$i] == 'shipping') {
                    if ($list['trasaction_type'] == 'Pickup Order') {
                        unset($exp[$i]);
                        unset($exp2[$i]);
                        continue;
                    } else {
                        $exp[$i] = 'transaction_shipment';
                        $exp2[$i] = 'transaction_shipment';
                        array_push($label, 'Delivery Cost');
                        array_push($label2, 'Delivery Cost');
                    }
                }

                if ($exp[$i] == 'discount') {
                    $exp2[$i] = 'transaction_discount';
                    array_push($label2, 'Discount');
                    unset($exp[$i]);
                    continue;
                }

                if (stristr($exp[$i], 'empty')) {
                    unset($exp[$i]);
                    unset($exp2[$i]);
                    continue;
                }
            }

            $list['payment'] = [];            
            switch ($list['trasaction_payment_type']) {
                case 'Balance':
                    $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get()->toArray();
                    if ($multiPayment) {
                        foreach ($multiPayment as $keyMP => $mp) {
                            switch ($mp['type']) {
                                case 'Balance':
                                    $log = LogBalance::where('id_reference', $mp['id_transaction'])->first();
                                    if ($log['balance'] < 0) {
                                        $list['balance'] = $log['balance'];
                                        $list['check'] = 'tidak topup';
                                    } else {
                                        $list['balance'] = $list['transaction_grandtotal'] - $log['balance'];
                                        $list['check'] = 'topup';
                                    }
                                    $list['payment'][] = [
                                        'name'      => 'Balance',
                                        'amount'    => $list['balance']
                                    ];
                                    break;
                                case 'Manual':
                                    $payment = TransactionPaymentManual::with('manual_payment_method.manual_payment')->where('id_transaction', $list['id_transaction'])->first();
                                    $list['payment'] = $payment;
                                    $list['payment'][] = [
                                        'name'      => 'Cash',
                                        'amount'    => $payment['payment_nominal']
                                    ];
                                    break;
                                case 'Midtrans':
                                    $payMidtrans = TransactionPaymentMidtran::find($mp['id_payment']);
                                    $payment['name']      = strtoupper(str_replace('_', ' ', $payMidtrans->payment_type)).' '.strtoupper($payMidtrans->bank);
                                    $payment['amount']    = $payMidtrans->gross_amount;
                                    $payment['reject']    = 'payment expired';
                                    $list['payment'][]    = $payment;
                                    break;
                                case 'Ovo':
                                    $payOvo = TransactionPaymentOvo::find($mp['id_payment']);
                                    $payment = $payOvo;
                                    $payment['name']    = 'OVO';
                                    $payment['reject']  = $payOvo->response_description;
                                    $list['payment'][]  = $payment;
                                    break;
                                case 'Nobu':
                                    $payNobu = TransactionPaymentNobu::find($mp['id_payment']);
                                    $list['payment'][] = [
                                        'name'      => 'QRIS Nobu',
                                        'amount'    => $payNobu->gross_amount,
                                        'qris'      => $payNobu->qris_data??'',
                                    ];
                                    break;
                                case 'IPay88':
                                    $PayIpay = TransactionPaymentIpay88::find($mp['id_payment']);
                                    // $payment['name']    = $PayIpay->payment_method;
                                    $payment['name']    = "CREDIT/DEBIT CARD";
                                    $payment['amount']  = $PayIpay->amount / 100;
                                    $payment['reject']  = $PayIpay->err_desc?:'payment expired';
                                    $list['payment'][]  = $payment;
                                    break;
                                case 'Shopeepay':
                                    $shopeePay = TransactionPaymentShopeePay::find($mp['id_payment']);
                                    $payment['name']    = 'ShopeePay';
                                    $payment['amount']  = $shopeePay->amount / 100;
                                    $payment['reject']  = $shopeePay->err_reason?:'payment expired';
                                    $list['payment'][]  = $payment;
                                    break;
                                case 'Offline':
                                    $payment = TransactionPaymentOffline::where('id_transaction', $list['id_transaction'])->get();
                                    foreach ($payment as $key => $value) {
                                        $list['payment'][$key] = [
                                            'name'      => $value['payment_bank'],
                                            'amount'    => $value['payment_amount']
                                        ];
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    } else {
                        $log = LogBalance::where('id_reference', $list['id_transaction'])->first();
                        if ($log['balance'] < 0) {
                            $list['balance'] = $log['balance'];
                            $list['check'] = 'tidak topup';
                        } else {
                            $list['balance'] = $list['transaction_grandtotal'] - $log['balance'];
                            $list['check'] = 'topup';
                        }
                        $list['payment'][] = [
                            'name'      => 'Balance',
                            'amount'    => $list['balance']
                        ];
                    }
                    break;
                case 'Manual':
                    $payment = TransactionPaymentManual::with('manual_payment_method.manual_payment')->where('id_transaction', $list['id_transaction'])->first();
                    $list['payment'] = $payment;
                    $list['payment'][] = [
                        'name'      => 'Cash',
                        'amount'    => $payment['payment_nominal']
                    ];
                    break;
                case 'Midtrans':
                    $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                    $payment = [];
                    foreach($multiPayment as $dataKey => $dataPay){
                        if($dataPay['type'] == 'Midtrans'){
                            $payMidtrans = TransactionPaymentMidtran::find($dataPay['id_payment']);
                            $payment[$dataKey]['name']      = strtoupper(str_replace('_', ' ', $payMidtrans->payment_type)).' '.strtoupper($payMidtrans->bank);
                            $payment[$dataKey]['amount']    = $payMidtrans->gross_amount;
                            $payment[$dataKey]['reject']    = 'payment expired';
                        }else{
                            $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                            $payment[$dataKey]              = $dataPay;
                            $list['balance']                = $dataPay['balance_nominal'];
                            $payment[$dataKey]['name']      = 'Balance';
                            $payment[$dataKey]['amount']    = $dataPay['balance_nominal'];
                        }
                    }
                    $list['payment'] = $payment;
                    break;
                case 'Ovo':
                    $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                    $payment = [];
                    foreach($multiPayment as $dataKey => $dataPay){
                        if($dataPay['type'] == 'Ovo'){
                            $payOvo = TransactionPaymentOvo::find($dataPay['id_payment']);
                            $payment[$dataKey] = $payOvo;
                            $payment[$dataKey]['name']    = 'OVO';
                            $payment[$dataKey]['reject']  = $payOvo->response_description;
                        }else{
                            $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                            $payment[$dataKey] = $dataPay;
                            $list['balance']                = $dataPay['balance_nominal'];
                            $payment[$dataKey]['name']      = 'Balance';
                            $payment[$dataKey]['amount']    = $dataPay['balance_nominal'];
                        }
                    }
                    $list['payment'] = $payment;
                    break;
                case 'Ipay88':
                    $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                    $payment = [];
                    foreach($multiPayment as $dataKey => $dataPay){
                        if($dataPay['type'] == 'IPay88'){
                            $PayIpay = TransactionPaymentIpay88::find($dataPay['id_payment']);
                            // $payment[$dataKey]['name']      = $PayIpay->payment_method;
                            $payment[$dataKey]['name']      = "CREDIT/DEBIT CARD";
                            $payment[$dataKey]['amount']    = $PayIpay->amount / 100;
                            $payment[$dataKey]['reject']    = $PayIpay->err_desc;
                        }else{
                            $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                            $payment[$dataKey]              = $dataPay;
                            $list['balance']                = $dataPay['balance_nominal'];
                            $payment[$dataKey]['name']      = 'Balance';
                            $payment[$dataKey]['amount']    = $dataPay['balance_nominal'];
                        }
                    }
                    $list['payment'] = $payment;
                    break;
                case 'Shopeepay':
                    $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                    $payment = [];
                    foreach($multiPayment as $dataKey => $dataPay){
                        if($dataPay['type'] == 'Shopeepay'){
                            $payShopee = TransactionPaymentShopeePay::find($dataPay['id_payment']);
                            $payment[$dataKey]['name']      = 'ShopeePay';
                            $payment[$dataKey]['amount']    = $payShopee->amount / 100;
                            $payment[$dataKey]['reject']    = $payShopee->err_reason?:'payment expired';
                        }else{
                            $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                            $payment[$dataKey]              = $dataPay;
                            $list['balance']                = $dataPay['balance_nominal'];
                            $payment[$dataKey]['name']      = 'Balance';
                            $payment[$dataKey]['amount']    = $dataPay['balance_nominal'];
                        }
                    }
                    $list['payment'] = $payment;
                    break;
                case 'Nobu' :
                    $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                    $payment = [];
                    foreach($multiPayment as $dataKey => $dataPay){
                        if($dataPay['type'] == 'Nobu'){
                            $payNobu = TransactionPaymentNobu::find($dataPay['id_payment']);
                            $payment[$dataKey]['name']      = 'Nobu';
                            $payment[$dataKey]['amount']    = $payNobu->gross_amount;
                            $payment[$dataKey]['reject']    = 'payment expired';
                            $payment[$dataKey]['qris']      = $payNobu->qris_data??'';
                        }else{
                            $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                            $payment[$dataKey]              = $dataPay;
                            $list['balance']                = $dataPay['balance_nominal'];
                            $payment[$dataKey]['name']      = 'Balance';
                            $payment[$dataKey]['amount']    = $dataPay['balance_nominal'];
                        }
                    }
                    $list['payment'] = $payment;
                    break;
                case 'Offline':
                    $payment = TransactionPaymentOffline::where('id_transaction', $list['id_transaction'])->get();
                    foreach ($payment as $key => $value) {
                        $list['payment'][$key] = [
                            'name'      => $value['payment_bank'],
                            'amount'    => $value['payment_amount']
                        ];
                    }
                    break;
                default:
                    break;
            }

            array_splice($exp, 0, 0, 'transaction_subtotal');
            array_splice($label, 0, 0, 'Cart Total');

            array_splice($exp2, 0, 0, 'transaction_subtotal');
            array_splice($label2, 0, 0, 'Cart Total');

            array_values($exp);
            array_values($label);

            array_values($exp2);
            array_values($label2);

            $imp = implode(',', $exp);
            $order_label = implode(',', $label);

            $imp2 = implode(',', $exp2);
            $order_label2 = implode(',', $label2);

            $detail = [];

            if ($list['trasaction_type'] == 'Pickup Order') {
                $detail = TransactionPickup::where('id_transaction', $list['id_transaction'])->first()->toArray();
                if($detail){
                    $qr      = $detail['order_id'];

                    $qrCode = 'https://chart.googleapis.com/chart?chl='.$qr.'&chs=250x250&cht=qr&chld=H%7C0';
                    $qrCode =   html_entity_decode($qrCode);

                    $newDetail = [];
                    foreach($detail as $key => $value){
                        $newDetail[$key] = $value;
                        if($key == 'order_id'){
                            $newDetail['order_id_qrcode'] = $qrCode;
                        }
                    }

                    $detail = $newDetail;
                }
            } elseif ($list['trasaction_type'] == 'Delivery') {
                $detail = TransactionShipment::with('city.province')->where('id_transaction', $list['id_transaction'])->first();
            }

            $list['detail'] = $detail;
            $list['order'] = $imp;
            $list['order_label'] = $order_label;

            $list['order_v2'] = $imp2;
            $list['order_label_v2'] = $order_label2;

            $list['date'] = $list['transaction_date'];
            $list['type'] = 'trx';

            $result = [
                'id_transaction'                => $list['id_transaction'],
                'transaction_receipt_number'    => $list['transaction_receipt_number'],
                'transaction_date'              => date('d M Y H:i', strtotime($list['transaction_date'])),
                'trasaction_type'               => $list['trasaction_type'],
                'transaction_grandtotal'        => MyHelper::requestNumber($list['transaction_grandtotal'],'_CURRENCY'),
                'transaction_subtotal'          => MyHelper::requestNumber($list['transaction_subtotal'],'_CURRENCY'),
                'transaction_discount'          => MyHelper::requestNumber($list['transaction_discount'],'_CURRENCY'),
                'transaction_cashback_earned'   => MyHelper::requestNumber($list['transaction_cashback_earned'],'_POINT'),
                'trasaction_payment_type'       => $list['trasaction_payment_type'],
                'transaction_payment_status'    => $list['transaction_payment_status'],
                'rating_value'                  => UserRating::select('rating_value')->where('id_transaction', $list['id_transaction'])->pluck('rating_value')->first(),
                'outlet'                        => [
                    'outlet_name'       => $list['outlet']['outlet_name'],
                    'outlet_address'    => $list['outlet']['outlet_address']
                ]
            ];

            if ($list['trasaction_payment_type'] != 'Offline') {
                $result['detail'] = [
                        'order_id_qrcode'   => $list['detail']['order_id_qrcode'],
                        'order_id'          => $list['detail']['order_id'],
                        'pickup_type'       => $list['detail']['pickup_type'],
                        'pickup_date'       => date('d F Y', strtotime($list['transaction_date'])),
                        'pickup_time'       => ($list['detail']['pickup_type'] == 'right now') ? 'RIGHT NOW' : date('H : i', strtotime($list['detail']['pickup_at'])),
                ];
                if (isset($list['transaction_payment_status']) && $list['transaction_payment_status'] == 'Cancelled') {
                    unset($result['detail']['order_id_qrcode']);
                    unset($result['detail']['order_id']);
                    unset($result['detail']['pickup_time']);
                    $result['transaction_status'] = 'Payment Canceled';
                    $result['transaction_status_code'] = 0;
                } elseif(isset($list['transaction_payment_status']) && $list['transaction_payment_status'] == 'Pending') {
                    $result['transaction_status'] = 'Payment Pending';
                    $result['transaction_status_code'] = 5;
                } elseif($list['detail']['reject_at'] != null) {
                    if (strpos(strtolower($list['detail']['reject_reason']), 'user') !== FALSE) {
                        $result['transaction_status'] = 'Order Canceled by User';
                    } else {
                        $result['transaction_status'] = 'Order Canceled by System';
                    }
                    $result['transaction_status_code'] = 0;
                } elseif($list['detail']['taken_by_system_at'] != null) {
                    $result['transaction_status'] = 'Order Completed';
                    $result['transaction_status_code'] = 1;
                } elseif($list['detail']['taken_at'] != null) {
                    $result['transaction_status'] = 'Order Completed';
                    $result['transaction_status_code'] = 1;
                } elseif($list['detail']['ready_at'] != null) {
                    if (($list['detail']['pickup_by'] ?? false) == 'Outlet') {
                        $result['transaction_status'] = 'Maxx Crew Delivering your order';
                    } else {
                        $result['transaction_status'] = 'Order Is Ready';
                    }
                    $result['transaction_status_code'] = 2;
                } elseif($list['detail']['receive_at'] != null) {
                    $result['transaction_status'] = 'Order is On Process';
                    $result['transaction_status_code'] = 3;
                } else {
                    $result['transaction_status'] = 'Waiting for Outlet Confirmation';
                    $result['transaction_status_code'] = 4;
                }
            }else{
                if($list['transaction_payment_status'] == 'Completed'){
                    $result['transaction_status'] = 'Order Completed';
                    $result['transaction_status_code'] = 1;
                }else{
                    $result['transaction_status'] = 'Order Pending';
                    $result['transaction_status_code'] = 5;
                }
            }

            $discount = abs($list['transaction_discount']);
            $discount_delivery = abs($list['transaction_discount_delivery']);
            $quantity = 0;
            foreach ($list['product_transaction'] as $keyTrx => $valueTrx) {
                $quantity = $quantity + $valueTrx['transaction_product_qty'];
                $result['product_transaction'][$keyTrx]['transaction_product_qty']              = $valueTrx['transaction_product_qty'];
                $result['product_transaction'][$keyTrx]['transaction_product_subtotal']         = MyHelper::requestNumber($valueTrx['transaction_product_subtotal'],'_CURRENCY');
                $result['product_transaction'][$keyTrx]['transaction_product_sub_item']         = '@'.MyHelper::requestNumber($valueTrx['transaction_product_subtotal'] / $valueTrx['transaction_product_qty'],'_CURRENCY');
                $result['product_transaction'][$keyTrx]['transaction_modifier_subtotal']        = MyHelper::requestNumber($valueTrx['transaction_modifier_subtotal'],'_CURRENCY');
                $result['product_transaction'][$keyTrx]['transaction_product_note']             = $valueTrx['transaction_product_note'];
                $result['product_transaction'][$keyTrx]['product']['product_name']              = $valueTrx['product']['product_group']['product_group_name'];
                // $discount = $discount + $valueTrx['transaction_product_discount'];
                if(isset($valueTrx['product']['product_variants'])){
                    foreach ($valueTrx['product']['product_variants'] as $keyVar => $valueVar) {
                        if(in_array($valueVar['product_variant_code'], ['general_size', 'general_type'])) continue;
                        $result['product_transaction'][$keyTrx]['product']['product_variants'][$keyVar]['product_variant_name']     = $valueVar['product_variant_name'];
                    }
                    $result['product_transaction'][$keyTrx]['product']['product_variants'] = array_values($result['product_transaction'][$keyTrx]['product']['product_variants']??[]);
                }
                foreach ($valueTrx['modifiers'] as $keyMod => $valueMod) {
                    $result['product_transaction'][$keyTrx]['product']['product_modifiers'][$keyMod]['product_modifier_name']   = $valueMod['text'];
                    $result['product_transaction'][$keyTrx]['product']['product_modifiers'][$keyMod]['product_modifier_qty']    = $valueMod['qty'];
                    $result['product_transaction'][$keyTrx]['product']['product_modifiers'][$keyMod]['product_modifier_price']  = MyHelper::requestNumber($valueMod['transaction_product_modifier_price'],'_CURRENCY');
                }
            }

            $result['payment_detail'][] = [
                'name'      => 'Subtotal',
                'desc'      => $quantity . ' items',
                'amount'    => (string) MyHelper::requestNumber($list['transaction_subtotal'],'_CURRENCY')
            ];

            $p = 0;
            $payment_detail_discount = [];
            $payment_detail_discount_delivery = [];

            if (!empty($list['transaction_vouchers'])) {
                foreach ($list['transaction_vouchers'] as $valueVoc) {
                    $result['promo']['code'][$p++]   = $valueVoc['deals_voucher']['voucher_code'];

                    if ($valueVoc['deals_voucher']['deal']['promo_type'] == 'Discount delivery') {
                    	$payment_detail_discount_delivery[] = [
                    		'name'          => 'Discount',
                    		// 'desc'          => $valueVoc['deals_voucher']['voucher_code'],
                    		'desc'          => 'Delivery',
                    		"is_discount"   => 1,
                    		'amount'        => (string) '-'.MyHelper::requestNumber($discount_delivery,'_CURRENCY')
                    	];
                    }else{
                    	$payment_detail_discount[] = [
                    		'name'          => 'Discount',
                    		'desc'          => $valueVoc['deals_voucher']['voucher_code'],
                    		"is_discount"   => 1,
                    		'amount'        => (string) '-'.MyHelper::requestNumber($discount,'_CURRENCY')
                    	];
                    }
                }
            }

            if (!empty($list['promo_campaign_promo_code']) && $list['promo_campaign_promo_code']['promo_campaign']['promo_type'] != 'Referral') {
                $result['promo']['code'][$p++]   = $list['promo_campaign_promo_code']['promo_code'];
                $payment_detail_discount[] = [
                    'name'          => 'Discount',
                    'desc'          => $list['promo_campaign_promo_code']['promo_code'],
                    "is_discount"   => 1,
                    'amount'        => (string) '-'.MyHelper::requestNumber($discount,'_CURRENCY')
                ];
            }

            if (!empty($list['promo_campaign_promo_code_delivery']) && $list['promo_campaign_promo_code_delivery']['promo_campaign']['promo_type'] != 'Referral') {
                $result['promo']['code'][$p++]   = $list['promo_campaign_promo_code_delivery']['promo_code'];
                $payment_detail_discount_delivery[] = [
                    'name'          => 'Delivery Discount',
                    // 'desc'          => $list['promo_campaign_promo_code_delivery']['promo_code'],
                    'desc'          => null,
                    "is_discount"   => 1,
                    'amount'        => (string) '-'.MyHelper::requestNumber($discount_delivery,'_CURRENCY')
                ];
            }

            $result['promo']['discount'] = $discount;
            $result['promo']['discount'] = MyHelper::requestNumber($discount,'_CURRENCY');

            if (!empty($list['promo_campaign_referral_transaction'])) {
            	if ($list['promo_campaign_referral_transaction']['referred_bonus_type'] == 'Cashback') {
	            	$result['promo_cashback'] = [
	            		'name' 		=> 'Referral',
	            		'code' 		=> $list['promo_campaign_promo_code']['promo_code'] ?? "",
	            		'cashback' 	=> !empty($list['promo_campaign_referral_transaction']['referred_bonus']) ? (string) number_format($list['promo_campaign_referral_transaction']['referred_bonus'],0,',','.').' points' : ""
	            		// 'cashback' 	=> MyHelper::requestNumber($list['promo_campaign_referral_transaction']['referred_bonus'],'point')
	            	];
            	}else{
            		$result['promo']['code'][$p++]   = $list['promo_campaign_promo_code']['promo_code'];
	                $payment_detail_discount[] = [
	                    'name'          => 'Discount',
	                    'desc'          => $list['promo_campaign_promo_code']['promo_code'],
	                    "is_discount"   => 1,
	                    'amount'        => (string) '-'.MyHelper::requestNumber($discount,'_CURRENCY')
	                ];
            	}
            }

            // add discount to payment detail
            if (!empty($payment_detail_discount)) {
            	foreach ($payment_detail_discount as $val) {
            		$result['payment_detail'][] = $val;
            	}
            }

            // add shipment/delivery to payment detail
            if (!empty($list['transaction_shipment']) || !empty($list['transaction_shipment_go_send']) || $list['detail']['pickup_by'] == 'Outlet') {

            	$available_delivery = config('delivery_method');
		    	$delivery_list = [];
		    	foreach ($available_delivery as $val) {
		    		$delivery_list[$val['type']] = $val['text'];
		    	}

            	switch ($list['detail']['pickup_by']) {
            		case 'GO-SEND':
            			$delivery_text = $delivery_list['GO-SEND'];
			            $delivery_text = null;
		            	$result['payment_detail'][] = [
			                'name'      => 'Delivery Fee',
			                'desc'		=> $delivery_text,
			                'amount'    => (string) MyHelper::requestNumber(($list['transaction_shipment'] ?: $list['transaction_shipment_go_send']),'_CURRENCY')
			            ];
            			break;
            		
            		case 'Outlet':
            			$delivery_text = $delivery_list['Internal Delivery'];
            			break;
            		
            		default:
            			$delivery_text = '';
            			break;
            	}
            }

            // add discount delivery to payment detail
            if (!empty($payment_detail_discount_delivery)) {
            	foreach ($payment_detail_discount_delivery as $val) {
            		$result['payment_detail'][] = $val;
            	}
            }

            if ($list['detail']['pickup_by'] == 'GO-SEND') {
                $result['trasaction_type'] = 'GO-SEND';
            } elseif ($list['detail']['pickup_by'] == 'Outlet') {
                $result['trasaction_type'] = 'Internal Delivery';
            }

            if ($list['trasaction_payment_type'] != 'Offline') {

                if ($list['detail']['pickup_by'] == 'GO-SEND') {
                    // $result['transaction_status'] = 5;
                    $result['delivery_info'] = [
                        'driver' => null,
                        'delivery_status' => '',
                        'delivery_address' => $list['transaction_pickup_go_send']['destination_address']?:'',
                        'delivery_address_note' => $list['transaction_pickup_go_send']['destination_note'] ?: '',
                        'delivery_status_code' => 0,
                        'booking_status' => 1,
                        'cancelable' => 0,
                        'go_send_order_no' => $list['transaction_pickup_go_send']['go_send_order_no']?:'',
                        'live_tracking_url' => $list['transaction_pickup_go_send']['live_tracking_url']?:'',
                        'delivery_id_name' => null
                    ];
                } elseif ($list['detail']['pickup_by'] == 'Outlet') {
                    // $result['transaction_status'] = 5;
                    $result['delivery_info'] = [
                        'driver' => null,
                        'delivery_status' => $result['transaction_status'],
                        'delivery_address' => $list['transaction_pickup_outlet']['destination_address']?:'',
                        'delivery_address_note' => $list['transaction_pickup_outlet']['destination_note'] ?: '',
                        'delivery_status_code' => $result['transaction_status_code'],
                        'cancelable' => 0,
                    ];
                }

                if ($list['transaction_payment_status'] == 'Cancelled') {
                    foreach ($list['payment'] as $key => $value) {
                        if (isset($value['reject'])) {
                            $text = 'Your transaction failed because ' . $value['reject'];
                            if (strpos($text, 'expire')) {
                                $text = 'Transaction timeout. We’re sorry but your transaction took longer than expected ​and we couldn’t process your transaction. Please try again.';
                            }
                            $result['detail']['detail_status'][] = [
                                'text'  => $text,
                                'date'  => date('d F Y H:i', strtotime($list['void_date']))
                            ];
                        }
                    }
                    $result['detail']['detail_status'][] = [
                        'text'  => 'Your order has been canceled',
                        'date'  => date('d F Y H:i', strtotime($list['void_date']))
                    ];
                } else {
                    if ($list['detail']['reject_at'] != null) {
                        if (strpos(strtolower($list['detail']['reject_reason']), 'user') !== false) {
                            $result['detail']['detail_status'][] = [
                                'text'  => 'Order canceled by user because driver not found',
                                'date'  => date('d F Y H:i', strtotime($list['detail']['reject_at'])),
                                'reason'=> $list['detail']['reject_reason']
                            ];
                        } else {
                            $reason = 'Order canceled by system because '.$list['transaction_pickup_go_send']['latest_status'] ?? '';
                            switch ($list['transaction_pickup_go_send']['latest_status'] ?? '') {
                                case 'no_driver':
                                    $reason = 'Order canceled by system because driver not found';
                                    break;
                                
                                case 'rejected':
                                    $email = MyHelper::setting('transaction_email_contact', 'value', env('MAIL_FROM_ADDRESS'));
                                    if ($request->support_html) {
                                        $email = "<a href='mailto:$email'>$email</a>";
                                    }
                                    $reason = "Sorry, our driver could not reach you, please contact us at $email";
                                    $result['transaction_status'] = 'Delivery Rejected';
                                    break;
                                
                                case 'cancelled':
                                    $reason = 'Order canceled by system because delivery canceled';
                                    break;

                                case '':
                                    $reason = 'Order canceled by system because failed book delivery';
                                    break;
                            }
                            $result['detail']['detail_status'][] = [
                                'text'  => $reason,
                                'date'  => date('d F Y H:i', strtotime($list['detail']['reject_at'])),
                                'reason'=> $list['detail']['reject_reason']
                            ];
                        }
                    }
                    if ($list['detail']['taken_by_system_at'] != null) {
                        $result['detail']['detail_status'][] = [
                        'text'  => 'Your order has been completed by system',
                        'date'  => date('d F Y H:i', strtotime($list['detail']['taken_by_system_at']))
                    ];
                    }
                    if ($list['detail']['taken_at'] != null) {
                        if ($list['detail']['pickup_by'] == 'Outlet') {
                            $result['detail']['detail_status'][] = [
                                'text'  => 'Your order has been received',
                                'date'  => date('d F Y H:i', strtotime($list['detail']['taken_at']))
                            ];
                        } elseif ($list['detail']['pickup_by'] == 'Customer') {
                            $result['detail']['detail_status'][] = [
                                'text'  => 'Your order has been picked up',
                                'date'  => date('d F Y H:i', strtotime($list['detail']['taken_at']))
                            ];
                        }
                    }
                    if ($list['detail']['ready_at'] != null) {
                        if ($list['detail']['pickup_by'] == 'Outlet') {
                            $result['detail']['detail_status'][] = [
                                'text'  => 'Your order deliver by MAXX Coffee',
                                'date'  => date('d F Y H:i', strtotime($list['detail']['ready_at']))
                            ];
                        } else {
                            $result['detail']['detail_status'][] = [
                                'text'  => 'Your order is ready ',
                                'date'  => date('d F Y H:i', strtotime($list['detail']['ready_at']))
                            ];
                        }
                    }

                    if ($list['detail']['receive_at'] != null && $list['detail']['pickup_by'] != 'GO-SEND') {
                        $result['detail']['detail_status'][] = [
                            'text'  => 'Your order is on process',
                            'date'  => date('d F Y H:i', strtotime($list['detail']['receive_at']))
                        ];
                    }

                    if ($list['detail']['pickup_by'] == 'GO-SEND' && $list['transaction_pickup_go_send'] && !$list['detail']['reject_at']) {
                        // $result['transaction_status'] = 5;
                        $result['delivery_info'] = [
                            'driver' => null,
                            'delivery_status' => '',
                            'delivery_address' => $list['transaction_pickup_go_send']['destination_address']?:'',
                            'delivery_address_note' => $list['transaction_pickup_go_send']['destination_note'] ?: '',
                            'delivery_status_code' => 0,
                            'booking_status' => $list['transaction_payment_status'] == 'Pending' ? 1 : 0,
                            'cancelable' => 1,
                            'go_send_order_no' => $list['transaction_pickup_go_send']['go_send_order_no']?:'',
                            'live_tracking_url' => $list['transaction_pickup_go_send']['live_tracking_url']?:'',
                            'delivery_id_name' => 'Gosend ID'
                        ];
                        $max_book = MyHelper::setting('booking_delivery_max_retry', 'value', 5);
                        if ($list['transaction_pickup_go_send']['go_send_id'] || $list['transaction_pickup_go_send']['retry_count'] < $max_book) {
                            $result['delivery_info']['booking_status'] = 1;
                        }
                        switch (strtolower($list['transaction_pickup_go_send']['latest_status'])) {
                            case 'finding driver':
                            case 'confirmed':
                                $result['delivery_info']['delivery_status_code'] = 1;
                                $result['delivery_info']['delivery_status'] = 'Looking for a Driver';
                                $result['transaction_status']          = 'Looking for a Driver';
                                break;
                            case 'driver allocated':
                            case 'allocated':
                                $result['delivery_info']['delivery_status_code'] = 2;
                                $result['delivery_info']['delivery_status'] = 'Driver Found';
                                $result['transaction_status']          = 'Driver Found';
                                $result['delivery_info']['driver']          = [
                                    'driver_id'         => $list['transaction_pickup_go_send']['driver_id']?:'',
                                    'driver_name'       => $list['transaction_pickup_go_send']['driver_name']?:'',
                                    'driver_phone'      => $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_whatsapp'   => env('URL_WA') . $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_photo'      => $list['transaction_pickup_go_send']['driver_photo']?:'',
                                    'vehicle_number'    => $list['transaction_pickup_go_send']['vehicle_number']?:'',
                                ];
                                break;
                            case 'enroute pickup':
                            case 'out_for_pickup':
                                $result['delivery_info']['delivery_status_code'] = 2;
                                $result['delivery_info']['delivery_status'] = 'Driver on the way to Outlet';
                                $result['transaction_status']          = 'Driver on the way to Outlet';
                                $result['delivery_info']['driver']          = [
                                    'driver_id'         => $list['transaction_pickup_go_send']['driver_id']?:'',
                                    'driver_name'       => $list['transaction_pickup_go_send']['driver_name']?:'',
                                    'driver_phone'      => $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_whatsapp'   => env('URL_WA') . $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_photo'      => $list['transaction_pickup_go_send']['driver_photo']?:'',
                                    'vehicle_number'    => $list['transaction_pickup_go_send']['vehicle_number']?:'',
                                ];
                                $result['delivery_info']['cancelable'] = 1;
                                break;
                            case 'enroute drop':
                            case 'out_for_delivery':
                                $result['delivery_info']['delivery_status_code'] = 3;
                                $result['delivery_info']['delivery_status'] = 'Driver Delivering your order';
                                $result['transaction_status']          = 'Driver Delivering your order';
                                $result['transaction_status_code']               = 3;
                                $result['delivery_info']['driver']          = [
                                    'driver_id'         => $list['transaction_pickup_go_send']['driver_id']?:'',
                                    'driver_name'       => $list['transaction_pickup_go_send']['driver_name']?:'',
                                    'driver_phone'      => $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_whatsapp'   => env('URL_WA') . $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_photo'      => $list['transaction_pickup_go_send']['driver_photo']?:'',
                                    'vehicle_number'    => $list['transaction_pickup_go_send']['vehicle_number']?:'',
                                ];
                                $result['delivery_info']['cancelable'] = 0;
                                break;
                            case 'completed':
                            case 'delivered':
                                $result['delivery_info']['delivery_status_code'] = 4;
                                $result['transaction_status_code'] = 1;
                                $result['transaction_status']          = 'Order Completed';
                                $result['delivery_info']['delivery_status'] = 'Order Completed';
                                $result['delivery_info']['driver']          = [
                                    'driver_id'         => $list['transaction_pickup_go_send']['driver_id']?:'',
                                    'driver_name'       => $list['transaction_pickup_go_send']['driver_name']?:'',
                                    'driver_phone'      => $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_whatsapp'   => env('URL_WA') . $list['transaction_pickup_go_send']['driver_phone']?:'',
                                    'driver_photo'      => $list['transaction_pickup_go_send']['driver_photo']?:'',
                                    'vehicle_number'    => $list['transaction_pickup_go_send']['vehicle_number']?:'',
                                ];
                                $url = $request->url();
                                if(!stristr($url, 'transaction/be/detail')){
                                    $result['delivery_info']['go_send_order_no'] = null;
                                }
                                $result['delivery_info']['cancelable'] = 0;
                                $result['delivery_info']['delivery_id_name'] = null;
                                break;
                            case 'cancelled':
                                $result['delivery_info']['delivery_status_code'] = 0;
                                $result['delivery_info']['booking_status'] = 0;
                                $result['transaction_status']         = 'Delivery Cancelled';
                                $result['delivery_info']['delivery_status'] = 'Delivery Cancelled';
                                $result['delivery_info']['cancelable']     = 0;
                                break;
                            case 'rejected':
                                $result['delivery_info']['booking_status'] = 1;
                                break;
                            case 'on_hold':
                                $result['delivery_info']['delivery_status_code'] = 5;
                                $result['delivery_info']['booking_status'] = 1;
                                $result['transaction_status']         = 'On Hold';
                                $result['delivery_info']['delivery_status'] = 'On Hold';
                                $result['delivery_info']['cancelable']     = 0;
                                break;
                            case 'driver not found':
                            case 'no_driver':
                                $result['delivery_info']['delivery_status_code'] = 0;
                                $result['delivery_info']['booking_status']  = 0;
                                $result['transaction_status']          = 'Driver not Found';
                                $result['delivery_info']['delivery_status'] = 'Driver not Found';
                                $result['delivery_info']['cancelable']      = 0;
                                break;
                        }
                    }
                    if ($list['transaction_pickup_go_send'] && $list['transaction_pickup_go_send']['transaction_pickup_update']) {
                        foreach ($list['transaction_pickup_go_send']['transaction_pickup_update'] as $valueGosend) {
                            switch (strtolower($valueGosend['status'])) {
                                case 'finding driver':
                                case 'confirmed':
                                    $result['detail']['detail_status'][] = [
                                        'text'  => 'Looking for a Driver',
                                        'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                    ];
                                    break;
                                // case 'driver allocated':
                                // case 'allocated':
                                //     $statusOrder[] = [
                                //         'text'  => 'Driver ditemukan',
                                //         'date'  => $valueGosend['created_at']
                                //     ];
                                //     break;
                                // case 'enroute pickup':
                                case 'out_for_pickup':
                                    $result['detail']['detail_status'][] = [
                                        'text'  => 'Driver on the way to Outlet',
                                        'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                    ];
                                    break;
                                case 'enroute drop':
                                case 'out_for_delivery':
                                    $result['detail']['detail_status'][] = [
                                        'text'  => 'Driver Delivering your order',
                                        'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                    ];
                                    break;
                                case 'completed':
                                case 'delivered':
                                    $result['detail']['detail_status'][] = [
                                        'text'  => 'Your order has been received',
                                        'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                    ];
                                    break;
                                case 'on_hold':
                                    $result['detail']['detail_status'][] = [
                                        'text'  => 'Delivery On Hold because Driver could not reach the destination address',
                                        'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                    ];
                                    break;
                                // removed because the reasons already explained by cancel reject_at messages
                                // case 'rejected':
                                //     $result['detail']['detail_status'][] = [
                                //         'text'  => 'Order canceled because Driver was unable to reach the destination address',
                                //         'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                //     ];
                                //     break;
                                case 'cancelled':
                                    $result['detail']['detail_status'][] = [
                                        'text'  => 'Delivery Cancelled',
                                        'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                    ];
                                    break;
                                case 'driver not found':
                                case 'no_driver':
                                    $result['detail']['detail_status'][] = [
                                        'text'  => 'Driver not Found',
                                        'date'  => date('d F Y H:i', strtotime($valueGosend['created_at']))
                                    ];
                                    break;
                            }
                        }
                    }
                }

                if ($list['transaction_payment_status'] == 'Completed' && $list['detail']['pickup_by'] != 'GO-SEND' && !($list['detail']['receive_at'])) {
                        $result['detail']['detail_status'][] = [
                        'text'  => 'Your order awaits outlet confirmation',
                        'date'  => date('d F Y H:i', strtotime($list['completed_at'])),
                    ];
                }

                $result['detail']['detail_status'][] = [
                    'text'  => 'Your order awaits payment confirmation',
                    'date'  => date('d F Y H:i', strtotime($list['created_at']))
                ];
            }

            usort($result['detail']['detail_status'], function($a, $b) {
                return strtotime($b['date']) <=> strtotime($a['date']);
            });

            if(!isset($list['payment'])){
                $result['transaction_payment'] = [];
            }else{
                foreach ($list['payment'] as $key => $value) {
                    if ($value['name'] == 'Balance') {
                        $result['transaction_payment'][$key] = [
                            'name'      => (env('POINT_NAME')) ? env('POINT_NAME') : $value['name'],
                            'is_balance'=> 1,
                            'amount'    => MyHelper::requestNumber($value['amount'],'_POINT')
                        ];
                    } else {
                        $result['transaction_payment'][$key] = [
                            'name'      => $value['name'],
                            'amount'    => MyHelper::requestNumber($value['amount'],'_CURRENCY')
                        ];
                        if($value['name'] == 'QRIS Nobu'){
                            $result['transaction_payment'][$key]['qris'] = $value['qris'];
                        }
                    }
                }
            }

            return response()->json(MyHelper::checkGet($result));
        } else {
            $list = DealsUser::with('outlet', 'dealVoucher.deal')->where('id_deals_user', $id)->orderBy('claimed_at', 'DESC')->first();

            if (empty($list)) {
                return response()->json(MyHelper::checkGet($list));
            }

            $result = [
                'trasaction_type'               => 'voucher',
                'id_deals_user'                 => $list['id_deals_user'],
                'deals_receipt_number'          => (!is_null($list['deals_receipt_number'])) ? $list['deals_receipt_number'] : implode('', [strtotime($list['claimed_at']), $list['id_deals_user']]),
                'date'                          => date('d M Y H:i', strtotime($list['claimed_at'])),
                'voucher_price_cash'            => MyHelper::requestNumber($list['voucher_price_cash'],'_CURRENCY'),
                'deals_voucher'                 => $list['dealVoucher']['deal']['deals_title'],
                'payment_methods'               => $list['payment_method']
            ];
            $result['payment'] = [];
            if (!is_null($list['balance_nominal'])) {
                $result['payment'][] = [
                    'name'      => (env('POINT_NAME')) ? env('POINT_NAME') : 'Balance',
                    'is_balance'=> 1,
                    'amount'    => MyHelper::requestNumber($list['balance_nominal'],'_POINT'),
                ];
            }
            switch ($list['payment_method']) {
                case 'Manual':
                    $payment = DealsPaymentManual::where('id_deals_user', $id)->first();
                    $result['payment'][] = [
                        'name'      => 'Manual',
                        'amount'    =>  MyHelper::requestNumber($payment->payment_nominal,'_CURRENCY')
                    ];
                    break;
                case 'Midtrans':
                    $payment = DealsPaymentMidtran::where('id_deals_user', $id)->first();
                    $result['payment'][] = [
                        'name'      => strtoupper(str_replace('_', ' ', $payment->payment_type)).' '.strtoupper($payment->bank),
                        'amount'    => MyHelper::requestNumber($payment->gross_amount,'_CURRENCY')
                    ];
                    break;
                case 'OVO':
                    $payment = DealsPaymentOvo::where('id_deals_user', $id)->first();
                    $result['payment'][] = [
                        'name'      => 'OVO',
                        'amount'    =>  MyHelper::requestNumber($payment->amount,'_CURRENCY')
                    ];
                    break;
                case 'Ipay88':
                    $payment = DealsPaymentIpay88::where('id_deals_user', $id)->first();
                    $result['payment'][] = [
                        // 'name'      => $payment->payment_method?:'Credit / Debit Card',
                        'name'      => 'CREDIT/DEBIT CARD',
                        'amount'    =>  MyHelper::requestNumber($payment->amount / 100,'_CURRENCY')
                    ];
                    break;
                case 'Shopeepay':
                    $payment = DealsPaymentShopeePay::where('id_deals_user', $id)->first();
                    $result['payment'][] = [
                        'name'      => 'ShopeePay',
                        'amount'    =>  MyHelper::requestNumber($payment->amount / 100,'_CURRENCY')
                    ];
                    break;
            }
            return response()->json(MyHelper::checkGet($result));
        }
    }

    public function transactionDetailTrx(Request $request) {
        $trid = $request->json('id_transaction');
        $rn = $request->json('request_number');
        $trx = Transaction::select('id_transaction','id_outlet')->where([
            'id_transaction' => $trid,
            'id_user' => $request->user()->id
        ])->first();
        if(!$trx){
            return [
                'status'=>'fail',
                'messages'=>['Transaction not found']
            ];
        }
        $id_transaction = $trx['id_transaction'];
        $pt = TransactionProduct::select(DB::raw('
            0 as id_custom,
            transaction_products.id_product,
            id_transaction_product,
            id_brand,
            transaction_products.id_outlet,
            outlets.outlet_code,
            transaction_product_qty as qty,
            product_prices.product_price,
            products.product_name,
            products.product_code,
            transaction_products.transaction_product_note as note
            '))
        ->join('products','products.id_product','=','transaction_products.id_product')
        ->join('product_prices','product_prices.id_product','=','products.id_product')
        ->join('outlets','outlets.id_outlet','=','transaction_products.id_outlet')
        ->whereRaw('product_prices.id_outlet = transaction_products.id_outlet')
        ->where(['id_transaction'=>$id_transaction])
        ->with(['modifiers'=>function($query){
                    $query->select('id_transaction_product','product_modifiers.code','transaction_product_modifiers.id_product_modifier','qty','product_modifiers.text')->join('product_modifiers','product_modifiers.id_product_modifier','=','transaction_product_modifiers.id_product_modifier');
                }])->first()->toArray();
        if(!$pt){
            return MyHelper::checkGet($pt);
        }
        $id_outlet = $pt['id_outlet'];
        $total_mod_price = 0;
        foreach ($pt['modifiers'] as &$modifier) {
            $price = ProductModifierPrice::select('product_modifier_price')->where([
                'id_product_modifier'=>$modifier['id_product_modifier'],
                'id_outlet' => $id_outlet
            ])->pluck('product_modifier_price')->first();
            $total_mod_price+=$price*$modifier['qty'];
            $modifier['product_modifier_price'] = MyHelper::requestNumber($price,$rn);
        }
        $pt['product_price_total'] = MyHelper::requestNumber($total_mod_price + $pt['product_price'],$rn);
        $pt['product_price'] = MyHelper::requestNumber($pt['product_price'],$rn);
        $pt['note'] = $pt['note']?:'';
        return MyHelper::checkGet($pt);
    }

    public function transactionPointDetail(Request $request) {
        $id     = $request->json('id');
        $select = [];
        $data   = LogPoint::where('id_log_point', $id)->first();

        if ($data['source'] == 'Transaction') {
            $select = Transaction::with('outlet')->where('id_transaction', $data['id_reference'])->first();

            $data['date'] = $select['transaction_date'];
            $data['type'] = 'trx';
            $data['outlet'] = $select['outlet']['outlet_name'];
            if ($select['trasaction_type'] == 'Offline') {
                $data['online'] = 0;
            } else {
                $data['online'] = 1;
            }

        } else {
            $select = DealsUser::with('dealVoucher.deal')->where('id_deals_user', $data['id_reference'])->first();
            $data['type']   = 'voucher';
            $data['date']   = date('Y-m-d H:i:s', strtotime($select['claimed_at']));
            $data['outlet'] = $select['outlet']['outlet_name'];
            $data['online'] = 1;
        }

        $data['detail'] = $select;
        return response()->json(MyHelper::checkGet($data));
    }

    public function transactionBalanceDetail(Request $request) {
        $id     = $request->json('id');
        $select = [];
        $data   = LogBalance::where('id_log_balance', $id)->first();
        // dd($data);
        if ($data['source'] == 'Transaction' || $data['source'] == 'Rejected Order Point' || $data['source'] == 'Rejected Order' || strpos($data['source'], 'Rejected Order') !== false) {
            $select = Transaction::select(DB::raw('transactions.*,transaction_pickups.pickup_by,sum(transaction_products.transaction_product_qty) item_total'))
                ->leftJoin('transaction_pickups','transactions.id_transaction','=','transaction_pickups.id_transaction')
                ->leftJoin('transaction_products','transactions.id_transaction','=','transaction_products.id_transaction')
                ->with('outlet')
                ->where('transactions.id_transaction', $data['id_reference'])
                ->groupBy('transactions.id_transaction')
                ->first();

            $data['date'] = $select['transaction_date'];
            $data['type'] = 'trx';
            $data['item_total'] = $select['item_total'];
            $data['outlet'] = $select['outlet']['outlet_name'];
            if ($select['trasaction_type'] == 'Offline') {
                $data['online'] = 0;
            } else {
                $data['online'] = 1;
            }
            $data['detail'] = $select;

            $result = [
                'type'                          => $data['type'],
                'id_log_balance'                => $data['id_log_balance'],
                'id_transaction'                => $data['detail']['id_transaction'],
                'transaction_receipt_number'    => $data['detail']['transaction_receipt_number'],
                'transaction_date'              => date('d M Y H:i', strtotime($data['detail']['transaction_date'])),
                'balance'                       => MyHelper::requestNumber($data['balance'], '_POINT'),
                'transaction_grandtotal'        => MyHelper::requestNumber($data['detail']['transaction_grandtotal'], '_CURRENCY'),
                'transaction_cashback_earned'   => MyHelper::requestNumber($data['detail']['transaction_cashback_earned'], '_POINT'),
                'name'                          => $data['detail']['outlet']['outlet_name'],
                'title'                         => 'Total Payment',
                'pickup_by'                     => $data['detail']['pickup_by'],
                'transaction_type'              => $data['detail']['pickup_by'] == 'Customer' ? 'Pickup Order' : 'Delivery'
            ];
        } else {
            $select = DealsUser::with('dealVoucher.deal')->where('id_deals_user', $data['id_reference'])->first();
            $data['type']   = 'voucher';
            $data['date']   = date('Y-m-d H:i:s', strtotime($select['claimed_at']));
            $data['outlet'] = $select['outlet']['outlet_name'];
            $data['online'] = 1;
            $data['detail'] = $select;

            $usedAt = '';
            $status = 'UNUSED';
            if($data['detail']['used_at'] != null){
                $usedAt = date('d M Y H:i', strtotime($data['detail']['used_at']));
                $status = 'USED';
            }

            $price = 0;
            if($data['detail']['voucher_price_cash'] != NULL){
                $price = MyHelper::requestNumber($data['detail']['voucher_price_cash'],'_CURRENCY');
            }elseif($data['detail']['voucher_price_point'] != NULL){
                $price = MyHelper::requestNumber($data['detail']['voucher_price_point'],'_POINT').' points';
            }

            $result = [
                'type'                          => $data['type'],
                'id_log_balance'                => $data['id_log_balance'],
                'id_deals_user'                 => $data['detail']['id_deals_user'],
                'status'                        => $status,
                'used_at'                       => $usedAt,
                'transaction_receipt_number'    => (!is_null($data['detail']['deals_receipt_number'])) ? $data['detail']['deals_receipt_number'] : implode('', [strtotime($data['date']), $data['detail']['id_deals_user']]),
                'transaction_date'              => date('d M Y H:i', strtotime($data['date'])),
                'balance'                       => MyHelper::requestNumber($data['balance'], '_POINT'),
                'transaction_grandtotal'        => $price,
                'transaction_cashback_earned'   => null,
                'name'                          => 'Buy Voucher',
                'title'                         => $data['detail']['dealVoucher']['deal']['deals_title']
            ];
        }

        return response()->json(MyHelper::checkGet($result));
    }

    public function setting($value) {
        $setting = Setting::where('key', $value)->first();

        if (empty($setting->value)) {
            return response()->json(['Setting Not Found']);
        }

        return $setting->value;
    }

    public function transactionHistory(TransactionHistory $request) {
		if($request->json('phone') == "") {
			$data = $request->user();
			$id   = $data['id'];
		} else {
			$user = User::where('phone', $request->json('phone'))->get->first();
			$id = $user['id'];
		}

        $transaction = Transaction::where('id_user', $id)->with('user', 'productTransaction', 'user.city', 'user.city.province', 'productTransaction.product', 'productTransaction.product.category', 'productTransaction.product.photos', 'productTransaction.product.discount')->get()->toArray();

        return response()->json(MyHelper::checkGet($transaction));
    }

    public function getProvince(GetProvince $request) {
        $id_province = $request->json('id_province');
        if (isset($id_province)) {
            $province = Province::where('id_province', $id_province)->orderBy('id_province', 'ASC');
        } else {
            $province = Province::orderBy('id_province', 'ASC');
        }

        $province = $province->with('cities')->get();

        return response()->json(MyHelper::checkGet($province));

    }

    public function getCity(GetCity $request) {
        $id_city = $request->json('id_city');
        if (isset($id_city)) {
            $city = City::where('id_city', $id_city)->orderBy('id_city', 'ASC');
        } else {
            $city = City::orderBy('id_city', 'ASC');
        }

        $city = $city->with('province')->get();

        return response()->json(MyHelper::checkGet($city));

    }

    public function getSubdistrict(GetSub $request) {
        $id_city = $request->json('id_city');
        $id_subdistrict = $request->json('id_subdistrict');

        $subdistrict = MyHelper::urlTransaction('https://pro.rajaongkir.com/api/subdistrict?city='.$id_city.'&id='.$id_subdistrict, 'GET', '', 'application/json');

        if ($subdistrict->rajaongkir->status->code == 200) {
            $subdistrict = $subdistrict->rajaongkir->results;
        }

        return response()->json(MyHelper::checkGet($subdistrict));
    }

    public function getAddress(GetAddress $request) {
        $id = $request->user()->id;

        if (!$id) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['User Not Found']
            ]);
        }

        $address = UserAddress::select('id_user_address','name','short_address','address','type','latitude','longitude','description', 'favorite', 'last_used', 'updated_at')->where('id_user', $id)->orderBy('last_used', 'DESC')->orderBy('updated_at', 'DESC')->get()->toArray();
        $result = [
            'favorite' => [
                'home' => null,
                'work' => null,
                'others' => [],
            ],
            'recently' => [],
        ];
        foreach ($address as $key => &$adr) {
            if ($adr['favorite']) {
                switch (strtolower($adr['type'])) {
                    case 'home':
                        $result['favorite']['home'] = &$adr;
                        break;

                    case 'work':
                        $result['favorite']['work'] = &$adr;
                        break;

                    default:
                        $result['favorite']['others'][] = &$adr;
                        break;
                }                
            }
            if (($adr['last_used'] || !$adr['favorite'])) {
                $result['recently'][] = &$adr;
            }
        }

        usort($result['favorite']['others'], function($a, $b) {
            return $a['id_user_address'] <=> $b['id_user_address'];
        });

        usort($result['recently'], function($a, $b) {
            return ($a['last_used'] ?? $a['updated_at']) <=> ($b['last_used'] ?? $b['updated_at']);
        });

        foreach ($address as &$adres) {
            unset($adres['last_used']);
            unset($adres['updated_at']);
        }
        $result['recently'] = array_splice($result['recently'], 0, 3);

        return response()->json(MyHelper::checkGet($result));
    }

    public function getRecentlyAddress(Request $request)
    {
        $id = $request->user()->id;
        $address = UserAddress::select('id_user_address','name','short_address','address','type','latitude','longitude','description', 'favorite', 'last_used', 'updated_at')
            ->where('id_user', $id)
            ->orderBy('last_used', 'DESC')
            ->orderBy('updated_at', 'DESC');

        if ($request->page) {
            $address = $address->paginate();
        } else {
            $address = $address->get();
        }

        return MyHelper::checkGet($address);
    }

    public function getNearbyAddress(GetNearbyAddress $request) {
        $id = $request->user()->id;
        $distance = Setting::select('value')->where('key','history_address_max_distance')->pluck('value')->first()?:50;
        $maxmin = MyHelper::getRadius($request->json('latitude'),$request->json('longitude'),$distance);
        $latitude = $request->json('latitude');
        $longitude = $request->json('longitude');

        if (!$latitude && !$longitude) {
            return [
                'status' => 'fail',
                'messages' => ['Make sure your phone\'s location settings are connected']
            ];
        }

        // get place from google maps . max 20
        $key_maps = env('GMAPS_PLACE_KEY');
        if (env('GMAPS_PLACE_KEY_TOTAL')) {
            $weekNow = date('W') % env('GMAPS_PLACE_KEY_TOTAL');
            $key_maps = env('GMAPS_PLACE_KEY'.$weekNow, $key_maps);
        }
        $param = [
            'key'=>$key_maps,
            'location'=>sprintf('%s,%s',$request->json('latitude'),$request->json('longitude')),
            'rankby'=>'distance'
        ];
        if($request->json('keyword')){
            $param['keyword'] = $request->json('keyword');
        }
        $gmaps = MyHelper::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json?'.http_build_query($param));

        $error_msg = 'Location is not available. Please try again later.';

        if($gmaps['status'] === 'OK'){
            $gmaps = $gmaps['results'];
            MyHelper::sendGmapsData($gmaps);
        } elseif ($gmaps['status'] == 'ZERO_RESULTS' && $request->json('keyword')) {
            $error_msg = 'The location you are looking for is not available. Try to search with other keywords.';
            $gmaps = [];
        } else {
            $gmaps = [];
        }

        $maxmin = MyHelper::getRadius($latitude,$longitude,$distance);
        $user_address = UserAddress::select('id_user_address','short_address','address','latitude','longitude','description','favorite')->where('id_user',$id)
            ->whereBetween('latitude',[$maxmin['latitude']['min'],$maxmin['latitude']['max']])
            ->whereBetween('longitude',[$maxmin['longitude']['min'],$maxmin['longitude']['max']])
            ->take(10);

        if($keyword = $request->json('keyword')){
            $user_address->where(function($query) use ($keyword) {
                $query->where('name','like','%'.$keyword.'%');
                $query->orWhere('address','like','%'.$keyword.'%');
                $query->orWhere('short_address','like','%'.$keyword.'%');
            });
        }

        $user_address = $user_address->get()->toArray();

        $saved = array_map(function($i){
            return [
                'latitude' => $i['latitude'],
                'longitude' => $i['longitude']
            ];
        },$user_address);

        foreach ($gmaps as $key => &$gmap){
            $coor = [
                'latitude' => number_format($gmap['geometry']['location']['lat'],8),
                'longitude' => number_format($gmap['geometry']['location']['lng'],8)
            ];
            if(in_array($coor, $saved)){
                unset($gmaps[$key]);
            }
            $gmap = [
                'id_user_address' => 0,
                'short_address' => $gmap['name'],
                'address' => $gmap['vicinity']??'',
                'latitude' => $coor['latitude'],
                'longitude' => $coor['longitude'],
                'description' => '',
                'favorite' => 0
            ];
        }

        // mix history and gmaps
        $user_address = array_merge($user_address,$gmaps);

        // reorder based on distance
        usort($user_address,function(&$a,&$b) use ($latitude,$longitude){
            return MyHelper::count_distance($latitude,$longitude,$a['latitude'],$a['longitude']) <=> MyHelper::count_distance($latitude,$longitude,$b['latitude'],$b['longitude']);
        });

        $selected_address = null;
        foreach ($user_address as $key => $addr) {
            if ($addr['favorite']) {
                $selected_address = $addr;
                break;
            }
            if ($addr['id_user_address']) {
                $selected_address = $addr;
                continue;
            }
            if ($key == 0) {
                $selected_address = $addr;
            }
        }

        if(!$selected_address){
            $selected_address = $user_address[0]??null;
        }
        // apply limit;
        // $max_item = Setting::select('value')->where('key','history_address_max_item')->pluck('value')->first()?:10;
        // $user_address = array_splice($user_address,0,$max_item);
        $result = [];
        if($user_address){
            $result = [
                'default' => $selected_address,
                'nearby' => $user_address
            ];
        }
        return response()->json(MyHelper::checkGet($result, $error_msg));
    }

    public function getDefaultAddress (GetNearbyAddress $request) {
        $id = $request->user()->id;
        $distance = Setting::select('value')->where('key','history_address_max_distance')->pluck('value')->first()?:50;
        $maxmin = MyHelper::getRadius($request->json('latitude'),$request->json('longitude'),$distance);
        $latitude = (float) $request->json('latitude');
        $longitude = (float) $request->json('longitude');

        $maxmin = MyHelper::getRadius($latitude,$longitude,$distance);
        $user_address = UserAddress::select('id_user_address','short_address','address','latitude','longitude','description','favorite')
            ->where('id_user',$id)
            ->whereNotNull('last_used')
            ->orderBy('last_used', 'desc')
            ->first();

        if (!$user_address) {
            $user_address = UserAddress::select('name', 'id_user_address','short_address','address','latitude','longitude','description','favorite')
                ->where('id_user',$id)
                ->orderByRaw("POW(latitude - $latitude, 2) + POW(longitude - $longitude, 2)")
                ->first();
        }

        // apply limit;
        // $max_item = Setting::select('value')->where('key','history_address_max_item')->pluck('value')->first()?:10;
        // $user_address = array_splice($user_address,0,$max_item);
        $result = [];
        if($user_address){
            $result = [
                'default' => $user_address
            ];
        }
        return response()->json(MyHelper::checkGet($result));
    }

    public function detailAddress(GetAddress $request) {
        $id = $request->user()->id;

        $address = UserAddress::where(['id_user'=> $id,'id_user_address'=>$request->id_user_address])->orderBy('id_user_address', 'DESC')->get()->toArray();
        return response()->json(MyHelper::checkGet($address));
    }

    public function addAddress(AddAddress $request) {
        $post = $request->json()->all();

        $data['id_user'] = $request->user()->id;
        $data['name']        = isset($post['name']) ? $post['name'] : $post['short_address'];
        $data['short_address'] = $post['short_address'] ?? null;
        $data['address']     = isset($post['address']) ? $post['address'] : null;
        $data['description'] = isset($post['description']) ? $post['description'] : null;
        $data['latitude'] = number_format($post['latitude'],8);
        $data['longitude'] = number_format($post['longitude'],8);
        $type = ucfirst($post['type'] ?? 'Other');
        $data['name'] = $type != 'Other'?$type:$data['name'];
        $exists = UserAddress::where('id_user',$request->user()->id)
            ->where('name',$data['name'])
            ->where('favorite',1)
            ->where(function($q) use ($type){
                $q->where('type',$type);
                if($type == 'Other'){
                    $q->orWhereNull('type');
                }
            })
            ->exists();
        if($exists){
            return ['status'=>'fail','messages'=>['Alamat dengan nama yang sama sudah ada']];
        }
        if(in_array($type, ['Home','Work'])){
            UserAddress::where('type',$type)->delete();
        }
        $toMatch = $data;
        unset($toMatch['name']);
        $found = UserAddress::where($toMatch+['type'=>$type])->first();
        if($found){
            if($found->favorite){
                return ['status'=>'fail','messages'=>['Alamat sudah disimpan sebagai '.(in_array($found->type,['Work','Home'])?$found->type:$found->name)]];
            }
            $found->update([
                'name' => $data['name'],
                'type' => $type?:$found->type,
                'favorite' => 1,
            ]);
        }else{
            $data['type'] = $type;
            $data['favorite'] = 1;
            $found = UserAddress::create($data);
        }

        return response()->json(MyHelper::checkCreate($found));
    }

    public function updateAddress (UpdateAddress $request) {
        $post = $request->json()->all();
        $data['id_user'] = $request->user()->id;

        if (empty($data['id_user'])) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['User not found']
            ]);
        }

        $data['name']        = isset($post['name']) ? $post['name'] : null;
        $data['address']     = isset($post['address']) ? $post['address'] : null;
        $data['short_address'] = $post['short_address'] ?? null;
        $data['description'] = isset($post['description']) ? $post['description'] : null;
        $data['latitude'] = $post['latitude']??null;
        $data['longitude'] = $post['longitude']??null;
        $type = ($post['type']??null)?ucfirst($post['type']):null;
        if($type){
            UserAddress::where('type',$type)->update(['type'=>null]);
        }
        $data['type'] = $type;
        $data['favorite'] = 1;

        $update = UserAddress::where('id_user_address', $post['id_user_address'])->update($data);
        return response()->json(MyHelper::checkUpdate($update));
    }

    public function deleteAddress (DeleteAddress $request) {
        $id = $request->json('id_user_address');

        $check = UserAddress::where('id_user_address', $id)->first();
        if (empty($check)) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Address not found']
            ]);
        }

        $check->delete();

        return response()->json(MyHelper::checkDelete($check));
    }

    public function getCourier(Request $request) {
        $courier = Courier::orderBy('id_courier', 'ASC')->get();

        return response()->json(MyHelper::checkGet($courier));
    }

    public function getShippingFee(TransactionShipping $request) {
        $post = $request->json()->all();

        if (isset($post['from'])) {
            $from = $post['from'];
        }

        if (isset($post['fromType'])) {
            $fromType = $post['fromType'];
        }

        if (isset($post['to'])) {
            $to = $post['to'];
        }

        if (isset($post['toType'])) {
            $toType = $post['toType'];
        }

        if (isset($post['weight'])) {
            $weight = $post['weight'];
        }

        if (isset($post['courier'])) {
            $courier = $post['courier'];
        }

        $data = "origin=".$from."&originType=".$fromType."&destination=".$to."&destinationType=".$toType."&weight=".$weight."&courier=".$courier;

        $shiping = MyHelper::urlTransaction('http://pro.rajaongkir.com/api/cost', 'POST', $data, 'application/x-www-form-urlencoded');

        if (isset($shiping->rajaongkir->status->code) && $shiping->rajaongkir->status->code == 200) {
            if (!empty($shiping->rajaongkir->results[0]->costs)) {
                $data = [
                    'status'    => 'success',
                    'result'    => $shiping->rajaongkir->results[0]->costs
                ];
            } else {
                $data = [
                    'status'      => 'empty',
                    'messages'    => ['Maaf, pengiriman ke kota tersebut belum tersedia']
                ];
            }

        } elseif (isset($shiping->rajaongkir->status->code) && $shiping->rajaongkir->status->code == 400) {
            $data = [
                'status'    => 'fail',
                'messages'    => [$shiping->rajaongkir->status->description]
            ];
        } else {
            $data = [
                'status'    => 'error',
                'messages'    => ['Data invalid!!']
            ];
        }

        return response()->json($data);
    }

    public function transactionVoid(Request $request) {
        $id = $request->json('transaction_receipt_number');

        $transaction = Transaction::where('transaction_receipt_number', $id)->first();
        if (empty($transaction)) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Transaction not found !!']
            ]);
        }

        MyHelper::updateFlagTransactionOnline($transaction, 'cancel');

        $transaction->void_date = date('Y-m-d H:i:s');
        $transaction->save();

        if (!$transaction) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Void transaction failure !!']
            ]);
        }

        return response()->json([
            'status'    => 'success',
            'messages'  => ['Void transaction success']
        ]);
    }

    public function transactionFinish(Request $request) {
        $result = $request->input('result_data');
        $result = json_decode($result);
        echo $result->status_message . '<br>';
        echo 'RESULT <br><pre>';
        var_dump($result);
        echo '</pre>' ;
    }

    public function transactionApprove(Request $request) {
        $json_result = file_get_contents('php://input');
        $result = json_decode($json_result);

        $url = 'https://api.sandbox.midtrans.com/v2/'.$result->order_id.'/status';
    }

    public function transactionCancel(Request $request) {
        return 'cancel';
    }

    public function transactionError(Request $request) {
        return 'error';
    }

    public function transactionNotif(Request $request) {
        $json_result = file_get_contents('php://input');
        $result = json_decode($json_result);

        DB::beginTransaction();
        $checkTransaction = Transaction::where('transaction_receipt_number', $result->order_id)->first();

        if (!$checkTransaction) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Receipt number not available']
            ]);
        }

        if (count($checkTransaction) > 0) {
            $url = 'https://api.sandbox.midtrans.com/v2/'.$result->order_id.'/status';

            $getStatus = $this->getToken(false, $url, false);

            if ($getStatus->status_code != 200) {
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Cannot access this transaction']
                ]);
            }

            if (!empty($getStatus)) {
                $masked_card        = isset($getStatus['masked_card']) ? $getStatus['masked_card'] : null;
                $approval_code      = isset($getStatus['approval_code']) ? $getStatus['approval_code'] : null;
                $bank               = isset($getStatus['bank']) ? $getStatus['bank'] : null;
                $eci                = isset($getStatus['eci']) ? $getStatus['eci'] : null;
                $transaction_time   = isset($getStatus['transaction_time']) ? $getStatus['transaction_time'] : null;
                $payment_type       = isset($getStatus['payment_type']) ? $getStatus['payment_type'] : null;
                $signature_key      = isset($getStatus['signature_key']) ? $getStatus['signature_key'] : null;
                $status_code        = isset($getStatus['status_code']) ? $getStatus['status_code'] : null;
                $vt_transaction_id  = isset($getStatus['vt_transaction_id']) ? $getStatus['vt_transaction_id'] : null;
                $transaction_status = isset($getStatus['transaction_status']) ? $getStatus['transaction_status'] : null;
                $fraud_status       = isset($getStatus['fraud_status']) ? $getStatus['fraud_status'] : null;
                $status_message     = isset($getStatus['status_message']) ? $getStatus['status_message'] : null;

                if ($getStatus->status_code == 200) {
                    if ($transaction_status == 'capture') {
                        $checkTransaction->transaction_payment_status = 'Success';

                        if (!empty($checkTransaction->id_user)) {
                            $dataPoint = [
                                'id_user'      => $checkTransaction->id_user,
                                'point'        => $checkTransaction->transaction_point_earned,
                                'id_reference' => $checkTransaction->id_transaction,
                                'source'       => 'transaction'
                            ];

                            $insertPoint = PointLog::create($dataPoint);

                            if (!$insertPoint) {
                                DB::rollBack();
                                return response()->json([
                                    'status'    => 'fail',
                                    'messages'  => ['insert point failed']
                                ]);
                            }
                        }
                    } else {
                        $checkTransaction->transaction_payment_status = ucwords($transaction_status);
                    }

                    $checkTransaction->transaction_payment_method = $payment_type;
                    $checkTransaction->save();

                    if (!$checkTransaction) {
                        DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'  => ['Update status payment failed']
                        ]);
                    }
                }


                $dataPayment = [
                    'id_transaction'     => $checkTransaction->id_transaction,
                    'masked_card'        => $masked_card,
                    'approval_code'      => $approval_code,
                    'bank'               => $bank,
                    'eci'                => $eci,
                    'transaction_time'   => $transaction_time,
                    'gross_amount'       => $getStatus->gross_amount,
                    'order_id'           => $getStatus->order_id,
                    'payment_type'       => $payment_type,
                    'signature_key'      => $signature_key,
                    'status_code'        => $status_code,
                    'vt_transaction_id'  => $vt_transaction_id,
                    'transaction_status' => $transaction_status,
                    'fraud_status'       => $fraud_status,
                    'status_message'     => $status_message,
                ];

                $insertPayment = TransactionPayment::create($dataPayment);

                if (!$insertPayment) {
                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Transaction payment cannot be create']
                    ]);
                }

                DB::commit();
                return response()->json([
                    'status'    => 'success',
                    'result'    => $dataPayment
                ]);

            }
        }
    }

    public function sendTransaction($data) {
        $tes = MyHelper::curlData('http://localhost/natasha-api/public/api/transaction/tes2', $data);
    }

    public function testing() {
        $testing = MyHelper::logCount('089674657270', 'point');
        return $testing;
    }

    public function insertUserTrxProduct($data){
        foreach ($data as $key => $value) {
            # code...
            $check = UserTrxProduct::where('id_user', $value['id_user'])->where('id_product', $value['id_product'])->first();

            if(empty($check)){
                $insertData = UserTrxProduct::create($value);
            }else{
                $value['product_qty'] = $check->product_qty + $value['product_qty'];
                $insertData = $check->update($value);
            }

            if(!$insertData){
                return 'fail';
            }
        }
        return 'success';
    }

    public function shippingCostGoSend(ShippingGosend $request){
        $post = $request->json()->all();

        $outlet = Outlet::find($post['id_outlet']);
        if(!$outlet){
            return response()->json(['status' => 'fail', 'messages' => ['Outlet not found.']]);
        }

        $origin['latitude'] = $outlet['latitude'];
        $origin['longitude'] = $outlet['longitude'];
        $shipping = GoSend::getPrice($origin, $post['destination']);

        if(isset($shipping['Instant']['price']['total_price'])){
            $shippingCost = $shipping['Instant']['price']['total_price'];
            $shippingFree = null;
            $isFree = '0';
            $setting = Setting::where('key', 'like', '%free_delivery%')->get();
            if($setting){
                $freeDev = [];
                foreach($setting as $dataSetting){
                    $freeDev[$dataSetting['key']] = $dataSetting['value'];
                }

                if(isset($freeDev['free_delivery_type'])){
                    if($freeDev['free_delivery_type'] == 'free' || isset($freeDev['free_delivery_nominal'])){
                        if(isset($freeDev['free_delivery_requirement_type']) && $freeDev['free_delivery_requirement_type'] == 'total item' && isset($freeDev['free_delivery_min_item'])){
                            if($post['total_item'] >= $freeDev['free_delivery_min_item']){
                                $isFree = '1';
                            }
                        }elseif(isset($freeDev['free_delivery_requirement_type']) && $freeDev['free_delivery_requirement_type'] == 'subtotal' && isset($freeDev['free_delivery_min_subtotal'])){
                            if($post['subtotal'] >= $freeDev['free_delivery_min_subtotal']){
                                $isFree = '1';
                            }
                        }

                        if($isFree == '1'){
                            if($freeDev['free_delivery_type'] == 'free'){
                                $shippingFree = 'FREE';
                            }else{
                                $shippingFree = $freeDev['free_delivery_nominal'];
                            }
                        }
                    }
                }

            }

            $result['shipping_cost_go_send'] = $shippingCost;

            if($shippingFree != null){
                if($shippingFree == 'FREE'){
                    $result['shipping_cost_discount'] = $shippingCost;
                    $result['is_free'] = 'yes';
                    $result['shipping_cost'] = 'FREE';
                }else{
                    if($shippingFree > $shippingCost){
                        $result['shipping_cost_discount'] = $shippingCost;
                        $result['is_free'] = 'no';
                        $result['shipping_cost'] = 0;
                    }else{
                        $result['shipping_cost_discount'] = (int)$shippingFree;
                        $result['is_free'] = 'no';
                        $result['shipping_cost'] = $shippingCost - $shippingFree;
                    }
                }
            }else{
                $result['shipping_cost_discount'] = 0;
                $result['is_free'] = 'no';
                $result['shipping_cost'] = $shippingCost;
            }

            return response()->json([
                'status' => 'success',
                'result' => $result
            ]);
        }else{
            if(isset($shipping['status']) && $shipping['status'] == 'fail'){
                return response()->json($shipping);
            }
            return response()->json([
                'status' => 'fail',
                'messages' => [$shipping]
            ]);
        }

    }

    public function listNoDriver(Request $request)
    {
        $gosends = TransactionPickupGoSend::select('transactions.id_transaction', 'order_id', 'transaction_receipt_number')->join('transaction_pickups', 'transaction_pickups.id_transaction_pickup', 'transaction_pickup_go_sends.id_transaction_pickup')
            ->join('transactions', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
            ->where('transaction_payment_status', 'Completed')
            ->whereNull('reject_at')
            ->whereIn('latest_status', ['cancelled', 'rejected', 'no_driver'])
            ->whereDate('transaction_pickup_go_sends.created_at', date('Y-m-d'))
            ->where('transaction_pickup_go_sends.updated_at', '<', date('Y-m-d H:i:s', time() - (5 * 60)))
            ->get()
            ->each(function($item) {
                $item->delivery_status = 'Driver not Found';
            })->toArray();
        return MyHelper::checkGet($gosends);
    }

    public function transactionDeliveryRejected(Request $request)
    {
        $result = Transaction::select('transactions.id_transaction', 'transaction_date', 'transaction_receipt_number', 'users.name', 'users.phone', 'transaction_multiple_payments.type as trasaction_payment_type', 'transaction_shipment', 'transaction_grandtotal', 'order_id')
            ->join('users', 'users.id', 'transactions.id_user')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
            ->join('transaction_pickup_go_sends', 'transaction_pickup_go_sends.id_transaction_pickup', 'transaction_pickups.id_transaction_pickup')
            ->join('transaction_multiple_payments', function($query) {
                $query->on('transaction_multiple_payments.id_transaction', 'transactions.id_transaction')
                    ->where('type', '<>', 'Balance');
            })
            ->leftJoin('transaction_payment_balances', 'transaction_payment_balances.id_transaction', 'transactions.id_transaction')
            ->whereNotNull('transaction_pickups.reject_at')
            ->where('transaction_pickups.pickup_by', 'GO-SEND')
            ->where('transaction_pickup_go_sends.latest_status', 'rejected')
            ->with('transaction_payment_midtrans', 'transaction_payment_ipay88');

        $countTotal = null;

        // if ($request->rule) {
        //     $countTotal = $result->count();
        //     $this->filterList($result, $request->rule, $request->operator ?: 'and');
        // }

        if (is_array($orders = $request->order)) {
            $columns = [
                'transaction_date', 
                'transaction_receipt_number', 
                'name', 
                'trasaction_payment_type',
                'transaction_grandtotal', 
                'transaction_shipment', 
            ];

            foreach ($orders as $column) {
                if ($colname = ($columns[$column['column']] ?? false)) {
                    $result->orderBy($colname, $column['dir']);
                }
            }
        }
        $result->orderBy('transactions.id_transaction', $column['dir'] ?? 'DESC');

        if ($request->page) {
            $result = $result->paginate($request->length ?: 15);
            $result->each(function($item) {
                $item->images = array_map(function($item) {
                    return env('STORAGE_URL_API').$item;
                }, json_decode($item->images) ?? []);
            });
            $result = $result->toArray();
            if (is_null($countTotal)) {
                $countTotal = $result['total'];
            }
            // needed by datatables
            $result['recordsTotal'] = $countTotal;
            $result['recordsFiltered'] = $result['total'];
        } else {
            $result = $result->get();
        }
        return MyHelper::checkGet($result);
    }
}
