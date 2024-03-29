<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\User;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\ProductModifierProduct;
use App\Http\Models\Courier;
use App\Http\Models\UserAddress;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\UserOutlet;
use App\Http\Models\Configs;
use Modules\Brand\Entities\BrandProduct;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request as RequestGuzzle;
use Guzzle\Http\Message\Response as ResponseGuzzle;
use Guzzle\Http\Exception\ServerErrorResponseException;

use Modules\Transaction\Http\Requests\Transaction\DumpData;

use App\Lib\MyHelper;

class ApiDumpController extends Controller
{
    var $token = 'ampas';

    function __construct() {
        ini_set('max_execution_time', 0);
        date_default_timezone_set('Asia/Jakarta');
    }

    public function dumpData(DumpData $request) {
        $post = $request->json()->all();

        for ($dataCount=0; $dataCount < $post['how_many']; $dataCount++) {
            $dataItem = [];

            $date_start = strtotime($post['date_start']);
            $date_end   = strtotime($post['date_end']);
            $date       = date('Y-m-d H:i:s', rand($date_start, $date_end));

            $time_start = strtotime('08:00:00');
            $time_end   = strtotime('22:00:00');
            $time       = date('H:i:s', rand($time_start, $time_end));

            //user
            if(isset($post['id_user'])){
                if(is_array($post['id_user'])){
                    $idUser = $post['id_user'][array_rand($post['id_user'])];
                }else{
                    $idUser = $post['id_user'];
                }
            }else{
                $dataUser = User::get()->toArray();
                if (empty($dataUser)) {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => ['User is empty']
                    ]);
                }
                $splitUser = array_column($dataUser, 'id');
                $idUser = $splitUser[array_rand($splitUser)];
            }

            $user = User::where('id', $idUser)->first();

            $configDeliveryOrder = Configs::where('config_name', 'delivery order')->first();
            $configPickupOrder = Configs::where('config_name', 'pickup order')->first();

