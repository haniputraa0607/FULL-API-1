<?php
namespace App\Lib;

use App\Http\Models\Transaction;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentCimb;
use App\Http\Models\TransactionPaymentOvo;

class ConnectPOS{
	public static $obj = null;
	/**
	 * Initialize Lib
	 */
	public function __construct() {
		$this->url = env('POS_API_URL');
		$this->secret = env('POS_SECRET_KEY');
	}
	/**
	 * Create object from static function
	 * @return ConnectPOS ConnectPOS Instance
	 */
	public static function create() {
		if(!self::$obj){
			self::$obj = new self();
		}
		return self::$obj;
	}

	/**
	 * return array of head section
	 * @param  string $destination 	Function end point name
	 * @return Array              	array of head section
	 */
	public function getHead($destination='/MobileReceiver/transaction') {
		return [
			'requestId'			=> 0,
			'requestName'		=> 'Mobile Apps',
			'requestDate'		=> date('Ymd His'),
			'requestDestination'=> $destination
		];
	}

	/**
	 * Signing request
	 * @param  Array $data request data
	 * @return void
	 */
	public function sign(&$data) {
		$head = $data['head'];
		// 3.Signature formula Md5(requestName + requestId + requestDate + requestDestination + secretKey + json_encode(body.content))
		$data['signature'] = md5($head['requestName'].$head['requestId'].$head['requestDate'].$head['requestDestination'].$this->secret.json_encode($data['body']));
	}

	/**
	 * send transaction to POS
	 * @param  Array<Integer> $id_transaction array of id_transaction
	 * @return boolean          true if success, otherwise false
	 */
	public function sendTransaction(...$id_transactions) {
		$trxDatas = Transaction::whereIn('transactions.id_transaction',$id_transactions)
		->join('transaction_pickups','transactions.id_transaction','=','transaction_pickups.id_transaction')
		->with(['user','products','products.product_group','products.product_variants'=>function($query){
			$query->orderBy('parent');
		},'outlet'])->get();
		// return $trxData;
		if(!$trxDatas){return false;}
		$item = [];
		foreach ($trxDatas as $trxData) {
			$user = $trxData['user'];
			$body = [
				'header' => [
					'orderNumber'=> $trxData->transaction_receipt_number, //receipt number
					'outletId'=> env('POS_OUTLET_OVERWRITE')?:$trxData->outlet->outlet_code, //outlet code
					// 'outletId'=> 'M030', //outlet code
					'bookingCode'=> $trxData->order_id,
					'businessDate'=> date('Ymd',strtotime($trxData->transaction_date)), //tgl trx
					'trxDate'=> date('Ymd',strtotime($trxData->transaction_date)), // tgl trx
					'trxStartTime'=> date('Ymd His',strtotime($trxData->transaction_date)), //created at
					'trxEndTime'=> date('Ymd His',strtotime($trxData->transaction_date)),// completed at
					'pax'=> count($trxData->products), // total item
					'orderType'=> 'take away', //take away
					'grandTotal'=> $trxData->transaction_grandtotal, //grandtotal
					'subTotal'=> $trxData->transaction_subtotal, //subtotal
					'tax'=> $trxData->transaction_tax, // transaction tax, ikut tabel
					'notes'=> '', // “”
					'appliedPromo'=> $trxData->id_promo_campaign_promo_code?'MOBILE APPS PROMO':'', // kalau pakai prromo / “”
					'pos'=> [ //hardcode
					'id'=> 1,
					'cashDrawer'=> 1,
					'cashierId'=> ''
				],
				'customer'=> [
						'id'=> MyHelper::createQR(time(), $trxData->user->phone), // uid
						'name'=> $trxData->user->name, //name
						'gender'=> $trxData->user->gender?:'', //gender / “”
						'age'=> $trxData->user->birthday?(date_diff(date_create($trxData->user->birthday), date_create('now'))->y):'', // age / “”
						'occupation'=> '' // “’
					]
				],
				'item' => []
			];
			foreach ($trxData->products as $key => $product) {
				// $tax = (10/100) * $product->pivot->transaction_product_subtotal;
				$tax = 0;
				$body['item'][] = [
					"number"=> $key+1, // key+1
					"menuId"=> $product->product_group->product_group_code, // product code
					"sapMatnr"=> $product->product_code, // product code
					"categoryId"=> 0, // ga ada / 0
					"qty"=> $product->pivot->transaction_product_qty, // qty
					"price"=> $product->pivot->transaction_product_price, // item price/ item
					"discount"=> $product->pivot->transaction_product_discount, // udah * qty
					"grossAmount"=> $product->pivot->transaction_product_subtotal, //grsndtotal /item
					"netAmount"=> $product->pivot->transaction_product_subtotal - $tax, // potong tAX 10%
					"tax"=> $tax, //10%
					"type"=> $product->product_variants[1]->product_variant_code == 'general_type'?null:$product->product_variants[1]->product_variant_code, //code variant /null
					"size"=> $product->product_variants[0]->product_variant_code == 'general_size'?null:$product->product_variants[0]->product_variant_code, // code variant /null
					"promoNumber"=> "", //kode voucher //null
					"promoType"=> "5", //hardcode //null
					"status"=> "ACTIVE" // hardcode
				];
				$payment = [];
		        //cek di multi payment
				$multi = TransactionMultiplePayment::where('id_transaction', $trxData->id_transaction)->get();
				if (!$multi) {
	            //cek di balance
					$balance = TransactionPaymentBalance::where('id_transaction', $trxData->id_transaction)->get();
					if ($balance) {
						foreach ($balance as $payBalance) {
							$pay = [
								'number'        => 1,
								'type'          => 'Points',
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
									'type'          => 'Midtrans',
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
									'type'          => 'Points',
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
									'type'          => 'Midtrans',
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
									'type'              => 'Ovo',
									'amount'            => (float) $ovo['amount'],
									'changeAmount'     => 0,
									'cardNumber'       => '',
									'cardOwner'        => '',
									'referenceNumber'  => $ovo['approval_code']??''
								];
								$payment[] = $pay;
							}
						} elseif ($payMulti['type'] == 'Cimb') {
							$cimb = TransactionPaymentCimb::find($payMulti['id_payment']);
							if ($cimb) {
								$pay = [
									'number'            => $key + 1,
									'type'              => 'Cimb',
									'amount'            => (float) $cimb['amount']??$check['transaction_grandtotal'],
									'changeAmount'     => 0,
									'cardNumber'       => '',
									'cardOwner'        => ''
								];
								$payment[] = $pay;
							}
						}
					}
				}
				$body['payment'] = $payment;
			}
			$item[] = $body;
		}
		$sendData = [
			'head' => $this->getHead(),
			'body' => $item
		];
		$this->sign($sendData);
		$response = MyHelper::post($this->url,null,$sendData);
		return $response;
	}
}