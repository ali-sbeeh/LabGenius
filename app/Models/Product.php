<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\CartItem ;
use App\Models\Category ;
use App\Models\Discount ;
use App\Models\User;

use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    public $fillable = ['seller_id' ,
     'category_id' ,
     'recommended_usage',
     'name',
      'price' ,
      'brand' ,
       'stock_quantity' ,
        'condition' ,
        'description' ,
         'cpu_type' ,
         'ram_size' ,
         'gpu_type' ,
         'igpu' ,
         'storage_size' ,
         'screen_size' ,
         'battery_capacity',
         'os' ,
          'weight',
          'is_active'
      ];
    public function cartItems(){
        return $this->hasMany(CartItem::class) ;
    }
    public function category(){
        return $this->belongsTo(Category::class);
    }
    public function discount(){
        return $this->hasMany(Discount::class);
    }
    public function items(){
        return $this->hasMany(OrderItem::class);
    }

    public function wishlistItems(){
        return $this->hasMany(WishlistItem::class);
    }

    public function reviews(){
        return $this->hasMany(Review::class) ;
    }

    public function productImages(){
        return $this->hasMany(ProductImage::class , 'product_id');
    }

     public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
