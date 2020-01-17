<?php

namespace Modules\ProductVariant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\ProductVariant\Entities\ProductGroup;

class ApiProductGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $pg = (new ProductGroup)->newQuery();
        if($request->post('rule')){
            $this->filterList($pg,$request->post('rule'),$request->post('operator'));
        }
        if($request->page){
            $pg = $pg->paginate(10);
        }else{
            $pg = $pg->get();
        }
        return MyHelper::checkGet($pg->toArray());
    }

    public function filterList($model,$rule,$operator='and')
    {
        $where = $operator=='and'?'where':'orWhere';
        $newRule=[];
        $where=$operator=='and'?'where':'orWhere';
        foreach ($rule as $var) {
            $var1=['operator'=>$var['operator']??'=','parameter'=>$var['parameter']??null];
            if($var1['operator']=='like'){
                $var1['parameter']='%'.$var1['parameter'].'%';
            }
            $newRule[$var['subject']][]=$var1;
        }

        $inner=['product_group_name'];
        foreach ($inner as $col_name) {
            if($rules=$newRule[$col_name]??false){
                foreach ($rules as $rul) {
                    $model->$where('outlets.'.$col_name,$rul['operator'],$rul['parameter']);
                }
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
        $data = [
            'product_group_name' => $request->json('product_group_name'),
            'product_group_description' => $request->json('product_group_description'),
            'product_group_photo' => $request->json('product_group_photo')
        ];
        $create = ProductGroup::create($data);
        return MyHelper::checkCreate($create);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $data = ProductGroup::find($request->json('id_product_group'));
        return MyHelper::checkGet($data);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request)
    {
        $data = [
            'product_group_name' => $request->json('product_group_name'),
            'product_group_description' => $request->json('product_group_description'),
            'product_group_photo' => $request->json('product_group_photo')
        ];
        $update = ProductGroup::update($data);
        return MyHelper::checkUpdate($create);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $delete = ProductGroup::delete($request->json('id_product_group'));
        return MyHelper::checkDelete($create);
    }
}
