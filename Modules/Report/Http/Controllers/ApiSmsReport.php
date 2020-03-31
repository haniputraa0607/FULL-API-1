<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Models\User;
use App\Http\Models\LogApiSms;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Report\Http\Requests\DetailReport;

use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;
use Mail;


class ApiSmsReport extends Controller
{
    function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function getReport(Request $request){
        $post = $request->json()->all();
        $take = 25;

        $data = LogApiSms::leftJoin('users','users.phone','=','log_api_sms.phone')
            ->select('log_api_sms.*', 'users.name', 'users.email');

        if(isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])){
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $data->whereDate('log_api_sms.created_at', '>=', $start_date)
                ->whereDate('log_api_sms.created_at', '<=', $end_date);
        }

        if(isset($post['conditions']) && !empty($post['conditions'])){
            $rule = 'and';
            if(isset($post['rule'])){
                $rule = $post['rule'];
            }

            if($rule == 'and'){
                foreach ($post['conditions'] as $row){
                    if($row['subject'] == 'name' || $row['subject'] == 'email'){
                        if($row['operator'] == '='){
                            $data->where('users.'.$row['subject'], $row['parameter']);
                        }else{
                            $data->where('users.'.$row['subject'], 'like', '%'.$row['parameter'].'%');
                        }
                    }

                    if($row['subject'] == 'phone'){
                        if($row['operator'] == '='){
                            $data->where('log_api_sms.phone', $row['parameter']);
                        }else{
                            $data->where('log_api_sms.phone', 'like', '%'.$row['parameter'].'%');
                        }
                    }

                    if($row['subject'] == 'response'){
                        $data->where('log_api_sms.response', 'like', '%'.$row['operator'].'%');
                    }
                }
            }else{
                $data->where(function ($subquery) use ($post){
                    foreach ($post['conditions'] as $row){
                        if($row['subject'] == 'name' || $row['subject'] == 'email'){
                            if($row['operator'] == '='){
                                $subquery->orWhere('users.'.$row['subject'], $row['parameter']);
                            }else{
                                $subquery->orWhere('users.'.$row['subject'], 'like', '%'.$row['parameter'].'%');
                            }
                        }

                        if($row['subject'] == 'phone'){
                            if($row['operator'] == '='){
                                $subquery->orWhere('log_api_sms.phone', $row['parameter']);
                            }else{
                                $subquery->orWhere('log_api_sms.phone', 'like', '%'.$row['parameter'].'%');
                            }
                        }

                        if($row['subject'] == 'response'){
                            $subquery->orWhere('log_api_sms.response', 'like', '%'.$row['operator'].'%');
                        }
                    }
                });
            }
        }

        if(isset($post['export']) && $post['export'] == 1){
            $data = $data->get();
        }else{
            $data = $data->paginate($take);
        }
        return response()->json(MyHelper::checkGet($data));
    }
}
