<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ShippingCompany;


class Order extends Model
{
    use HasFactory ;
    public $fillable = ['user_id' ,  'shipping_company_id' , 'total_price' , 'status' , 'shipping_address' , 'order_date' , 'note'];
    public function user() { return $this->belongsTo(User::class)->withTrashed(); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function payment() { return $this->hasOne(Payment::class); }
    public function shippingCompany() { return $this->belongsTo(ShippingCompany::class); }
}

