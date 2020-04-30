<?php 
namespace Modules\IPay88\Lib;

use Illuminate\Support\Facades\Log;
use DB;

use App\Http\Models\Transaction;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\DealsUser;
use App\Http\Models\Deal;
use App\Http\Models\User;
use App\Http\Models\Setting;
use Modules\IPay88\Entities\LogIpay88;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use Modules\IPay88\Entities\DealsPaymentIpay88;

use App\Lib\MyHelper;
/**
 * IPay88 Payment Integration Class
 */
class IPay88
{
	public static $obj = null;
	function __construct() {
        $this->notif = "Modules\Transaction\Http\Controllers\ApiNotification";
		$this->autocrm = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
		$this->promo_campaign = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
		$this->voucher  = "Modules\Deals\Http\Controllers\ApiDealsVoucher";

		$this->posting_url = ENV('IPAY88_POSTING_URL');
		$this->requery_url = ENV('IPAY88_REQUERY_URL');
		$this->merchant_code = ENV('IPAY88_MERCHANT_CODE');
		$this->merchant_key = ENV('IPAY88_MERCHANT_KEY');
		$this->payment_id = [
			'CREDIT_CARD' => strpos(ENV('IPAY88_POSTING_URL'),'sandbox') !== false?1:35,
			'CREDIT_CARD_BCA' => 52,
			'CREDIT_CARD_BRI' => 35,
			'CREDIT_CARD_CIMB' => 42,
			'CREDIT_CARD_CIMB_AUTHORIZATION' => 56,
			'CREDIT_CARD_CIMB IPG)' => 34,
			'CREDIT_CARD_DANAMON' => 45,
			'CREDIT_CARD_MANDIRI' => 53,
			'CREDIT_CARD_MAYBANK' => 43,
			'CREDIT_CARD_UNIONPAY' => 54,
			'CREDIT_CARD_UOB' => 46,
			'MAYBANK_VA' => 9,
			'MANDIRI_ATM' => 17,
			'BCA_VA' => 25,
			'BNI_VA' => 26,
			'PERMATA_VA' => 31,
			'LinkAja' => 13,
			'OVO' => 63,
			'PAYPAL' => 6,
			'KREDIVO' => 55,
			'ALFAMART' => 60,
			'INDOMARET' => 65
		];
		$this->currency = ENV('IPAY88_CURRENCY','IDR');
	}
	/**
	 * Create object from static function
	 * @return IPay88 IPay88 Instance
	 */
	public static function create() {
		if(!self::$obj){
			self::$obj = new self();
		}
		return self::$obj;
	}

