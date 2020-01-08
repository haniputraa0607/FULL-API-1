<?php

namespace Modules\UserRating\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Transaction;
use Modules\UserRating\Entities\UserRating;

use App\Lib\MyHelper;

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
}
