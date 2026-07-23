<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Product;

class Discount extends Model
{
    use HasFactory ;
    public $fillable = ['product_id' ,  'discount_percent' , 'start_date' ,  'end_date' , 'description' ];
    public function product() { return $this->belongsTo(Product::class); }

}
