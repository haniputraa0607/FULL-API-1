<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Http\Models\User;
use App\Http\Models\UserInbox;
use App\Http\Models\Campaign;
use App\Http\Models\CampaignEmailSent;
use App\Http\Models\CampaignSmsSent;
use App\Http\Models\CampaignPushSent;
use App\Http\Models\Outlet;
use App\Http\Models\News;
use App\Http\Models\Deal;
use App\Http\Models\Setting;
use App\Http\Models\OauthAccessToken;
//use Modules\Campaign\Http\Requests\campaign_list;
//use Modules\Campaign\Http\Requests\campaign_create;
//use Modules\Campaign\Http\Requests\campaign_update;
//use Modules\Campaign\Http\Requests\campaign_delete;

use App\Lib\PushNotificationHelper;
use App\Lib\classMaskingJson;
use App\Lib\classJatisSMS;
use DB;
use App\Lib\MailQueue as Mail;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data=$data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        date_default_timezone_set('Asia/Jakarta');
        $userr     = "Modules\Users\Http\Controllers\ApiUser";
        $autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $rajasms = new classMaskingJson();
		$this->jatissms = new classJatisSMS();
        $campaign=$this->data['campaign'];
        $type=$this->data['type'];
        $recipient=$this->data['recipient'];
        switch ($type) {
            case 'email':
                foreach($recipient as $key => $receipient){
                    $receipient = trim($receipient);
                    $to = $receipient;
                    $em_arr = explode('@',$receipient);
                    $name = ucwords(str_replace("_"," ", str_replace("-"," ", str_replace("."," ", $em_arr[0]))));

                    $subject = app($autocrm)->TextReplace($campaign['campaign_email_subject'], $receipient, null, 'email');
                    $content = app($autocrm)->TextReplace($campaign['campaign_email_content'], $receipient, null, 'email');

                    // get setting email
                    $setting = array();
                    $set = Setting::where('key', 'email_from')->first();
                    if(!empty($set)){
                        $setting['email_from'] = $set['value'];
                    }else{
                        $setting['email_from'] = null;
                    }
                    $set = Setting::where('key', 'email_sender')->first();
                    if(!empty($set)){
                        $setting['email_sender'] = $set['value'];
                    }else{
                        $setting['email_sender'] = null;
                    }
                    $set = Setting::where('key', 'email_reply_to')->first();
                    if(!empty($set)){
                        $setting['email_reply_to'] = $set['value'];
                    }else{
                        $setting['email_reply_to'] = null;
                    }
                    $set = Setting::where('key', 'email_reply_to_name')->first();
                    if(!empty($set)){
                        $setting['email_reply_to_name'] = $set['value'];
                    }else{
                        $setting['email_reply_to_name'] = null;
                    }
                    $set = Setting::where('key', 'email_cc')->first();
                    if(!empty($set)){
                        $setting['email_cc'] = $set['value'];
                    }else{
                        $setting['email_cc'] = null;
                    }
                    $set = Setting::where('key', 'email_cc_name')->first();
                    if(!empty($set)){
                        $setting['email_cc_name'] = $set['value'];
                    }else{
                        $setting['email_cc_name'] = null;
                    }
                    $set = Setting::where('key', 'email_bcc')->first();
                    if(!empty($set)){
                        $setting['email_bcc'] = $set['value'];
                    }else{
                        $setting['email_bcc'] = null;
                    }
                    $set = Setting::where('key', 'email_bcc_name')->first();
                    if(!empty($set)){
                        $setting['email_bcc_name'] = $set['value'];
                    }else{
                        $setting['email_bcc_name'] = null;
                    }
                    $set = Setting::where('key', 'email_logo')->first();
                    if(!empty($set)){
                        $setting['email_logo'] = $set['value'];
                    }else{
                        $setting['email_logo'] = null;
                    }
                    $set = Setting::where('key', 'email_logo_position')->first();
                    if(!empty($set)){
                        $setting['email_logo_position'] = $set['value'];
                    }else{
                        $setting['email_logo_position'] = null;
                    }
                    $set = Setting::where('key', 'email_copyright')->first();
                    if(!empty($set)){
                        $setting['email_copyright'] = $set['value'];
                    }else{
                        $setting['email_copyright'] = null;
                    }
                    $set = Setting::where('key', 'email_contact')->first();
                    if(!empty($set)){
                        $setting['email_contact'] = $set['value'];
                    }else{
                        $setting['email_contact'] = null;
                    }
                    $set = Setting::where('key', 'email_setting_url')->first();
                    if(!empty($set)){
                        $setting['email_setting_url'] = (array)json_decode($set['value_text']);
                    }else{
                        $setting['email_setting_url'] = null;
                    }

                    $data = array(
                        'customer' => $name,
                        'html_message' => $content,
                        'setting' => $setting
                    );

                    try{
                        Mail::send('emails.test', $data, function($message) use ($to,$subject,$name,$setting)
                        {

                            if(stristr($to, 'gmail.con')){
                                $to = str_replace('gmail.con', 'gmail.com', $to);
                            }

                            $message->to($to, $name)->subject($subject);
							if(env('MAIL_DRIVER') == 'mailgun'){
								$message->trackClicks(true)
										->trackOpens(true);
							}
                            if(!empty($setting['email_from']) && !empty($setting['email_sender'])){
                                $message->from($setting['email_sender'], $setting['email_from']);
                            }else if(!empty($setting['email_sender'])){
                                $message->from($setting['email_sender']);
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
                        }, 'email_default', [
                            'type' => 'send_campaign',
                            'data' => ['id_campaign' => $campaign['id_campaign']]
                        ]);
                        $outbox = [];
                        $outbox['id_campaign'] = $campaign['id_campaign'];
                        $outbox['email_sent_to'] = $receipient;
                        $outbox['email_sent_subject'] = $subject;
                        $outbox['email_sent_message'] = $content;
                        $outbox['email_sent_send_at'] = date("Y-m-d H:i:s");

                        $logs = CampaignEmailSent::create($outbox);
                    }catch(\Exception $e){
                        print "Mail to $receipient not send\n";
                    }

                }
                break;

            case 'sms':
                $senddata = array(
                    'apikey' => env('SMS_KEY'),
                    'callbackurl' => env('APP_URL'),
                    'datapacket'=>array()
                );


                foreach($recipient as $key => $receipient){
                    $content    = app($autocrm)->TextReplace($campaign['campaign_sms_content'], $receipient);

                    switch (env('SMS_GATEWAY')) {
						case 'Jatis':
							$senddata = [
								'userid'	=> env('SMS_USER'),
								'password'	=> env('SMS_PASSWORD'),
								'msisdn'	=> '62'.substr($receipient,1),
								'sender'	=> env('SMS_SENDER'),
								'division'	=> env('SMS_DIVISION'),
								'batchname'	=> env('SMS_BATCHNAME'),
                                'uploadby'	=> env('SMS_UPLOADBY'),
                                'channel'   => env('SMS_CHANNEL')
							];

                            $senddata['message'] = $content;

							$this->jatissms->setData($senddata);
							$send = $this->jatissms->send();

							break;
						case 'RajaSMS':
							$senddata = array(
								'apikey' => env('SMS_KEY'),
								'callbackurl' => env('APP_URL'),
								'datapacket'=>array()
							);

                            array_push($senddata['datapacket'],array(
                                'number' => trim($receipient),
                                'message' => urlencode(stripslashes(utf8_encode($content))),
                                'sendingdatetime' => ""));

							$this->rajasms->setData($senddata);
							$send = $this->rajasms->send();
							break;
						default:
							$senddata = array(
								'apikey' => env('SMS_KEY'),
								'callbackurl' => env('APP_URL'),
								'datapacket'=>array()
							);

                            array_push($senddata['datapacket'],array(
                                'number' => trim($receipient),
                                'message' => urlencode(stripslashes(utf8_encode($content))),
                                'sendingdatetime' => ""));

							$this->rajasms->setData($senddata);
							$send = $this->rajasms->send();
							break;
					}

                    $outbox = [];
                    $outbox['id_campaign'] = $campaign['id_campaign'];
                    $outbox['sms_sent_to'] = $receipient;
                    $outbox['sms_sent_content'] = $content;
                    $outbox['sms_sent_send_at'] = date("Y-m-d H:i:s");

                    $logs = CampaignSmsSent::create($outbox);

                    DB::table('campaigns')
                       ->where('id_campaign', $campaign['id_campaign'])
                       ->update([
                           'campaign_sms_count_sent' => DB::raw('campaign_sms_count_sent + 1')
                       ]);
                }
                break;

            case 'push':

                foreach($recipient as $key => $receipient){
                    $dataOptional          = [];
                    $image = null;
                    if (isset($campaign['campaign_push_image']) && $campaign['campaign_push_image'] != null) {
                        $dataOptional['image'] = env('S3_URL_API').$campaign['campaign_push_image'];
                        $image = env('S3_URL_API').$campaign['campaign_push_image'];
                    }

                    if (isset($campaign['campaign_push_clickto']) && $campaign['campaign_push_clickto'] != null) {
                        $dataOptional['type'] = $campaign['campaign_push_clickto'];
                    } else {
                        $dataOptional['type'] = 'Home';
                    }

                    if (isset($campaign['campaign_push_link']) && $campaign['campaign_push_link'] != null) {
                        if($dataOptional['type'] == 'Link')
                            $dataOptional['link'] = $campaign['campaign_push_link'];
                        else
                            $dataOptional['link'] = null;
                    } else {
                        $dataOptional['link'] = null;
                    }

                    if (isset($campaign['campaign_push_id_reference']) && $campaign['campaign_push_id_reference'] != null) {
                        $dataOptional['id_reference'] = (int)$campaign['campaign_push_id_reference'];
                    } else{
                        $dataOptional['id_reference'] = 0;
                    }

                    if($campaign['campaign_push_clickto'] == 'News' && $campaign['campaign_push_id_reference'] != null){
                        $news = News::find($campaign['campaign_push_id_reference']);
                        if($news){
                            $dataOptional['news_title'] = $news->news_title;
                            $dataOptional['title'] = $news->news_title;
                        }
                        $dataOptional['url'] = env('APP_URL').'news/webview/'.$campaign['campaign_push_id_reference'];
                    }
                    elseif($campaign['campaign_push_clickto'] == 'Order' && $campaign['campaign_push_id_reference'] != null){
                        $outlet = Outlet::find($campaign['campaign_push_id_reference']);
                        if($outlet){
                            $dataOptional['title'] = $outlet->outlet_name;
                        }
                    }
                    elseif($campaign['campaign_push_clickto'] == 'Deals' && $campaign['campaign_push_id_reference'] != null){
                        $deals = Deal::find($campaign['campaign_push_id_reference']);
                        if($deals){
                            $dataOptional['title'] = $deals->deals_title;
                        }
                    }

                    //push notif logout
                    if($campaign['campaign_push_clickto'] == 'Logout'){
                        $user = User::where('phone', $receipient)->first();
                        if($user){
                            //delete token
                            $del = OauthAccessToken::join('oauth_access_token_providers', 'oauth_access_tokens.id', 'oauth_access_token_providers.oauth_access_token_id')
                                    ->where('oauth_access_tokens.user_id', $user['id'])->where('oauth_access_token_providers.provider', 'users')->delete();

                        }

                    }

                    $deviceToken = PushNotificationHelper::searchDeviceToken("phone", $receipient);

                    $subject = app($autocrm)->TextReplace($campaign['campaign_push_subject'], $receipient);
                    $content = app($autocrm)->TextReplace($campaign['campaign_push_content'], $receipient);
                    $deviceToken = PushNotificationHelper::searchDeviceToken("phone", $receipient);

                    if (!empty($deviceToken)) {
                        if (isset($deviceToken['token']) && !empty($deviceToken['token'])) {
                            try{
                                $push = PushNotificationHelper::sendPush($deviceToken['token'], $subject, $content, $image, $dataOptional);

                                if (isset($push['success']) && $push['success'] > 0) {
                                    $push = [];
                                    $push['id_campaign'] = $campaign['id_campaign'];
                                    $push['push_sent_to'] = $receipient;
                                    $push['push_sent_subject'] = $subject;
                                    $push['push_sent_content'] = $content;
                                    $push['push_sent_send_at'] = date('Y-m-d H:i:s', strtotime("+ 5 minutes"));

                                    $logs = CampaignPushSent::create($push);
                                    DB::table('campaigns')
                                        ->where('id_campaign', $campaign['id_campaign'])
                                        ->update([
                                            'campaign_push_count_sent' => DB::raw('campaign_push_count_sent + 1')
                                        ]);
                                }
                            }catch(\Exception $e){
                                \Log::error($e);
                            }
                        }
                    }
                }
                break;

            case 'inbox':
                $user = User::whereIn('phone',$recipient)->get()->toArray();

                foreach($user as $key => $receipient){

                    $inbox = [];
                    $inbox['id_campaign'] = $campaign['id_campaign'];
                    $inbox['id_user']     = $receipient['id'];
                    $inbox['inboxes_subject'] = app($autocrm)->TextReplace($campaign['campaign_inbox_subject'], $receipient['id'], null, 'id');
                    $inbox['inboxes_clickto'] = $campaign['campaign_inbox_clickto'];

                    if($campaign['campaign_inbox_clickto'] == 'Content'){
                        $inbox['inboxes_content'] = app($autocrm)->TextReplace($campaign['campaign_inbox_content'], $receipient['id'], null, 'id');
                    }

                    if($campaign['campaign_inbox_clickto'] == 'Link'){
                        $inbox['inboxes_link'] = $campaign['campaign_inbox_link'];
                    }

                    if(!empty($campaign['campaign_inbox_id_reference'])){
                        $inbox['inboxes_id_reference'] = $campaign['campaign_inbox_id_reference'];
                    }else{
                        $inbox['inboxes_id_reference'] = 0;
                    }

                    if($campaign['campaign_inbox_clickto'] == 'No Action' || !empty($campaign['campaign_inbox_clickto'])){
                        $inbox['inboxes_clickto'] = 'Default';
                    }

                    $inbox['inboxes_send_at'] = date("Y-m-d H:i:s");
                    $inbox['created_at'] = date("Y-m-d H:i:s");
                    $inbox['updated_at'] = date("Y-m-d H:i:s");
                    $inbox['created_by']  = $campaign['created_by'];
                    $inbox['updated_by']  = $campaign['updated_by'];

                    $inboxQuery = UserInbox::insert($inbox);
                }
                break;

            case 'whatsapp':
                $api_key = Setting::where('key', 'api_key_whatsapp')->first();
                if($api_key){
                    if($api_key->value){
                        foreach($recipient as $key => $receipient){

                            $contentWaSent = [];
                            //send every content whatsapp
                            foreach($campaign->whatsapp_content as $contentWhatsapp){
                                if($contentWhatsapp['content_type'] == 'text'){
                                    $content = app($this->autocrm)->TextReplace($contentWhatsapp['content'], $receipient);
                                }else{
                                    $content = $contentWhatsapp['content'];
                                }
                                // add country code in number
                                $ptn = "/^0/";
                                $rpltxt = "62";
                                $phone = preg_replace($ptn, $rpltxt, $receipient);

                                $send = $this->apiwha->send($api_key->value, $phone, $content);

                                //api key whatsapp not valid
                                if(isset($send['result_code']) && $send['result_code'] == -1){
                                    break 2;
                                }

                                $dataContent['content'] = $content;
                                $dataContent['content_type'] = $contentWhatsapp['content_type'];
                                array_push($contentWaSent, $dataContent);

                            }

                            $outbox = [];
                            $outbox['id_campaign'] = $campaign['id_campaign'];
                            $outbox['whatsapp_sent_to'] = $receipient;
                            $outbox['whatsapp_sent_send_at'] = date("Y-m-d H:i:s");

                            $logs = CampaignWhatsappSent::create($outbox);
                            if($logs){
                                foreach($dataContent as $data){
                                    $data['id_campaign_whatsapp_sent'] = $logs['id_campaign_whatsapp_sent'];
                                    $create = CampaignWhatsappSentContent::create($data);
                                }
                            }

                            DB::table('campaigns')
                            ->where('id_campaign', $campaign['id_campaign'])
                            ->update([
                                'campaign_whatsapp_count_sent' => DB::raw('campaign_whatsapp_count_sent + 1')
                            ]);
                        }
                    }
                }
                break;

            default:
                // print("Do nothing\n");
                break;
        }
        return true;
    }
}
