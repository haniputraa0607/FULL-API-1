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
use App\Http\Models\Setting;
use App\Http\Models\StockLog;
use App\Http\Models\UserAddress;
use App\Http\Models\ManualPayment;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\ManualPaymentTutorial;
use App\Http\Models\TransactionPaymentManual;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\Outlet;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsPaymentManual;
use App\Http\Models\UserTrxProduct;

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

use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;
use Mail;
use Image;

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
                DB::rollback();
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
                DB::rollback();
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
                DB::rollback();
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
                DB::rollback();
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
                DB::rollback();
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
                DB::rollback();
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
            array_push($dataResult, '/');
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
                DB::rollback();
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
            array_push($dataResult, '/');
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
                DB::rollback();
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

            $updateCashback->value = $post['value'];
            $updateCashback->save();
            if (!$updateCashback) {
                DB::rollback();
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
                DB::rollback();
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
                DB::rollback();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Data setting not found']
                ]);
            }

            $update->value = $post['value'];
            $update->save();

            if (!$update) {
                DB::rollback();
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
                DB::rollback();
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
            //     DB::rollback();
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
            DB::rollback();
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
                DB::rollback();
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
            //     DB::rollback();
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
            DB::rollback();
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
        //             DB::rollback();
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
        //             DB::rollback();
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
                DB::rollback();
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
                            DB::rollback();
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
                    DB::rollback();
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
                            DB::rollback();
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
                            DB::rollback();
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
        $query = LogBalance::select('log_balances.*',
                              'users.name')
                    ->leftJoin('users','log_balances.id_user','=','users.id')
                    ->where('log_balances.created_at', '>=', $start)
                    ->where('log_balances.created_at', '<=', $end)
                    ->orderBy('log_balances.id_log_balance', 'DESC')
                    ->groupBy('log_balances.id_log_balance');
                    // ->orderBy('transactions.id_transaction', 'DESC');

        // return response()->json($query->get());
        if (isset($post['conditions'])) {
            foreach ($post['conditions'] as $key => $con) {
                
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

    public function balanceUser(Request $request) {
        $balance = LogBalance::with('user')->paginate(10);
        return response()->json(MyHelper::checkGet($balance));
    }

    public function manualPaymentListUnpay(Request $request) {
        $list = TransactionPaymentManual::with('transaction', 'manual_payment_method')->get()->toArray();
        return response()->json(MyHelper::checkGet($list));
    }
	
    public function transactionList($key){
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-d 23:59:59');
        $list = Transaction::orderBy('id_transaction', 'DESC')->with('user', 'productTransaction.product.product_category')->where('trasaction_type', ucwords($key))->where('created_at', '>=', $start)->where('created_at', '<=', $end)->paginate(10);

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
                              'transaction_products.*',
                              'users.*',
                              'products.*',
                              'product_categories.*')
                    ->leftJoin('transaction_products','transactions.id_transaction','=','transaction_products.id_transaction')
                    ->leftJoin('users','transactions.id_user','=','users.id')
                    ->leftJoin('products','products.id_product','=','transaction_products.id_product')
                    ->leftJoin('product_categories','products.id_product_category','=','product_categories.id_product_category')
                    ->where('trasaction_type', $post['key'])
                    ->where('transactions.created_at', '>=', $start)
                    ->where('transactions.created_at', '<=', $end)
                    ->orderBy('transactions.id_transaction', 'DESC')
                    ->groupBy('transactions.id_transaction');
                    // ->orderBy('transactions.id_transaction', 'DESC');

        // return response()->json($query->get());
        if (isset($post['conditions'])) {
            foreach ($post['conditions'] as $key => $con) {
                if ($con['subject'] == 'receipt') {
                    $var = 'transactions.transaction_receipt_number';
                } elseif ($con['subject'] == 'name' || $con['subject'] == 'phone' || $con['subject'] == 'email') {
                    $var = 'users.'.$con['subject'];
                } elseif ($con['subject'] == 'product_name' || $con['subject'] == 'product_code') {
                    $var = 'products.'.$con['subject'];
                } elseif ($con['subject'] == 'product_category') {
                    $var = 'product_categories.product_category_name';
                }

                if ($con['subject'] == 'receipt' || $con['subject'] == 'name' || $con['subject'] == 'phone' || $con['subject'] == 'email' || $con['subject'] == 'product_name' || $con['subject'] == 'product_code' || $con['subject'] == 'product_category') {
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
        $id = $request->json('transaction_receipt_number');
        $type = $request->json('type');

        if ($type == 'trx') {
            $list = Transaction::where('transaction_receipt_number', $id)->with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->first();
            $label = [];
            $label2 = [];

            $cart = $list['transaction_subtotal'] + $list['transaction_shipment'] + $list['transaction_service'] + $list['transaction_tax'] - $list['transaction_discount'];

            $list['transaction_carttotal'] = $cart;

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

            if ($list['trasaction_payment_type'] == 'Balance') {
                $log = LogBalance::where('id_reference', $list['id_transaction'])->first();
                if ($log['balance'] < 0) {
                    $list['balance'] = $log['balance'];
                    $list['check'] = 'tidak topup';
                } else {
                    $list['balance'] = $list['transaction_grandtotal'] - $log['balance'];
                    $list['check'] = 'topup';
                }
            }

            if ($list['trasaction_payment_type'] == 'Manual') {
                $payment = TransactionPaymentManual::with('manual_payment_method.manual_payment')->where('id_transaction', $list['id_transaction'])->first();
                $list['payment'] = $payment;
            }

            if ($list['trasaction_payment_type'] == 'Midtrans') {
                $payment = TransactionPaymentMidtran::where('id_transaction', $list['id_transaction'])->first();
                $list['payment'] = $payment;
            }

            if ($list['trasaction_payment_type'] == 'Offline') {
                $payment = TransactionPaymentOffline::where('id_transaction', $list['id_transaction'])->get();
                $list['payment_offline'] = $payment;
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
                    $qr      = $detail['order_id'].strtotime($list['transaction_date']);
                    
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

            return response()->json(MyHelper::checkGet($list));
        } else {
            $list = $voucher = DealsUser::with('outlet', 'dealVoucher.deal')->where('id_deals_user', $id)->orderBy('claimed_at', 'DESC')->first();

            if (empty($list)) {
                return response()->json(MyHelper::checkGet($list));
            }

            if ($list['payment_method'] == 'Midtrans') {
                $payment = DealsPaymentMidtran::where('id_deals_user', $id)->first();
            } else {
                $payment = DealsPaymentManual::where('id_deals_user', $id)->first();
            }

            $list['payment'] = $payment;

            $list['date'] = $list['claimed_at'];
            $list['type'] = 'voucher';

            return response()->json(MyHelper::checkGet($list));
        }
        
        
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

        $address = UserAddress::where('id_user', $id)->with('user', 'city.province', 'user.city.province')->orderBy('primary', 'DESC')->get()->toArray();
        return response()->json(MyHelper::checkGet($address));
    }

    public function addAddress(AddAddress $request) {
        $post = $request->json()->all();

        $data['id_user'] = $request->user()->id;

        if (empty($data['id_user'])) {
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['User not found']
            ]);
        }

        if ($post['primary'] == 1) {
            $data['primary'] = '1';
            $select = UserAddress::where('id_user', $data['id_user'])->where('primary', '1')->first();
            if (!empty($select)) {
                $select->primary = '0';
                $select->save();

                if (!$select) {
                    return response()->json([
                        'status' => 'fail', 
                        'messages'  => ['Failed']
                    ]);
                }
            }
        } else {
            $data['primary'] = '0';
        }

        $data['name']        = isset($post['name']) ? $post['name'] : null;
        $data['phone']       = isset($post['phone']) ? $post['phone'] : null;
        $data['address']     = isset($post['address']) ? $post['address'] : null;
        $data['id_city']     = isset($post['id_city']) ? $post['id_city'] : null;
        $data['postal_code'] = isset($post['postal_code']) ? $post['postal_code'] : null;
        $data['description'] = isset($post['description']) ? $post['description'] : null;
     
        $insert = UserAddress::create($data);
        return response()->json(MyHelper::checkCreate($insert));
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

        if ($post['primary'] == 1) {
            $data['primary'] = '1';
            $select = UserAddress::where('id_user', $data['id_user'])->where('primary', '1')->first();
            if (!empty($select)) {
                $select->primary = '0';
                $select->save();

                if (!$select) {
                    return response()->json([
                        'status' => 'fail', 
                        'messages'  => ['Failed']
                    ]);
                }
            }
        } else {
            $data['primary'] = '0';
        }

        $data['name']        = isset($post['name']) ? $post['name'] : null;
        $data['phone']       = isset($post['phone']) ? $post['phone'] : null;
        $data['address']     = isset($post['address']) ? $post['address'] : null;
        $data['id_city']     = isset($post['id_city']) ? $post['id_city'] : null;
        $data['postal_code'] = isset($post['postal_code']) ? $post['postal_code'] : null;
        $data['description'] = isset($post['description']) ? $post['description'] : null;
     
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
                    'messages'    => ['Service is not ready']
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
                                DB::rollback();
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
                        DB::rollback();
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
                    DB::rollback();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Transaction payment cannot be create']
                    ]);
                }
                
                DB::commit();
                return $response->json([
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

}