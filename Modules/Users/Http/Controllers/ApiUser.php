<?php

namespace Modules\Users\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\User;
use App\Http\Models\UserFeature;
use App\Http\Models\UserDevice;
use App\Http\Models\Level;
use App\Http\Models\Doctor;
use App\Http\Models\UserOutlet;
use App\Http\Models\LogRequest;
use App\Http\Models\UserInbox;
use App\Http\Models\LogPoint;
use App\Http\Models\UserNotification;
use App\Http\Models\Transaction;
use App\Http\Models\FraudSetting;

use Modules\Users\Http\Requests\users_list;
use Modules\Users\Http\Requests\users_forgot;
use Modules\Users\Http\Requests\users_phone;
use Modules\Users\Http\Requests\users_phone_pin;
use Modules\Users\Http\Requests\users_phone_pin_new;
use Modules\Users\Http\Requests\users_phone_pin_new_admin;
use Modules\Users\Http\Requests\users_new;
use Modules\Users\Http\Requests\users_create;
use Modules\Users\Http\Requests\users_profile;
use Modules\Users\Http\Requests\users_profile_admin;
use Modules\Users\Http\Requests\users_notification;

use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;
use Mail;
use Auth;

class ApiUser extends Controller
{
	function __construct() {
        date_default_timezone_set('Asia/Jakarta');
		$this->home     = "Modules\Users\Http\Controllers\ApiHome";
		if(\Module::collections()->has('Autocrm')) {
			$this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
		}
		$this->membership  = "Modules\Membership\Http\Controllers\ApiMembership";
		$this->inbox  = "Modules\InboxGlobal\Http\Controllers\ApiInbox";
        $this->setting_fraud = "Modules\SettingFraud\Http\Controllers\ApiSettingFraud";
    }
	
	function LogActivityFilter($rule='and', $conditions = null, $order_field='id', $order_method='asc', $skip=0, $take=999999999999){
		$query = DB::table(env('DB2_DATABASE').'.log_activities as t_log_activities')->select('t_log_activities.*',
													't_users.name',
													't_users.email'
													)
					->leftJoin(env('DB_DATABASE').'.users as t_users','t_users.phone','=','t_log_activities.phone')
					
					->orderBy($order_field, $order_method);
		
		if($conditions != null){
			foreach($conditions as $condition){
				if(isset($condition['subject'])){
					if($condition['subject'] == 'name' || $condition['subject'] == 'email'){
						$var = "t_users.".$condition['subject'];
						
						if($rule == 'and'){
							if($condition['operator'] == 'like')
								$query = $query->where($var,'like','%'.$condition['parameter'].'%');
							else
								$query = $query->where($var,'=',$condition['parameter']);
						} else {
							if($condition['operator'] == 'like')
								$query = $query->orWhere($var,'like','%'.$condition['parameter'].'%');
							else
								$query = $query->orWhere($var,'=',$condition['parameter']);
						}
					}
					
					if($condition['subject'] == 'phone' || $condition['subject'] == 'url' || $condition['subject'] == 'subject' || $condition['subject'] == 'request' || $condition['subject'] == 'response' || $condition['subject'] == 'ip' || $condition['subject'] == 'useragent'){
						$var = "t_log_activities.".$condition['subject'];
						
						if($rule == 'and'){
							if($condition['operator'] == 'like')
								$query = $query->where($var,'like','%'.$condition['parameter'].'%');
							else
								$query = $query->where($var,'=',$condition['parameter']);
						} else {
							if($condition['operator'] == 'like')
								$query = $query->orWhere($var,'like','%'.$condition['parameter'].'%');
							else
								$query = $query->orWhere($var,'=',$condition['parameter']);
						}
					}
					
					if($condition['subject'] == 'response_status'){
						$var = "t_log_activities.".$condition['subject'];
						
						if($rule == 'and'){
							$query = $query->where($var,'=',$condition['operator']);
						} else {
							$query = $query->orWhere($var,'=',$condition['operator']);
						}
					}
				} 
			}
		}
		
		$hasil = $query->skip($skip)->take($take)->get()->toArray();
		$hasilcount = $query->count();
		
		if($hasil){
			$result = ['status'	=> 'success',
                       'result'	=> $hasil,
					   'total' => $hasilcount
					];
		} else{
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['Log Activity Not Found']
						];
		}
		
