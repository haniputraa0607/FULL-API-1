<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\Configs;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionPaymentNobu;
use App\Http\Models\LogNobu;
use App\Http\Models\User;
use App\Http\Models\Outlet;
use App\Http\Models\LogBalance;
use App\Http\Models\LogTopup;
use App\Http\Models\UsersMembership;
use App\Http\Models\Setting;
use App\Http\Models\UserOutlet;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPickupGoSend;
use App\Http\Models\TransactionShipment;
use App\Http\Models\LogPoint;
use App\Http\Models\FraudSetting;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;

use App\Jobs\FraudJob;
use GuzzleHttp\Client;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;
use App\Lib\GoSend;
use Hash;
use DB;

class ApiNobuController extends Controller
{

    public function __construct()
    {
        $this->get_login = 'MAXX';
        $this->get_password = 'MAXX';
        $this->get_merchant = '936005030000049084';
        $this->get_store = 'ID2020081400327';
        $this->get_pos = 'A01';
        $this->get_secret_key = 'SecretNobuKey';
    }

    public function notifNobu(Request $request)
    {

        $post = $request->post();
        $data = json_decode(base64_decode($post['data']),true) ?? [];

        $validSignature = $this->nobuSignature($data);
        if ($data['signature'] != $validSignature) {
            $status_code = 401;
            $response    = [
                'status'   => 'fail',
                'messages' => ['Signature mismatch'],
            ];
        }
        
    }

    public function nobuSignature($data)
    {
        $return = md5($this->get_login.$this->get_password.$data['transactionNo'].$data['referenceNo'].$data['amount'].$data['paymentStatus'].$data['paymentReferenceNo'].$data['paymentDate'].$data['issuerID'].$data['retrievalReferenceNo'].$this->get_secret_key);
        return $return;
    }



}
