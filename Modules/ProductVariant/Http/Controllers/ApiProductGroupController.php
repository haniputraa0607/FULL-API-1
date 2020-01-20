<?php

namespace Modules\ProductVariant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\ProductVariant\Entities\ProductGroup;
use Modules\ProductVariant\Entities\ProductProductVariant;
use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductCategory;

use App\Lib\MyHelper;

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
            'id_product_category' => $request->json('id_product_category'),
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
            'id_product_category' => $request->json('id_product_category'),
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

    /**
     * Assign products to product group.
     * @param int $id
     * @return Response
     */
    public function assign(Request $request) {
        $products = $request->json('products');
        $id_product_group = $request->json('id_product_group');
        $update = Product::whereIn('id_product',$products)->update(['id_product_group'=>$id_product_group]);
        return MyHelper::checkUpdate($update);
    }
    // list product group yang ada di suatu outlet dengan nama, gambar, harga, order berdasarkan kategori
    public function tree(Request $request) {
        $post = $request->json()->all();
        $data = Product::select(\DB::raw('product_groups.id_product_group,product_groups.product_group_name,product_groups.product_group_description,product_groups.product_group_photo,min(product_price) as product_price,GROUP_CONCAT(product_code) as product_codes,product_groups.id_product_category'))
                    ->join('product_groups','products.id_product_group','=','product_groups.id_product_group')
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    // where active
                    ->where(function($query){
                        $query->where('product_prices.product_visibility','=','Visible')
                                ->orWhere(function($q){
                                    $q->whereNull('product_prices.product_visibility')
                                    ->where('products.product_visibility', 'Visible');
                                });
                    })
                    ->where('product_prices.product_status','=','Active')
                    ->whereNotNull('product_prices.product_price')
                    ->join('brand_product','brand_product.id_product','=','products.id_product')
                    ->whereNull('brand_product.id_product_category')
                    // order by position
                    ->orderBy('products.position')
                    // group by product_groups
                    ->groupBy('product_groups.id_product_group')
                    ->get()
                    ->toArray();
        if(!$data){
            return MyHelper::checkGet($data);
        }
        $categorized = [];
        $uncategorized = [];
        foreach ($data as $product) {
            $product['product_price'] = MyHelper::requestNumber($product['product_price'],$request->json('request_number'));
            if(!$product['id_product_category']){
                unset($product['id_product_category']);
                $uncategorized[] = $product;
                continue;
            }
            if(!isset($categorized[$product['id_product_category']]['product_category_name'])){
                $category = ProductCategory::select('product_category_name')->find($product['id_product_category'])->toArray();
                unset($category['url_product_category_photo']);
                $categorized[$product['id_product_category']] = $category;
            }
            $categorized[$product['id_product_category']]['products'][] = &$product;
            unset($product['id_product_category']);
        }
        $result = [
            'categorized' => array_values($categorized),
            'uncategorized_name' => Setting::select('value_text')->where('key','uncategorized_name')->pluck('value_text')->first()?:'Product',
            'uncategorized' => $uncategorized
        ];
        return MyHelper::checkGet($result);
    }
    public function product(Request $request) {
        $post = $request->json()->all();
        $id_products = Product::select('products.id_product')
                    ->join('product_groups','products.id_product_group','=','product_groups.id_product_group')
                    ->where('product_groups.id_product_group',$post['id_product_group'])
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    // where active
                    ->where(function($query){
                        $query->where('product_prices.product_visibility','=','Visible')
                                ->orWhere(function($q){
                                    $q->whereNull('product_prices.product_visibility')
                                    ->where('products.product_visibility', 'Visible');
                                });
                    })
                    ->where('product_prices.product_status','=','Active')
                    ->whereNotNull('product_prices.product_price')
                    ->join('brand_product','brand_product.id_product','=','products.id_product')
                    ->whereNull('brand_product.id_product_category')
                    // order by position
                    ->orderBy('products.position')
                    // group by product_groups
                    ->pluck('id_product')
                    ->toArray();
        $data = ProductGroup::select('product_group_name','product_group_description','product_group_photo')->find($post['id_product_group']);
        $data['variants'] = ProductProductVariant::select('product_variants.id_product_variant','product_variants.product_variant_name','product_product_variants.product_variant_price')
            ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
            ->whereIn('product_product_variants.id_product',$id_products)->get();
        return MyHelper::checkGet($data);
    }
}
