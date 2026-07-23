<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product ;
class Category extends Model
{
    use HasFactory ;
    public $fillable = ['name' , 'parent_id'];

      public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // العلاقة مع الفئات الفرعية (children)
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function product(){
        return $this->hasMany(Product::class);
    }
}
