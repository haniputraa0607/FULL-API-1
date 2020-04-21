<?php

namespace Modules\InboxGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\User;
use App\Http\Models\UserInbox;
use App\Http\Models\InboxGlobal;
use App\Http\Models\InboxGlobalRule;
use App\Http\Models\InboxGlobalRuleParent;
use App\Http\Models\InboxGlobalRead;
use App\Http\Models\News;

use Modules\InboxGlobal\Http\Requests\MarkedInbox;
use Modules\InboxGlobal\Http\Requests\DeleteUserInbox;

use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;

class ApiInbox extends Controller
{
    function __construct() {
        date_default_timezone_set('Asia/Jakarta');
		$this->user     = "Modules\Users\Http\Controllers\ApiUser";
		$this->inboxGlobal  = "Modules\InboxGlobal\Http\Controllers\ApiInboxGlobal";
		$this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
    }

    public function deleteInboxUser(DeleteUserInbox $request){
    	$delete=UserInbox::where('id_user_inboxes',$request->json('id_inbox'))->delete();
    	return MyHelper::checkDelete($delete);
    }

    public function listInboxUser(Request $request, $mode = false){
        if(is_numeric($phone=$request->json('phone'))){
            $user=User::where('phone',$phone)->first();
        }else{
            $user = $request->user();
        }
        $today = date("Y-m-d H:i:s");
        $arrInbox = [];
        $countUnread = 0;
        $countInbox = 0;
        $arrDate = [];
        $inboxes = InboxGlobal::select(\DB::raw('"global" as type, id_inbox_global as id_inbox,inbox_global_subject as subject,inbox_global_clickto as clickto, inbox_global_link as link, inbox_global_id_reference as id_reference, inbox_global_content as content, created_at, "unread" as status, 0 as id_brand'))->with('inbox_global_rule_parents', 'inbox_global_rule_parents.rules')
            ->where('inbox_global_start', '<=', $today)
            ->where('inbox_global_end', '>=', $today)
            ->union(UserInbox::select(\DB::raw('"private" as type,id_user_inboxes as id_inbox,inboxes_subject as subject,inboxes_clickto as clickto,inboxes_link as link,inboxes_id_reference as id_reference,inboxes_content as content,inboxes_send_at as created_at, CASE WHEN `read` = 1 THEN "read" ELSE "unread" END as status,id_brand'))->where('id_user','=',$user['id']))
            ->orderBy('created_at', 'desc');
        if($request->page){
            $inboxes = $inboxes->paginate(50)
                ->toArray();
            $globals = $inboxes['data'];
        }else{
            $globals = $inboxes->get()->toArray();
        }
        foreach($globals as $ind => $global){
            $content = $global;
            if($global['type'] == 'global'){
                $cons = array();
                $cons['subject'] = 'phone';
                $cons['operator'] = '=';
                $cons['parameter'] = $user['phone'];
                array_push($global['inbox_global_rule_parents'], ['rule' => 'and', 'rule_next' => 'and', 'rules' => [$cons]]);
                $users = app($this->user)->UserFilter($global['inbox_global_rule_parents']);
                $content['subject'] = app($this->autocrm)->TextReplace($global['subject'], $user['phone']);
                if($content['content']){
                    $content['content'] = app($this->autocrm)->TextReplace($global['content'], $user['phone']);
                }
                if(isset($users['status']) && $users['status'] == 'success'){
                    $read = InboxGlobalRead::where('id_inbox_global', $global['id_inbox'])->where('id_user', $user['id'])->first();
                    if(!empty($read)){
                        $content['status'] = 'read';
                    }else{
                        $content['status'] = 'unread';
                    }
                } else {
                    continue;
                }
            }
            if(!$content['id_reference']){
                $content['id_reference'] = 0;
            }
            if($content['clickto']=='Deals Detail'){
                $content['id_brand'] = $content['id_brand'];
            }else{
                unset($content['id_brand']);
            }
            if($content['clickto'] == 'News'){
                $news = News::find($global['id_reference']);
                if($news){
                    $content['news_title'] = $news->news_title;
                    $content['url'] = env('APP_URL').'news/webview/'.$news->id_news;
                }
            }
            if($content['clickto'] != 'Content'){
                $content['content'] = null;
            }
            if($content['clickto'] != 'Link'){
                $content['link'] = null;
            }

            if(is_numeric(strpos(strtolower($global['subject']), 'transaksi')) || is_numeric(strpos(strtolower($global['subject']), 'transaction'))
                || is_numeric(strpos(strtolower($global['subject']), 'deals'))  || is_numeric(strpos(strtolower($global['subject']), 'voucher'))
                || is_numeric(strpos(strtolower($global['subject']), 'order')) ||
                is_numeric(strpos(strtolower($global['subject']), 'first'))){
                $content['clickto'] = $global['clickto'];
            }elseif (is_numeric(strpos(strtolower($global['subject']), 'point'))){
                $content['clickto'] = 'Membership';
            }elseif (is_numeric(strpos(strtolower($global['subject']), 'subscription'))){
                $content['clickto'] = 'Subscription';
            }else{
                $content['clickto'] = '';
            }

            unset($content['inbox_global_rule_parents']);
            if($mode == 'simple'){
                $arrInbox[] = $content;
            }else{
                if(!in_array(date('Y-m-d', strtotime($content['created_at'])), $arrDate)){
                    $arrDate[] = date('Y-m-d', strtotime($content['created_at']));
                    $temp['created'] =  date('Y-m-d', strtotime($content['created_at']));
                    $temp['list'][0] =  $content;
                    $arrInbox[] = $temp;
                }else{
                    $position = array_search(date('Y-m-d', strtotime($content['created_at'])), $arrDate);
                    $arrInbox[$position]['list'][] = $content;
                }
            }
        }
        if(isset($arrInbox) && !empty($arrInbox)) {
            if($mode == 'simple'){
                usort($arrInbox, function($a, $b){
                    $t1 = strtotime($a['created_at']);
                    $t2 = strtotime($b['created_at']);
                    return $t2 - $t1;
                });
            }else{
                foreach ($arrInbox as $key => $value) {
                    usort($arrInbox[$key]['list'], function($a, $b){
                        $t1 = strtotime($a['created_at']);
                        $t2 = strtotime($b['created_at']);
                        return $t2 - $t1;
                    });
                }
                usort($arrInbox, function($a, $b){
                    $t1 = strtotime($a['created']);
                    $t2 = strtotime($b['created']);
                    return $t2 - $t1;
                });
            }
            if($request->page){
                $inboxes['data'] = $arrInbox;
                $inboxes['count_unread'] = $this->listInboxUnread($user['id']);
                $result = [
                    'status'  => 'success',
                    'result'  => $inboxes,
                ];
            }else{
                $result = [
                    'status'  => 'success',
                    'result'  => $arrInbox,
                    'count_unread' => $this->listInboxUnread($user['id']),
                ];
            }
        } else {
            $result = [
                'status'  => 'fail',
                'messages'  => ["You Don't Have Any Messages Yet"]
            ];
        }
        return response()->json($result);
    }

