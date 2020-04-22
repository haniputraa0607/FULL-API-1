<?php

namespace Modules\POS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Membership;
use App\Http\Models\UsersMembership;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionProductModifier;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionDuplicate;
use App\Http\Models\TransactionDuplicatePayment;
use App\Http\Models\TransactionDuplicateProduct;
use App\Http\Models\TransactionVoucher;
use App\Http\Models\TransactionSetting;
use App\Http\Models\User;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\ProductPhoto;
use App\Http\Models\ProductModifier;
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

use Modules\POS\Http\Controllers\CheckVoucher;
use Exception;

use DB;

class ApiTransactionSync extends Controller
{
    function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->balance    = "Modules\Balance\Http\Controllers\BalanceController";
        $this->membership = "Modules\Membership\Http\Controllers\ApiMembership";
        $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->setting_fraud = "Modules\SettingFraud\Http\Controllers\ApiFraud";

        $this->pos = "Modules\POS\Http\Controllers\ApiPOS";
    }

    public function transaction(Request $request){
        $x = 1;
        $getDataQueue = SyncTransactionQueues::where('type', 'Transaction')
            ->orWhereNull('type')
            ->Orderby('created_at', 'asc')->limit($x)->get()->toArray();

        foreach($getDataQueue as $key => $trans){
            $data['store_code'] = $trans['outlet_code'];
            $data['transactions'] = json_decode($trans['request_transaction'], true);
            $process = app($this->pos)->transaction($request, $data, 0);
            if(isset($process->getData()->status) && $process->getData()->status == 'success'){
                SyncTransactionQueues::where('id_sync_transaction_queues', $trans['id_sync_transaction_queues'])->delete();
            }
        }

    }

    public function transactionRefund(Request $request){
        $x = 1;
        $getDataQueue = SyncTransactionQueues::whereIn('type', 'Transaction Refund')->Orderby('created_at', 'asc')->limit($x)->get()->toArray();
        foreach($getDataQueue as $key => $trans){
            $data = json_decode($trans['request_transaction'], true);
            $process = app($this->pos)->transactionRefund($request, $data, 0);
            if(isset($process->getData()->status) && $process->getData()->status == 'success'){
                SyncTransactionQueues::where('id_sync_transaction_queues', $trans['id_sync_transaction_queues'])->delete();
            }
        }
    }

}