            //user address
            if($configDeliveryOrder && $configDeliveryOrder->is_active == '1'){
                $dataUserAddress = UserAddress::where('id_user', $idUser)->get()->toArray();
                if (empty($dataUserAddress)) {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => [$user['name'].' dont have user address']
                    ]);
                }
                $splitUserAddress = array_column($dataUserAddress, 'id_user_address');
                $getUserAddress = array_rand($splitUserAddress);
            }

            //outlet
            $dataOutlet = Outlet::select('outlets.id_outlet')
                                ->rightJoin('product_prices', 'outlets.id_outlet', 'product_prices.id_outlet')
                                ->whereNotNull('product_price')->whereNotNull('product_price_base')->whereNotNull('product_price_tax')
                                ->distinct()
                                ->get()->toArray();

            if (empty($dataOutlet)) {
                return response()->json([
                    'status'    => 'fail',
                    'messages' => ['Outlet is empty']
                ]);
            }

            $splitOutlet = array_column($dataOutlet, 'id_outlet');

            $getOutlet = array_rand($splitOutlet);


            //product
            $dataProduct = ProductPrice::where('id_outlet', $splitOutlet[$getOutlet])->where('product_price', '>',  0)->where('product_stock_status', 'Available')->get()->toArray();

            if (empty($dataProduct)) {
                return response()->json([
                    'status'    => 'fail',
                    'messages' => ['Product is empty']
                ]);
            }

            $splitProduct = array_column($dataProduct, 'id_product');

            $getProduct = array_rand($splitProduct);

            $totalItem = 0;
            $totalPrice = 0;

            $priceMin = 0;
            if(isset($post['price_start'])){
                $priceMin = $post['price_start'];
            }

            $qtyMin = 0;
            if(isset($post['qty_start'])){
                $qtyMin = $post['qty_start'];
            }

            if(isset($post['qty_end'])){
                $qtyEnd = $post['qty_end'];
            }else{
                $qtyEnd = 4;
            }

            do{
                $getItem = rand(1,count($dataProduct));

                for ($i=1; $i <= $getItem; $i++) {
                    $setProduct = array_column($dataProduct, 'id_product');
                    $insertProduct = array_rand($setProduct);

                    if (!empty($dataItem)) {
                        $checkIdProduct = array_column($dataItem, 'id_product');
                        if (!in_array($setProduct[$insertProduct], $checkIdProduct)) {
                            $modifier = [];
                            $mod = ProductModifierProduct::where('id_product', $setProduct[$insertProduct])->first();
                            $brand = BrandProduct::where('id_product', $setProduct[$insertProduct])->first();
                            if($mod){
                                $modifier[] = $mod['id_product_modifier'];
                            }
                            $setItem = [
                                'id_brand'   => $brand['id_brand'],
                                'id_product' => $setProduct[$insertProduct],
                                'qty'        => rand(1,$qtyEnd),
                                'note'       => $this->getrandomstring(),
                                'price'      => $dataProduct[$insertProduct]['product_price'],
                                'modifiers'   => $modifier
                            ];
                            $totalItem += $setItem['qty'];
                            $totalPrice += ($setItem['qty'] * $dataProduct[$insertProduct]['product_price']);
                        } else {
                            $setItem = [];
                        }
                    } else {
                        $modifier = [];
                        $mod = ProductModifierProduct::where('id_product', $setProduct[$insertProduct])->first();
                        $brand = BrandProduct::where('id_product', $setProduct[$insertProduct])->first();
                        if($mod){
                            $modifier[] = $mod['id_product_modifier'];
                        }
                        $setItem = [
                            'id_brand'   => $brand['id_brand'],
                            'id_product' => $setProduct[$insertProduct],
                            'qty'        => rand(1,$qtyEnd),
                            'note'       => $this->getrandomstring(),
                            'price'      => $dataProduct[$insertProduct]['product_price'],
                            'modifiers'   => $modifier
                        ];
                        $totalItem += $setItem['qty'];
                        $totalPrice += ($setItem['qty'] * $dataProduct[$insertProduct]['product_price']);
                    }

                    if (!empty($setItem)) {
                        array_push($dataItem, $setItem);
                    }

                    //harga sudah melebihi maksimal
                    if(isset($post['price_end'])){
                        if($totalPrice >= $post['price_end']){
                            break;
                        }
                    }

                    //jumlah item sudah melebihi maksimal
                    if(isset($post['qty_end'])){
                        if($totalItem >= $post['qty_end']){
                            break;
                        }
                    }
                }
            }while(($totalPrice < $priceMin) || ($totalItem < $qtyMin));

            if(isset($post['price_end'])){
                while($totalPrice > $post['price_end']){
                    if(count($dataItem) > 1 || (count($dataItem) == 1 && $dataItem[0]['qty'] > 1)){
                        if($dataItem[0]['qty'] > 1){
                            $dataItem[0]['qty'] -= 1;
                            $totalPrice -= $dataItem[0]['price'];
                        }else{
                            $totalPrice -= $dataItem[0]['price'];
                            array_splice($dataItem, 0, 1);
                        }
                    }
                }
            }

            if(isset($post['qty_end'])){
                while($totalItem > $post['qty_end']){
                    if($totalItem > $post['qty_end'] && (count($dataItem) > 1 || (count($dataItem) == 1 && $dataItem[0]['qty'] > 1))){
                        if($dataItem[0]['qty'] > 1){
                            $dataItem[0]['qty'] -= 1;
                        }else{
                            array_splice($dataItem, 0, 1);
                        }
                        $totalItem -= 1;
                    }
                }
            }


            $type = [];
            if($configDeliveryOrder && $configDeliveryOrder->is_active == '1'){
                $type[] = 'Delivery';

                //courier
                $getCourier = $this->courierSet();
                if (isset($getCourier['status']) && $getCourier['status'] == 'success') {
                    $setCourier = $getCourier['courier'];
                    if (!empty($getCourier['service'])) {
                        $splitService = array_rand($getCourier['service']);
                        $setCourierService = $getCourier['service'][$splitService];
                    } else {
                        $setCourierService = [];
                    }
                } elseif (isset($getCourier['status']) && $getCourier['status'] == 'fail') {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => [$getCourier['messages']]
                    ]);
                } else {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => ['Data Not Valid']
                    ]);
                }
            }

            if($configPickupOrder && $configPickupOrder->is_active == '1'){
                $type[] = 'Pickup Order';
            }

            $getType = array_rand($type);

            //payment
            $payment = ['Midtrans'];

            $configManualPayment = Configs::where('config_name', 'manual payment')->first();
            if($configManualPayment && $configManualPayment->is_active == '1'){
                $payment[] = 'Manual';
            }

            $getPayment = array_rand($payment);

            //manual_payment_method
            if($payment[$getPayment] == 'Manual'){
                $dataManualMethod = ManualPaymentMethod::get()->toArray();
                if (empty($dataManualMethod)) {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => ['Manual Payment Method is empty']
                    ]);
                }

                $splitManualMethod = array_column($dataManualMethod, 'id_manual_payment_method');
                $getManualMethod = array_rand($splitManualMethod);

                //bank_payment
                $accountBank = ManualPaymentMethod::with('manual_payment')->where('id_manual_payment_method', $splitManualMethod[$getManualMethod])->first();
            }

            //tax
            $setShip =  [ '15000', '16000', '17000', '18000', '19000', '20000', '21000', '22000', '23000', '24000', '25000', '26000', '27000', '28000', '29000', '30000', '31000', '32000', '33000', '34000', '35000', '36000', '37000', '38000', '39000', '40000', '41000', '42000', '43000', '44000', '45000', '46000', '47000', '48000', '49000', '50000', '51000', '52000', '53000', '54000', '55000', '56000', '57000', '58000', '59000', '60000', '61000', '62000', '63000', '64000'];

            $getShip = array_rand($setShip);

            if ($type[$getType] == 'Delivery') {
                $data = [
                    'id_outlet'                  => $splitOutlet[$getOutlet],
                    'id_user'                    => $idUser,
                    'type'                       => 'Delivery',
                    'notes'                      => $this->getrandomstring(),
                    'shipping'                   => $setShip[$getShip],
                    'courier'                    => $getCourier['courier'],
                    'cour_service'               => $setCourierService,
                    'cour_etd'                   => '1-2',
                    'id_user_address'            => $splitUserAddress[$getUserAddress],
                    'transaction_date'           => $date,
                    'payment_type'               => $payment[$getPayment],
                    'transaction_payment_status' => 'Completed'
                ];

                $configAdminOutlet = Configs::where('config_name', 'admin outlet')->first();
                if($configAdminOutlet && $configAdminOutlet->is_active == '1'){
                    $configAdminOutlet = Configs::where('config_name', 'admin outlet delivery order')->first();

                    if($configAdminOutlet && $configAdminOutlet->is_active == '1'){
                        $adminOutlet = UserOutlet::where('id_outlet', $data['id_outlet'])->where('delivery', 1)->get()->toArray();
                        if (empty($adminOutlet)) {
                            return response()->json([
                                'status'    => 'fail',
                                'messages' => ['Admin Outlet is empty']
                            ]);
                        }

                        $splitAdminOutlet = array_column($adminOutlet, 'id_user_outlet');
                        $getAdminOutlet = array_rand($splitAdminOutlet);

                        // return $splitAdminOutlet[$getAdminOutlet];

                        $data['receive_at']              = $date.' '.$time;
                        $data['id_admin_outlet_receive'] = $splitAdminOutlet[$getAdminOutlet];
                        $data['send_at']                 = $date.' '.$time;
                        $data['id_admin_outlet_send']    = $splitAdminOutlet[$getAdminOutlet];
                    }
                }

                $data['item'] = $dataItem;

                if ($payment[$getPayment] == 'Manual') {
                    $data['id_manual_payment_method'] = $splitManualMethod[$getManualMethod];
                    $data['payment_date']             = date('Y-m-d', strtotime($date));
                    $data['payment_time']             = $time;
                    $data['payment_bank']             = $accountBank['manual_payment']['manual_payment_name'];
                    $data['payment_method']           = $accountBank['payment_method_name'];
                    $data['payment_account_number']   = $accountBank['manual_payment']['account_number'];
                    $data['payment_account_name']     = $accountBank['manual_payment']['account_name'];
                    $data['payment_receipt_image']    = $this->image();
                    $data['payment_note']             = $this->getrandomstring();
                }

                $data['from_fake'] = '1';

                $insertTransaction = $this->insert($data);
                // return $insertTransaction;

                if (isset($insertTransaction['status']) && $insertTransaction['status'] == 'success') {
                    continue;
                } elseif (isset($insertTransaction['status']) && $insertTransaction['status'] == 'fail') {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => $insertTransaction['messages']
                    ]);
                } else {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => ['Failed']
                    ]);
                }
            } else {
                $pickupType = ['set time', 'right now', 'at arrival'];
                $getPickupType = array_rand($pickupType);

                $data = [
                    'id_outlet'                  => $splitOutlet[$getOutlet],
                    'id_user'                    => $idUser,
                    'type'                       => 'Pickup Order',
                    'notes'                      => $this->getrandomstring(),
                    'pickup_type'                => $pickupType[$getPickupType],
                    'pickup_at'                  => date('Y-m-d', strtotime($date)).' '.$time,
                    'transaction_date'           => date('Y-m-d', strtotime($date)).' '.$time,
                    'payment_type'               => $payment[$getPayment],
                    'transaction_payment_status' => 'Completed'
                ];

                $data['item'] = $dataItem;

                $configAdminOutlet = Configs::where('config_name', 'admin outlet')->first();
                if($configAdminOutlet && $configAdminOutlet->is_active == '1'){
                    $configAdminOutlet = Configs::where('config_name', 'admin outlet pickup order')->first();

                    if($configAdminOutlet && $configAdminOutlet->is_active == '1'){

                        $adminOutlet = UserOutlet::where('id_outlet', $data['id_outlet'])->where('pickup_order', 1)->get()->toArray();
                        if (empty($adminOutlet)) {
                            return response()->json([
                                'status'    => 'fail',
                                'messages' => ['Admin Outlet is empty']
                            ]);
                        }

                        $splitAdminOutlet = array_column($adminOutlet, 'id_user_outlet');
                        $getAdminOutlet = array_rand($splitAdminOutlet);

                        $data['receive_at']              = $date.' '.$time;
                        $data['id_admin_outlet_receive'] = $splitAdminOutlet[$getAdminOutlet];
                        $data['taken_at']                = $date.' '.$time;
                        $data['id_admin_outlet_taken']   = $splitAdminOutlet[$getAdminOutlet];
                    }
                }

                if ($payment[$getPayment] == 'Manual') {
                    $data['id_manual_payment_method'] = $splitManualMethod[$getManualMethod];
                    $data['payment_date']             = date('Y-m-d', strtotime($date));
                    $data['payment_time']             = $time;
                    $data['payment_bank']             = $accountBank['manual_payment']['manual_payment_name'];
                    $data['payment_method']           = $accountBank['payment_method_name'];
                    $data['payment_account_number']   = $accountBank['manual_payment']['account_number'];
                    $data['payment_account_name']     = $accountBank['manual_payment']['account_name'];
                    $data['payment_receipt_image']    = $this->image();
                    $data['payment_note']             = $this->getrandomstring();
                }

                $data['from_fake'] = '1';

                $insertTransaction = $this->insert($data);
                // return $insertTransaction;
                if (isset($insertTransaction['status']) && $insertTransaction['status'] == 'success') {
                    continue;
                } elseif (isset($insertTransaction['status']) && $insertTransaction['status'] == 'fail') {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => $insertTransaction['messages']
                    ]);
                } else {
                    return response()->json([
                        'status'    => 'fail',
                        'messages' => ['Failed']
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'result' => 'Input '.$dataCount.' Data Transaction Success'
        ]);
    }

    public function insert($data) {
        $url = env('API_URL').'api/transaction/be/new';

        $create = $this->sendStatus($url, $data);

        return $create;
    }

    public function getrandomstring($length = 15) {

       global $template;
       settype($template, "string");

       $template = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

       settype($length, "integer");
       settype($rndstring, "string");
       settype($a, "integer");
       settype($b, "integer");

       for ($a = 0; $a <= $length; $a++) {
               $b = rand(0, strlen($template) - 1);
               $rndstring .= $template[$b];
       }

       return $rndstring;
    }

    public function courierSet() {
        $dataCourier = Courier::get()->toArray();
        if (empty($dataCourier)) {
            return ['status' => 'fail', 'messages' => ['Courier is empty']];
        }

        $splitCourier = array_column($dataCourier, 'short_name');
        $getCourier = array_rand($splitCourier);

        if ($splitCourier[$getCourier] == 'jne') {
            $courierService = [
                'OKE', 'REG', 'YES'
            ];
        } elseif ($splitCourier[$getCourier] == 'ncs') {
            $courierService = [
                'NRS', 'ONS'
            ];
        } elseif ($splitCourier[$getCourier] == 'pcp') {
            $courierService = [
                'ONS', 'NFS', 'REG'
            ];
        } elseif ($splitCourier[$getCourier] == 'dse') {
            $courierService = [
                'SDS', 'ONS', 'ECO'
            ];
        } elseif ($splitCourier[$getCourier] == 'psc') {
            $courierService = [
                'SDS', 'ONS', 'ECO'
            ];
        } elseif ($splitCourier[$getCourier] == 'pos') {
            $courierService = [
                'Paket Kilat Khusus', 'Express Next Day Barang', 'Paketpos Dangerous Goods', 'Paketpos Valuable Goods'
            ];
        } elseif ($splitCourier[$getCourier] == 'tiki') {
            $courierService = [
                'REG', 'ONS', 'ECO', 'SDS', 'HDS'
            ];
        } elseif ($splitCourier[$getCourier] == 'esl') {
            $courierService = [
                'RPX/RDX'
            ];
        } elseif ($splitCourier[$getCourier] == 'rpx') {
            $courierService = [
                'SDP', 'MDP', 'NDP', 'RGP'
            ];
        } elseif ($splitCourier[$getCourier] == 'pandu') {
            $courierService = [
                'REG'
            ];
        } elseif ($splitCourier[$getCourier] == 'wahana') {
            $courierService = [
                'DES'
            ];
        } elseif ($splitCourier[$getCourier] == 'sicepat') {
            $courierService = [
                'REG', 'BEST', 'Priority'
            ];
        } elseif ($splitCourier[$getCourier] == 'jnt') {
            $courierService = [
                'EZ'
            ];
        } elseif ($splitCourier[$getCourier] == 'pahala') {
            $courierService = [
                'EXPRESS', 'ONS', 'SDS'
            ];
        } elseif ($splitCourier[$getCourier] == 'sap') {
            $courierService = [
                'REG', 'SDS', 'ODS'
            ];
        } elseif ($splitCourier[$getCourier] == 'jet') {
            $courierService = [
                'CRG', 'PRI', 'REG'
            ];
        } elseif ($splitCourier[$getCourier] == 'slis') {
            $courierService = [
                'REGULAR', 'EXPRESS'
            ];
        } elseif ($splitCourier[$getCourier] == 'first') {
            $courierService = [
                'REG', 'ONS', 'SDS'
            ];
        } elseif ($splitCourier[$getCourier] == 'star') {
            $courierService = [
                'Express', 'Reguler', 'Dokumen', 'MOTOR', 'MOTOR 150 - 250 CC'
            ];
        } else {
            $courierService = ['No Service'];
        }

        $data = [
            'status' => 'success',
            'courier' => $splitCourier[$getCourier],
            'service' => $courierService
        ];

        return $data;
    }

    public function sendStatus($url, $data) {
        $client = new Client;
        $content = array(
          'headers' => [
            'Authorization' => 'Bearer '.$this->getBearerToken(),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'
          ],
          'json' => (array) $data
        );

        try {
          $response =  $client->request('POST', $url, $content);
          return json_decode($response->getBody(), true);
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
          try{

            if($e->getResponse()){
              $response = $e->getResponse()->getBody()->getContents();
              // print_r($response); exit();
              $error = json_decode($response, true);

              if(!$error) {
                return $e->getResponse()->getBody();
              }
              else {
               return $error;
              }
            }
            else return ['status' => 'fail', 'messages' => [0 => 'Check your internet connection.']];

          }
          catch(Exception $e){
            return ['status' => 'fail', 'messages' => [0 => 'Check your internet connection.']];
          }
        }

    }

    function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }

        if (isset($_SERVER['REDIRECT_HTTP_AUTHENTICATION']) && $headers == null){
            $headers = trim($_SERVER["REDIRECT_HTTP_AUTHENTICATION"]);
        }

        if (isset($_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION']) && $headers == null){
            $headers = trim($_SERVER["REDIRECT_REDIRECT_HTTP_AUTHORIZATION"]);
        }

        return $headers;

        return $headers;
    }

    function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }else{
                return $headers;
            }
        }

        return null;
    }

    public function image() {
        return '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';
    }

    public function generateNumber() {
        return $this->token;
        $data = 15000;
        do {
            echo $data.' ';
            $data += 1000;
        } while ($data < 65000);
    }
}