    public function markedInbox(MarkedInbox $request){
		$user = $request->user();
		$post = $request->json()->all();
		if($post['type'] == 'private'){
			$userInbox = UserInbox::where('id_user_inboxes', $post['id_inbox'])->first();
			if(!empty($userInbox)){
				$update = UserInbox::where('id_user_inboxes', $post['id_inbox'])->update(['read' => '1']);
				// if(!$update){
				// 	$result = [
				// 		'status'  => 'fail',
				// 		'messages'  => ['Failed marked inbox']
				// 	];
				// }else{
					$countUnread = $this->listInboxUnread( $user['id']);
					$result = [
						'status'  => 'success',
						'result'  => ['count_unread' => $countUnread]
					];
				// }
			}else{
				$result = [
					'status'  => 'fail',
					'messages'  => ['Inbox not found']
				];
			}
		}elseif($post['type'] == 'global'){
			$inboxGlobal = InboxGlobal::where('id_inbox_global', $post['id_inbox'])->first();
			if(!empty($inboxGlobal)){

				$inboxGlobalRead = InboxGlobalRead::where('id_inbox_global', $post['id_inbox'])->where('id_user', $user['id'])->first();
				if(empty($inboxGlobalRead)){
					$create = InboxGlobalRead::create(['id_inbox_global' => $post['id_inbox'], 'id_user' => $user['id']]);
					if(!$create){
						$result = [
							'status'  => 'fail',
							'messages'  => ['Failed marked inbox']
						];
					}
				}

				$countUnread = $this->listInboxUnread( $user['id']);
				$result = [
					'status'  => 'success',
					'result'  => ['count_unread' => $countUnread]
				];

			}else{
				$result = [
					'status'  => 'fail',
					'messages'  => ['Inbox not found']
				];
			}

		}elseif($post['type'] == 'multiple'){
			if($post['inboxes']['global']??false){
				foreach ($post['inboxes']['global'] as $id_inbox) {
					$inboxGlobal = InboxGlobal::where('id_inbox_global', $id_inbox)->first();
					if($inboxGlobal){
						$inboxGlobalRead = InboxGlobalRead::where('id_inbox_global', $id_inbox)->where('id_user', $user['id'])->first();
						if(empty($inboxGlobalRead)){
							$create = InboxGlobalRead::create(['id_inbox_global' => $id_inbox, 'id_user' => $user['id']]);
						}
					}
				}
			}
			if($post['inboxes']['private']){
				$update = UserInbox::whereIn('id_user_inboxes', $post['inboxes']['private'])->where('id_user', $user['id'])->update(['read' => '1']);
			}
			$countUnread = $this->listInboxUnread( $user['id']);
			$result = [
				'status'  => 'success',
				'result'  => ['count_unread' => $countUnread]
			];
		}
		return response()->json($result);
	}

	public function listInboxUnread($id_user){
		$user = User::find($id_user);

		$today = date("Y-m-d H:i:s");
		$countUnread = 0;

		$read = array_pluck(InboxGlobalRead::where('id_user', $user['id'])->get(), 'id_inbox_global');

		$globals = InboxGlobal::with('inbox_global_rule_parents', 'inbox_global_rule_parents.rules')
							->where('inbox_global_start', '<=', $today)
							->where('inbox_global_end', '>=', $today)
							->get()
							->toArray();

		foreach($globals as $global){
			$cons = array();
			$cons['subject'] = 'phone';
			$cons['operator'] = '=';
			$cons['parameter'] = $user['phone'];

			array_push($global['inbox_global_rule_parents'], ['rule' => 'and', 'rule_next' => 'and', 'rules' => [$cons]]);
			$users = app($this->user)->UserFilter($global['inbox_global_rule_parents']);

			if(($users['status']??false)=='success'){
				$read = InboxGlobalRead::where('id_inbox_global', $global['id_inbox_global'])->where('id_user', $id_user)->first();
				if(empty($read)){
					$countUnread += 1;
				}
			}
		}

		$privates = UserInbox::where('id_user','=',$user['id'])->where('read', '0')->get();


		$countUnread = $countUnread + count($privates);

		return $countUnread;
	}

	public function unread(Request $request){
		$user=$request->user();
		$countUnread=$this->listInboxUnread($user->id);
		return [
			'status'=>'success',
			'result'=>['unread'=>$countUnread]
		];
	}

}
