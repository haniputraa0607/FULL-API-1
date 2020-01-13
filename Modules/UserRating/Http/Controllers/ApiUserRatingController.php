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
    public function index(Request $request)
    {
        $post = $request->json()->all();
        $data = UserRating::with(['transaction'=>function($query){
            $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal','id_outlet');
        },'transaction.outlet'=>function($query){
            $query->select('id_outlet','outlet_code','outlet_name');
        },'user'=>function($query){
            $query->select('id','name','phone');
        }]);

        // if($outlet_code = ($request['outlet_code']??false)){
        //     $data->whereHas('transaction.outlet',function($query) use ($outlet_code){
        //         $query->where('outlet_code',$outlet_code);
        //     });
        // }

        if($post['rule']??false){
            $this->filterList($data,$post['rule'],$post['operator']??'and');
        }

        $data= $data->paginate(10)->toArray();
        $data['data'] = array_map(function($var){
            $var['id_user_rating'] = MyHelper::createSlug($var['id_user_rating'],$var['created_at']);
            return $var;
        },$data['data']);
        return MyHelper::checkGet($data);
    }

    public function filterList($model,$rule,$operator='and'){
        $newRule=[];
        $where=$operator=='and'?'where':'orWhere';
        foreach ($rule as $var) {
            $var1=['operator'=>$var['operator']??'=','parameter'=>$var['parameter']??null];
            if($var1['operator']=='like'){
                $var1['parameter']='%'.$var1['parameter'].'%';
            }
            $newRule[$var['subject']][]=$var1;
        }
        if($rules=$newRule['review_date']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Date'}('created_at',$rul['operator'],$rul['parameter']);
            }
        }
        if($rules=$newRule['star']??false){
            foreach ($rules as $rul) {
                $model->$where('star',$rul['operator'],$rul['parameter']);
            }
        }
        if($rules=$newRule['transaction_date']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction',function($query) use ($rul){
                    $query->whereDate('transaction_date',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['transaction_type']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction',function($query) use ($rul){
                    $query->where('transaction_type',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['transaction_receipt_number']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction',function($query) use ($rul){
                    $query->where('transaction_receipt_number',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['user_name']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('user',function($query) use ($rul){
                    $query->where('name',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['user_phone']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('user',function($query) use ($rul){
                    $query->where('phone',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['user_email']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('user',function($query) use ($rul){
                    $query->where('email',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['outlet']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction.outlet',function($query) use ($rul){
                    $query->where('id_outlet',$rul['operator'],$rul['parameter']);
                });
            }
        }
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
            'suggestion' => $post['suggestion']??'',
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
        $post = $request->json()->all();
        $id = $post['id'];
        $exploded = MyHelper::explodeSlug($id);
        return MyHelper::checkGet(UserRating::with(['transaction'=>function($query){
            $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal','id_outlet');
        },'transaction.outlet'=>function($query){
            $query->select('id_outlet','outlet_code','outlet_name');
        },'user'=>function($query){
            $query->select('id','name','phone');
        }])->where([
            'id_user_rating'=>$exploded[0],
            'created_at'=>$exploded[1]
        ])->first());
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
            $transaction = Transaction::select('id_transaction','transaction_receipt_number','transaction_date','id_outlet')->with(['outlet'=>function($query){
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
            }else{
                UserRatingLog::create([
                    'id_user' => $user->id,
                    'refuse_count' => 1,
                    'last_popup' => date('Y-m-d H:i:s')
                ]);
            }
            $transaction = Transaction::select('id_transaction','transaction_receipt_number','transaction_date','id_outlet')->with(['outlet'=>function($query){
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
        $result['question_text'] = Setting::where('key','rating_question_text')->pluck('value_text')->first()?:'How about our Service';
        $result['transaction_date'] = date('d M Y H:i',strtotime($transaction->transaction_date));
        $defaultOptions = [
            'question'=>Setting::where('key','default_rating_question')->pluck('value_text')->first()?:'What\'s best from us?',
            'options' =>explode(',',Setting::where('key','default_rating_options')->pluck('value_text')->first()?:'Cleanness,Accuracy,Employee Hospitality,Process Time')];
        $options = ['1'=>$defaultOptions,'2'=>$defaultOptions,'3'=>$defaultOptions,'4'=>$defaultOptions,'5'=>$defaultOptions];
        $ratings = RatingOption::select('star','question','options')->get();
        foreach ($ratings as $rt) {
            $stars = explode(',',$rt['star']);
            foreach ($stars as $star) {
                $options[$star] = [
                    'question'=>$rt['question'],
                    'options'=>explode(',',$rt['options'])
                ];
            }
        }
        $result['options'] = $options;
        $result['webview_url'] = env('APP_API_URL').'api/transaction/web/view/trx/'.$result['id'];
        return MyHelper::checkGet($result);
    }
}
