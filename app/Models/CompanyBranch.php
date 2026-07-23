<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ShippingCompany;
use App\Models\Province;


class CompanyBranch extends Model
{
    use HasFactory ;
    public $fillable = ['shipping_company_id' , 'province_id' , 'branch_name',  'address'];
    public function shippingCompany() { return $this->belongsTo(ShippingCompany::class); }
    public function province() { return $this->belongsTo(Province::class); }
}
