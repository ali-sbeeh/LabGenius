<?php

namespace App\Models;

use GuzzleHttp\Handler\Proxy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class WishlistItem extends Model
{
    use HasFactory ;
    public $fillable = ['wishlist_id' , 'product_id'];
    public function product(){
        return $this->belongsTo(Product::class) ;
    }
    public function wishlist(){
        return $this->belongsTo(Wishlist::class);
    }
}
