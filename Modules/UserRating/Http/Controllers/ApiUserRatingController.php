<?php

namespace Modules\UserRating\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Outlet;
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
        }])->orderBy('id_user_rating','desc');

        // if($outlet_code = ($request['outlet_code']??false)){
        //     $data->whereHas('transaction.outlet',function($query) use ($outlet_code){
        //         $query->where('outlet_code',$outlet_code);
        //     });
        // }

        if($post['rule']??false){
            $this->filterList($data,$post['rule'],$post['operator']??'and');
        }

        $data= $data->paginate(10)->toArray();
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
                $model->$where('rating_value',$rul['operator'],$rul['parameter']);
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
        $user = $request->user();
        $trx = Transaction::where([
            'id_transaction'=>$id,
            'id_user'=>$request->user()->id
        ])->first();
        if(!$trx){
            return [
                'status'=>'fail',
                'messages'=>['Transaction not found']
            ];
        }
        $max_rating_value = Setting::select('value')->where('key','response_max_rating_value')->pluck('value')->first()?:2;
        if($post['rating_value'] <= $max_rating_value){
            $trx->load('outlet_name');
            $variables = [
                'receipt_number' => $trx->transaction_receipt_number,
                'outlet_name' => $trx->outlet_name->outlet_name,
                'transaction_date' => date('d F Y H:i',strtotime($trx->transaction_date)),
                'rating_value' => (string) $post['rating_value'],
                'suggestion' => $post['suggestion']??'',
                'question' => $post['option_question'],
                'selected_option' => implode(',',array_map(function($var){return trim($var,'"');},$post['option_value']??[]))
            ];
            app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('User Rating', $user->phone, $variables,null,true);
        }
        $insert = [
            'id_transaction' => $trx->id_transaction,
            'id_user' => $request->user()->id,
            'rating_value' => $post['rating_value'],
            'suggestion' => $post['suggestion']??'',
            'option_question' => $post['option_question'],
            'option_value' => implode(',',array_map(function($var){return trim($var,'"');},$post['option_value']??[]))
        ];
        $create = UserRating::updateOrCreate(['id_transaction'=>$trx->id_transaction],$insert);
        UserRatingLog::where('id_user',$request->user()->id)->update(['refuse_count' => 0]);
        if($create){
            Transaction::where('id_transaction',$trx->id_transaction)->update(['show_rate_popup'=>0]);
        }
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
        $data = UserRating::with(['transaction'=>function($query){
            $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal','id_outlet');
        },'transaction.outlet'=>function($query){
            $query->select('id_outlet','outlet_code','outlet_name');
        },'user'=>function($query){
            $query->select('id','name','phone');
        }])->where([
            'id_user_rating'=>$post['id']
        ])->first();
        return MyHelper::checkGet($data);
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
        $user = $request->user();
        if($post['id']??false){
            $id_transaction = $post['id'];
            $rn = $id_trx[0]??'';
            $transaction = Transaction::select('id_transaction','transaction_receipt_number','transaction_date','id_outlet')->with(['outlet'=>function($query){
                $query->select('outlet_name','id_outlet');
            }])
            ->where(['id_transaction'=>$id_transaction,'id_user'=>$user->id])
            ->find($id_transaction);
            if(!$transaction){
                return [
                    'status' => 'fail',
                    'messages' => ['Transaction not found']
                ];
            }
        }else{
            $user->load('log_popup');
            $log_popup = $user->log_popup;
            if($log_popup){
                $interval =(Setting::where('key','popup_min_interval')->pluck('value')->first()?:900);
                if(
                    $log_popup->refuse_count>=(Setting::where('key','popup_max_refuse')->pluck('value')->first()?:3) ||
                    strtotime($log_popup->last_popup)+$interval>time()
                ){
                    return MyHelper::checkGet([]);
                }
            }
            $max_date = date('Y-m-d',time() - ((Setting::select('value')->where('key','popup_max_days')->pluck('value')->first()?:3) * 86400));
            $transaction = Transaction::select('id_transaction','transaction_receipt_number','transaction_date','id_outlet')->with(['outlet'=>function($query){
                $query->select('outlet_name','id_outlet');
            }])
            ->where(['show_rate_popup'=>1,'id_user'=>$user->id])
            ->whereDate('transaction_date','>',$max_date)
            ->orderBy('transaction_date','asc')
            ->first();
            if(!$transaction){
                return MyHelper::checkGet([]);
            }
            if($log_popup) {
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
        }
        $result['id'] = $transaction->id_transaction;
        $result['transaction_receipt_number'] = $transaction->transaction_receipt_number;
        $result['question_text'] = Setting::where('key','rating_question_text')->pluck('value_text')->first()?:'How about our Service';
        $result['transaction_date'] = date('d M Y H:i',strtotime($transaction->transaction_date));
        $defaultOptions = [
            'question'=>Setting::where('key','default_rating_question')->pluck('value_text')->first()?:'What\'s best from us?',
            'options' =>explode(',',Setting::where('key','default_rating_options')->pluck('value_text')->first()?:'Cleanness,Accuracy,Employee Hospitality,Process Time')
        ];
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

        $params = ['id_transaction' => $transaction->id_transaction, 'type' => 'trx'];

        $result['options'] = $options;

        // mocking request object and create fake request
        $fake_request = new \Modules\Transaction\Http\Requests\TransactionDetail();
        $fake_request->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($params));
        $fake_request->merge(['user' => $request->user()]);
        $fake_request->setUserResolver(function () use ($request) {
            return $request->user();
        });
        // get detail transaction
        $result['detail_trx'] = app('Modules\Transaction\Http\Controllers\ApiTransaction')->transactionDetail($fake_request)->getData(true)['result']??[];

        $result['webview_url'] = env('APP_API_URL').'api/transaction/web/view/trx/'.$result['id'];
        return MyHelper::checkGet($result);
    }
    public function report(Request $request) {
        $post = $request->json()->all();
        $showOutlet = 10;
        $counter = UserRating::select(\DB::raw('rating_value,count(id_user_rating) as total'))
        ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
        ->groupBy('rating_value');
        $this->applyFilter($counter,$post);
        $counter = $counter->get()->toArray();
        foreach ($counter as &$value) {
            $datax = UserRating::where('rating_value',$value['rating_value'])
                ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
                ->with([
                'transaction'=>function($query){
                    $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal');
                },'user'=>function($query){
                    $query->select('id','name','phone');
                }
            ])->take(10);
            $this->applyFilter($datax,$post);
            $value['data'] = $datax->get();
        }
        $outlet5 = UserRating::select(\DB::raw('outlets.id_outlet,outlet_name,outlet_code,user_ratings.rating_value,count(*) as total'))
        ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
        ->join('outlets','transactions.id_outlet','=','outlets.id_outlet')
        ->where('rating_value','5')
        ->groupBy('outlets.id_outlet')
        ->orderBy('total','desc')
        ->take($showOutlet);
        $this->applyFilter($outlet5,$post);
        for ($i=4; $i > 0 ; $i--) { 
            $outlet = UserRating::select(\DB::raw('outlets.id_outlet,outlet_name,outlet_code,user_ratings.rating_value,count(*) as total'))
            ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
            ->join('outlets','transactions.id_outlet','=','outlets.id_outlet')
            ->where('rating_value',$i)
            ->groupBy('outlets.id_outlet')
            ->orderBy('total','desc')
            ->take($showOutlet);
            $this->applyFilter($outlet,$post);
            $outlet5->union($outlet);
        }
        $data['rating_item'] = $counter;
        $data['rating_item_count'] = count($counter);
        $data['outlet_data'] = $outlet5->get();
        return MyHelper::checkGet($data);
    }
    // apply filter photos only/notes_only
    public function applyFilter($model,$rule,$col='user_ratings'){
        if($rule['notes_only']??false){
            $model->whereNotNull($col.'.suggestion');
            $model->where($col.'.suggestion','<>','');
        }
        if(($rule['transaction_type']??false) == 'online'){
            $model->where('trasaction_type', 'pickup order');
        } elseif (($rule['transaction_type']??false) == 'offline'){
            $model->where('trasaction_type', 'offline');
        }
        $model->whereDate($col.'.created_at','>=',$rule['date_start'])->whereDate($col.'.created_at','<=',$rule['date_end']);
    }
    public function reportOutlet(Request $request) {
        $post = $request->json()->all();
        if($post['outlet_code']??false){
            $outlet = Outlet::select(\DB::raw('outlets.id_outlet,outlets.outlet_code,outlets.outlet_name,count(f1.id_user_rating) as rating1,count(f2.id_user_rating) as rating2,count(f3.id_user_rating) as rating3,count(f4.id_user_rating) as rating4,count(f5.id_user_rating) as rating5'))
            ->where('outlet_code',$post['outlet_code'])->join('transactions','outlets.id_outlet','=','transactions.id_outlet')
            ->leftJoin('user_ratings as f1',function($join) use ($post){
                $join->on('f1.id_transaction','=','transactions.id_transaction')
                ->where('f1.rating_value','=','1');
                $this->applyFilter($join,$post,'f1');
            })
            ->leftJoin('user_ratings as f2',function($join) use ($post){
                $join->on('f2.id_transaction','=','transactions.id_transaction')
                ->where('f2.rating_value','=','2');
                $this->applyFilter($join,$post,'f2');
            })
            ->leftJoin('user_ratings as f3',function($join) use ($post){
                $join->on('f3.id_transaction','=','transactions.id_transaction')
                ->where('f3.rating_value','=','3');
                $this->applyFilter($join,$post,'f3');
            })
            ->leftJoin('user_ratings as f4',function($join) use ($post){
                $join->on('f4.id_transaction','=','transactions.id_transaction')
                ->where('f4.rating_value','=','4');
                $this->applyFilter($join,$post,'f4');
            })
            ->leftJoin('user_ratings as f5',function($join) use ($post){
                $join->on('f5.id_transaction','=','transactions.id_transaction')
                ->where('f5.rating_value','=','5');
                $this->applyFilter($join,$post,'f5');
            })->first();
            if(!$outlet){
                return MyHelper::checkGet($outlet);
            }
            $data['outlet_data'] = $outlet;
            $post['id_outlet'] = $outlet->id_outlet;
            $counter['data'] = [];
            for ($i = 1; $i<=5 ;$i++) {
                $datax = UserRating::where('rating_value',$i)->with([
                    'transaction'=>function($query){
                        $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal');
                    },'user'=>function($query){
                        $query->select('id','name','phone');
                    }
                ])
                ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
                ->where('id_outlet',$outlet->id_outlet)
                ->take(10);
                $this->applyFilter($datax,$post);
                $counter['data'][$i] = $datax->get();
            }
            $data['rating_item'] = $counter;
            return MyHelper::checkGet($data);
        }else{
            $dasc = ($post['order']??'outlet_name')=='outlet_name'?'asc':'desc';
            $outlet = Outlet::select(\DB::raw('outlets.id_outlet,outlets.outlet_code,outlets.outlet_name,count(f1.id_user_rating) as rating1,count(f2.id_user_rating) as rating2,count(f3.id_user_rating) as rating3,count(f4.id_user_rating) as rating4,count(f5.id_user_rating) as rating5'))
            ->join('transactions','outlets.id_outlet','=','transactions.id_outlet')
            ->leftJoin('user_ratings as f1',function($join) use ($post){
                $join->on('f1.id_transaction','=','transactions.id_transaction')
                ->where('f1.rating_value','=','1');
                $this->applyFilter($join,$post,'f1');
            })
            ->leftJoin('user_ratings as f2',function($join) use ($post){
                $join->on('f2.id_transaction','=','transactions.id_transaction')
                ->where('f2.rating_value','=','2');
                $this->applyFilter($join,$post,'f2');
            })
            ->leftJoin('user_ratings as f3',function($join) use ($post){
                $join->on('f3.id_transaction','=','transactions.id_transaction')
                ->where('f3.rating_value','=','3');
                $this->applyFilter($join,$post,'f1');
            })
            ->leftJoin('user_ratings as f4',function($join) use ($post){
                $join->on('f4.id_transaction','=','transactions.id_transaction')
                ->where('f4.rating_value','=','4');
                $this->applyFilter($join,$post,'f4');
            })
            ->leftJoin('user_ratings as f5',function($join) use ($post){
                $join->on('f5.id_transaction','=','transactions.id_transaction')
                ->where('f5.rating_value','=','5');
                $this->applyFilter($join,$post,'f5');
            })
            ->orderBy($post['order']??'outlet_name',$dasc)
            ->groupBy('outlets.id_outlet');
            if($post['search']??false){
                $outlet->where(function($query) use($post){
                    $param = '%'.$post['search'].'%';
                    $query->where('outlet_name','like',$param)
                    ->orWhere('outlet_code','like',$param);
                });
            }
            return MyHelper::checkGet($outlet->paginate(15)->toArray());
        }
    }
}
