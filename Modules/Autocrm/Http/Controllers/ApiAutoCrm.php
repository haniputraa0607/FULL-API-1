<?php

namespace Modules\Autocrm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Autocrm;
use App\Http\Models\AutocrmRule;
use App\Http\Models\User;
use App\Http\Models\TextReplace;
use App\Http\Models\AutocrmEmailLog;
use App\Http\Models\AutocrmSmsLog;
use App\Http\Models\AutocrmPushLog;
use App\Http\Models\AutocrmWhatsappLog;
use App\Http\Models\AutocrmWhatsappLogContent;
use App\Http\Models\WhatsappContent;
use App\Http\Models\UserInbox;
use App\Http\Models\Setting;

use App\Lib\MyHelper;
use App\Lib\PushNotificationHelper;
use App\Lib\classTexterSMS;
use App\Lib\classMaskingJson;
use App\Lib\apiwha;
use Validator;
use Hash;
use DB;
use Mail;
use Mailgun;

class ApiAutoCrm extends Controller
{
	public $Sms;
	private $textersms;
	
    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
		$this->textersms = new classTexterSMS();
		$this->rajasms = new classMaskingJson();
		$this->apiwha = new apiwha();
    }
	
	function SendAutoCRM($autocrm_title, $receipient, $variables = null){
		$query = Autocrm::where('autocrm_title','=',$autocrm_title)->with('whatsapp_content')->get()->toArray();
		$users = User::where('phone','=',$receipient)->get()->toArray();
		if(empty($users)){
			return true;
		}
		if($query){
			$crm 	= $query[0];
			$user 	= $users[0];
			if($crm['autocrm_email_toogle'] == 1){
				if(!empty($user['email'])){
					if($user['name'] != "")
						$name	 = "";
					else
						$name	 = $user['name'];
					
					$to		 = $user['email'];
					$subject = $this->TextReplace($crm['autocrm_email_subject'], $receipient, $variables);
					
					$content = $this->TextReplace($crm['autocrm_email_content'], $receipient, $variables);
					//get setting email
					$getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
					$setting = array();
					foreach ($getSetting as $key => $value) {
						$setting[$value['key']] = $value['value']; 
					}

					$data = array(
						'customer' => $name,
						'html_message' => $content,
						'setting' => $setting
					);
					
					Mailgun::send('emails.test', $data, function($message) use ($to,$subject,$name,$setting)
					{
						$message->to($to, $name)->subject($subject)
										->trackClicks(true)
										->trackOpens(true);
						if(!empty($setting['email_from']) && !empty($setting['email_sender'])){
							$message->from($setting['email_from'], $setting['email_sender']);
						}else if(!empty($setting['email_from'])){
							$message->from($setting['email_from']);
						}

						if(!empty($setting['email_reply_to'])){
							$message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
						}

						if(!empty($setting['email_cc']) && !empty($setting['email_cc_name'])){
							$message->cc($setting['email_cc'], $setting['email_cc_name']);
						}

						if(!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])){
							$message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
						}
					});
					
					$logData = [];
					$logData['id_user'] = $user['id'];
					$logData['email_log_to'] = $user['email'];
					$logData['email_log_subject'] = $subject;
					$logData['email_log_message'] = $content;
					
					$logs = AutocrmEmailLog::create($logData);
				}
			}
			
			if($crm['autocrm_forward_toogle'] == 1){
				if(!empty($crm['autocrm_forward_email'])){
					$exparr = explode(';',str_replace(',',';',$crm['autocrm_forward_email']));
					foreach($exparr as $email){
						$n	 = explode('@',$email);
						$name = $n[0];
						
						$to		 = $email;
						$subject = $this->TextReplace($crm['autocrm_forward_email_subject'], $receipient, $variables);
						
						$content = $this->TextReplace($crm['autocrm_forward_email_content'], $receipient, $variables);

						// get setting email
						$getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
						$setting = array();
						foreach ($getSetting as $key => $value) {
							$setting[$value['key']] = $value['value']; 
						}
						
						$data = array(
							'customer' => $name,
							'html_message' => $content,
							'setting' => $setting
						);
						
						Mailgun::send('emails.test', $data, function($message) use ($to,$subject,$name,$setting)
						{
							$message->to($to, $name)->subject($subject)
											->trackClicks(true)
											->trackOpens(true);
							if(!empty($setting['email_from']) && !empty($setting['email_sender'])){
								$message->from($setting['email_from'], $setting['email_sender']);
							}else if(!empty($setting['email_from'])){
								$message->from($setting['email_from']);
							}

							if(!empty($setting['email_reply_to'])){
								$message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
							}

							if(!empty($setting['email_cc']) && !empty($setting['email_cc_name'])){
								$message->cc($setting['email_cc'], $setting['email_cc_name']);
							}

							if(!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])){
								$message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
							}
						});
						
						$logData = [];
						$logData['id_user'] = $user['id'];
						$logData['email_log_to'] = $email;
						$logData['email_log_subject'] = $subject;
						$logData['email_log_message'] = $content;
						
						$logs = AutocrmEmailLog::create($logData);
					}
				}
			}
			
			if($crm['autocrm_sms_toogle'] == 1){
				if(!empty($user['phone'])){
					$senddata = array(
						'apikey' => 'd49091c827903ef28a07cca2c4e99064',  
						'callbackurl' => env('APP_URL'), 
						'datapacket'=>array()
					);

					//add <#> and Hash Key in pin sms content
					if($crm['autocrm_title'] == 'Pin Sent'){
						$crm['autocrm_sms_content'] = '<#> '.$crm['autocrm_sms_content'].' '.ENV('HASH_KEY_'.ENV('HASH_KEY_TYPE'));
					}

					$content 	= $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables);
					array_push($senddata['datapacket'],array(
							'number' => trim($user['phone']),
							'message' => urlencode(stripslashes(utf8_encode($content))),
							'sendingdatetime' => ""));
							
					$this->rajasms->setData($senddata);
					$send = $this->rajasms->send();

					$logData = [];
					$logData['id_user'] = $user['id'];
					$logData['sms_log_to'] = $user['phone'];
					$logData['sms_log_content'] = $content;
					
					$logs = AutocrmSmsLog::create($logData);
				}
			}

			if($crm['autocrm_whatsapp_toogle'] == 1){
				if(!empty($user['phone'])){
					//cek api key whatsapp
					$api_key = Setting::where('key', 'api_key_whatsapp')->first();
					if($api_key){
						if($api_key->value){
							$contentWaSent = [];
							//send every content whatsapp
							foreach($crm['whatsapp_content'] as $contentWhatsapp){
								if($contentWhatsapp['content_type'] == 'text'){
									$content = $this->TextReplace($contentWhatsapp['content'], $user['phone'], $variables);
								}else{
									$content = $contentWhatsapp['content'];
								}
								// add country code in number
								$ptn = "/^0/";  
								$rpltxt = "62"; 
								$phone = preg_replace($ptn, $rpltxt, $user['phone']);

								$send = $this->apiwha->send($api_key->value, $phone, $content);

								//api key whatsapp not valid
								if(isset($send['result_code']) && $send['result_code'] == -1){
									break 1;
								}

								$dataContent['content'] = $content;
								$dataContent['content_type'] = $contentWhatsapp['content_type'];
								array_push($contentWaSent, $dataContent);

							}

							// insert to whatsapp log
							$outbox = [];
							if(isset($user['id'])){
								$outbox['id_user'] = $user['id'];
							}
							$outbox['whatsapp_log_to'] = $user['phone'];
							
							$logs = AutocrmWhatsappLog::create($outbox);
							if($logs){
								// insert to whatsapp log content 
								foreach($contentWaSent as $data){
									$dataContentWhatsapp['content'] = $data['content'];
									$dataContentWhatsapp['content_type'] = $data['content_type'];
									$dataContentWhatsapp['id_autocrm_whatsapp_log'] =  $logs['id_autocrm_whatsapp_log'];
									$create = AutocrmWhatsappLogContent::create($dataContentWhatsapp);
								}
							}
						}
					}
				}
			}
			
			if($crm['autocrm_push_toogle'] == 1){
				if(!empty($user['phone'])){
					try {
						$dataOptional          = [];
						$image = null;
						if (isset($crm['autocrm_push_image']) && $crm['autocrm_push_image'] != null) {
							$dataOptional['image'] = env('APP_API_URL').$crm['autocrm_push_image'];
							$image = env('APP_API_URL').$crm['autocrm_push_image'];
						}
						
						if (isset($crm['autocrm_push_clickto']) && $crm['autocrm_push_clickto'] != null) {
							$dataOptional['type'] = $crm['autocrm_push_clickto'];
						} else {
							$dataOptional['type'] = 'Home';
						}
						
						if (isset($crm['autocrm_push_link']) && $crm['autocrm_push_link'] != null) {
							if($dataOptional['type'] == 'Link')
								$dataOptional['link'] = $crm['autocrm_push_link'];
							else 
								$dataOptional['link'] = null;
						} else {
							$dataOptional['link'] = null;
						}
						
						if (isset($crm['autocrm_push_id_reference']) && $crm['autocrm_push_id_reference'] != null) {
							$dataOptional['id_reference'] = (int)$crm['autocrm_push_id_reference'];
						} else{
							if ($dataOptional['type'] == 'Transaction') {
								if (isset($variables['id_reference'])) {
									$dataOptional['id_reference'] = $variables['id_reference'];
								} else {
									$dataOptional['id_reference'] = 0;
								}
							} else {
								$dataOptional['id_reference'] = 0;
							}
						}

						if (isset($variables['notif_type'])) {
							$dataOptional['notif_type'] = $variables['notif_type'];
						}

						$deviceToken = PushNotificationHelper::searchDeviceToken("phone", $user['phone']);
						// print_r($deviceToken);exit;
						$subject = $this->TextReplace($crm['autocrm_push_subject'], $receipient, $variables);
						$content = $this->TextReplace($crm['autocrm_push_content'], $receipient, $variables);
						$deviceToken = PushNotificationHelper::searchDeviceToken("phone", $user['phone']);
						
						if (!empty($deviceToken)) {
							if (isset($deviceToken['token']) && !empty($deviceToken['token'])) {
								$push = PushNotificationHelper::sendPush($deviceToken['token'], $subject, $content, $image, $dataOptional);

								if (isset($push['success']) && $push['success'] > 0) {
									$logData = [];
									$logData['id_user'] = $user['id'];
									$logData['push_log_to'] = $user['phone'];
									$logData['push_log_subject'] = $subject;
									$logData['push_log_content'] = $content;
									
									$logs = AutocrmPushLog::create($logData);
								}
							}
						}
						return true;
					} catch (\Exception $e) {
						return response()->json(MyHelper::throwError($e));
					}
				}
			}
			
			if($crm['autocrm_inbox_toogle'] == 1){
				if(!empty($user['id'])){

					$inbox['id_user'] 	  	  = $user['id'];
					$inbox['inboxes_subject'] = $this->TextReplace($crm['autocrm_inbox_subject'], $user['id'], $variables, 'id');
					$inbox['inboxes_clickto'] = $crm['autocrm_inbox_clickto'];

					if($crm['autocrm_inbox_clickto'] == 'Content'){
						$inbox['inboxes_content'] = $this->TextReplace($crm['autocrm_inbox_content'], $user['id'], $variables, 'id');
					}

					if($crm['autocrm_inbox_clickto'] == 'Link'){
						$inbox['inboxes_link'] = $crm['autocrm_inbox_link'];
					}

					if (isset($crm['autocrm_inbox_id_reference']) && $crm['autocrm_inbox_id_reference'] != null) {
						$inbox['inboxes_id_reference'] = (int)$crm['autocrm_inbox_id_reference'];
					} else{
						if ($crm['autocrm_inbox_clickto'] == 'Transaction') {
							if (isset($variables['id_reference'])) {
								$inbox['inboxes_id_reference'] = $variables['id_reference'];
							} else {
								$inbox['inboxes_id_reference'] = 0;
							}
						} else {
							$inbox['inboxes_id_reference'] = 0;
						}
					}

					$inbox['inboxes_send_at'] = date("Y-m-d H:i:s");
					$inbox['created_at'] = date("Y-m-d H:i:s");
					$inbox['updated_at'] = date("Y-m-d H:i:s");
					
					$inboxQuery = UserInbox::insert($inbox);
				}
			}
			
			return true;
		} else {
			return false;
		}
	}
	
	public function listTextReplace(Request $request, $var = null){
		if($var != null) $query = TextReplace::get()->toArray();
		else $query = TextReplace::where('reference','!=','variables')->where('status','=','Activated')->get()->toArray();
		return response()->json(MyHelper::checkGet($query));
	}
	
	public function updateTextReplace(Request $request){
		$post = $request->json()->all();
		$id_text_replace = $post['id_text_replace'];
		unset($post['id_text_replace']);
		$query = TextReplace::where('id_text_replace','=',$id_text_replace)->update($post);
		
		return response()->json(MyHelper::checkUpdate($query));
	}
	
	function TextReplace($text, $receipient, $variables = null, $wherefield = null){
		$query = TextReplace::where('status','=','Activated')->get()->toArray();
		if($wherefield != null){
			$user = User::leftJoin('cities','cities.id_city','=','users.id_city')
							->leftJoin('provinces','cities.id_province','=','provinces.id_province')
							->where($wherefield,'=',$receipient)
							->get()
							->first();
		} else {
			$user = User::leftJoin('cities','cities.id_city','=','users.id_city')
							->leftJoin('provinces','cities.id_province','=','provinces.id_province')
							->where('phone','=',$receipient)
							->get()
							->first();
		}
		
		if($user){

			//add - to pin 
			if(isset($variables['pin'])){
				$variables['pin'] = substr($variables['pin'], 0, 3).'-'.substr($variables['pin'], 3, 3);
			}

			foreach($query as $replace){
				$replaced = "";
				if($replace['type'] == 'String'){
					if($replace['reference'] == 'variables'){
						if(isset($variables[str_replace('%','',$replace['keyword'])])){
							$replaced = $variables[str_replace('%','',$replace['keyword'])];
						} else {
							$replaced = $replace['default_value'];
						}
					} else {
						if($user[$replace['reference']] != ""){
							$replaced = $user[$replace['reference']];
						} else {
							$replaced = $replace['default_value'];
						}
					}
				}
				
				if($replace['type'] == 'Alias'){
					if($replace['reference'] == 'variables'){
						if(isset($variables[$replace['reference']])){
							if($replace['custom_rule'] != ""){
								$ruleexp = explode(";", $replace['custom_rule']);
								if($ruleexp){
									foreach($ruleexp as $exp){
										$customruleexp = explode("=", $exp);
										if($customruleexp[0] == $variables[$replace['reference']]){
											$replaced = $customruleexp[1];
										}
									}
								} else {
									$replaced = $variables[$replace['reference']];
								}
							} else {
								$replaced = $variables[$replace['reference']];
							}
						} else {
							if($replace['custom_rule'] != ""){
								$ruleexp = explode(";", $replace['custom_rule']);
								if($ruleexp){
									foreach($ruleexp as $exp){
										$customruleexp = explode("=", $exp);
										if($customruleexp[0] == $replace['default_value']){
											$replaced = $customruleexp[1];
										}
									}
								} else {
									$replaced = $replace['default_value'];
								}
							} else {
								$replaced = $replace['default_value'];
							}
						}
					} else {
						if($user[$replace['reference']] != ""){
							if($replace['custom_rule'] != ""){
								$ruleexp = explode(";", $replace['custom_rule']);
								if($ruleexp){
									foreach($ruleexp as $exp){
										$customruleexp = explode("=", $exp);
										if($customruleexp[0] == $user[$replace['reference']]){
											$replaced = $customruleexp[1];
										}
									}
								} else {
									$replaced = $user[$replace['reference']];
								}
							} else {
								$replaced = $user[$replace['reference']];
							}
						} else {
							if($replace['custom_rule'] != ""){
								$ruleexp = explode(";", $replace['custom_rule']);
								if($ruleexp){
									foreach($ruleexp as $exp){
										$customruleexp = explode("=", $exp);
										if($customruleexp[0] == $user[$replace['reference']]){
											$replaced = $customruleexp[1];
										}
									}
								} else {
									$replaced = $replace['default_value'];
								}
							} else {
								$replaced = $replace['default_value'];
							}
						}
					}
				}
				
				if($replace['type'] == 'Date'){
					if($replace['reference'] == 'variables'){
						if(isset($variables[$replace['reference']])){
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime($variables[$replace['reference']]));
							} else {
								$replaced = date('Y-m-d', strtotime($variables[$replace['reference']]));
							}
						} else {
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime($replace['default_value']));
							} else {
								$replaced = date('Y-m-d', strtotime($replace['default_value']));
							}
						}
					} else {
						if($user[$replace['reference']] != ""){
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime($user[$replace['reference']]));
							} else {
								$replaced = date('Y-m-d', strtotime($user[$replace['reference']]));
							}
						} else {
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime(date('Y-m-d')));
							} else {
								$replaced = date('Y-m-d', strtotime($replace['default_value']));
							}
						}
					}
				}
				
				if($replace['type'] == 'DateTime'){
					if($replace['reference'] == 'variables'){
						if(isset($variables[$replace['reference']])){
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime($variables[$replace['reference']]));
							} else {
								$replaced = date('Y-m-d H:i', strtotime($variables[$replace['reference']]));
							}
						} else {
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime(date('Y-m-d H:i:s')));
							} else {
								$replaced = date('Y-m-d H:i', strtotime($replace['default_value']));
							}
						}
					} else {
						if($user[$replace['reference']] != ""){
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime($user[$replace['reference']]));
							} else {
								$replaced = date('Y-m-d H:i', strtotime($user[$replace['reference']]));
							}
						} else {
							if($replace['custom_rule'] != ""){
								$replaced = date($replace['custom_rule'], strtotime(date('Y-m-d H:i:s')));
							} else {
								$replaced = date('Y-m-d H:i', strtotime($replace['default_value']));
							}
						}
					}
				}
				
				if($replace['type'] == 'Currency'){
					if($replace['reference'] == 'variables'){
						if(isset($variables[$replace['reference']])){
							if($replace['custom_rule'] != ""){
								$replaced = $replace['custom_rule']." ".number_format($variables[$replace['reference']], 2, '.', ',');
							} else {
								$replaced = number_format($replace['reference'], 2, '.', ',');
							}
						} else {
							if($replace['custom_rule'] != ""){
								$replaced = $replace['custom_rule']." ".number_format($replace['default_value'], 2, '.', ',');
							} else {
								$replaced = number_format($replace['default_value'], 2, '.', ',');
							}
						}
					} else {
						if($user[$replace['reference']] != ""){
							if($replace['custom_rule'] != ""){
								$replaced = $replace['custom_rule']." ".number_format($user[$replace['reference']], 2, '.', ',');
							} else {
								$replaced = number_format($user[$replace['reference']], 2, '.', ',');
							}
						} else {
							if($replace['custom_rule'] != ""){
								$replaced = $replace['custom_rule']." ".number_format($replace['default_value'], 2, '.', ',');
							} else {
								$replaced = number_format($replace['default_value'], 2, '.', ',');
							}
						}
					}
				}
				
				$text = str_replace($replace['keyword'],$replaced, $text);
			}

			if(!empty($variables)){
				foreach($variables as $key => $var){
					$text = str_replace('%'.$key.'%',$var, $text);
				}
			}
		} 
		
		return $text;
	}

	public function listAutoCrm(){
		$query = Autocrm::with('whatsapp_content')->get()->toArray();
		return response()->json(MyHelper::checkGet($query));
	}

	public function updateAutoCrm(Request $request){
		$post = $request->json()->all();
		
		$id_autocrm = $post['id_autocrm'];
		unset($post['id_autocrm']);
		
		if (isset($post['autocrm_push_image'])) {
			$upload = MyHelper::uploadPhoto($post['autocrm_push_image'], $path = 'img/push/', 600);

			if ($upload['status'] == "success") {
				$post['autocrm_push_image'] = $upload['path'];
			} else{
				$result = [
						'status'	=> 'fail',
						'messages'	=> ['Update Push Notification Image failed.']
					];
				return response()->json($result);
			}
		}

		if(isset($post['whatsapp_content'])){
			$contentWa = $post['whatsapp_content'];
			unset($post['whatsapp_content']);
		}
		
		DB::beginTransaction();
		$query = Autocrm::where('id_autocrm','=',$id_autocrm)->update($post);
		if(!$query){
			DB::rollBack();
			$result = [
					'status'	=> 'fail',
					'messages'	=> ['Update Autocrm Failed.']
				];
			return response()->json($result);
		}

		//whatsapp contents
		if(isset($contentWa)){

			//delete content
			$idOld = array_filter(array_pluck($contentWa,'id_whatsapp_content'));
			$contentOld = WhatsappContent::where('source', 'autocrm')->where('id_reference', $id_autocrm)->whereNotIn('id_whatsapp_content', $idOld)->get();
			if(count($contentOld) > 0){
				foreach($contentOld as $old){
					if($old['content_type'] == 'image' || $old['content_type'] == 'file'){
						$del = MyHelper::deletePhoto(str_replace(env('APP_API_URL'), '', $old['content']));
					}
				}

				$delete =  WhatsappContent::where('source', 'autocrm')->where('id_reference', $id_autocrm)->whereNotIn('id_whatsapp_content', $idOld)->delete();
				if(!$delete){
					DB::rollBack();
					$result = [
							'status'	=> 'fail',
							'messages'	=> ['Update WhatsApp Content Failed.']
						];
					return response()->json($result);
				}
			}

			//create or update content
			foreach($contentWa as $content){

				if($content['content']){
					//delete file if update
					if($content['id_whatsapp_content']){
						$whatsappContent = WhatsappContent::find($content['id_whatsapp_content']);
						if($whatsappContent && ($whatsappContent->content_type == 'image' || $whatsappContent->content_type == 'file')){
							MyHelper::deletePhoto($whatsappContent->content);
						}
					}

					if($content['content_type'] == 'image'){
						if (!file_exists('whatsapp/img/autocrm/')) {
							mkdir('whatsapp/img/autocrm/', 0777, true);
						}

						//upload file
						$upload = MyHelper::uploadPhoto($content['content'], $path = 'whatsapp/img/autocrm/');
						if ($upload['status'] == "success") {
							$content['content'] = env('APP_API_URL').$upload['path'];
						} else{
							DB::rollBack();
							$result = [
									'status'	=> 'fail',
									'messages'	=> ['Update WhatsApp Content Image Failed.']
								];
							return response()->json($result);
						}
					}
					else if($content['content_type'] == 'file'){
						if (!file_exists('whatsapp/file/autocrm/')) {
							mkdir('whatsapp/file/autocrm/', 0777, true);
						}

						$i = 1;
						$filename = $content['content_file_name'];
						while (file_exists('whatsapp/file/autocrm/'.$content['content_file_name'].'.'.$content['content_file_ext'])) {
							$content['content_file_name'] = $filename.'_'.$i;
							$i++;
						}

						$upload = MyHelper::uploadFile($content['content'], $path = 'whatsapp/file/campaign/', $content['content_file_ext'], $content['content_file_name']);
						if ($upload['status'] == "success") {
							$content['content'] = env('APP_API_URL').$upload['path'];
						} else{
							DB::rollBack();
							$result = [
									'status'	=> 'fail',
									'messages'	=> ['Update WhatsApp Content File Failed.']
								];
							return response()->json($result);
						}
					}

					$dataContent['source'] 		 = 'autocrm';
					$dataContent['id_reference'] = $id_autocrm;
					$dataContent['content_type'] = $content['content_type'];
					$dataContent['content'] 	 = $content['content'];

					//for update
					if($content['id_whatsapp_content']){
						$whatsappContent = WhatsappContent::where('id_whatsapp_content',$content['id_whatsapp_content'])->update($dataContent);
					}
					//for create
					else{
						$whatsappContent = WhatsappContent::create($dataContent);
					}

					if(!$whatsappContent){
						DB::rollBack();
						$result = [
								'status'	=> 'fail',
								'messages'	=> ['Update WhatsApp Content Failed.']
							];
						return response()->json($result);
					}
				}
				
			}
		}

		DB::commit();
		return response()->json(MyHelper::checkUpdate($query));
	}
}