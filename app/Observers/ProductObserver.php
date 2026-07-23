<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\WishlistItem;

class ProductObserver
{
    /**
     * عند تحديث المنتج: إذا وصل المخزون إلى 0 احذفه من كل قوائم المفضلة
     */
    public function updated(Product $product): void
    {
        // تحقق هل تغير stock_quantity وهل أصبح 0
        if ($product->wasChanged('stock_quantity') && $product->stock_quantity <= 0) {
            // احذف هذا المنتج من جميع قوائم المفضلة
            WishlistItem::where('product_id', $product->id)->delete();
        }
    }
}
