<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\WishlistItem;

class Wishlist extends Model
{
    use HasFactory;
    public $fillable = ['user_id'];
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(WishlistItem::class); }
}
