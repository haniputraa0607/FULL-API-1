<?php

namespace Modules\ProductVariant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\ProductVariant\Entities\ProductVariant;
use Modules\ProductVariant\Entities\ProductProductVariant;
use App\Http\Models\Product;

use App\Lib\MyHelper;

class ApiProductVariantController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $pg = ProductVariant::with(['parent'=>function($query){
            $query->select('id_product_variant','product_variant_name');
        }]);
        if($request->json('rule')){
            $this->filterList($pg,$request->post('rule'),$request->post('operator'));
        }
        $pg->orderBy('product_variant_position');
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

        $inner=['product_variant_name'];
        foreach ($inner as $col_name) {
            if($rules=$newRule[$col_name]??false){
                foreach ($rules as $rul) {
                    $model->$where('outlets.'.$col_name,$rul['operator'],$rul['parameter']);
                }
            }
        }
        if($rules=$newRule['variant_type']??false){
            foreach ($rules as $rul) {
                if($rul['parameter'] == 'parent'){
                    $model->{$where.'Null'}('parent');
                }else{
                    $model->{$where.'NotNull'}('parent');
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
            'product_variant_code' => $request->json('product_variant_code',''),
            'product_variant_subtitle' => $request->json('product_variant_subtitle',''),
            'product_variant_title' => $request->json('product_variant_title',''),
            'product_variant_name' => $request->json('product_variant_name',''),
            'parent' => $request->json('parent')
        ];
        $create = ProductVariant::create($data);
        return MyHelper::checkCreate($create);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $data = ProductVariant::where('product_variant_code',$request->json('product_variant_code'))->first();
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
            'product_variant_code' => $request->json('product_variant_code',''),
            'product_variant_subtitle' => $request->json('product_variant_subtitle',''),
            'product_variant_title' => $request->json('product_variant_title',''),
            'product_variant_name' => $request->json('product_variant_name',''),
            'parent' => $request->json('parent')
        ];
        $update = ProductVariant::where(['product_variant_code'=>$product_variant_code_ori])->update($data);
        return MyHelper::checkUpdate($update);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $delete = ProductVariant::delete($request->json('id_product_variant'));
        return MyHelper::checkDelete($create);
    }

    public function assign(Request $request) {
        \DB::beginTransaction();
        $id_product = $request->json('id_product');
        ProductProductVariant::where('id_product',$id_product)->delete();
        foreach ($request->json('variants') as $variant) {
            $create = ProductProductVariant::create([
                'id_product'=> $id_product,
                'id_product_variant'=>$variant['id_product_variant'],
                'product_variant_price'=>$variant['product_variant_price']
            ]);
            if(!$create){
                \DB::rollback();
                return MyHelper::checkCreate($create);
            }
        }
        \DB::commit();
        return MyHelper::checkCreate($create);
    }
    public function reorder(Request $request) {
        $parent = $request->json('parent',[]);
        $child = $request->json('child',[]);
        $variants = $request->json('variants',[]);
        $inserts = [];
        $dataId = [];
        \DB::beginTransaction();
        // reorder parent
        foreach ($parent as $key => $value) {
            $dataId[] = $value;
            $update = ProductVariant::where('id_product_variant' , $value)->update(['product_variant_position' => ($key+1)]);
            if(!$update){
                \DB::rollback();
                return MyHelper::checkUpdate($update);
            }
        }
        // reorder child and assign its parent
        foreach ($child as $par => $child2) {
            foreach ($child2 as $key => $value) {
                $dataId[] = $value;
                $update = ProductVariant::where('id_product_variant' , $value)->update(['product_variant_position' => ($key+1),'parent'=>$par]);
                if(!$update){
                    \DB::rollback();
                    return MyHelper::checkUpdate($update);
                }
            }
        }        
        // update data
        foreach ($variants as $variant) {
            $update = ProductVariant::updateOrCreate(['id_product_variant'=>$variant['id_product_variant']],$variant);
            if(!$update){
                \DB::rollback();
                return MyHelper::checkUpdate($update);
            }
        }
        // delete not exist variant
        $delete = ProductVariant::whereNotIn('id_product_variant',$dataId)->delete();
        if(!$delete){
            \DB::rollback();
            return MyHelper::checkDelete($update);
        }
        \DB::commit();
        return MyHelper::checkUpdate($update);
    }
    public function tree(Request $request) {
        $variants = ProductVariant::whereNotNull('parent')->get();
        $result = MyHelper::groupIt($variants,'parent');
        $newResult = [];
        foreach ($result as $key => $value) {
            $parent = ProductVariant::find($key);
            $parent['childs'] = $value;
            $newResult[] = $parent;
        }
        return MyHelper::checkGet($newResult);
    }
    public function availableProduct(Request $request) {
        $variants = Product::select('products.id_product','products.product_code','products.product_name')
            ->whereNull('products.id_product_group')
            ->orWhere('products.id_product_group',$request->json('id_product_group'))
            ->leftJoin('product_product_variants','product_product_variants.id_product','products.id_product')
            ->orWhereNull('product_product_variants.id_product_variant')
            ->groupBy('products.id_product')
            ->groupBy('products.id_product')
            ->get()->toArray();
        return MyHelper::checkGet($variants);
    }
}
