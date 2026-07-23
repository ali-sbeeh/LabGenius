<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CompanyBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Province extends Model
{
    use HasFactory;
    public $fillable = ['name'];
    public function branches() { return $this->hasMany(CompanyBranch::class); }
}
