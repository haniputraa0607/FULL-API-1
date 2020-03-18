<?php

namespace Modules\ProductVariant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Product\Entities\ProductPromoCategory;
use Modules\ProductVariant\Entities\ProductGroup;
use Modules\ProductVariant\Entities\ProductProductVariant;
use Modules\ProductVariant\Entities\ProductVariant;
use App\Http\Models\Outlet;
use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductModifier;
use App\Http\Models\ProductCategory;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandProduct;

use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\PromoCampaign\Lib\PromoCampaignTools;

use App\Lib\MyHelper;

class ApiProductGroupController extends Controller
{

	function __construct() {
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";

        //code of general
        $this->general = ['general_type','general_size'];
    }

    public $saveImage = "img/product/item/";

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $pg = ProductGroup::select(\DB::raw('product_groups.*,count(id_product) as products_count'))
            ->leftJoin('products','products.id_product_group','=','product_groups.id_product_group')
            ->groupBy('product_groups.id_product_group')
            ->orderBy('product_groups.product_group_position')
            ->with('product_category');
        if($request->json('keyword')){
            $pg->where(function($query) use ($request){
                $query->where('product_group_name','like',"%{$request->json('keyword')}%");
                $query->orWhere('product_group_code','like',"%{$request->json('keyword')}%");
            });
        }
        if($request->json('rule')){
            $this->filterList($pg,$request->post('rule'),$request->post('operator'));
        }
        if($request->page){
            $pg = $pg->paginate(10);
        }else{
            $pg = $pg->get();
        }
        return MyHelper::checkGet($pg->toArray());
    }


    function photoAjax(Request $request) {
    	$post = $request->json()->all();
    	$data = [];
        $checkCode = ProductGroup::where('product_group_code', $post['name'])->first();
    	if ($checkCode) {
            if ($post['detail'] == 1) {
                $upload = MyHelper::uploadPhotoStrict($post['photo'], $this->saveImage, 720, 360);
            } else {
                $upload = MyHelper::uploadPhotoStrict($post['photo'], $this->saveImage, 200, 200);
            }

    	    if (isset($upload['status']) && $upload['status'] == "success") {
                if ($post['detail'] == 1) {
                    $data['product_group_image_detail'] = $upload['path'];
                } else {
                    $data['product_group_photo'] = $upload['path'];
                }
    	    }
    	    else {
    	        $result = [
    	            'status'   => 'fail',
    	            'messages' => ['fail upload image']
    	        ];
    	        return response()->json($result);
    	    }
    	}
    	if (empty($data)) {
    		return reponse()->json([
    			'status' => 'fail',
    			'messages' => ['fail save to database']
    		]);
    	} else {
            $data['id_product_group']       = $checkCode->id_product_group;
            $save                           = ProductGroup::updateOrCreate(['id_product_group' => $checkCode->id_product_group], $data);
    		return response()->json(MyHelper::checkCreate($save));
    	}
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
        $post = $request->json()->all();
        if (isset($post['product_group_photo'])) {
            $upload = MyHelper::uploadPhotoStrict($post['product_group_photo'], $path = 'img/product-group/photo/',200,200);
            if ($upload['status'] == "success") {
                $post['product_group_photo'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        if (isset($post['product_group_image_detail'])) {
            $upload = MyHelper::uploadPhotoStrict($post['product_group_image_detail'], $path = 'img/product-group/image/',720,360);
            if ($upload['status'] == "success") {
                $post['product_group_image_detail'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        $data = [
            'id_product_category' => $request->json('id_product_category'),
            'product_group_code' => $request->json('product_group_code'),
            'product_group_name' => $request->json('product_group_name'),
            'product_group_description' => $request->json('product_group_description'),
            'product_group_photo' => $post['product_group_photo'],
            'product_group_image_detail' => $post['product_group_image_detail']
        ];
        \DB::beginTransaction();
        $create = ProductGroup::create($data);
        if($create && $request->json('variant_type') == 'single'){
            $id_product = $post['id_product'];
            ProductProductVariant::where('id_product',$id_product)->delete();
            $variant1 = ProductVariant::where('product_variant_code','general_type')->first();
            if(!$variant1){
                $variant1 = ProductVariant::create([
                    'product_variant_code' => 'general_type',
                    'product_variant_subtitle' => 'General Type',
                    'product_variant_title' => 'General Type',
                    'product_variant_name' => 'General Type',
                    'parent' => 2
                ]);
            }
            $variant2 = ProductVariant::where('product_variant_code','general_size')->first();
            if(!$variant2){
                $variant2 = ProductVariant::create([
                    'product_variant_code' => 'general_size',
                    'product_variant_subtitle' => 'General Size',
                    'product_variant_title' => 'General Size',
                    'product_variant_name' => 'General Size',
                    'parent' => 1
                ]);
            }
            $insertData = [];
            $insertData[] = [
                'id_product'=>$id_product,
                'id_product_variant'=>$variant1->id_product_variant
            ];
            $insertData[] = [
                'id_product'=>$id_product,
                'id_product_variant'=>$variant2->id_product_variant
            ];
            $insert = ProductProductVariant::insert($insertData);
            if(!$insert){
                \DB::rollBack();
                return MyHelper::checkCreate($insert);
            }
            $update = Product::where('id_product',$id_product)->update(['id_product_group'=>$create['id_product_group']]);
            if(!$update){
                \DB::rollBack();
                return MyHelper::checkUpdate($update);
            }
        }
        \DB::commit();
        return MyHelper::checkCreate($create);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $id_product_group = $request->json('id_product_group');
        $data = ProductGroup::find($id_product_group)->toArray();
        $data['variants'] = Product::select(\DB::raw('products.id_product,products.product_name,GROUP_CONCAT(id_product_variant) as variants'))
            ->where('id_product_group',$id_product_group)
            ->leftJoin('product_product_variants','product_product_variants.id_product','products.id_product')
            ->groupBy('products.id_product')
            ->get()->toArray();

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
        $pg = ProductGroup::find($request->json('id_product_group'));
        if(!$pg){
            return MyHelper::checkGet($pg);
        }
        $post = $request->json()->all();
        if (isset($post['product_group_photo'])) {
            $pg_old['product_group_photo'] = $pg['product_group_photo'];
            $upload = MyHelper::uploadPhotoStrict($post['product_group_photo'], $path = 'img/product-group/photo/',200,200);
            if ($upload['status'] == "success") {
                $post['product_group_photo'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        if (isset($post['product_group_image_detail'])) {
            $pg_old['product_group_image_detail'] = $pg['product_group_image_detail'];
            $upload = MyHelper::uploadPhotoStrict($post['product_group_image_detail'], $path = 'img/product-group/image/',720,360);
            if ($upload['status'] == "success") {
                $post['product_group_image_detail'] = $upload['path'];
            } else {
                $result = [
                    'status'    => 'fail',
                    'messages'    => ['fail upload image']
                ];
                return response()->json($result);
            }
        }
        $data = [
            'id_product_category' => $request->json('id_product_category'),
            'product_group_code' => $request->json('product_group_code'),
            'product_group_name' => $request->json('product_group_name'),
            'product_group_description' => $request->json('product_group_description'),
        ];
        if($post['product_group_photo']??false){
            $data['product_group_photo'] = $post['product_group_photo'];
        }
        if($post['product_group_photo']??false){
            $data['product_group_image_detail'] = $post['product_group_image_detail'];
        }
        $update = $pg->update($data);
        $append = [];
        if($update && $request->json('variant_type') == 'single'){
            $id_product = $post['id_product'];
            Product::where('id_product_group',$pg->id_product_group)->update(['id_product_group'=>null]);
            ProductProductVariant::where('id_product',$id_product)->delete();
            $variant1 = ProductVariant::where('product_variant_code','general_type')->first();
            if(!$variant1){
                $variant1 = ProductVariant::create([
                    'product_variant_code' => 'general_type',
                    'product_variant_subtitle' => 'General Type',
                    'product_variant_title' => 'General Type',
                    'product_variant_name' => 'General Type',
                    'parent' => 2
                ]);
            }
            $variant2 = ProductVariant::where('product_variant_code','general_size')->first();
            if(!$variant2){
                $variant2 = ProductVariant::create([
                    'product_variant_code' => 'general_size',
                    'product_variant_subtitle' => 'General Size',
                    'product_variant_title' => 'General Size',
                    'product_variant_name' => 'General Size',
                    'parent' => 1
                ]);
            }
            $insertData = [];
            $insertData[] = [
                'id_product'=>$id_product,
                'id_product_variant'=>$variant1->id_product_variant
            ];
            $insertData[] = [
                'id_product'=>$id_product,
                'id_product_variant'=>$variant2->id_product_variant
            ];
            $insert = ProductProductVariant::insert($insertData);
            if(!$insert){
                \DB::rollBack();
                return MyHelper::checkCreate($insert);
            }
            $update = Product::where('id_product',$id_product)->update(['id_product_group'=>$pg->id_product_group]);
            if(!$update){
                \DB::rollBack();
                return MyHelper::checkUpdate($update);
            }
        }else{
            // check if previously was single
            $pv = Product::where('id_product_group',$pg->id_product_group)->get();
            if(count($pv) == 1){
                $pv[0]->load(['product_variants'=>function($query){
                    $query->whereIn('product_variant_code',['general_type','general_size']);
                }]);
                if(count($pv[0]->product_variants) == 2){
                    // delete record
                    ProductProductVariant::where('id_product',$pv[0]->id_product)->delete();
                    $pv[0]->update(['id_product_group'=>null]);
                    $append = ['switch'=>true];
                }
            }
        }
        if($update){
            if($pg_old['product_group_photo']??false){
                MyHelper::deletePhoto($pg_old['product_group_photo']);
            }
            if($pg_old['product_group_image_detail']??false){
                MyHelper::deletePhoto($pg_old['product_group_image_detail']);
            }
        }
        \DB::commit();
        return MyHelper::checkUpdate($update)+$append;
    }

    public function reorder(Request $request) {
        $post = $request->json()->all();
        foreach ($post['id_product_group']??[] as $key => $id_product_group) {
            $update = ProductGroup::where('id_product_group',$id_product_group)->update(['product_group_position'=>$key+1]);
        }
        return MyHelper::checkUpdate(1);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $pg = ProductGroup::find($request->json('id_product_group'));
        if(!$pg){
            return MyHelper::checkDelete(false);
        }
        $delete = $pg->delete();
        if($delete){
            if($pg['product_group_photo']){
                MyHelper::deletePhoto($pg['product_group_photo']);
            }
            if($pg['product_group_image_detail']){
                MyHelper::deletePhoto($pg['product_group_image_detail']);
            }
        }
        return MyHelper::checkDelete($delete);
    }

    /**
     * Assign products to product group.
     * @return Response
     */
    public function assign(Request $request) {
        $products = $request->json('products');
        $id_product_group = $request->json('id_product_group');
        \DB::beginTransaction();
        $id_products = [];
        $updatex = [];
        foreach ($products as $id_variant1 => $variants2) {
            foreach($variants2 as $id_variant2 => $id_product){
                $id_products[] = $id_product;
                ProductProductVariant::where('id_product',$id_product)->delete();
                $insertData = [];
                $insertData[] = [
                    'id_product'=>$id_product,
                    'id_product_variant'=>$id_variant1
                ];
                $insertData[] = [
                    'id_product'=>$id_product,
                    'id_product_variant'=>$id_variant2
                ];
                $insert = ProductProductVariant::insert($insertData);
                $updatex[] = $insertData;
                if(!$insert){
                    \DB::rollBack();
                    return MyHelper::checkCreate($insert);
                }
            }
        }
        $update = Product::where('id_product_group',$id_product_group)->update(['id_product_group'=>null]);
        $update = Product::whereIn('id_product',$id_products)->update(['id_product_group'=>$id_product_group]);
        if(!$update){
            \DB::rollBack();
            return MyHelper::checkUpdate($update);
        }
        \DB::commit();
        return MyHelper::checkUpdate($update)+$updatex;
    }
    protected function checkAvailable($availables) {
        $avarr = explode(',', $availables);
        foreach ($avarr as $avail) {
            if($avail == 'Available'){
                return 'Available';
            }
        }
        return 'Sold Out';
    }
    // list product group yang ada di suatu outlet dengan nama, gambar, harga, order berdasarkan kategori
    public function tree(Request $request) {
        $post = $request->json()->all();
        $data = ProductGroup::select(\DB::raw('product_groups.id_product_group,product_groups.product_group_code,product_groups.product_group_name,product_groups.product_group_description,product_groups.product_group_photo,min(product_price) as product_price,product_groups.id_product_category,GROUP_CONCAT(product_stock_status) as product_stock_status'))
                    ->join('products','products.id_product_group','=','product_groups.id_product_group')
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    ->join('product_product_variants','products.id_product','=','product_product_variants.id_product')
                    ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
                    ->join('product_variants as parents','product_variants.parent','=','parents.id_product_variant')
                    ->join('brand_product','brand_product.id_product','=','product_product_variants.id_product')
                    // brand produk ada di outlet
                    ->where('brand_outlet.id_outlet','=',$post['id_outlet'])
                    ->join('brand_outlet','brand_outlet.id_brand','=','brand_product.id_brand')
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
                    ->whereNotNull('product_groups.id_product_category')
                    // order by position
                    ->orderByRaw('product_groups.product_group_position = 0')
                    ->orderBy('product_groups.product_group_position')
                    ->orderBy('product_groups.id_product_group')
                    // group by product_groups
                    ->groupBy('product_groups.id_product_group')
                    ->with(['promo_category'=>function($query){
                        $query->select('product_group_product_promo_categories.id_product_promo_category');
                    }]);
                    // ->get();
        if (isset($post['promo_code'])) {
        	$data = $data->with('products');
        }

        $data = $data->get()->toArray();

        if(!$data){
            return MyHelper::checkGet($data);
        }

        $promo_data = $this->applyPromo($post, $data, $promo_error);

        if ($promo_data) {
        	$data = $promo_data;
        }

        $result = [];
        foreach ($data as $product) {
            $product['product_stock_status'] = $this->checkAvailable($product['product_stock_status']);
            $product['product_price'] = MyHelper::requestNumber($product['product_price'],$request->json('request_number'));
            $product['product_price_pretty'] = MyHelper::requestNumber($product['product_price'],'_CURRENCY');
            $id_product_category = $product['id_product_category'];
            if(!isset($result[$id_product_category]['product_category_name'])){
                $category = ProductCategory::select('product_category_name','id_product_category','product_category_order')->find($id_product_category)->toArray();
                unset($category['url_product_category_photo']);
                $result[$id_product_category] = $category;
            }
            unset($product['id_product_category']);
            unset($product['products']);
            foreach ($product['promo_category'] as $key => $value) {
                $id_product_promo_category = $value['id_product_promo_category'];
                if(!isset($result['promo'.$id_product_promo_category]['product_category_name'])){
                    $category = ProductPromoCategory::select('product_promo_category_name','id_product_promo_category','product_promo_category_order')->find($id_product_promo_category)->toArray();
                    $result['promo'.$id_product_promo_category] = [
                        'id_product_category' => $category['id_product_promo_category'],
                        'product_category_name' => $category['product_promo_category_name'],
                        'product_category_order' => ($category['product_promo_category_order']-1000000)
                    ];
                }
                $result['promo'.$id_product_promo_category]['products'][] = $product;
            }
            $result[$id_product_category]['products'][] = $product;
        }
        usort($result, function($a,$b){
            return ($b['product_category_order']*-1) <=> ($a['product_category_order']*-1);
        });
        $result = MyHelper::checkGet(array_values($result));
        $result['promo_error'] = $promo_error;

        return response()->json($result);
    }
    public function product(Request $request) {
        $post = $request->json()->all();
        $query = Product::join('product_groups','products.id_product_group','=','product_groups.id_product_group')
                    ->where('product_groups.id_product_group',$post['id_product_group'])
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    ->where('product_prices.product_stock_status','=','Available') // filter stock available
                    ->join('brand_product','brand_product.id_product','=','products.id_product')
                    ->where('brand_product.id_brand',$post['id_brand'])
                    // brand produk ada di outlet
                    ->where('brand_outlet.id_outlet','=',$post['id_outlet'])
                    ->join('brand_outlet','brand_outlet.id_brand','=','brand_product.id_brand')
                    // where active
                    ->where(function($query){
                        $query->where('product_prices.product_visibility','=','Visible')
                                ->orWhere(function($q){
                                    $q->whereNull('product_prices.product_visibility')
                                    ->where('products.product_visibility', 'Visible');
                                });
                    })
                    ->where('product_prices.product_status','=','Active')
                    ->whereNotNull('product_prices.product_price');
        $query2 = clone $query;
        // get all product on this group
        $products = $query
            ->join('product_product_variants','products.id_product','=','product_product_variants.id_product')
            ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
            ->join('product_variants as parents','product_variants.parent','=','parents.id_product_variant')
            ->select(\DB::raw('products.id_product,product_prices.product_stock_status,GROUP_CONCAT(DISTINCT (product_variants.product_variant_code) order by parents.product_variant_position) as product_variant_code,count(DISTINCT(product_variants.product_variant_code)) as product_variant_count,product_prices.product_price'))
            ->having('product_variant_count','2')
            ->groupBy('products.id_product')
            ->get('id_product')->toArray();
        $id_products = array_column($products, 'id_product');
        $is_visible = 1;
        // get product lowest price and default variant
        $default = $query2
            ->select(\DB::raw('product_price,GROUP_CONCAT(CONCAT_WS(",",product_variants.parent,product_variants.id_product_variant) separator ";") as defaults'))
            ->join('product_product_variants','product_product_variants.id_product','=','products.id_product')
            ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
            ->having('defaults','<>','')
            ->orderBy('product_price')
            ->groupBy('product_price')
            ->first();
        // arrange default variant
        if($default['defaults']??false){
            $default['defaults'] = explode(';',$default['defaults']);
            $defaults = [];
            foreach ($default['defaults'] as $defaulte) {
                if($defaulte){
                    $exp = explode(',', $defaulte);
                    $defaults[$exp[0]??''] = $exp[1]??'';
                }
            }
        }
        //get variant stock
        $variant_stock = [];
        foreach ($products as $product) {
            if($product['product_variant_code']){
                $varcode = explode(',',$product['product_variant_code']);
                if(count($varcode) < 2) continue;
                $first = (int) count($varcode)/2;
                $variant_stock[$varcode[0]][$varcode[$first]] = [
                    'product_variant_code' => $varcode[1],
                    'product_stock_status' => $product['product_stock_status'],
                    'product_price' => $product['product_price'],
                    'more_price_pretty' => MyHelper::requestNumber($product['product_price'] - $default['product_price'],'_CURRENCY')
                ];
                if(in_array($varcode[0], $this->general)){
                    $is_visible = 0;
                }
            }
        }
        // product exists?
        if(!$id_products || !$variant_stock){
            return MyHelper::checkGet([],'Product not found');
        }
        // get product group detail
        $data = ProductGroup::select('id_product_group','id_product_category','product_group_name','product_group_image_detail','product_group_code','product_group_description')->find($post['id_product_group'])->toArray();

        // add id_brand, outlet_name,outlet_code
        $data['id_brand'] = $post['id_brand'];
        $outlet = Outlet::select('outlet_name','outlet_code')->where('id_outlet',$post['id_outlet'])->first();
        $data['outlet_name'] = $outlet->outlet_name;
        $data['outlet_code'] = $outlet->outlet_code;

        // get list product variant
        $variants = ProductProductVariant::select(
            'product_variants.id_product_variant',
            'product_variants.product_variant_name',
            'product_variants.product_variant_code',
            'product_product_variants.product_variant_price',
            't2.product_variant_title as type_title',
            't2.product_variant_position as type_position',
            't2.product_variant_subtitle as type_subtitle',
            't2.id_product_variant as parent_id')
            ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
            ->join('product_variants as t2','product_variants.parent','=','t2.id_product_variant')
            ->whereIn('product_product_variants.id_product',$id_products)->orderBy('product_variants.product_variant_position')->groupBy('product_variant_code')->get()->toArray();
        // set price to response
        $data['product_price'] = MyHelper::requestNumber($default['product_price'],$request->json('request_number'));
        $data['product_price_pretty'] = MyHelper::requestNumber($default['product_price'],'_CURRENCY');
        $arranged_variant = [];
        foreach ($variants as $key => $variant) {
            if(!isset($arranged_variant[$variant['parent_id']]['type_name'])){
                $arranged_variant[$variant['parent_id']]['type_title'] = $variant['type_title'];
                $arranged_variant[$variant['parent_id']]['type_position'] = $variant['type_position'];
                $arranged_variant[$variant['parent_id']]['type_subtitle'] = $variant['type_subtitle'];
            }
            $child = [
                'id_product_variant'=>$variant['id_product_variant'],
                'product_variant_code'=>$variant['product_variant_code'],
                'product_variant_name'=>$variant['product_variant_name'],
                'product_variant_price'=>MyHelper::requestNumber($variant['product_variant_price'],$request->json('request_number'))
            ];
            $child['default'] = ($defaults[$variant['parent_id']]??false) == $child['id_product_variant']?1:0;
            $arranged_variant[$variant['parent_id']]['childs'][$variant['product_variant_code']] = $child;
        }
        $arranged_variant = array_values($arranged_variant);
        // sorting by type position
        usort($arranged_variant, function($a,$b){
            return $a['type_position']<=>$b['type_position'];
        });
        $data['variants'] = $arranged_variant[0];
        $data['variants']['is_visible'] = $is_visible;
        unset($data['variants']['childs']);
        foreach ($variant_stock as $key => $vstock) {
            if($arranged_variant[0]['childs'][$key]??false){
                $stock = $arranged_variant[0]['childs'][$key];
                $child = $arranged_variant[1];
                unset($child['childs']);
                $is_should_hidden = 1;
                foreach ($arranged_variant[1]['childs'] as $vrn) {
                    if($variant_stock[$key][$vrn['product_variant_code']]??false){
                        if(in_array($vrn['product_variant_code'], $this->general)){
                            $is_should_hidden = 0;
                        }
                        $child['childs'][] = array_merge($vrn,$variant_stock[$key][$vrn['product_variant_code']]);
                    }
                }
                if(!in_array(1, array_column($child['childs'], 'default'))){
                    $child['childs'][0]['default'] = 1;
                }
                $child['is_visible'] = $is_should_hidden;
                $stock['childs'] = $child;
                $data['variants']['childs'][]=$stock;
            }
        }
        if(in_array(1, array_column($data['variants']['childs'], 'default'))){
            $data['variants']['childs'][0]['default'] = 1;
        }
        // get available modifiers
        $posta = [
            'id_product' => $id_products,
            'id_outlet' => $post['id_outlet'],
            'id_product_category' => $data['id_product_category']??'',
            'id_brand' => $data['id_brand']??''
        ];
        $modifiers = ProductModifier::select('product_modifiers.id_product_modifier','code','type','text','product_modifier_stock_status','product_modifier_price as price')
            ->where(function($query) use($posta){
                $query->where('modifier_type','Global')
                ->orWhere(function($query) use ($posta){
                    $query->whereHas('products',function($query) use ($posta){
                        $query->whereIn('products.id_product',$posta['id_product']);
                    });
                    $query->orWhereHas('product_categories',function($query) use ($posta){
                        $query->where('product_categories.id_product_category',$posta['id_product_category']);
                    });
                    $query->orWhereHas('brands',function($query) use ($posta){
                        $query->where('brands.id_brand',$posta['id_brand']);
                    });
                });
            })
            ->join('product_modifier_prices',function($join) use ($posta){
                $join->on('product_modifier_prices.id_product_modifier','=','product_modifiers.id_product_modifier');
                $join->where('product_modifier_prices.id_outlet',$posta['id_outlet']);
            })->where('product_modifier_status','Active')
            ->where(function($query){
                $query->where('product_modifier_prices.product_modifier_visibility','=','Visible')
                        ->orWhere(function($q){
                            $q->whereNull('product_modifier_prices.product_modifier_visibility')
                            ->where('product_modifiers.product_modifier_visibility', 'Visible');
                        });
            })
            ->get()->toArray();
        $data['modifiers'] = array_values(MyHelper::groupIt($modifiers,'type',function($key,&$val) use ($request){
            $val['price'] = MyHelper::requestNumber($val['price'],$request->json('request_number'));
            $val['price_pretty'] = MyHelper::requestNumber($val['price'],'_CURRENCY');
            return $key;
        },function($key,&$val){
            $newval['type'] = $key;
            $newval['modifiers'] = $val;
            $val = $newval;
            return $key;
        }));
        $data['is_visible'] = $is_visible;
        return MyHelper::checkGet($data);
    }
    public function search(Request $request) {
        $post = $request->json()->all();
        $data = ProductGroup::select(\DB::raw('product_groups.id_product_group,product_groups.product_group_code,product_groups.product_group_name,product_groups.product_group_description,product_groups.product_group_photo,min(product_price) as product_price,product_groups.id_product_category,GROUP_CONCAT(product_stock_status) as product_stock_status'))
                    ->join('products','products.id_product_group','=','product_groups.id_product_group')
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$post['id_outlet']) // filter outlet
                    ->join('product_product_variants','products.id_product','=','product_product_variants.id_product')
                    ->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
                    ->join('product_variants as parents','product_variants.parent','=','parents.id_product_variant')
                    // where name like key_free
                    ->where('product_groups.product_group_name','like','%'.$post['key_free'].'%')
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
                    ->whereNotNull('product_groups.id_product_category')
                    // order by position
                    ->orderBy('products.position')
                    // group by product_groups
                    ->groupBy('product_groups.id_product_group');

        if (isset($post['promo_code'])) {
        	$data = $data->with('products');
        }

        $data = $data->get()->toArray();

        if(!$data){
            return MyHelper::checkGet($data);
        }

        $promo_data = $this->applyPromo($post, $data, $promo_error);

        if ($promo_data) {
        	$data = $promo_data;
        }

        $result = [];
        foreach ($data as $product) {
            $product['product_stock_status'] = $this->checkAvailable($product['product_stock_status']);
            $product['product_price_pretty'] = MyHelper::requestNumber($product['product_price'],'_CURRENCY');
            $product['product_price'] = MyHelper::requestNumber($product['product_price'],$request->json('request_number'));
            unset($product['products']);
            $result[] = $product;
        }

        $result = MyHelper::checkGet(array_values($result));
        $result['promo_error'] = $promo_error;

        return response()->json($result);
    }

    public function applyPromo($promo_post, $data_product, &$promo_error)
    {
    	$post = $promo_post;
    	$data = $data_product;
    	// promo code
		foreach ($data as $key => $value) {
			$data[$key]['is_promo'] = 0;
		}
		$promo_error = null;
        if ( (!empty($post['promo_code']) && empty($post['id_deals_user'])) || (empty($post['promo_code']) && !empty($post['id_deals_user'])) ) {

        	if (!empty($post['promo_code']))
        	{
        		$code = app($this->promo_campaign)->checkPromoCode($post['promo_code'], 1, 1);
        		$source = 'promo_campaign';
        	}else{
        		$code = app($this->promo_campaign)->checkVoucher($post['id_deals_user'], 1, 1);
        		$source = 'deals';
        	}

	        if(!$code){
	        	$promo_error = 'Promo not valid';
	        	return false;
	        }else{

	        	if ( ($code['promo_campaign']['date_end']??$code['voucher_expired_at']) < date('Y-m-d H:i:s') ) {
	        		$promo_error = 'Promo is ended';
	        		return false;
	        	}
	        	$code = $code->toArray();

				if (isset($post['id_outlet'])) {
					$pct = new PromoCampaignTools();
					if (!$pct->checkOutletRule($post['id_outlet'], $code[$source]['is_all_outlet']??0,$code[$source][$source.'_outlets']??$code['deal_voucher'][$source]['outlets_active'])) {
						$promo_error = Setting::where('key','promo_error_product_list')->first()['value']??'Cannot use promo at this outlet.';
	        			return false;
					}
				}

	        	$applied_product = app($this->promo_campaign)->getProduct($source,($code['promo_campaign']??$code['deal_voucher']['deals']))['applied_product']??[];

        		if ($applied_product == '*') {
        			foreach ($data as $key => $value) {
	        			$data[$key]['is_promo'] = 1;
						unset($data[$key]['products']);
    				}
        		}else{
        			if ( ($code['product_type']??$code['deal_voucher']['deals']['product_type']) == 'group')
        			{
        				if (isset($applied_product[0])) {
	        				// loop available product
				        	foreach ($applied_product as $key => $value) {
				        		// loop product group
			        			foreach ($data as $key2 => $value2) {
			        				if ( $value2['id_product_group'] == $value['id_product'] ) {
			    						$data[$key2]['is_promo'] = 1;
			    						break;
			    					}
			        			}
				        	}
				        	// unset products
				        	foreach ($data as $key => $value) {
								unset($data[$key]['products']);
				        	}
	        			}elseif(isset($applied_product['id_product'])){
	        				foreach ($data as $key2 => $value2) {
		        				if ( $value2['id_product_group'] == $applied_product['id_product'] ) {
		    						$data[$key2]['is_promo'] = 1;
		    						break;
		    					}
		        			}
	        			}
        			}
        			else
        			{
	        			if (isset($applied_product[0])) {
	        				// loop available product
				        	foreach ($applied_product as $key => $value) {
				        		// loop product group
			        			foreach ($data as $key2 => $value2) {
			        				// loop product
			        				if (isset($value2['products'])) {
					        			foreach ($value2['products'] as $key3 => $value3) {
					        				if ( $value3['id_product'] == $value['id_product'] ) {
					    						$data[$key2]['is_promo'] = 1;
					    						break;
					    					}
					        			}
			        				}
			        			}
				        	}
				        	// unset products
				        	foreach ($data as $key => $value) {
								unset($data[$key]['products']);
				        	}
	        			}elseif(isset($applied_product['id_product'])){
	        				foreach ($data as $key2 => $value2) {
		        				foreach ($value2['products'] as $key3 => $value3) {
									unset($data[$key2]['products']);
			        				if ( $value3['id_product'] == $applied_product['id_product'] ) {
			    						$data[$key2]['is_promo'] = 1;
			    						break;
			    					}
		        				}
		        			}
	        			}
        			}
        		}
	        }
        }elseif( !empty($post['promo_code']) && !empty($post['id_deals_user']) ){
        	$promo_error = 'Can only use either promo code or voucher';
        }
        return $data;
        // end promo code
    }

    /**
     * Export data product
     * @param Request $request Laravel Request Object
     */
    public function import(Request $request) {
        $post = $request->json()->all();
        $result = [
            'processed' => 0,
            'invalid' => 0,
            'updated' => 0,
            'updated_price' => 0,
            'updated_price_fail' => 0,
            'create' => 0,
            'create_category' => 0,
            'no_update' => 0,
            'failed' => 0,
            'not_found' => 0,
            'more_msg' => [],
            'more_msg_extended' => []
        ];
        switch ($post['type']) {
            case 'global':
                // update or create if not exist 
                $data = $post['data']??[];
                foreach ($data['products'] as $key => $value) {
                    if(empty($value['product_group_code'])){
                        $result['invalid']++;
                        continue;
                    }
                    $result['processed']++;
                    if(empty($value['product_group_name'])){
                        unset($value['product_group_name']);
                    }
                    if(empty($value['product_group_description'])){
                        unset($value['product_group_description']);
                    }
                    $product = ProductGroup::where('product_group_code',$value['product_group_code'])->first();
                    if($product){
                        if($product->update($value)){
                            $result['updated']++;
                        }else{
                            $result['no_update']++;
                        }
                    }else{
                        $product = ProductGroup::create($value);
                        if($product){
                            $result['create']++;
                        }else{
                            $result['failed']++;
                            $result['more_msg_extended'][] = "Product Group with product group code {$value['product_group_code']} failed to be created";
                            continue;
                        }
                    }
                }
                break;
            
            case 'detail':
                // update only, never create
                $data = $post['data']??[];
                foreach ($data['products'] as $key => $value) {
                    if(empty($value['product_group_code'])){
                        $result['invalid']++;
                        continue;
                    }
                    $result['processed']++;
                    if(empty($value['product_group_name'])){
                        unset($value['product_group_name']);
                    }
                    if(empty($value['product_group_description'])){
                        unset($value['product_group_description']);
                    }
                    if(empty($value['product_group_position'])){
                        unset($value['product_group_position']);
                    }
                    $product = ProductGroup::where([
                            'product_group_code' => $value['product_group_code']
                        ])->first();
                    if(!$product){
                        $result['not_found']++;
                        $result['more_msg_extended'][] = "Product with product code {$value['product_group_code']} not found";
                        continue;
                    }
                    if(empty($value['product_category_name'])){
                        unset($value['product_category_name']);
                    }else{
                        $pc = ProductCategory::where('product_category_name',$value['product_category_name'])->first();
                        if(!$pc){
                            $result['create_category']++;
                            $pc = ProductCategory::create([
                                'product_category_name' => $value['product_category_name']
                            ]);
                        }
                        $value['id_product_category'] = $pc->id_product_category;
                        unset($value['product_category_name']);
                    }
                    $update1 = $product->update($value);
                    if($update1){
                        $result['updated']++;
                    }else{
                        $result['no_update']++;
                    }
                }
                break;
            
            default:
                # code...
                break;
        }
        $response = [];
        if($result['invalid']+$result['processed']<=0){
            return MyHelper::checkGet([],'File empty');
        }else{
            $response[] = $result['invalid']+$result['processed'].' total data found';
        }
        if($result['processed']){
            $response[] = $result['processed'].' data processed';
        }
        if($result['updated']){
            $response[] = 'Update '.$result['updated'].' product';
        }
        if($result['create']){
            $response[] = 'Create '.$result['create'].' new product';
        }
        if($result['create_category']){
            $response[] = 'Create '.$result['create_category'].' new category';
        }
        if($result['no_update']){
            $response[] = $result['no_update'].' product not updated';
        }
        if($result['invalid']){
            $response[] = $result['invalid'].' row data invalid';
        }
        if($result['failed']){
            $response[] = 'Failed create '.$result['failed'].' product';
        }
        if($result['not_found']){
            $response[] = $result['not_found'].' product not found';
        }
        if($result['updated_price']){
            $response[] = 'Update '.$result['updated_price'].' product price';
        }
        if($result['updated_price_fail']){
            $response[] = 'Update '.$result['updated_price_fail'].' product price fail';
        }
        $response = array_merge($response,$result['more_msg_extended']);
        return MyHelper::checkGet($response);
    }

    /**
     * Export data product
     * @param Request $request Laravel Request Object
     */
    public function export(Request $request) {
        $post = $request->json()->all();
        switch ($post['type']) {
            case 'global':
                $data['products'] = ProductGroup::select('product_group_code','product_group_name','product_group_description')
                    ->join('products','product_groups.id_product_group','=','products.id_product_group')
                    ->groupBy('product_groups.id_product_group')
                    ->orderBy('product_group_position')
                    ->orderBy('product_groups.id_product_group')
                    ->distinct()
                    ->get();
                break;

            case 'detail':
                $data['products'] = ProductGroup::select('product_categories.product_category_name','product_groups.product_group_position','product_group_code','product_group_name','product_group_description')
                    ->leftJoin('product_categories','product_categories.id_product_category','=','product_groups.id_product_category')
                    ->groupBy('product_groups.id_product_group')
                    ->groupBy('product_category_name')
                    ->orderBy('product_category_name')
                    ->orderBy('product_group_position')
                    ->orderBy('product_groups.id_product_group')
                    ->distinct()
                    ->get();
                break;

            default:
                # code...
                break;
        }
        return MyHelper::checkGet($data);
    }
}
