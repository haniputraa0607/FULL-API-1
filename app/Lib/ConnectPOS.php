<?php
namespace App\Lib;

use App\Http\Models\Transaction;
use App\Http\Models\TransactionVoucher;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentNobu;
use App\Http\Models\TransactionPaymentOvo;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use App\Http\Models\LogActivitiesPosTransactionsOnline;
use Modules\POS\Entities\LogActivitiesPosCancelTransactionOnline;
use App\Http\Models\TransactionOnlinePos;
use Modules\POS\Entities\TransactionOnlinePosCancel;
use App\Http\Models\Setting;
use Modules\Transaction\Entities\TransactionPaymentCimb;
use Modules\IPay88\Entities\TransactionPaymentIpay88;

use App\Jobs\SendPOS;
use App\Jobs\SendCancelPOS;

class ConnectPOS{
	public static $obj = null;
	public static $mappingPickup = [
		'GO-SEND' => 'MADGOJEK',
		'Grab' => 'MADGRAB',
		'Lalamove' => 'MADLALA',
		'Outlet' => 'MADMAXX'
	];

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
	 * Add to queue
	 * @param string $value [description]
	 */
	public function sendTransaction(...$id_transactions)
	{
		foreach ($id_transactions as $id_transaction) {
			$transaction = Transaction::select('transactions_online_pos.id_transaction', 'transaction_date', 'count_retry')->leftJoin('transactions_online_pos', 'transactions_online_pos.id_transaction', 'transactions.id_transaction')->where('transactions.id_transaction', $id_transaction)
				->where('sent_to_pos', '0')
				->first();
			if (!$transaction) {
				continue;
			}
			$queue = 'send_pos_jobs';
			if (($transaction->count_retry ?: 0) < 3 && (date('Y-m-d', strtotime($transaction->transaction_date)) == date('Y-m-d'))) {
				$queue = 'high';
			}
	        SendPOS::dispatch($id_transactions)->allOnConnection('send_pos_jobs')->onQueue($queue);
		}
        return true;
	}
	/**
	 * send transaction to POS
	 * @param  Integer $id_transactions one or more id_transaction separated by comma
	 * @return boolean          true if success, otherwise false
	 */
	public function doSendTransaction(...$id_transactions) {
		$module_url = '/MobileReceiver/transaction';
		$trxDatas = Transaction::whereIn('transactions.id_transaction',$id_transactions)
			->where('transaction_payment_status','Completed')
			->join('transaction_pickups','transactions.id_transaction','=','transaction_pickups.id_transaction')
			->with(['user','modifiers','modifiers.product_modifier','products','products.product_group','products.product_variants'=>function($query){
				$query->orderBy('parent');
			},'outlet','promo_campaign_promo_code'])
			->where('sent_to_pos', '0')
			->get();
		// return $trxData;
		if(!$trxDatas->count()){return true;}
		Transaction::whereIn('id_transaction', $trxDatas->pluck('id_transaction'))->update(['sent_to_pos' => 2]);
		$item = [];
		$users = [];
		$outlets = [];
		$transactions = [];
        $expired = Setting::where('key', 'qrcode_expired')->first();
        if (!$expired || ($expired && $expired->value == null)) {
            $expired = '10';
        } else {
            $expired = $expired->value;
        }
        $timestamp = strtotime('+' . $expired . ' minutes');
		foreach ($trxDatas as $trxData) {
			$user = $trxData['user'];
			$users[] = $user['phone'];
	        // $memberUid = MyHelper::createQRV2($timestamp, $user->id);
			$memberUid = $user['id'];
			if(strlen((string)$memberUid) < 8){
                $memberUid = "00000000".$memberUid;
                $memberUid = substr($memberUid, -8);
            }
			$transactions[$user['phone']] = $trxData->toArray();
			$transactions[$user['phone']]['transaction_object'] = $trxData;
			$transactions[$user['phone']]['outlet_name'] = $trxData->outlet->outlet_name;
			$outlets[] = env('POS_OUTLET_OVERWRITE')?:$trxData->outlet->outlet_code;
			$receive_at = $trxData->receive_at?:date('Y-m-d H:i:s');
			$vouchers = TransactionVoucher::where('id_transaction',$trxData->id_transaction)->get();
			$appliedPromo = '';
			$promoNumber = '';
			if($trxData->id_promo_campaign_promo_code || count($vouchers)){
				$appliedPromo = 'MOBILE APPS PROMO';
				// if($trxData->id_promo_campaign_promo_code){
				// 	$promoNumber = $trxData->promo_campaign_promo_code->promo_code;
				// }else{
				// 	$voucher->load('deals_voucher');
				// 	$promoNumber = $voucher->deals_voucher->voucher_code;
				// }
				$promoNumber = '01198';
				$hasPromoProduct = true;
				if (!$trxData->id_promo_campaign_promo_code) {
					$hasPromoProduct = false;
					foreach ($vouchers as $voucher) {
						$voucher->load('deals_voucher.deals');
						if (($voucher['deals_voucher']['deals']['promo_type'] ?? false) != 'Discount delivery') {
							$hasPromoProduct = true;
							break;
						}
					}
				}

				if (!$hasPromoProduct) {
					$appliedPromo = '';
					$promoNumber = '';
				}
			}

			$delivery = [];

			if ($trxData->pickup_by == 'GO-SEND') {
				$trxData->load('transaction_pickup_go_send');
				$deliveryData = $trxData->transaction_pickup_go_send;
				$delivery = [
					'address' => $deliveryData->destination_address,
					'latitude' => $deliveryData->destination_latitude, 
					'longitude' => $deliveryData->destination_longitude, 
					'courierName' => ConnectPOS::$mappingPickup[$trxData->pickup_by] ?? $trxData->pickup_by, 
					'deliveryCost' => $trxData->transaction_shipment, 
					'discDeliveryCost' => abs($trxData->transaction_discount_delivery), 
					'netDeliveryCost' => $trxData->transaction_shipment - abs($trxData->transaction_discount_delivery), 
					'promoDeliveryId' => '', 
					'promoDeliveryDesc' => '', 
					'note' => $deliveryData->destination_note ?: ''
				];

				if ($trxData->transaction_discount_delivery) {
					$promoDelivery = $trxData->getUsedPromoDelivery();
					$delivery['promoDeliveryId'] = $promoDelivery['promoDeliveryId'];
					$delivery['promoDeliveryDesc'] = $promoDelivery['promoDeliveryDesc'];
				}
			} elseif ($trxData->pickup_by == 'Outlet') {
				$trxData->load('transaction_pickup_outlet');
				$deliveryData = $trxData->transaction_pickup_outlet;
				$delivery = [
					'address' => $deliveryData->destination_address,
					'latitude' => $deliveryData->destination_latitude, 
					'longitude' => $deliveryData->destination_longitude, 
					'courierName' => ConnectPOS::$mappingPickup[$trxData->pickup_by] ?? $trxData->pickup_by, 
					'deliveryCost' => $trxData->transaction_shipment, 
					'discDeliveryCost' => abs($trxData->transaction_discount_delivery), 
					'netDeliveryCost' => $trxData->transaction_shipment - abs($trxData->transaction_discount_delivery), 
					'promoDeliveryId' => '', 
					'promoDeliveryDesc' => '', 
					'note' => $deliveryData->destination_note ?: ''
				];

				if ($trxData->transaction_discount_delivery) {
					$promoDelivery = $trxData->getUsedPromoDelivery();
					$delivery['promoDeliveryId'] = $promoDelivery['promoDeliveryId'];
					$delivery['promoDeliveryDesc'] = $promoDelivery['promoDeliveryDesc'];
				}
			}

			$body = [
				'header' => [
					'orderNumber'=> $trxData->transaction_receipt_number, //receipt number
					'outletId'=> env('POS_OUTLET_OVERWRITE')?:$trxData->outlet->outlet_code, //outlet code
					// 'outletId'=> 'M030', //outlet code
					'bookingCode'=> $trxData->order_id,
					'businessDate'=> date('Ymd',strtotime($trxData->transaction_date)), //tgl trx
					'trxDate'=> date('Ymd',strtotime($trxData->transaction_date)), // tgl trx
					'trxStartTime'=> date('Ymd His',strtotime($trxData->pickup_at?:$trxData->completed_at)), // pickup at
					'trxEndTime'=> date('Ymd His',strtotime($trxData->completed_at)),// completed at
					'pax'=> count($trxData->products), // total item
					'orderType'=> 'take away', //take away
					'grandTotal'=> $trxData->transaction_grandtotal, //grandtotal
					'subTotal'=> $trxData->transaction_grandtotal - $trxData->transaction_tax, //subtotal
					'tax'=> $trxData->transaction_tax, // transaction tax, ikut tabel
					'notes'=> '', // “”
					'appliedPromo'=> $appliedPromo, // kalau pakai prromo / “”
					'pos'=> [ //hardcode
						'id'=> 1,
						'cashDrawer'=> 1,
						'cashierId'=> ''
					],
					'customer'=> [
						// 'id'=> MyHelper::createQR(time(), $trxData->user->phone), // uid
						'id'=> $memberUid, // uid
						'name'=> $trxData->user['name'], //name
						'gender'=> $trxData->user['gender']?:'', //gender / “”
						'age'=> $trxData->user['birthday']?(date_diff(date_create($trxData->user['birthday']), date_create('now'))->y):'', // age / “”
						'occupation'=> '' // “’
					],
					'delivery' => $delivery,
				],
				'item' => []
			];
			$last = 0;
			$trx_modifier = [];
			foreach ($trxData->modifiers as $key => $modifier) {
				// $tax = (10/100) * $product->pivot->transaction_product_subtotal;
				$tax = 0;
				$trx_modifier[$modifier->id_transaction_product][] = [
					"menuId"=> $modifier->product_modifier->menu_id_pos, // product code
					"sapMatnr"=> $modifier->code, // product code
					"categoryId"=> $modifier->product_modifier->category_id_pos, // ga ada / 0
					"qty"=> $modifier->qty, // qty
					"price"=> (float) $modifier->transaction_product_modifier_price / $modifier->qty, // item price/ item
					"discount"=> 0, // udah * qty
					"grossAmount"=> $modifier->transaction_product_modifier_price, //grsndtotal
					"netAmount"=> $modifier->transaction_product_modifier_price - $tax, // potong tAX 10%
					"tax"=> $tax, //10%
					"type"=> null, //code variant /null
					"size"=> null, // code variant /null
					'note' => '',
					"promoNumber"=> $promoNumber, //kode voucher //null
					"promoType"=> $appliedPromo?"5":null, //hardcode //null
					"status"=> "ACTIVE" // hardcode
				];
			}
			foreach ($trxData->products as $key => $product) {
				// $tax = (10/100) * $product->pivot->transaction_product_subtotal;
				$tax = 0;
				$grossAmount = $product->pivot->transaction_product_subtotal - ($product->pivot->transaction_modifier_subtotal * $product->pivot->transaction_product_qty);
				$discountTotal = $product->pivot->transaction_product_discount;
				
				$modifierRatio = ($product->pivot->transaction_modifier_subtotal * $product->pivot->transaction_product_qty) / $product->pivot->transaction_product_subtotal;

				$discountModifier = (int) ($modifierRatio * $discountTotal);
				$discountProduct = $discountTotal - $discountModifier;

				$grossAmount -= $discountProduct;

				$body['item'][] = [
					"number"=> $last+1, // key+1
					"menuId"=> $product->product_group->product_group_code, // product code
					"sapMatnr"=> $product->product_code, // product code
					"categoryId"=> $product->category_id_pos, // ga ada / 0
					"qty"=> $product->pivot->transaction_product_qty, // qty
					"price"=> $product->pivot->transaction_product_price, // item price/ item
					"discount"=> $discountProduct, // udah * qty
					"grossAmount"=> $grossAmount, //grsndtotal
					"netAmount"=> $grossAmount - $tax, // potong tAX 10%
					"tax"=> $tax, //10%
					"type"=> $product->product_variants[1]->product_variant_code == 'general_type'?null:$product->product_variants[1]->product_variant_code, //code variant /null
					"size"=> $product->product_variants[0]->product_variant_code == 'general_size'?null:$product->product_variants[0]->product_variant_code, // code variant /null
					'note' => $product->pivot->transaction_product_note,
					"promoNumber"=> $promoNumber, //kode voucher //null
					"promoType"=> $appliedPromo?"5":"", //hardcode //null
					"status"=> "ACTIVE" // hardcode
				];
				$last++;
				if ($trx_modifier[$product->pivot->id_transaction_product] ?? false) {
					$remainingDiscount = $discountModifier;
					foreach ($trx_modifier[$product->pivot->id_transaction_product] as $index => $modifier) {
						if ($index == count($trx_modifier[$product->pivot->id_transaction_product]) - 1) { //last
							$appliedDiscount = $remainingDiscount;
						} else {
							$discountRatio = $modifier['grossAmount'] / $product->pivot->transaction_modifier_subtotal;
							$appliedDiscount = (int) ($discountRatio * $discountModifier);
							$remainingDiscount -= $appliedDiscount;
						}

						$grossAmountMod = $modifier['grossAmount'] * $product->pivot->transaction_product_qty;
						$grossAmountMod -= $appliedDiscount;

						$modifier['number'] = $last+1;
						$modifier['discount'] = $appliedDiscount;
						$modifier['qty'] = $modifier['qty'] * $product->pivot->transaction_product_qty;
						$modifier['grossAmount'] = $grossAmountMod;
						$modifier['netAmount'] = $grossAmountMod;
						$body['item'][] = $modifier;
						$last++;
					}
				}
			}
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
								'type'          => 'GOPAYMOB',
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
								'type'              => 'OVOMOB',
								'amount'            => (float) $ovo['amount'],
								'changeAmount'     => 0,
								'cardNumber'       => $ovo['phone'], // nomor telepon ovo
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
					} elseif ($payMulti['type'] == 'IPay88') {
						$ipay = TransactionPaymentIpay88::find($payMulti['id_payment']);
						if ($ipay) {
							$pay = [
								'number'            => $key + 1,
								'type'              => 'IPAYMOB',
								'amount'            => (float) $ipay['amount']/100,
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
								'type'              => 'SHOPEEMOB',
								'amount'            => (float) $shopeepay['amount']/100,
								'changeAmount'     => 0,
								'cardNumber'       => '',
								'cardOwner'        => '',
								'referenceNumber'  => $shopeepay['transaction_sn']
							];
							$payment[] = $pay;
						}
					} elseif ($payMulti['type'] == 'Nobu') {
						$nobu = TransactionPaymentNobu::find($payMulti['id_payment']);
						if ($nobu) {
							$pay = [
								'number'            => $key + 1,
								'type'              => 'NOBUMOB',
								'amount'            => (float) $nobu['gross_amount'],
								'changeAmount'     => 0,
								'cardNumber'       => '',
								'cardOwner'        => '',
								'referenceNumber'  => $nobu['no_transaction_reference']
							];
							$payment[] = $pay;
						}
					}
				}
			}
			$body['payment'] = $payment;
			$item[] = $body;
		}
		$sendData = [
			'head' => $this->getHead($module_url),
			'body' => $item
		];
		$this->sign($sendData);

		$top = TransactionOnlinePos::updateOrCreate(
			[
				'id_transaction' => $trxData['id_transaction']
			] , [
				'request' => json_encode($sendData),
				'response' => '',
				'id_transaction' => $trxData['id_transaction'],
				'count_retry' => (TransactionOnlinePos::where('id_transaction', $trxData['id_transaction'])->pluck('count_retry')->first() ?? 0) + 1,
			]
		);

		$response = MyHelper::postWithTimeout($this->url.$module_url,null,$sendData,0,null,65,false);
		$dataLog = [
			'url' 		        => $this->url.$module_url,
			'subject' 		    => 'POS Send Transaction',
			'outlet_code' 	    => implode(',',$outlets),
			'user' 		        => implode(',',$users),
			'request' 		    => json_encode($sendData),
			'response_status'   => ($response['status_code']??null),
			'response'   		=> json_encode($response),
			'ip' 		        => \Request::ip(),
			'useragent' 	    => \Request::header('user-agent')
		];
		$is_success = ($response['status_code']??false) == 200;
		if(!$is_success){
			Transaction::whereIn('id_transaction', $trxDatas->pluck('id_transaction'))->update(['sent_to_pos' => 0]);
			foreach ($users as $phone) {
				$variables = $transactions[$phone];
				if($top){
					$top->update([
						'request' => json_encode($sendData),
						'response' => json_encode($response),
						'count_retry'=>($top->count_retry+1),
						'success_retry_status'=>0,
						'send_email_status' => 0
					]);
				}else{
					$top = TransactionOnlinePos::create([
						'request' => json_encode($sendData),
						'response' => json_encode($response),
						'id_transaction' => $variables['id_transaction'],
						'count_retry' => 1
					]);
				}
				// replaced by 7mnt cron
				// if(app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('Transaction Online Failed Pos', $phone, $variables,null,true)){
				// 	TransactionOnlinePos::where('id_transaction',$variables['id_transaction'])->update(['send_email_status'=>1]);
				// }
			}
		}else{
			Transaction::whereIn('id_transaction', $trxDatas->pluck('id_transaction'))->update(['sent_to_pos' => 1]);
			TransactionPickup::whereIn('id_transaction',$id_transactions)->whereNull('receive_at')->update([
				'receive_at' => date('Y-m-d H:i:s')
			]);
			foreach ($users as $phone) {
				$variables = $transactions[$phone];
				$transaction = $variables['transaction_object'];
				unset($variables['transaction_object']);
				if ($transaction->should_cancel == 1) {
					$this->sendCancelOrder($transaction);
				}
				$top = TransactionOnlinePos::where('id_transaction',$variables['id_transaction'])->first();
				if($top){
					$top->update([
						'request' => json_encode($sendData),
						'response' => json_encode($response),
						'count_retry'=>($top->count_retry+1),
						'success_retry_status'=>1
					]);
				}else{
					$top = TransactionOnlinePos::create([
						'request' => json_encode($sendData),
						'response' => json_encode($response),
						'id_transaction' => $variables['id_transaction'],
						'count_retry' => 1,
						'success_retry_status'=>1
					]);
				}
			}
		}
		LogActivitiesPosTransactionsOnline::create($dataLog);
		return $is_success;
	}

	public function sendMail()
	{
		$trxs = TransactionOnlinePos::select('id_transaction')
			->where('success_retry_status',0)
			->take(50)
			->get();
		TransactionOnlinePos::whereIn('id_transaction', $trxs->pluck('id_transaction'))->update(['success_retry_status' => 2]); // success_retry_status == 2 : job masih jalan
		if (!$trxs) {
			return true;
		}
		$func = 'doSendTransaction';
		if (count($trxs) > 5) {
			$func = 'sendTransaction';
		}

		foreach($trxs as $trx) {
	        $send = \App\Lib\ConnectPOS::create()->$func($trx->id_transaction);
	        if(!$send){
	            \Log::error('Failed send transaction to POS');
	        }			
		}
        
		$trxs = TransactionOnlinePos::select('id_transaction_online_pos', 'outlet_code', 'outlet_name', 'transaction_date', 'transaction_receipt_number', 'name', 'phone', 'transactions.id_transaction')
			->join('transactions', 'transactions_online_pos.id_transaction', 'transactions.id_transaction')
			->where('success_retry_status',0)
			->where('send_email_status', 0)
			->join('users', 'users.id', 'transactions.id_user')
			->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
			->take(50)
			->get();
		if (!$trxs) {
			return true;
		}

		$variables = [
			'detail' => view('emails.send_pos_failed', compact('trxs'))->render()
		];
		if (app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('Transaction Online Failed Pos', $trxs->pluck('phone')->first(), $variables,null,true)) {
			if (\App\Http\Models\Autocrm::where('autocrm_title','=','Transaction Online Failed Pos')->where('autocrm_forward_toogle', '1')->exists()) {
				TransactionOnlinePos::whereIn('id_transaction_online_pos', $trxs->pluck('id_transaction_online_pos'))->update(['send_email_status' => 1]);
			}
		}
		return true;
	}

	/**
	 * start queue cancel order
	 * @param  Transaction/Integer $transaction Transaction model join transaction pickups or id_transaction
	 * @return boolean              true/false of send cancel transaction
	 */
	public function sendCancelOrder($transaction)
	{
		if (is_numeric($transaction)) {
			$transaction = Transaction::select('transactions.*', 'transaction_pickups.receive_at', 'transaction_online_pos_cancels.count_retry')
				->where('transactions.id_transaction', $transaction)
				->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
				->leftJoin('transaction_online_pos_cancels', 'transaction_online_pos_cancels.id_transaction', 'transactions.id_transaction')
				->first();
		} else {
			// retrieve database data to get the latest data
			$transaction = Transaction::select('transactions.*', 'transaction_pickups.receive_at', 'transaction_online_pos_cancels.count_retry')
				->where('transactions.id_transaction', $transaction->id_transaction)
				->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
				->leftJoin('transaction_online_pos_cancels', 'transaction_online_pos_cancels.id_transaction', 'transactions.id_transaction')
				->first();
		}
		if (!$transaction) {
			return false;
		}

		if ($transaction->receive_at == null) {
			$transaction->update(['should_cancel' => 1]);
			return true;
		}

		$queue = 'send_cancel_pos_jobs';
		if (($transaction->count_retry ?: 0) < 3 && (date('Y-m-d', strtotime($transaction->transaction_date)) == date('Y-m-d'))) {
			$queue = 'high';
		}
        SendCancelPOS::dispatch($transaction)->allOnConnection('send_cancel_pos_jobs')->onQueue($queue);
	}

	/**
	 * send a request to the post to notify the canceled transactions
	 * @param  Transaction/Integer $transaction Transaction model or id_transaction
	 * @return boolean              true/false of send cancel transaction
	 */
	public function doSendCancelOrder($transaction)
	{
		$module_url = '/MobileReceiver/transactionCancel';
		if (is_numeric($transaction)) {
			$transaction = Transaction::where('transactions.id_transaction', $transaction)
				->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
				->with('outlet', 'user')
				->first();
		} else {
			// retrieve database data to get the latest data
			$transaction = Transaction::where('transactions.id_transaction', $transaction->id_transaction)
				->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
				->with('outlet', 'user')
				->first();
		}

		if (!$transaction) {
			return false;
		}

		if ($transaction->receive_at == null) {
			$transaction->update(['should_cancel' => 1]);
			return true;
		}

		$cancelReason = $transaction->reject_reason;

		if (strpos(strtolower($cancelReason), 'rejected')) {
			$cancelReason = 'GOJEK DELIVERY REJECTED';
		} elseif (strpos(strtolower($cancelReason), 'cancelled')) {
			$cancelReason = 'GOJEK DELIVERY CANCELLED';
		} elseif (strpos(strtolower($cancelReason), 'no driver')) {
			$cancelReason = 'GOJEK DRIVER NOT FOUND';
		}

		$head_section = $this->getHead($module_url);
		$body_section = [
			'outletId'=> env('POS_OUTLET_OVERWRITE')?:$transaction->outlet->outlet_code, //outlet code
			'bookingCode' => $transaction->order_id,
			'businessDate' => date('Ymd',strtotime($transaction->transaction_date)),
			'trxDate' => date('Ymd',strtotime($transaction->transaction_date)),
			'cancelReason' => $cancelReason,
		];

		$sendData = [
			'head' => $head_section,
			'body' => $body_section,
		];

		$top = TransactionOnlinePosCancel::updateOrCreate(
			[
				'id_transaction' => $transaction['id_transaction'],
			] , [
				'request' => json_encode($sendData),
				'response' => '',
				'id_transaction' => $transaction['id_transaction'],
				'count_retry' => (TransactionOnlinePosCancel::where('id_transaction', $transaction['id_transaction'])->pluck('count_retry')->first() ?? 0) + 1,
			]
		);

		$response = MyHelper::postWithTimeout($this->url.$module_url,null,$sendData,0,null,30,false);
		$dataLog = [
			'url' 		        => $this->url.$module_url,
			'subject' 		    => 'POS Send Cancel Transaction',
			'outlet_code' 	    => $transaction->outlet->outlet_code,
			'user' 		        => $transaction->user->phone,
			'request' 		    => json_encode($sendData),
			'response_status'   => ($response['status_code']??null),
			'response'   		=> json_encode($response),
			'ip' 		        => \Request::ip(),
			'useragent' 	    => \Request::header('user-agent')
		];

		$is_success = ($response['status_code']??false) == 200;
		if(!$is_success){
			if($top){
				$top->update([
					'request' => json_encode($sendData),
					'response' => json_encode($response),
					'count_retry'=>($top->count_retry+1),
					'success_retry_status'=>0,
					'send_email_status' => 0
				]);
			}else{
				$top = TransactionOnlinePosCancel::create([
					'request' => json_encode($sendData),
					'response' => json_encode($response),
					'id_transaction' => $variables['id_transaction'],
					'count_retry' => 1
				]);
			}
		}else{
			$transaction->update(['should_cancel' => 2]);
			$top = TransactionOnlinePosCancel::where('id_transaction',$transaction['id_transaction'])->first();
			if($top){
				$top->update([
					'request' => json_encode($sendData),
					'response' => json_encode($response),
					'count_retry'=>($top->count_retry+1),
					'success_retry_status'=>1
				]);
			}else{
				$top = TransactionOnlinePosCancel::create([
					'request' => json_encode($sendData),
					'response' => json_encode($response),
					'id_transaction' => $transaction['id_transaction'],
					'count_retry' => 1,
					'success_retry_status'=>1
				]);
			}
		}
		LogActivitiesPosCancelTransactionOnline::create($dataLog);
		return $is_success;
	}

	public function sendMailCancel()
	{
		$trxs = TransactionOnlinePosCancel::select('id_transaction')
			->where('success_retry_status',0)
			->take(50)
			->get();
		TransactionOnlinePosCancel::whereIn('id_transaction', $trxs->pluck('id_transaction'))->update(['success_retry_status' => 2]); // success_retry_status == 2 : job masih jalan
		if (!$trxs) {
			return true;
		}
		$func = 'doSendCancelOrder';
		if (count($trxs) > 20) {
			$func = 'sendCancelOrder';
		}

		foreach($trxs as $trx) {
	        $send = \App\Lib\ConnectPOS::create()->$func($trx->id_transaction);
	        if(!$send){
	            \Log::error("Failed send cancel transaction to POS [id_transaction = $trx->id_transaction]");
	        }
		}
        
		$trxs = TransactionOnlinePosCancel::select('id_transaction_online_pos_cancel', 'outlet_code', 'outlet_name', 'transaction_date', 'transaction_receipt_number', 'name', 'phone', 'transactions.id_transaction')
			->join('transactions', 'transactions_online_pos.id_transaction', 'transactions.id_transaction')
			->where('success_retry_status',0)
			->where('send_email_status', 0)
			->join('users', 'users.id', 'transactions.id_user')
			->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
			->take(50)
			->get();
		if (!$trxs) {
			return true;
		}

		$variables = [
			'detail' => view('emails.send_pos_failed', compact('trxs'))->render()
		];
		if (app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('Cancel Transaction Online Failed Pos', $trxs->pluck('phone')->first(), $variables,null,true)) {
			if (\App\Http\Models\Autocrm::where('autocrm_title','=','Transaction Online Failed Pos')->where('autocrm_forward_toogle', '1')->exists()) {
				TransactionOnlinePos::whereIn('id_transaction_online_pos_cancel', $trxs->pluck('id_transaction_online_pos_cancel'))->update(['send_email_status' => 1]);
			}
		}
		return true;
	}

}