	/**
	 * Signing data (add signature parameter)
	 * @param  array $data array of full unsigned data
	 * @return array       array of signed data
	 */
	public function sign($data) {
		$string = $this->merchant_key.$data['MerchantCode'].$data['RefNo'].$data['Amount'].$data['Currency'].$data['xfield1'];
		$hex2bin = function($hexSource){
			$bin = '';
			for ($i=0;$i<strlen($hexSource);$i=$i+2){
				$bin .= chr(hexdec(substr($hexSource,$i,2)));
			}
			return $bin;
		};
		$signature = base64_encode($hex2bin(sha1($string)));
		$data['Signature'] = $signature;
		return $data;
	}
	/**
	 * generate formdata to be send to IPay88
	 * @param  Integer $reference id_transaction/id_deals_user
	 * @param  string $type type of transaction ('trx'/'deals')
	 * @return Array       array formdata
	 */
	public function generateData($reference,$type = 'trx',$payment_method = null){
		$data = [
			'MerchantCode' => $this->merchant_code,
			'PaymentId' => $this->getPaymentId($payment_method),
			'Currency' => $this->currency,
			'Lang' => 'UTF-8'
		];
		if($type == 'trx'){
			$trx = Transaction::with('user')
			->join('transaction_payment_ipay88s','transaction_payment_ipay88s.id_transaction','=','transactions.id_transaction')
			->where('transactions.id_transaction',$reference)->first();
			if(!$trx) return false;
			$data += [
				'RefNo' => $trx->transaction_receipt_number,
				'Amount' => $trx->amount,
				'ProdDesc' => Setting::select('value_text')->where('key','ipay88_product_desc')->pluck('value_text')->first()?:$trx->transaction_receipt_number,
				'UserName' => $trx->user->name,
				'UserEmail' => $trx->user->email,
				'UserContact' => $trx->user->phone,
				'Remark' => '',
				'ResponseURL' => env('API_URL').'api/ipay88/detail/trx',
				'BackendURL' => env('API_URL').'api/ipay88/notif/trx',
				'xfield1' => ''
			];
		}elseif($type == 'deals'){
			$deals_user = DealsUser::with('user')
			->where([
				'deals_users.id_deals_user' => $reference,
				'deals_users.payment_method' => 'Ipay88'
			])
			->join('deals_payment_ipay88s','deals_payment_ipay88s.id_deals_user','=','deals_users.id_deals_user')
			->join('deals','deals.id_deals','=','deals_payment_ipay88s.id_deals')
			->first();
			if(!$deals_user) return false;
			$data += [
				'RefNo' => $deals_user->order_id,
				'Amount' => $deals_user->amount,
				'ProdDesc' => 'Voucher '.$deals_user->deals_title,
				'UserName' => $deals_user->user->name,
				'UserEmail' => $deals_user->user->email,
				'UserContact' => $deals_user->user->phone,
				'Remark' => '',
				'ResponseURL' => env('API_URL').'api/ipay88/detail/deals',
				'BackendURL' => env('API_URL').'api/ipay88/notif/deals',
				'xfield1' => ''
			];
		}else{
			return false;
		}
		$signed = $this->sign($data);
		return [
			'action_url' => $this->posting_url,
			'data' => $signed
		];
	}
	/**
	 * function to Re-query received data (for security reason) and validate status
	 * @param  Array $data received data
	 * @param  String $status received payment status
	 * @return String reply from IPay88
	 */
	public function reQuery($data,$status)
	{
		$submitted = [
			'MerchantCode' => $data['MerchantCode'],
			'RefNo' => $data['RefNo'],
			'Amount' => $data['Amount']
		];
		$url = $this->requery_url.'?'.http_build_query($submitted);
		$response = MyHelper::postWithTimeout($url,null,$submitted,25);
        $toLog = [
            'type' => $data['type'].'_requery',
            'triggers' => $data['triggers'],
            'id_reference' => $data['RefNo'],
            'request' => json_encode($submitted),
            'request_header' => '',
            'request_url' => $url,
            'response' => json_encode($response['response']),
            'response_status_code' => json_encode($response['status_code'])
        ];
        try{
            LogIpay88::create($toLog);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
		$is_valid = false;
		if(
			($status == '1' && $response['response'] == '00') ||
			($status == '0' && $response['response'] == 'Payment fail') ||
			($status == '6' && $response['response'] == 'Payment Pending')			
		){
			$is_valid = true;
		}
		$response['valid'] = $is_valid;
		return $response;
	}
	/**
	 * Insert new transaction payment to database or return existing
	 * @param  Array $data      Array version of Transaction / DealsUser Model
	 * @param  String $type 	trx/deals
	 * @return Object           TransactionPaymentIpay88 / DealsPaymentIpay88
	 */
	public function insertNewTransaction($data, $type='trx',$grandtotal) {
		$result = TransactionPaymentIpay88::where('id_transaction',$data['id_transaction'])->first();
		if($result){
			return $result;
		}
		if($type == 'trx'){
			$toInsert = [
				'id_transaction' => $data['id_transaction'],
				'amount' => $grandtotal*100
			];

			$result = TransactionPaymentIpay88::create($toInsert);
		}elseif($type == 'deals'){
			$toInsert = [
				'id_transaction' => $data['id_transaction'],
				'amount' => $grandtotal*100
			];

			$result = DealsPaymentIpay88::create($toInsert);
		}
		return $result;
	}
	/**
	 * Update transaction ipay table
	 * @param  Model $model     [Transaction/Deals]Ipay88 Object
	 * @param  Array $data 		update data (request data from ipay)
	 * @param  Boolean $response_only  Save response requery only, ignore other
	 * @param  Boolean $saveToLog  Save update data to log or not
	 */
	public function update($model,$data,$response_only = false,$saveToLog = true) {
		if($response_only){
			return $model->update(['requery_response'=>$data['requery_response']]);
		}
		DB::beginTransaction();
        switch ($data['type']) {
            case 'trx':
            	$trx = Transaction::with('user','outlet')->where('id_transaction',$model->id_transaction)->first();
                $mid = [
                    'order_id' => $trx['transaction_receipt_number'],
                    'gross_amount' => $model->amount
                ];
            	switch ($data['Status']) {
            		case '1':
	                    $update = $trx->update(['transaction_payment_status'=>'Completed','completed_at'=>date('Y-m-d H:i:s')]);
	                    if(!$update){
		                    DB::rollBack();
	                        return [
	                            'status'=>'fail',
	                            'messages' => ['Failed update payment status']
	                        ];
	                    }

				        $trx->load('outlet');
						$trx->load('productTransaction');

				        $send = app($this->notif)->notification($mid, $trx);
            			break;

            		case '6':
	                    $update = $trx->update(['transaction_payment_status'=>'Pending']);
	                    if(!$update){
		                    DB::rollBack();
	                        return [
	                            'status'=>'fail',
	                            'messages' => ['Failed update payment status']
	                        ];
	                    }
            			break;

            		case '0':
	                    $update = $trx->update(['transaction_payment_status'=>'Cancelled']);
		                $trx->load('outlet_name');
		                $send = app($this->notif)->notificationDenied($mid, $trx);

				        //return balance
				        $payBalance = TransactionMultiplePayment::where('id_transaction', $trx->id_transaction)->where('type', 'Balance')->first();
				        if (!empty($payBalance)) {
				            $checkBalance = TransactionPaymentBalance::where('id_transaction_payment_balance', $payBalance['id_payment'])->first();
				            if (!empty($checkBalance)) {
				                $insertDataLogCash = app("Modules\Balance\Http\Controllers\BalanceController")->addLogBalance($trx['id_user'], $checkBalance['balance_nominal'], $trx['id_transaction'], 'Transaction Failed', $trx['transaction_grandtotal']);
				                if (!$insertDataLogCash) {
				                    DB::rollBack();
				                    return response()->json([
				                        'status'    => 'fail',
				                        'messages'  => ['Insert Cashback Failed']
				                    ]);
				                }
				                $usere= User::where('id',$trx['id_user'])->first();
				                $send = app($this->autocrm)->SendAutoCRM('Transaction Failed Point Refund', $usere->phone,
				                    [
				                        "outlet_name"       => $trx['outlet_name']['outlet_name']??'',
				                        "transaction_date"  => $trx['transaction_date'],
				                        'id_transaction'    => $trx['id_transaction'],
				                        'receipt_number'    => $trx['transaction_receipt_number'],
				                        'received_point'    => (string) $checkBalance['balance_nominal']
				                    ]
				                );
				                if($send != true){
				                    DB::rollBack();
				                    return response()->json([
				                            'status' => 'fail',
				                            'messages' => ['Failed Send notification to customer']
				                        ]);
				                }
				            }
				        }

				        // delete promo campaign report
			            if ($trx->id_promo_campaign_promo_code) 
			            {
			            	$update_promo_report = app($this->promo_campaign)->deleteReport($trx->id_transaction, $trx->id_promo_campaign_promo_code);
			            }

			            // return voucher
			            $update_voucher = app($this->voucher)->returnVoucher($trx->id_transaction);

	                    if(!$update){
		                    DB::rollBack();
	                        return [
	                            'status'=>'fail',
	                            'messages' => ['Failed update payment status']
	                        ];
	                    }
            			break;

            		default:
            			# code...
            			break;
            	}
                break;

            case 'deals':
    			$deals_user = DealsUser::with('userMid')->where('id_deals_user',$model->id_deals_user)->first();
    			$deals = Deal::where('id_deals',$model->id_deals)->first();
            	switch ($data['Status']) {
            		case '1':
	                    $update = $deals_user->update(['paid_status'=>'Completed']);
	                    if(!$update){
		                    DB::rollBack();
	                        return [
	                            'status'=>'fail',
	                            'messages' => ['Failed update payment status']
	                        ];
	                    }
	                    $send = app($this->autocrm)->SendAutoCRM(
	                        'Payment Deals Success',
	                        $deals_user['userMid']['phone'],
	                        [
	                            'deals_title'       => $deals->title,
	                            'id_deals_user'     => $model->id_deals_user
	                        ]
	                    );
            			break;

            		case '6':
	                    $update = $deals_user->update(['paid_status'=>'Pending']);
	                    if(!$update){
		                    DB::rollBack();
	                        return [
	                            'status'=>'fail',
	                            'messages' => ['Failed update payment status']
	                        ];
	                    }
            			break;

            		case '0':
			            if($deals_user->balance_nominal){
			                $insertDataLogCash = app("Modules\Balance\Http\Controllers\BalanceController")->addLogBalance($deals_user->id_user, $deals_user->balance_nominal, $deals_user->id_deals_user, 'Claim Deals Failed');
			                if (!$insertDataLogCash) {
			                    DB::rollBack();
			                    return response()->json([
			                        'status'    => 'fail',
			                        'messages'  => ['Insert Cashback Failed']
			                    ]);
			                }
			            }
	                    $update = $deals_user->update(['paid_status'=>'Cancelled']);
	                    if(!$update){
		                    DB::rollBack();
	                        return [
	                            'status'=>'fail',
	                            'messages' => ['Failed update payment status']
	                        ];
	                    }
            			break;

            		default:
            			# code...
            			break;
            	}
                break;
            
            
            default:
                # code...
                break;
        }
        if(!$saveToLog){
        	DB::commit();
        	return 1;
        }
        $payment_method = $this->getPaymentMethod($data['PaymentId']);
		$forUpdate = [
	        'from_user' => $data['from_user']??0,
	        'from_backend' => $data['from_backend']??0,
	        'merchant_code' => $data['MerchantCode']??'',
	        'payment_id' => $data['PaymentId']??null,
	        'payment_method' => $payment_method,
	        'ref_no' => $data['RefNo'],
	        'amount' => $data['Amount'],
	        'currency' => $data['Currency'],
	        'remark' => $data['Remark']??null,
	        'trans_id' => $data['TransId']??null,
	        'auth_code' => $data['AuthCode']??null,
	        'status' => $data['Status']??'0',
	        'err_desc' => $data['ErrDesc']??null,
	        'signature' => $data['Signature']??'',
	        'xfield1' => $data['xfield1']??null,
	        'requery_response' => $data['requery_response']??''
		];
		$up = $model->update($forUpdate);
        DB::commit();
        $sendPOS = ($trx['id_transaction']??false)?\App\Lib\ConnectPOS::create()->sendTransaction([$trx['id_transaction']]):null;
        return $up;
	}
    function getHtml($trx, $item, $name, $phone, $date, $outlet, $receipt)
    {
        $setting = Setting::where('key', 'transaction_grand_total_order')->first();
        $order = $setting['value'];

        $exp   = explode(',', $order);
        $manna = [];

        for ($i=0; $i < count($exp); $i++) {
            if (substr($exp[$i], 0, 5) == 'empty') {
                unset($exp[$i]);
                continue;
            }

            if ($exp[$i] == 'subtotal') {
                $manna[$exp[$i]] = $trx['transaction_subtotal'];
            }

            if ($exp[$i] == 'tax') {
                $manna[$exp[$i]] = $trx['transaction_tax'];
            }

            if ($exp[$i] == 'discount') {
                $manna[$exp[$i]] = $trx['transaction_discount'];
            }

            if ($exp[$i] == 'service') {
                $manna[$exp[$i]] = $trx['transaction_service'];
            }

            if ($exp[$i] == 'shipping') {
                $manna[$exp[$i]] = $trx['transaction_shipment'];
            }
        }

    	$data = [
    		'trx' => $trx,
    		'item' => $item,
    		'name' => $name,
    		'phone' => $phone,
    		'date' => $date,
    		'outlet' => $outlet,
    		'receipt' => $receipt,
    		'manna' => $manna
    	];

        return view('ipay88::components.detail_transaction',$data)->render();
    }
    public function getPaymentId($payment_method){
    	return $this->payment_id[$payment_method]??null;
    }
    public function getPaymentMethod($payment_id,$pretty=true){
        $payment_method = array_flip($this->payment_id)[$payment_id]??null;
    	if($pretty){
	        $payment_method = $payment_method?str_replace('_', ' ', $payment_method):null;
    	}
    	return $payment_method;
    }
    /**
     * Cancel trx or deals
     * @param  String $type  'trx'/'deals'
     * @param  Model $model Transaction/DealsUser Model
     * @param  Array $errors Error message, if any
     * @return [type]        [description]
     */
    public function cancel($type,$model,&$errors=null){
		$errors = ['Payment in progress'];
    	switch($type){
    		case 'trx':
    			$model->load('transaction_payment_ipay88');
    			if(!$model->transaction_payment_ipay88){
					return false;
    			}
				$submitted = [
					'MerchantCode' => $model->transaction_payment_ipay88->merchant_code?:$this->merchant_code,
					'RefNo' => $model->transaction_receipt_number,
					'Amount' => $model->transaction_payment_ipay88->amount,
					'type' => 'cancel',
					'triggers' => 'user'
				];
			
    			$requery = $this->reQuery($submitted,'0');
    			if(in_array($requery['response'],['Record not found','Payment fail'])){
	    			$update = $this->update($model->transaction_payment_ipay88,[
	    				'type' =>'trx',
	    				'Status' => '0',
	    				'requery_response' => $requery['response']
	    			],false,false);
	    			if(!$update){
	    				$errors = ['Failed update transaction'];
	    				return false;
	    			}
	    			return true;
    			}
    			break;
    		case 'deals':
    			$model->load('deals_payment_ipay88');
    			if(!$model->deals_payment_ipay88){
					return false;
    			}
				$submitted = [
					'MerchantCode' => $model->deals_payment_ipay88->merchant_code?:$this->merchant_code,
					'RefNo' => $model->deals_payment_ipay88->order_id,
					'Amount' => $model->deals_payment_ipay88->amount,
					'type' => 'cancel',
					'triggers' => 'user'
				];
			
    			$requery = $this->reQuery($submitted,'0');
    			if(in_array($requery['response'],['Record not found','Payment fail'])){
	    			$update = $this->update($model->deals_payment_ipay88,[
	    				'type' =>'deals',
	    				'Status' => '0',
	    				'requery_response' => $requery['response']
	    			],false,false);
	    			if(!$update){
	    				$errors = ['Failed update voucher'];
	    				return false;
	    			}
	    			return true;
    			}
    			break;
    	}
    	return false;
    }
}
?>
