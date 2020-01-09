<?php

namespace Modules\UserRating\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Setting;
use App\Http\Models\Transaction;
use Modules\UserRating\Entities\UserRating;
use Modules\UserRating\Entities\RatingOption;

use App\Lib\MyHelper;

use Modules\UserRating\Entities\UserRatingLog;

class ApiUserRatingController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return MyHelper::checkGet(UserRating::paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $post = $request->json()->all();
        $id = $post['id'];
        $exploded = explode(',',$id);
        $trx = Transaction::where([
            'id_transaction'=>$exploded[1],
            'transaction_receipt_number'=>$exploded[0],
            'id_user'=>$request->user()->id
        ])->first();
        if(!$trx){
            return [
                'status'=>'fail',
                'messages'=>['Transaction not found']
            ];
        }
        $insert = [
            'id_transaction' => $trx->id_transaction,
            'id_user' => $request->user()->id,
            'rating_value' => $post['rating_value'],
            'sugestion' => $post['sugestion']??'',
            'option_question' => $post['option_question'],
            'option_value' => implode(',',$post['option_value']??[])
        ];
        $create = UserRating::updateOrCreate(['id_transaction'=>$trx->id_transaction],$insert);
        return MyHelper::checkCreate($create);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        return MyHelper::checkGet(UserRating::find($request->json('id_user_rating')));
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        return MyHelper::checkDelete(UserRating::find($request->json('id_user_rating'))->delete());
    }
    
    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function getDetail(Request $request) {
        $post = $request->json()->all();
        // rating item
        if($post['id']??false){
            $id_trx = explode(',',$post['id']);
            $id_transaction = $id_trx[1]??'';
            $rn = $id_trx[0]??'';
            $transaction = Transaction::select('id_transaction','transaction_receipt_number','id_outlet')->with(['outlet'=>function($query){
                $query->select('outlet_name','id_outlet');
            }])
            ->where(['transaction_receipt_number'=>$rn,'id_transaction'=>$id_transaction])
            ->find($id_transaction);
            if(!$transaction){
                return [
                    'status' => 'fail',
                    'messages' => ['Transaction not found']
                ];
            }
        }else{
            $user = $request->user();
            $user->load('log_popup');
            $log_popup = $user->log_popup;
            if($log_popup){
                $interval =(Setting::where('key','popup_min_interval')->pluck('value')->first()?:15)*60;
                if(
                    $log_popup->refuse_count>=(Setting::where('key','popup_max_refuse')->pluck('value')->first()?:3) ||
                    strtotime($log_popup->last_popup)+$interval>time()
                ){
                    return MyHelper::checkGet([]);
                }
                $log_popup->refuse_count++;
                $log_popup->last_popup = date('Y-m-d H:i:s');
                $log_popup->save();
            }elseif(false){
                UserRatingLog::create([
                    'id_user' => $user->id,
                    'refuse_count' => 1,
                    'last_popup' => date('Y-m-d H:i:s')
                ]);
            }
            $transaction = Transaction::select('id_transaction','transaction_receipt_number','id_outlet')->with(['outlet'=>function($query){
                $query->select('outlet_name','id_outlet');
            }])
            ->where('show_rate_popup',1)
            ->first();
            if(!$transaction){
                return MyHelper::checkGet([]);
            }
        }
        $result['id_transaction'] = $transaction->id_transaction;
        $result['id'] = $transaction->transaction_receipt_number.','.$transaction->id_transaction;
        $result['transaction_receipt_number'] = $transaction->transaction_receipt_number;
        $result['question_text'] = Setting::where('key','rating_question_text')->pluck('value')->first()?:'How about our Service';
        $defaultOptions = [
            'question'=>Setting::where('key','default_rating_options')->pluck('value')->first()?:'What\'s best from us?',
            'options' =>explode(',',Setting::where('key','default_rating_options')->pluck('value')->first()?:'Cleanness,Accuracy,Employee Hospitality,Process Time')];
        $options = ['1'=>$defaultOptions,'2'=>$defaultOptions,'3'=>$defaultOptions,'4'=>$defaultOptions,'5'=>$defaultOptions];
        $ratings = RatingOption::select('rule_operator','value','question','options')->orderBy('order','desc')->get();
        foreach ($ratings as $rating) {
            if($rating->rule_operator == '<'){
                for ($i=$rating->value-1; $i > 0; $i--) { 
                    $options[(string)$i] = [
                        'question' => $rating->question,
                        'options' => explode(',',$rating->options)
                    ];
                }
            }elseif($rating->rule_operator == '>'){
                for ($i=$rating->value+1; $i <= 5; $i++) { 
                    $options[(string)$i] = [
                        'question' => $rating->question,
                        'options' => explode(',',$rating->options)
                    ];
                }
            }elseif($rating->rule_operator == '<='){
                for ($i=$rating->value; $i > 0; $i--) { 
                    $options[(string)$i] = [
                        'question' => $rating->question,
                        'options' => explode(',',$rating->options)
                    ];
                }
            }elseif($rating->rule_operator == '>='){
                for ($i=$rating->value; $i <= 5; $i++) { 
                    $options[(string)$i] = [
                        'question' => $rating->question,
                        'options' => explode(',',$rating->options)
                    ];
                }
            }else{
                $options[(string)$rating->value] = [
                    'question' => $rating->question,
                    'options' => explode(',',$rating->options)
                ];
            }
        }
        $result['options'] = $options;
        $result['webview_url'] = env('APP_API_URL').'api/transaction/web/view/trx/'.$result['id'];
        return MyHelper::checkGet($result);
    }
}
