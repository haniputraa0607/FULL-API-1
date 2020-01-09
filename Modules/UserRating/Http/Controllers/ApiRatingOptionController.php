<?php

namespace Modules\UserRating\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\UserRating\Entities\RatingOption;

use App\Lib\MyHelper;

class ApiRatingOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $ratings = array_map(function($var){
            $var['id_rating_option'] = MyHelper::createSlug($var['id_rating_option'],$var['created_at']);
            return $var;
        },RatingOption::orderBy('order')->get()->toArray());
        return MyHelper::checkGet($ratings);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $post = $request->json()->all();
        $key = [
            'rule_operator' => $post['rule_operator'],
            'value' => $post['value'],
        ];
        $insert = [
            'question' => $post['question'],
            'options' => implode(',',$post['options']),
            'order' => $post['order']??0
        ];
        $create = RatingOption::updateOrCreate($key,$insert);
        if($create){
            $create = $create->toArray();
            $create['id_rating_option'] = MyHelper::createSlug($create['id_rating_option'],$create['created_at']);
        }
        return MyHelper::checkCreate($create);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request)
    {
        $post = $request->json()->all();
        $exploded = MyHelper::explodeSlug($post['id_rating_option']);
        $post['id_rating_option'] = $exploded[0];
        $post['created_at'] = $exploded[1];
        $key = [
            'rule_operator' => $post['rule_operator'],
            'value' => $post['value'],
        ];
        $val = [
            'question' => $post['question'],
            'options' => implode(',',$post['options']),
            'order' => $post['order']??0
        ];
        $check = RatingOption::where($key)->where('id_rating_option','!=',$post['id_rating_option'])->exists();
        if($check){
            return [
                'status' => 'fail',
                'messages' => ['Another data with same rule already exist']
            ];
        }
        $update = RatingOption::where([
            'id_rating_option' => $post['id_rating_option'],
            'created_at' => $post['created_at']
        ])->update(array_merge($key,$val));
        return MyHelper::checkUpdate($update);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $post = $request->json()->all();
        $exploded = MyHelper::explodeSlug($post['id_rating_option']);
        $post['id_rating_option'] = $exploded[0];
        $post['created_at'] = $exploded[1];
        $delete = RatingOption::where([
            'id_rating_option' => $post['id_rating_option'],
            'created_at' => $post['created_at']
        ])->delete();
        return MyHelper::checkDelete($delete);
    }
}
