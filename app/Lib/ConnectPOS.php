<?php
namespace App\Lib;

use App\Http\Models\Transaction;

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
	public function getHead($destination='/pos/transactionReceiver') {
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
	 * @param  Integer $id_transaction Array of transaction data
	 * @return boolean          true if success, otherwise false
	 */
	public function sendTransaction($id_transaction) {
		$trxData = Transaction::where('id_transaction',$id_transaction)->with(['user','products','products.product_variants'=>function($query){
			$query->orderBy('parent');
		},'outlet'])->first();
		// return $trxData;
		if(!$trxData){return false;}
		$body = [
			'header' => [
				'orderNumber'=> $trxData->transaction_receipt_number, //receipt number
				'outletId'=> $trxData->outlet->outlet_code, //outlet code
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
			$tax = (10/100) * $product->pivot->transaction_product_subtotal;
			$body['item'][] = [
				"number"=> $key+1, // key+1
				"sapMatnr"=> $product->product_code, // product code
				// "categoryId"=> 0, // ga ada / 0
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
		}
		foreach ($trxData->transaction_payments?:[] as $key => $payment) {
			$body['payment'][] = [
				"number"=> 1,
				"type"=> "CASH", //cimb/ovo/point
				"amount"=> 50000, //jumlah
				"changeAmount"=> 4000,//0
				"cardNumber"=> "", // ovo no hp // cimb ? // point “”
				"cardOwner"=> "", // ovo=> fullname, point nama customer
				"referenceNumber"=> "" // approval code ovo
			];
		}
		$sendData = [
			'head' => $this->getHead(),
			'body' => $body
		];
		$this->sign($sendData);
		$response = MyHelper::post($this->url,null,$sendData);
		return $response;
	}
}