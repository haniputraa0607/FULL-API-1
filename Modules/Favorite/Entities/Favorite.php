<?php

namespace Modules\Favorite\Entities;

use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductModifier;
use App\Http\Models\ProductPrice;
use App\Http\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\ProductVariant\Entities\ProductGroup;

class Favorite extends Model
{
	protected $primaryKey = 'id_favorite';

	protected $fillable = [
		'id_outlet',
		'id_brand',
		'id_product',
		'id_user',
		'notes',
		'product_qty'
	];

	protected $appends = ['product'];

	public function modifiers(){
		return $this->belongsToMany(ProductModifier::class,'favorite_modifiers','id_favorite','id_product_modifier');
	}

	public function outlet(){
		return $this->belongsTo(Outlet::class,'id_outlet','id_outlet');
	}

	public function product(){
		return $this->belongsTo(Product::class,'id_product','id_product');
	}

	protected function getProductPrice($id_outlet,$id_product,$product_qty){
		return ProductPrice::where([
			'id_outlet'=>$id_outlet,
			'id_product'=>$id_product
		])->pluck('product_price')->first()*$product_qty;
	}

	public function getProductAttribute(){
		$use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();
		$id_outlet = $this->id_outlet;
		$id_product = $this->id_product;
		$product_qty = $this->product_qty;

		if($use_product_variant){
			$product_group = ProductGroup::select(\DB::raw('product_groups.id_product_group,product_group_name,product_group_code,product_group_description,product_group_photo,GROUP_CONCAT(product_variants.id_product_variant) as variants,product_prices.product_price as price'))
				->join('products','products.id_product_group','=','product_groups.id_product_group')
				->join('product_prices','products.id_product','=','product_prices.id_product')
				->where('product_prices.id_outlet',$id_outlet)
				->join('product_product_variants','product_product_variants.id_product','=','products.id_product')
				->join('product_variants','product_variants.id_product_variant','=','product_product_variants.id_product_variant')
				->groupBy('products.id_product')->first()->toArray();
			unset($product_group['products']);
			return $product_group;
		}

		$product = Product::select('id_product','product_name','product_code','product_description')->where([
			'id_product'=>$id_product
		])->with([
			'photos' => function($query){
				$query->select('id_product','product_photo')->limit(1);
			}
		])->first();
		return [
			'product_name' => $product->product_name,
			'product_code' => $product->product_code,
			'product_description' => $product->product_description,
			'url_product_photo' => optional($product->photos[0]??null)->url_product_photo?:env('S3_URL_API').'img/product/item/default.png',
			'price' => $this->getProductPrice($id_outlet,$id_product,$product_qty)
		];
	}

	public function user(){
		return $this->belongsTo(User::class,'id_user','id');
	}

	public function favorite_modifiers() {
		return $this->hasMany(FavoriteModifier::class,'id_favorite','id_favorite');
	}
}
