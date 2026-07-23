<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\CompanyBranch;
use App\Models\Order;

class ShippingCompany extends Model
{
    use HasFactory;
    public $fillable = ['name'];
    public function branches() { return $this->hasMany(CompanyBranch::class); }
    public function orders() { return $this->hasMany(Order::class); }
}
