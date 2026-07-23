<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory ;
    public $fillable = ['order_id' , 'payment_method' , 'amount' , 'status' , 'proof_url' , 'transaction_id' , 'payment_date'];
    public function order(){
        return $this->belongsTo(Order::class) ;
    }
}
