<?php
// app/Models/AdminLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'target_id',
        'target_type',
        'details',
        'ip_address'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * العلاقة مع الأدمن (المستخدم)
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