		return $result;
	}
	
	function UserFilter($conditions = null, $order_field='id', $order_method='asc', $skip=0, $take=99999999999){
		$hasilSebelum = [];
		$hasilAkhir = [];
		// return $conditions;
		if($conditions != null){
			$key = 0;
			foreach ($conditions as $cond) {
				$query = User::leftJoin('cities','cities.id_city','=','users.id_city')
				->leftJoin('provinces','provinces.id_province','=','cities.id_province')
				->orderBy($order_field, $order_method);
				
				$notProduct = false;
				$notOutlet = false;

				if($cond != null){
					$scanTrx = false;
					$scanProd = false;
					$scanTag = false;
					$notTrx = false;
					$userTrxProduct = false;
					$exceptUserTrxProduct = false;
					$scanOtherProduct = false;
					
					$rule = $cond['rule'];
					unset($cond['rule']);
					$conRuleNext = $cond['rule_next'];
					unset($cond['rule_next']);
					
					if(isset($cond['rules'])){
						$cond = $cond['rules'];
					}

					$countTrxDate = 0;
					foreach($cond as $i => $condition){
						if(stristr($condition['subject'], 'trx')) $scanTrx = true;
						if(stristr($condition['subject'], 'trx_product')) $scanProd = true;
						if(stristr($condition['subject'], 'trx_product_tag')) $scanTag = true;

						if($condition['subject'] == 'trx_count' && ($condition['operator'] == '=' || $condition['operator'] == '<' || $condition['operator'] == '<=') && $condition['parameter'] <= 0){
							$notTrx = true;
							unset($cond[$i]);
						}

						if($condition['subject'] == 'trx_product_not'){
							$notProduct = true;
						}

						if($condition['subject'] == 'trx_outlet_not'){
							$notOutlet = true;
						}

						if($condition['subject'] == 'trx_product' || $condition['subject'] == 'trx_product_count' || $condition['subject'] == 'trx_product_tag' || $condition['subject'] == 'trx_product_tag_count'){
							$userTrxProduct = true;
						}elseif($condition['subject']  != 'trx_date'){
							if(stristr($condition['subject'], 'trx')){
								$scanOtherProduct = true;
							}
						}
						if($condition['subject'] == 'trx_date'){
							$countTrxDate++;
							if($condition['parameter'] == date('Y-m-d') && ($condition['operator'] == '<=')){
								// $exceptUserTrxProduct == false;
							}else{
								$exceptUserTrxProduct = true;
							}
						}
						
						if($condition['subject'] == 'last_trx_date'){
							$exceptUserTrxProduct = true;
						}
						
						
					}
					
					if($exceptUserTrxProduct == true || $countTrxDate > 1){
						$userTrxProduct = false;
					}

					if($scanTrx == true){
						if($userTrxProduct == true){
							if($scanOtherProduct == true){
								if($scanTag == true){
									$query = $query->leftJoin('transactions','transactions.id_user','=','users.id');
									$query = $query->leftJoin('transaction_shipments','transactions.id_transaction','=','transaction_shipments.id_transaction');
									$query = $query->leftJoin('user_trx_products','users.id','=','user_trx_products.id_user');
									$query = $query->leftJoin('product_tags','product_tags.id_product','=','user_trx_products.id_product');
									$query = $query->groupBy('users.id');
									$query = $query->select('users.*',
													'cities.*',
													'provinces.*',
													DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age'),
													DB::raw('count(*) as total')
												);
								}else{
									$query = $query->leftJoin('transactions','transactions.id_user','=','users.id');
									$query = $query->leftJoin('transaction_shipments','transactions.id_transaction','=','transaction_shipments.id_transaction');
									$query = $query->leftJoin('user_trx_products','transactions.id_user','=','user_trx_products.id_user');
									$query = $query->groupBy('users.id');
									$query = $query->select('users.*',
													'cities.*',
													'provinces.*',
													DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age'),
													DB::raw('count(*) as total')
												);
								}
							}else{
								if($scanTag == true){
									$query = $query->leftJoin('user_trx_products','users.id','=','user_trx_products.id_user');
									$query = $query->leftJoin('product_tags','product_tags.id_product','=','user_trx_products.id_product');
									$query = $query->groupBy('users.id');
									$query = $query->select('users.*',
													'cities.*',
													'provinces.*',
													DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age'),
													DB::raw('count(*) as total')
												);
								}else{
									$query = $query->leftJoin('user_trx_products','users.id','=','user_trx_products.id_user');
									$query = $query->groupBy('users.id');
									$query = $query->select('users.*',
													'cities.*',
													'provinces.*',
													DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age'),
													DB::raw('count(*) as total')
												);
								}
							}

						}elseif($scanProd == true){
							if($scanTag == true){
								$query = $query->leftJoin('transaction_products','users.id','=','transaction_products.id_user');
								$query = $query->leftJoin('transactions','transactions.id_transaction','=','transaction_products.id_transaction');
								$query = $query->leftJoin('transaction_shipments','transactions.id_transaction','=','transaction_shipments.id_transaction');
								$query = $query->leftJoin('products','transaction_products.id_product','=','products.id_product');
								$query = $query->leftJoin('product_tags','products.id_product','=','product_tags.id_product');
								$query = $query->groupBy('users.id');
								$query = $query->select('users.*',
											'cities.*',
											'provinces.*',
											DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age'),
											DB::raw('count(*) as total')
										);
							} else{
								$query = $query->leftJoin('transaction_products','users.id','=','transaction_products.id_user');
								$query = $query->leftJoin('transactions','transactions.id_transaction','=','transaction_products.id_transaction');
								$query = $query->leftJoin('transaction_shipments','transactions.id_transaction','=','transaction_shipments.id_transaction');
								$query = $query->groupBy('users.id');
								$query = $query->select('users.*',
											'cities.*',
											'provinces.*',
											DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age'),
											DB::raw('count(*) as total')
											);
							}
						} else {
							$query = $query->leftJoin('transactions','transactions.id_user','=','users.id');
							$query = $query->leftJoin('transaction_shipments','transactions.id_transaction','=','transaction_shipments.id_transaction');
							$query = $query->groupBy('users.id');
							$query = $query->select('users.*',
										'cities.*',
										'provinces.*',
										DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age'),
										DB::raw('count(*) as total')
										);
						}
					} else {
						$query = $query->select('users.*',
									'cities.*',
									'provinces.*',
									DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age')
									);
					}

					if($notTrx){
						$query = $query->whereDoesntHave('transactions', function ($q) use ($cond, $rule, $userTrxProduct) {
							$q = $this->queryFilter($cond, $rule, $q, $userTrxProduct);
						});
					}else{
						$query = $this->queryFilter($cond, $rule, $query, $userTrxProduct);
					}
					// $query = $query->select('users.id');
				}else {
					$query = $query->select('users.*',
								'cities.*',
								'provinces.*',
								DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age')
								);
				}
				
				if($notProduct){
					$exceptUser = array_pluck($query->get()->toArray(),'id');
					$query = User::leftJoin('cities','cities.id_city','=','users.id_city')
							->leftJoin('provinces','provinces.id_province','=','cities.id_province')
							->orderBy($order_field, $order_method)
							->select('users.*',
								'cities.*',
								'provinces.*',
								DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age')
								)
							->whereNotIn('users.id', $exceptUser);
							
				}

				if($notOutlet){
					$exceptUser = array_pluck($query->get()->toArray(),'id');
					$query = User::leftJoin('cities','cities.id_city','=','users.id_city')
							->leftJoin('provinces','provinces.id_province','=','cities.id_province')
							->orderBy($order_field, $order_method)
							->select('users.*',
								'cities.*',
								'provinces.*',
								DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age')
								)
							->whereNotIn('users.id', $exceptUser);
				}
				$hasil = array_pluck($query->get()->toArray(),'id');
				
				if($key > 0){
					if($ruleNext == 'and'){
						$hasilSebelum = array_intersect($hasil,$hasilSebelum);
					}else{
						$hasilSebelum = array_unique(array_merge($hasil, $hasilSebelum));
					}
					$ruleNext = $conRuleNext;
				}else{
					$hasilSebelum = $hasil;
					$ruleNext = $conRuleNext;
				}
			
				$key++;
			}
			$hasilAkhir = User::leftJoin('cities','cities.id_city','=','users.id_city')
						->leftJoin('provinces','provinces.id_province','=','cities.id_province')
						->orderBy($order_field, $order_method)
						->select('users.*',
							'cities.*',
							'provinces.*',
							DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age')
							)
						->whereIn('users.id', $hasilSebelum);
		}else {
			$query = User::leftJoin('cities','cities.id_city','=','users.id_city')
				->leftJoin('provinces','provinces.id_province','=','cities.id_province')
				->orderBy($order_field, $order_method);
			$hasilAkhir = $query->select('users.*',
						'cities.*',
						'provinces.*',
						DB::raw('YEAR(CURDATE()) - YEAR(users.birthday) AS age')
						);
		}
		// return $hasilAkhir->get();
			// return $query->toSql();
		$hasilcount = count($hasilAkhir->get());
		$hasil = $hasilAkhir->skip($skip)->take($take)->get()->toArray();

		if($hasil){
			$result = ['status'	=> 'success',
                       'result'	=> $hasil,
					   'total' => $hasilcount
					];
		} else{
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['User Not Found']
						];
		}
		
		return $result;
	}

	function queryFilter($conditions, $rule, $query, $userTrxProduct){
		
		foreach($conditions as $index => $condition){
			if(isset($condition['subject'])){
				if($condition['subject'] == 'name' || $condition['subject'] == 'phone' || $condition['subject'] == 'email' || $condition['subject'] == 'address'){
					$var = "users.".$condition['subject'];
					
					if($rule == 'and'){
						if($condition['operator'] == 'like')
							$query = $query->where($var,'like','%'.$condition['parameter'].'%');
						else
							$query = $query->where($var,'=',$condition['parameter']);
					} else {
						if($condition['operator'] == 'like')
							$query = $query->orWhere($var,'like','%'.$condition['parameter'].'%');
						else
							$query = $query->orWhere($var,'=',$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'gender' || $condition['subject'] == 'is_suspended' || $condition['subject'] == 'email_verified' || $condition['subject'] == 'phone_verified' || $condition['subject'] == 'email_unsubscribed' || $condition['subject'] == 'provider' || $condition['subject'] == 'city_name' || $condition['subject'] == 'city_postal_code' || $condition['subject'] == 'province_name' || $condition['subject'] == 'level'){
					if($condition['subject'] == 'city_name' || $condition['subject'] == 'city_postal_code') $var = "cities.".$condition['subject'];
					else if($condition['subject'] == 'province_name') $var = "provinces.".$condition['subject'];
					else $var = "users.".$condition['subject'];
					
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}

					if($rule == 'and'){
						$query = $query->where($var,'=',$condition['parameter']);
					} else {
						$query = $query->orWhere($var,'=',$condition['parameter']);
					}
				}

				if($condition['subject'] == 'device'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					
					if($condition['parameter'] == 'None'){
						if($rule == 'and'){
							$query = $query->whereNull('users.android_device');
							$query = $query->whereNull('users.ios_device');
						} else {
							$query = $query->orWhereNull('users.android_device');
							$query = $query->orWhereNull('users.ios_device');
						}
					}
					
					if($condition['parameter'] == 'Android'){
						if($rule == 'and'){
							$query = $query->whereNotNull('users.android_device');
							$query = $query->whereNull('users.ios_device');
						} else {
							$query = $query->orwhereNotNull('users.android_device');
							$query = $query->orWhereNull('users.ios_device');
						}
					}
					
					if($condition['parameter'] == 'IOS'){
						if($rule == 'and'){
							$query = $query->notNull('users.android_device');
							$query = $query->whereNotNull('users.ios_device');
						} else {
							$query = $query->orwhereNull('users.android_device');
							$query = $query->orwhereNotNull('users.ios_device');
						}
					}
					
					if($condition['parameter'] == 'Both'){
						if($rule == 'and'){
							$query = $query->whereNotNull('users.android_device');
							$query = $query->whereNotNull('users.ios_device');
						} else {
							$query = $query->orwhereNotNull('users.android_device');
							$query = $query->orwhereNotNull('users.ios_device');
						}
					}
				}
				
				if($condition['subject'] == 'age'){
					if($rule == 'and'){
						$query = $query->whereRaw(DB::raw('timestampdiff(year,users.birthday,curdate()) '.$condition['operator'].' '.$condition['parameter']));
					} else {
						$query = $query->orWhereRaw(DB::raw('timestampdiff(year,users.birthday,curdate()) '.$condition['operator'].' '.$condition['parameter']));
					}
				}
				
				if($condition['subject'] == 'birthday_date'){
					$var = 'users.birthday';
					if($rule == 'and'){
						$query = $query->where($var, $condition['operator'], $condition['parameter']);
					} else {
						$query = $query->orWhere($var, $condition['operator'], $condition['parameter']);
					}
				}

				if($condition['subject'] == 'birthday_month'){
					$var = 'users.birthday';
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						$query = $query->whereMonth($var,'=',$condition['parameter']);
					} else {
						$query = $query->orWhereRaw('MONTH('.$var.')='.$condition['parameter']);
					}
				}

				if($condition['subject'] == 'birthday_year'){
					$var = 'users.birthday';
					if($rule == 'and'){
						$query = $query->whereYear($var, $condition['operator'], $condition['parameter']);
					} else {
						$query = $query->orWhereRaw('YEAR('.$var.')='.$condition['parameter']);
					}
				}

				if($condition['subject'] == 'birthday_today'){
					$var = 'users.birthday';
					if($rule == 'and'){
						$query = $query->where(function ($query) use ($var){
							$query->whereDay($var, '=', date('d'))
								  ->whereMonth($var, '=', date('m'));
						});
					} else {
						$query = $query->orWhere(function ($query) use ($var){
							$query->whereDay($var, '=', date('d'))
								  ->whereMonth($var, '=', date('m'));
						});
					}
				}

				if($condition['subject'] == 'membership'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						$query = $query->where('users.id_membership','=',$condition['parameter']);
					} else {
						$query = $query->orWhere('users.id_membership','=',$condition['parameter']);
					}
				}

				if($condition['subject'] == 'points'){
					if($rule == 'and'){
						$query = $query->where('users.points',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('users.points',$condition['operator'],$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'balance'){
					if($rule == 'and'){
						$query = $query->where('users.balance',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('users.balance',$condition['operator'],$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'register_date'){
					if($rule == 'and'){
						$query = $query->where('users.created_at',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('users.created_at',$condition['operator'],$condition['parameter']);
					}
				}

				if($condition['subject'] == 'register_today'){
					$today = date('Y-m-d');
					if($rule == 'and'){
						$query = $query->where('users.created_at', '=', $today);
					} else {
						$query = $query->orWhere('users.created_at', '=', $today);
					}
				}
				
				
				if($condition['subject'] == 'trx_type' || $condition['subject'] == 'trx_shipment_courier' || $condition['subject'] == 'trx_payment_type' || $condition['subject'] == 'trx_payment_status'){
					if($condition['subject'] == 'trx_type') 
						$var = "transactions.trasaction_type";
					if($condition['subject'] == 'trx_payment_type') 
						$var = "transactions.trasaction_payment_type";
					if($condition['subject'] == 'trx_payment_status') 
						$var = "transactions.transaction_payment_status";
					if($condition['subject'] == 'trx_shipment_courier') 
						$var = "transaction_shipments.shipment_courier";
					
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}

					if($rule == 'and'){
						$query = $query->where($var,'=',$condition['parameter']);
					} else {
						$query = $query->orWhere($var,'=',$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_date'){
					if($rule == 'and'){
						if($userTrxProduct == false){
							$query = $query->where('transactions.transaction_date',$condition['operator'],$condition['parameter']);
						}
					} else {
						if($userTrxProduct == false){
							$query = $query->orWhere('transactions.transaction_date',$condition['operator'],$condition['parameter']);
						}
					}
				}
				
				if($condition['subject'] == 'last_trx'){
					$condition['parameter'] = (int)$condition['parameter'] - 1;
					$lastDate = date('Y-m-d', strtotime('-'.$condition['parameter'].' days'));
					// return $condition['operator'];
					if($rule == 'and'){
						$query = $query->whereDate('transactions.transaction_date',$condition['operator'],$lastDate);
					} else {
						$query = $query->orWhereDate('transactions.transaction_date',$condition['operator'],$lastDate);
					}
				}
				
				if($condition['subject'] == 'trx_outlet'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						$query = $query->where('transactions.id_outlet','=',$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.id_outlet','=',$condition['parameter']);
					}
				}

				if($condition['subject'] == 'trx_outlet_not'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						$query = $query->where('transactions.id_outlet','=',$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.id_outlet','=',$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_count'){
					if($condition['parameter'] != 0){
						if($rule == 'and'){
							$query = $query->havingRaw('COUNT(*) '.$condition['operator'].' '.$condition['parameter'].'');
						} else {
							$query = $query->orHavingRaw('COUNT(*) '.$condition['operator'].' '.$condition['parameter'].'');
						}
					}else{
						if($condition['parameter'] == 0 && $condition['operator'] == '>'){
							if($rule == 'and'){
								$query = $query->whereNotNull('transactions.id_transaction');
							} else {
								$query = $query->orWhereNotNull('transactions.id_transaction');
							}	
						}
					}
				}
				
				if($condition['subject'] == 'trx_subtotal'){
					if($rule == 'and'){
						$query = $query->where('transactions.transaction_subtotal',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.transaction_subtotal',$condition['operator'],$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_tax'){
					if($rule == 'and'){
						$query = $query->where('transactions.transaction_tax',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.transaction_tax',$condition['operator'],$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_service'){
					if($rule == 'and'){
						$query = $query->where('transactions.transaction_service',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.transaction_service',$condition['operator'],$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_discount'){
					if($rule == 'and'){
						$query = $query->where('transactions.transaction_discount',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.transaction_discount',$condition['operator'],$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_payment_type'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						$query = $query->where('transactions.trasaction_payment_type','=',$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.trasaction_payment_type','=',$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_payment_status'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						$query = $query->where('transactions.transaction_payment_status','=',$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.transaction_payment_status','=',$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_void_count'){
					if($rule == 'and'){
						$query = $query->whereNotNull('transactions.void_date')->havingRaw('COUNT(*) '.$condition['operator'].' '.$condition['parameter']);
					} else {
						$query = $query->orWhereNotNull('transactions.void_date')->havingRaw('COUNT(*) '.$condition['operator'].' '.$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_shipment_value'){
					if($rule == 'and'){
						$query = $query->where('transactions.transaction_shipment',$condition['operator'],$condition['parameter']);
					} else {
						$query = $query->orWhere('transactions.transaction_shipment',$condition['operator'],$condition['parameter']);
					}
				}
				
				if($condition['subject'] == 'trx_product'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						if($userTrxProduct == true){
							$query = $query->where('user_trx_products.id_product','=',$condition['parameter']);
						}else{
							$query = $query->where('transaction_products.id_product','=',$condition['parameter']);
						}
					} else {
						if($userTrxProduct == true){
							$query = $query->orWhere('user_trx_products.id_product','=',$condition['parameter']);
						}else{
							$query = $query->orWhere('transaction_products.id_product','=',$condition['parameter']);
						}
					}
				}

				if($condition['subject'] == 'trx_product_not'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						if($userTrxProduct == true){
							$query = $query->where('user_trx_products.id_product','=',$condition['parameter']);
						}else{
							$query = $query->where('transaction_products.id_product','=',[$condition['parameter']]);
						}
					} else {
						if($userTrxProduct == true){
							$query = $query->orWhere('user_trx_products.id_product','=',$condition['parameter']);
						}else{
							$query = $query->orWhere('transaction_products.id_product','=',[$condition['parameter']]);
						}
					}
				}

				if($condition['subject'] == 'trx_product_count'){
					if($userTrxProduct == true){
						$query = $query->havingRaw('SUM(user_trx_products.product_qty)'.$condition['operator'].$condition['parameter']);
					}else{
						$query = $query->havingRaw('SUM(transaction_products.transaction_product_qty)'.$condition['operator'].$condition['parameter']);
					}
				}

				if($condition['subject'] == 'trx_product_tag'){
					if($condition['operator'] != '='){
						$condition['parameter'] = $condition['operator'];
					}
					if($rule == 'and'){
						$query = $query->where('product_tags.id_tag','=',$condition['parameter']);
					} else {
						$query = $query->orWhere('product_tags.id_tag','=',$condition['parameter']);
					}
				}

				if($condition['subject'] == 'trx_product_tag_count'){
					if($userTrxProduct == true){
						$query = $query->havingRaw('SUM(user_trx_products.product_qty)'.$condition['operator'].$condition['parameter']);
					}else{
						$query = $query->havingRaw('SUM(transaction_products.transaction_product_qty)'.$condition['operator'].$condition['parameter']);
					}
				}
			}
		}
		return $query;
	}

	function getFeatureControl(Request $request){
		$post = $request->json()->all();
		$userQuery = User::where('phone','=',$post['phone'])->get()->toArray();
		if($userQuery){
			$user = $userQuery[0];
			
			if($user['level'] == 'Super Admin'){
				$checkFeature = Feature::select('id_feature')->get()->toArray();
			}else{
				$checkFeature = UserFeature::join('features', 'features.id_feature', '=', 'user_features.id_feature')
								->where('user_features.id_user', '=', $user['id'])
								->select('features.id_feature')->get()->toArray();
			}
			$result = [
				'status'  => 'success',
				'result'  => array_pluck($checkFeature, 'id_feature')
			];
		} else {
			$result = [
				'status'  => 'fail',
				'messages'  => ['No User Found']
			];
		}
      return response()->json($result);
    }
	
	function check(users_phone $request){
		$phone = $request->json('phone');
		if(substr($phone, 0, 1) != '0'){
			$phone = '0'.$phone;
		}
        $data = User::where('phone', '=', $phone)->get()->toArray();
        return MyHelper::checkGet($data);
    }
    /**
	 * [Users] Create User & PIN
	 * 
	 * to register user based on phone and generate PIN
	 *
	 */
	function createPin(users_phone $request){
        $data = User::where('phone', '=', $request->json('phone'))
						->get()
						->toArray();
		
		if(!$data){
			$pin = MyHelper::createRandomPIN(6, 'angka');
			// $pin = '777777';
			
			$provider = MyHelper::cariOperator($request->json('phone'));
			$is_android 	= null;
			$is_ios 		= null;
			$device_id = $request->json('device_id');
			$device_token = $request->json('device_token');
			$device_type = $request->json('device_type');
			
			if($request->json('device_type') == "Android") {
				$is_android = $device_id;
			} else{
				$is_ios = $device_id;
			}
			
			if($request->json('device_token') != "") {
				$device_token = $request->json('device_token');
			}
			
			$create = User::create(['phone' => $request->json('phone'),
					'provider' 		=> $provider,
					'password'		=> bcrypt($pin),
					'android_device' => $is_android,
					'ios_device' 	=> $is_ios
					]);
			
			if($create){
				if ($request->json('device_id') && $request->json('device_token') && $request->json('device_type')){
					app($this->home)->updateDeviceUser($create, $request->json('device_id'), $request->json('device_token'), $request->json('device_type'));
				}
			}
			$useragent = $_SERVER['HTTP_USER_AGENT'];
			if(stristr($_SERVER['HTTP_USER_AGENT'],'iOS')) $useragent = 'iOS';
			if(stristr($_SERVER['HTTP_USER_AGENT'],'okhttp')) $useragent = 'Android';
			if(stristr($_SERVER['HTTP_USER_AGENT'],'GuzzleHttp')) $useragent = 'Browser';
						
			
			if(\Module::collections()->has('Autocrm')) {
			$autocrm = app($this->autocrm)->SendAutoCRM('Pin Sent', $request->json('phone'), 
																	['pin' => $pin, 
																	 'useragent' => $useragent, 
																	 'now' => date('Y-m-d H:i:s')
																	]); 
			}
			
			app($this->membership)->calculateMembership($request->json('phone'));
			
			$result = ['status'	=> 'success',
                        'result'	=> ['phone'	=>	$create->phone,
										'autocrm'	=>	$autocrm,
										'pin'	=>	$pin
									   ]
					];
			return response()->json($result);
			
		} else {
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number already registered']
						];
			return response()->json($result);
		}
    }
	
	/**
	 * [Users] Check User PIN
	 * 
	 * to check users phone number and PIN
	 *
	 */
	function checkPin(users_phone_pin $request){
		$is_android 	= 0;
		$is_ios 		= 0;
		$device_id 		= null;
		$device_token 	= null;
		
		
		$ip = null;
		if(!empty($request->json('ip'))){
			$ip = $request->json('ip');
		} else {
			if(!empty($request->header('ip-address-view'))){
				$ip = $request->header('ip-address-view');
			}else{
				$ip = $_SERVER["REMOTE_ADDR"];
			}
		}
		
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		if(!empty($request->json('useragent'))){
			$useragent = $request->json('useragent');
		} else {
			if(!empty($request->header('user-agent-view'))){
				$useragent = $request->header('user-agent-view');
			}else{
				$useragent = $_SERVER['HTTP_USER_AGENT'];
			}
		}
		
		$device = null;
		
		if($useragent == "Opera/9.80 (Windows NT 6.1) Presto/2.12.388 Version/12.16")
			$device = 'Web Browser';
		if(stristr($useragent, 'iOS'))
			$device = 'perangkat iOS';
		if(stristr($useragent, 'okhttp'))
			$device = 'perangkat Android';
		if(stristr($useragent, 'Linux; U;')){
			$sementara = preg_match('/\(Linux\; U\; (.+?)\; (.+?)\//', $useragent, $matches);
			$device = $matches[2];
		}
		if(empty($device))
			$device = $useragent;
		
		
		if($request->json('device_type') == "Android") {
			$is_android = 1;
		} elseif($request->json('device_type') == "IOS") {
			$is_ios = 1;
		}
		
		if($request->json('device_id') != "") {
			$device_id = $request->json('device_id');
		}
		
		if($request->json('device_token') != "") {
			$device_token = $request->json('device_token');
		}
			
		$datauser = User::where('phone', '=', $request->json('phone'))
						->get()
						->toArray();
		
		if($datauser){
			if(Auth::attempt(['phone' => $request->json('phone'), 'password' => $request->json('pin')])){
				//kalo login success
				if($is_android != 0 || $is_ios != 0){
					
					//check fraud
					$deviceCus = UserDevice::where('device_type','=',$device_type)
											->where('device_id','=',$device_id)
											->where('device_token','=',$device_token)
											->orderBy('id_device_user', 'ASC')
											->first();
					
					if($deviceCus['id_user'] != $datauser[0]['id']){
						// send notif fraud detection
						$fraud = FraudSetting::where('parameter', 'LIKE', '%device ID%')->first();
						if($fraudTrxDay){
							$sendFraud = app($this->setting_fraud)->SendFraudDetection($fraud['id_fraud_setting'], $datauser[0], null, $deviceCus['id_device_user']);
						}
					}

					//kalo dari device
					$checkdevice = UserDevice::where('device_type','=',$device_type)
											 ->where('device_id','=',$device_id)
											 ->where('device_token','=',$device_token)
											 ->where('id_user','=',$datauser[0]['id'])
											 ->get()
											 ->toArray();
					if(!$checkdevice){
						//not trusted device or new device
						$createdevice = UserDevice::create(['id_user' => $datauser[0]['id'],
														'device_id' 		=> $device_id,
														'device_token'		=> $device_token,
														'device_type'		=> $device_type
														]);
						if($device_type == "Android")
							$update = User::where('id','=',$datauser[0]['id'])->update(['android_device' => $device_id, 'ios_device' => null]);
						if($device_type == "IOS")
							$update = User::where('id','=',$datauser[0]['id'])->update(['android_device' => null, 'ios_device' => $device_id]);
						
						if(stristr($useragent,'iOS')) $useragent = 'iOS';
						if(stristr($useragent,'okhttp')) $useragent = 'Android';
						if(stristr($useragent,'GuzzleHttp')) $useragent = 'Browser';
						
						if(\Module::collections()->has('Autocrm')) {
							$autocrm = app($this->autocrm)->SendAutoCRM('Login Success', $request->json('phone'), 
																		['ip' => $ip, 
																		 'useragent' => $useragent, 
																		 'now' => date('Y-m-d H:i:s')
																		]);
						}
					}
				}
					
				if($device_id == null && $device_token == null){
					//handle login dari web, tidak ada device id dan token sama sekali wajib notif
					// app($this->Sms)->sendSMSAuto('login success', $datauser[0]['id'], date('F d, Y H:i'), $device, $ip);
				}
				if(stristr($useragent,'iOS')) $useragent = 'iOS';
				if(stristr($useragent,'okhttp')) $useragent = 'Android';
				if(stristr($useragent,'GuzzleHttp')) $useragent = 'Browser';
				if(\Module::collections()->has('Autocrm')) {
					$autocrm = app($this->autocrm)->SendAutoCRM('Login Success', $request->json('phone'), 
																		['ip' => $ip, 
																		 'useragent' => $useragent, 
																		 'now' => date('Y-m-d H:i:s')
																		]);
				}
				$result 			= [];
				$result['status'] 	= 'success';
				$result['date'] 	= date('Y-m-d H:i:s');
				$result['device'] 	= $device;
				$result['ip'] 		= $ip;
			} else{
				//kalo login gagal
				if($datauser){
					$autocrm = app($this->autocrm)->SendAutoCRM('Login Failed', $request->json('phone'), 
																	['ip' => $ip, 
																	 'useragent' => $useragent, 
																	 'now' => date('Y-m-d H:i:s')
																	]);
				}
				
				$result 			= [];
				$result['status'] 	= 'fail';
				$result['messages'] = ['The user credentials were incorrect'];
				$result['date'] 	= date('Y-m-d H:i:s');
				$result['device'] 	= $device;
				$result['ip'] 		= $ip;
			}
		}
		else {
			$result['status'] 	= 'fail';
			$result['messages'] = ['The user credentials were incorrect'];
		}
		
        return response()->json($result);
    }
	
	function resendPin(users_phone $request){
        $data = User::where('phone', '=', $request->json('phone'))
						->get()
						->toArray();

		if($data){
			$pinnya = rand(100000,999999);
			$pin = bcrypt($pinnya);
			/*if($data[0]['phone_verified'] == 0){*/
				$update = User::where('phone','=',$request->json('phone'))->update(['password' => $pin]);
				
				$useragent = $_SERVER['HTTP_USER_AGENT'];
				if(stristr($_SERVER['HTTP_USER_AGENT'],'iOS')) $useragent = 'iOS';
				if(stristr($_SERVER['HTTP_USER_AGENT'],'okhttp')) $useragent = 'Android';
				if(stristr($_SERVER['HTTP_USER_AGENT'],'GuzzleHttp')) $useragent = 'Browser';
			
				
				if(\Module::collections()->has('Autocrm')) {
					$autocrm = app($this->autocrm)->SendAutoCRM('Pin Sent', $request->json('phone'), 
																	['pin' => $pinnya,
																	'useragent' => $useragent, 
																	 'now' => date('Y-m-d H:i:s')]); 
				}
				
				$result = [
							'status'	=> 'success',
							'result'	=> ['phone'	=>	$data[0]['phone'],
											'pin'	=>	$pinnya
										   ]
						];
			/*} else {
				$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number is already verified']
                    ];
			}*/
		} else {
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number isn\'t registered']
                    ];
		}
		return response()->json($result);
    }
	
	function forgotPin(users_forgot $request){
		$user = User::where('phone', '=', $request->json('phone'))->first();
		if(!$user){
			$result = [
				'status'	=> 'fail',
				'messages'	=> ['User not found.']
			];
			return response()->json($result);
		}

		if($user['birthday'] == null){
			$result = [
				'status'	=> 'fail',
				'messages'	=> ['User birthday is empty.']
			];
			return response()->json($result);
		}
		
        $data = User::where('phone', '=', $request->json('phone'))
					->where('birthday', '=', date('Y-m-d', strtotime($request->json('birthday'))))
					->get()
					->toArray();

		if($data){
			$pin = MyHelper::createRandomPIN(6, 'angka');
			$password = bcrypt($pin);
			$update = User::where('id','=',$data[0]['id'])->update(['password' => $password]);
			
			if(!empty($request->header('user-agent-view'))){
				$useragent = $request->header('user-agent-view');
			}else{
				$useragent = $_SERVER['HTTP_USER_AGENT'];
			}

			if(stristr($useragent,'iOS')) $useragent = 'iOS';
			if(stristr($useragent,'okhttp')) $useragent = 'Android';
			if(stristr($useragent,'GuzzleHttp')) $useragent = 'Browser';
				
			$autocrm = app($this->autocrm)->SendAutoCRM('Pin Sent', $request->json('phone'), 
																	['pin' => $pin,
																	'useragent' => $useragent, 
																	 'now' => date('Y-m-d H:i:s')]);
			
			$result = [
                        'status'	=> 'success',
                        'result'	=> ['phone'	=>	$request->json('phone'),
										'pin'	=>	$pin
									   ]
                    ];
			return response()->json($result);
			
		} else {
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['Birthday didn\'t match. Please check Your input.']
                    ];
			return response()->json($result);
		}
    }
	
	function verifyPin(users_phone_pin $request){
        $data = User::where('phone', '=', $request->json('phone'))
						->get()
						->toArray();
		if($data){
			if($data[0]['phone_verified'] == 0){
				if(Auth::attempt(['phone' => $request->json('phone'), 'password' => $request->json('pin')])){
					$update = User::where('id','=',$data[0]['id'])->update(['phone_verified' => '1']);
					if($update){
						$profile = User::select('phone','email','name','id_city','gender','phone_verified', 'email_verified')
									->where('phone', '=', $request->json('phone'))
									->get()
									->toArray();
						if(\Module::collections()->has('Autocrm')) {
							$autocrm = app($this->autocrm)->SendAutoCRM('Pin Verify', $request->json('phone'));
						}
						$result = [
								'status'	=> 'success',
								'result'	=> ['phone'	=>	$data[0]['phone'],
												'pin'	=>	$request->json('pin'),
												'profile'=> $profile
											   ]
							];
					}
				} else {
					$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['PIN doesn\'t match']
                    ];
				}
			} else {
				$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number is already verified']
                    ];
			}
		} else {
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number isn\'t registered']
                    ];
		}
		return response()->json($result);
    }
	
	function changePin(users_phone_pin_new $request){
        $data = User::where('phone', '=', $request->json('phone'))
						->get()
						->toArray();
		if($data){
			if(Auth::attempt(['phone' => $request->json('phone'), 'password' => $request->json('pin_old')])){
				$pin 	= bcrypt($request->json('pin_new'));
				$update = User::where('id','=',$data[0]['id'])->update(['password' => $pin, 'phone_verified' => '1']);
				if(\Module::collections()->has('Autocrm')) {
					$autocrm = app($this->autocrm)->SendAutoCRM('Pin Changed', $request->json('phone'));
				}
				$result = [
						'status'	=> 'success',
						'result'	=> ['phone'	=>	$data[0]['phone'],
										'pin'	=>	$request->json('pin_new')
									   ]
					];
			} else {
				$result = [
					'status'	=> 'fail',
					'messages'	=> ['Current PIN doesn\'t match']
				];
			}
		} else {
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number isn\'t registered']
                    ];
		}
		return response()->json($result);
    }
	
	function createUserFromAdmin(users_create $request){
		$post = $request->json()->all();
		$d = explode('/',$post['birthday']);
		$post['birthday'] = $d[2]."-".$d[0]."-".$d[1];
		
		if($post['pin'] == null) {
			$pin = MyHelper::createRandomPIN(6, 'angka');
			$pin = '777777';
		} else {
			$pin = $post['pin'];
		}
		
		$post['password'] = bcrypt($pin);
		$post['provider'] = MyHelper::cariOperator($post['phone']);
		
		$sent_pin = $post['sent_pin'];
		if(isset($post['pickup_order'])){
			$pickup_order = $post['pickup_order'];
			unset($post['pickup_order']);
		}
		if(isset($post['enquiry'])){
			$enquiry = $post['enquiry'];
			unset($post['enquiry']);
		}
		
		if(isset($post['delivery'])){
			$delivery = $post['delivery'];
			unset($post['delivery']);
		}
		unset($post['pin']);
		unset($post['sent_pin']);
		
		
		$result = MyHelper::checkGet(User::create($post));
		
		if($result['status'] == "success"){
			if($post['level'] == 'Admin Outlet'){
				foreach($post['id_outlet'] as $id_outlet){
					$dataUserOutlet = [];
					$dataUserOutlet['id_user'] = $result['result']['id'];
					$dataUserOutlet['id_outlet'] = $id_outlet;
					$dataUserOutlet['enquiry'] = $enquiry;
					$dataUserOutlet['pickup_order'] = $pickup_order;
					$dataUserOutlet['delivery'] = $delivery;
					UserOutlet::create($dataUserOutlet);
				}
			}
		
			if($sent_pin == 'Yes'){
				if(!empty($request->header('user-agent-view'))){
					$useragent = $request->header('user-agent-view');
				}else{
					$useragent = $_SERVER['HTTP_USER_AGENT'];
				}

				if(stristr($useragent,'iOS')) $useragent = 'iOS';
				if(stristr($useragent,'okhttp')) $useragent = 'Android';
				if(stristr($useragent,'GuzzleHttp')) $useragent = 'Browser';

				if(\Module::collections()->has('Autocrm')) {
				$autocrm = app($this->autocrm)->SendAutoCRM('Pin Sent', $post['phone'], 
																		['pin' => $pin, 
																		 'useragent' => $useragent, 
																		 'now' => date('Y-m-d H:i:s')
																		]); 
				}
			}
		}
		return response()->json($result);
    }
	
	function profileUpdate(users_profile $request){
        $data = User::where('phone', '=', $request->json('phone'))
						->get()
						->toArray();
						
		if($data){
			// $pin_x = MyHelper::decryptkhususpassword($data[0]['pin_k'], md5($data[0]['id_user'], true));
			if($request->json('email') != ""){
				$checkEmail = User::where('email', '=', $request->json('email'))
						->get()
						->first();
				if($checkEmail){
					if($checkEmail['phone'] != $request->json('phone')){
						$result = [
							'status'	=> 'fail',
							'messages'	=> ['This email has already been registered to another account. Please choose other email.']
						];
						return response()->json($result);
					}
				}
			}
			if($data[0]['phone_verified'] == 1){
				// if(Auth::attempt(['phone' => $request->json('phone'), 'password' => $request->json('pin')])){
					$dataupdate = [];
					if($request->json('name')){
						$dataupdate['name'] = $request->json('name');
					}
					if($request->json('email')){
						$dataupdate['email'] = $request->json('email');
					}
					if($request->json('gender')){
						$dataupdate['gender'] = $request->json('gender');
					}
					if($request->json('birthday')){
						$dataupdate['birthday'] = $request->json('birthday');
					}
					if($request->json('id_city')){
						$dataupdate['id_city'] = $request->json('id_city');
					}
					$update = User::where('id','=',$data[0]['id'])->update($dataupdate);

					$datauser = User::where('id','=',$data[0]['id'])->get()->toArray();
					$result = [
							'status'	=> 'success',
							'result'	=> ['phone'	=>	$data[0]['phone'],
											// 'pin'	=>	$request->json('pin'),
											'name' => $datauser[0]['name'],
											'email' => $datauser[0]['email'],
											'gender' => $datauser[0]['gender'],
											'birthday' => $datauser[0]['birthday'],
											'id_city' => $datauser[0]['id_city']
										   ]
						];
				// } else {
				// 	$result = [
                //         'status'	=> 'fail',
                //         'messages'	=> ['Current PIN doesn\'t match']
                //     ];
				// }
			} else {
				$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number isn\'t verified']
                    ];
			}
		} else {
			$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This phone number isn\'t registered']
                    ];
		}
		return response()->json($result);
    }
	
	function createAdminOutlet(Request $request){
		$post = $request->json()->all();
		$query = null;
		foreach($post['id_outlet'] as $id_outlet){
			$data = [];
			$data['phone'] = $post['phone'];
			$data['name'] = $post['name'];
			$data['email'] = $post['email'];
			$data['id_outlet'] = $id_outlet;
			$data['enquiry'] = 0;
			$data['pickup_order'] = 0;
			$data['delivery'] = 0;
			$data['payment'] = 0;
			
			foreach($post['type'] as $type){
				$data[$type] = 1;
			}
			
			$check = UserOutlet::where('phone',$data['phone'])
								->where('id_outlet',$data['id_outlet'])
								->first();
			if($check){
				$query = UserOutlet::where('phone',$data['phone'])
								->where('id_outlet',$data['id_outlet'])
								->update($data);
			} else {
				$query = UserOutlet::create($data);
			}
		}
		return response()->json(MyHelper::checkCreate($query));
	}
	
	function deleteAdminOutlet(Request $request){
		$post = $request->json()->all();
		$query = UserOutlet::where('phone',$post['phone'])
							->where('id_outlet',$post['id_outlet'])
							->delete();
		return response()->json(MyHelper::checkDelete($query));
	}
	
	function listAdminOutlet(Request $request){
		$check = UserOutlet::join('outlets','outlets.id_outlet','=','user_outlets.id_outlet')
								->get()
								->toArray();
		return response()->json(MyHelper::checkGet($check));
	}
	
	function detailAdminOutlet(Request $request){
		$post = $request->json()->all();
		$check = UserOutlet::join('outlets','outlets.id_outlet','=','user_outlets.id_outlet')
								->where('user_outlets.phone','=',$post['phone'])
								->where('user_outlets.id_outlet','=',$post['id_outlet'])
								->first();
		return response()->json(MyHelper::checkGet($check));
	}
	
	function listVar($var){
		if($var == 'phone') $query = User::select('phone')->get()->toArray();
		if($var == 'email') $query = User::select('email')->get()->toArray();
		if($var == 'name') $query = User::select('name')->get()->toArray();
	
		return response()->json($query);
	}
	function list(Request $request){
		$post = $request->json()->all();
		// return response()->json($post);
		if(isset($post['order_field'])) $order_field = $post['order_field']; else $order_field = 'id';
		if(isset($post['order_method'])) $order_method = $post['order_method']; else $order_method = 'desc';
		if(isset($post['skip'])) $skip = $post['skip']; else $skip = '0';
		if(isset($post['take'])) $take = $post['take']; else $take = '10';
		// if(isset($post['rule'])) $rule = $post['rule']; else $rule = 'and';
		if(isset($post['conditions'])) $conditions = $post['conditions']; else $conditions = null;
		
		$query = $this->UserFilter($conditions, $order_field, $order_method, $skip, $take);

		return response()->json($query);
	}
	
	function activity(Request $request){
		$post = $request->json()->all();
		
		if(isset($post['order_field'])) $order_field = $post['order_field']; else $order_field = 'id';
		if(isset($post['order_method'])) $order_method = $post['order_method']; else $order_method = 'desc';
		if(isset($post['skip'])) $skip = $post['skip']; else $skip = '0';
		if(isset($post['take'])) $take = $post['take']; else $take = '10';
		if(isset($post['rule'])) $rule = $post['rule']; else $rule = 'and';
		if(isset($post['conditions'])) $conditions = $post['conditions']; else $conditions = null;
		
		$query = $this->LogActivityFilter($rule, $conditions, $order_field, $order_method, $skip, $take);
	
		return response()->json($query);
	}
	
	public function delete(Request $request)
    {
		$post = $request->json()->all();
		
		if(is_array($post['phone'])){
			$messages = "Users ";
			foreach($post['phone'] as $row){
				$checkUser = User::where('phone','=',$row)->get()->toArray();
				if(!$checkUser) continue;
				
				if($checkUser[0]['level'] != 'Super Admin' && $checkUser[0]['level'] != 'Admin')
					$action = User::where('phone','=',$row)->delete();
				else
					continue;
				if($action){
					$messages .= $row.", ";
				}
			}
			$messages = substr($messages, 0, -2);
			$messages .= " Has been Deleted";
			
			$result = ['status'	=> 'success',
					   'result'	=> [$messages]
						  ];
		} else {
			$checkUser = User::where('phone','=',$post['phone'])->get()->toArray();
			if($checkUser){
				if($checkUser[0]['level'] != 'Super Admin' && $checkUser[0]['level'] != 'Admin'){
					$deleteUser = User::where('phone','=',$post['phone'])->delete();
				
					if($deleteUser){
						$result = ['status'	=> 'success',
								   'result'	=> ['User '.$post['phone'].' has been deleted']
								  ];
					} else {
						$result = [
								'status'	=> 'fail',
								'messages'	=> ['User Admin & Super Admin Cannot be deleted']
								];
					}
				} else {
					$result = [
								'status'	=> 'fail',
								'messages'	=> ['User Admin & Super Admin Cannot be deleted']
								];
				} 
			} else {
				$result = [
							'status'	=> 'fail',
							'messages'	=> ['User Not Found']
							];
			}
		}
		return response()->json($result);
    }
	
	public function phoneVerified(Request $request)
    {
		$post = $request->json()->all();
		
		if(is_array($post['phone'])){
			$messages = "Users ";
			foreach($post['phone'] as $row){
				$updateUser = User::where('phone','=',$row)->update(['phone_verified' => 1]);
				if($updateUser){
					$messages .= $row.", ";
				}
			}
			$messages = substr($messages, 0, -2);
			$messages .= " Has been Phone Verified";
			
			$result = ['status'	=> 'success',
					   'result'	=> [$messages]
						  ];
		} else {
				$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This function is for bulk update']
						];
		}
		return response()->json($result);
    }
	
	public function phoneUnverified(Request $request)
    {
		$post = $request->json()->all();
		
		if(is_array($post['phone'])){
			$messages = "Users ";
			foreach($post['phone'] as $row){
				$updateUser = User::where('phone','=',$row)->update(['phone_verified' => 0]);
				if($updateUser){
					$messages .= $row.", ";
				}
			}
			$messages = substr($messages, 0, -2);
			$messages .= " Has been Phone Unverified";
			
			$result = ['status'	=> 'success',
					   'result'	=> [$messages]
						  ];
		} else {
				$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This function is for bulk update']
						];
		}
		return response()->json($result);
    }
	
	public function emailVerified(Request $request)
    {
		$post = $request->json()->all();
		
		if(is_array($post['phone'])){
			$messages = "Users ";
			foreach($post['phone'] as $row){
				$updateUser = User::where('phone','=',$row)->update(['email_verified' => 1]);
				if($updateUser){
					$messages .= $row.", ";
				}
			}
			$messages = substr($messages, 0, -2);
			$messages .= " Has been Email Verified";
			
			$result = ['status'	=> 'success',
					   'result'	=> [$messages]
						  ];
		} else {
				$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This function is for bulk update']
						];
		}
		return response()->json($result);
    }
	
	public function emailUnverified(Request $request)
    {
		$post = $request->json()->all();
		
		if(is_array($post['phone'])){
			$messages = "Users ";
			foreach($post['phone'] as $row){
				$updateUser = User::where('phone','=',$row)->update(['email_verified' => 0]);
				if($updateUser){
					$messages .= $row.", ";
				}
			}
			$messages = substr($messages, 0, -2);
			$messages .= " Has been Email Unverified";
			
			$result = ['status'	=> 'success',
					   'result'	=> [$messages]
						  ];
		} else {
				$result = [
                        'status'	=> 'fail',
                        'messages'	=> ['This function is for bulk update']
						];
		}
		return response()->json($result);
    }
	
	public function show(Request $request)
    {
		$post = $request->json()->all();
		
		$query = User::leftJoin('cities','cities.id_city','=','users.id_city')
					->leftJoin('provinces','provinces.id_province','=','cities.id_province')
					->with('transactions', 'pointTransaction', 'pointVoucher', 'pointVoucher.voucher', 'pointVoucher.voucher.voucher','point')
					->where('phone','=',$post['phone'])
					->get()
					->first();
					
		foreach ($query->point as $key => $value) {
            $value->detail_product = $value->detailProduct;
        }
		
		$countVoucher = LogPoint::where(['id_user' => $query['id'], 'source' => 'voucher'])->get()->count();
        $countTrx = LogPoint::where(['id_user' => $query['id'], 'source' => 'transaction'])->get()->count();
		
		if($query){
			if(!empty($query['photo']))
			$query['photo'] = env('API_URL')."/".$query['photo'];
			$result = ['status'	=> 'success',
					   'result'	=> $query,
					   'trx'     => $countTrx,
						'voucher' => $countVoucher
					  ]; 
		} else {
			$result = [
						'status'	=> 'fail',
						'messages'	=> ['User Not Found']
						];
		}
		return response()->json($result);
    }
	
	public function log(Request $request)
    {
		$post = $request->json()->all();
		
		$query = LogRequest::where('phone','=',$post['phone'])
							->orderBy('id_log_activity','desc')
							->get()
							->toArray();
		if($query){
			$result = ['status'	=> 'success',
					   'result'	=> $query
					  ]; 
		} else {
			$result = [
						'status'	=> 'fail',
						'messages'	=> ['Log Activity Not Found']
						];
		}
		return response()->json($result);
    }
	
	public function updateProfileByAdmin(Request $request)
    {
		$post = $request->json()->all();
		
		$user = User::where('phone',$post['phone'])->get()->toArray();
		
		if(isset($post['update']['phone'])){
			if($post['update']['phone'] != $user[0]['phone']){
				$check = User::where('phone',$post['update']['phone'])->get()->toArray();
				if($check){
					$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update profile failed. Phone Number already exist.']
					];
					return response()->json($result);
				}
			}
		}
		
		if(isset($post['update']['email'])){
			if($post['update']['email'] != $user[0]['email']){
				$check = User::where('email',$post['update']['email'])->get()->toArray();
				if($check){
					$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update profile failed. Email already exist.']
					];
					return response()->json($result);
				}
			}
		}
		
		if(isset($post['update']['birthday'])){
			if(stristr($post['update']['birthday'], '/')){
				$explode = explode('/', $post['update']['birthday']);
				$post['update']['birthday'] = $explode[2].'-'.$explode[1].'-'.$explode[0];
			}
		}
		
		$update = User::where('phone',$post['phone'])->update($post['update']);
		
		return MyHelper::checkUpdate($update);
    }
	
	public function updateProfilePhotoByAdmin(Request $request)
    {
		$post = $request->json()->all();
		if (isset($post['photo'])) {
			$upload = MyHelper::uploadPhotoStrict($post['photo'], $path = 'img/user/', 500, 500);

			if ($upload['status'] == "success") {
				$updatenya['photo'] = $upload['path'];
				$update = User::where('phone',$post['phone'])->update($updatenya);
				return MyHelper::checkUpdate($update);
			} else{
				$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update profile photo failed.']
					];
				return response()->json($result);
			}
		} else {
			$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update profile photo failed.']
					];
			return response()->json($result);
		}
	}
	
	public function updateProfilePasswordByAdmin(users_phone_pin_new_admin $request)
    {
		$post = $request->json()->all();
		if (isset($post['password_new'])) {
			$password = bcrypt($post['password_new']);
			$update = User::where('phone',$post['phone'])->update(['password' => $password]);

			return MyHelper::checkUpdate($update);
		} else {
			$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update profile password failed.']
					];
			return response()->json($result);
		}
	}
	
	public function updateDoctorOutletByAdmin(Request $request)
	{
		$post = $request->json()->all();
		$user = $request->user();
 		if (isset($post['outlet'])) {
			$checkDoctor = User::join('doctors','doctors.id_user','=','users.id')
								->where('users.phone',$post['phone'])
								->get()
								->first();
			if($checkDoctor){
				OutletDoctor::where('id_doctor','=',$checkDoctor['id_doctor'])->delete();
				foreach($post['outlet'] as $outlet){
					$dataOutletDoctor = [];
					$dataOutletDoctor['id_outlet'] = $outlet;
					$dataOutletDoctor['id_doctor'] = $checkDoctor['id_doctor'];
					
					$addOutlet = OutletDoctor::insert($dataOutletDoctor);
				}
				return MyHelper::checkUpdate($addOutlet);
			} else {
				$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update doctor outlet failed.','Doctor not found']
					];
				return response()->json($result);
			}
		}
	}
	public function updateProfileLevelByAdmin(Request $request)
    {
		$post = $request->json()->all();
		$user = $request->user();
		if(!Auth::check(['phone' => $user['phone'], 'password' => $post['password_level']])){
			$result = [
					'status'	=> 'fail',
					'messages'	=> ['Update level failed. Wrong PIN']
				];
			return response()->json($result);
		}
 		if (isset($post['level'])) {
			$checkUser = User::where('phone',$post['phone'])->get()->first();
			if($checkUser){
				if($post['level'] == 'Super Admin' && $user['level'] != "Super Admin"){
					$result = [
							'status'	=> 'fail',
							'messages'	=> ['Update level failed. Only Super Admin are allowed to grant Super Admin level to another user.']
						];
					return response()->json($result);
				}
				if($user['level'] != 'Super Admin' && $user['level'] != "Admin"){
					$result = [
							'status'	=> 'fail',
							'messages'	=> ['Update level failed. Only Super Admin and Admin are allowed to modify Level']
						];
					return response()->json($result);
				}
				if($post['level'] == 'Admin Outlet'){
					foreach($post['id_outlet'] as $id_outlet){
						$checkAdminOutlet = UserOutlet::where('id_user','=',$checkUser['id'])
													   ->where('id_outlet','=',$id_outlet)
														->get()
														->first();
						$dataAdminOutlet = [];
						$dataAdminOutlet['id_user'] = $checkUser['id'];
						$dataAdminOutlet['id_outlet'] = $id_outlet;
						$dataAdminOutlet['enquiry'] = $post['enquiry'];
						$dataAdminOutlet['pickup_order'] = $post['pickup_order'];
						$dataAdminOutlet['delivery'] = $post['delivery'];
						
						if($checkAdminOutlet){
							$updateAdminOutlet = UserOutlet::where('id_user','=',$checkUser['id'])
														   ->where('id_outlet','=',$id_outlet)
														   ->update($dataAdminOutlet);
						} else {
							$updateAdminOutlet = UserOutlet::create($dataAdminOutlet);
						}
					}
				}
				$update = User::where('phone',$post['phone'])->update(['level' => $post['level']]);

				return MyHelper::checkUpdate($update);
			} else {
				$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update level failed.','User not found']
					];
				return response()->json($result);
			}
		} else {
			$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update level failed.']
					];
			return response()->json($result);
		}
	}
	
	public function updateProfilePermissionByAdmin(Request $request)
    {
		$post = $request->json()->all();
		$user = $request->user();
		
		if(!Auth::check(['phone' => $user['phone'], 'password' => $post['password_permission']])){
			$result = [
					'status'	=> 'fail',
					'messages'	=> ['Update permission failed. Wrong PIN']
				];
			return response()->json($result);
		}
		
		if($user['level'] != 'Super Admin' && $user['level'] != "Admin"){
			$result = [
					'status'	=> 'fail',
					'messages'	=> ['Update permission failed. Only Super Admin and Admin are allowed to modify permission']
				];
			return response()->json($result);
		}
		
		$user = User::where('phone',$post['phone'])->get()->toArray();
		$delete = UserFeature::where('id_user',$user[0]['id'])->delete();
		
		$create = null;
		if(isset($post['module'])){
			foreach($post['module'] as $id_feature){
				$create = DB::insert('insert into user_features (id_user, id_feature) values (?, ?)', [$user[0]['id'], $id_feature]);
				
			}
		}
		$result = ['status'	=> 'success'];
		return response()->json($result);
	}
	
	public function updateUserOutletByAdmin(Request $request)
    {
		$post = $request->json()->all();
		$user = $request->user();
		
		if(!Auth::check(['phone' => $user['phone'], 'password' => $post['password_outlet_setting']])){
			$result = [
					'status'	=> 'fail',
					'messages'	=> ['Update user outlet failed. Wrong PIN']
				];
			return response()->json($result);
		}
		
		if($user['level'] != 'Super Admin' && $user['level'] != "Admin Outlet"){
			$result = [
					'status'	=> 'fail',
					'messages'	=> ['Update user outlet failed. Only Admin Outlet and Admin are allowed to modify user outlet']
				];
			return response()->json($result);
		}
		
		$user = User::where('phone',$post['phone'])->get()->toArray();
		
		$update = [];
		$update['delivery'] = '0';
		$update['enquiry'] = '0';
		$update['pickup_order'] = '0';
		$false = UserOutlet::where('id_user',$user[0]['id'])->update($update);

		
		foreach($post['outlets'] as $outlet){
			$check = UserOutlet::where('id_user',$user[0]['id'])->where('id_outlet',$outlet['id_outlet'])->first();
			
			if(isset($check)){
				if(isset($outlet['enquiry'])){
					UserOutlet::where('id_user',$user[0]['id'])->where('id_outlet',$outlet['id_outlet'])->update(['enquiry' => $outlet['enquiry']]);
				}
				
				if(isset($outlet['delivery'])){
					UserOutlet::where('id_user',$user[0]['id'])->where('id_outlet',$outlet['id_outlet'])->update(['delivery' => $outlet['delivery']]);
				}
				
				if(isset($outlet['pickup_order'])){
					UserOutlet::where('id_user',$user[0]['id'])->where('id_outlet',$outlet['id_outlet'])->update(['pickup_order' => $outlet['pickup_order']]);
				}
			}
		}

		$result = ['status'	=> 'success'];
		return response()->json($result);
	}
	
	public function outletUser(Request $request){
		$post = $request->json()->all();
		
		$query = UserOutlet::leftJoin('users','users.id','=','user_outlets.id_user')
						->leftJoin('outlets','outlets.id_outlet','=','user_outlets.id_outlet')
						->where('users.phone','=',$post['phone'])
						->where('users.level','=','Admin Outlet')
						->orderBy('user_outlets.id_outlet','desc')
						->get()
						->toArray();
		if($query){
			$result = ['status'	=> 'success',
					   'result'	=> $query
					  ]; 
		} else {
			$result = [
						'status'	=> 'fail',
						'messages'	=> ['User Outlet Is Empty']
						];
		}
		return response()->json($result);
	}
	public function inboxUser(Request $request)
    {
		$user = $request->user();
		
		$query = UserInbox::where('id_user','=',$user->id)
						->orderBy('id_user_inboxes','desc')
						->get()
						->toArray();
		if($query){
			$result = ['status'	=> 'success',
					   'result'	=> $query
					  ]; 
		} else {
			$result = [
						'status'	=> 'fail',
						'messages'	=> ['Inbox Is Empty']
						];
		}
		return response()->json($result);
	}

	public function getUserNotification(Request $request){
		$post = $request->json()->all();
		$user = $request->user();

		$tranPending = Transaction::where('transaction_payment_status', 'Pending')->where('id_user', $user->id)->count();
		$userNotification = UserNotification::where('id_user', $user->id)->first();
		if(empty($userNotification)){
			$notif['inbox'] = app($this->inbox)->listInboxUnread($user->id);
			$notif['voucher'] = 0;
			$notif['history'] = $tranPending; 

			$createUserNotif = UserNotification::create(['id_user' => $user->id]);
			$result = [
				'status'	=> 'success',
				'result'	=> $notif
			]; 
		}else{
			// update voucher jika sudah diliat
			if(isset($post['type']) && $post['type'] == 'voucher'){
				$updateNotif = UserNotification::where('id_user', $user->id)->update([$post['type']=>0]);
				$userNotification = UserNotification::where('id_user', $user->id)->first();
			}

			$notif['inbox'] = app($this->inbox)->listInboxUnread($user->id);
			$notif['voucher'] = $userNotification['voucher'];
			$notif['history'] = $tranPending;
			$result = [
				'status'	=> 'success',
				'result'	=> $notif
			]; 
		}
		return response()->json($result);
	}

	// get user profile for news custom form autofill
	public function getUserByPhone(Request $request)
	{
		$post = $request->json()->all();
		$user = User::where('phone', $post['phone'])->first();
		if ($user->id_city != null) {
			$user = $user->setAttribute('city_name', $user->city->city_name);
		}

		return response()->json(MyHelper::checkGet($user));
	}

	public function resetCountTransaction(Request $request){
		$user = User::get();

		DB::beginTransaction();

		//reset transaction week
		foreach($user as $dataUser){
			$countTrx = Transaction::whereDate('transaction_date', date('Y-m-d', strtotime(' - 7 days')))->where('id_user', $dataUser->id)->count();
			if($countTrx > 0){
				$newCountTrx = $dataUser->count_transaction_week - $countTrx;
				$update = User::where('id_user', $dataUser->id_user)->update(['count_transaction_week' => $newCountTrx]);
				if(!$update){
					DB::rollBack();
					return response()->json([
						'status'   => 'fail',
						'messages' => 'failed update count transaction week.'
					]);
				}
			}
		}

		//reset transaction day
		$updateDay = DB::table('users')->update(['count_transaction_day' => 0]);
		if(!is_integer($updateDay)){
			DB::rollBack();
			return response()->json([
				'status'   => 'fail',
				'messages' => 'failed update count transaction day.'
			]);
		}

		DB::commit();
		return response()->json([
			'status'   => 'success'
		]);

	}
}