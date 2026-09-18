<?php

namespace App\Models\Access;

use App\Models\Core\AppUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ผู้ดูแลระบบของ SBMS — เป็นข้อมูลของ SBMS เอง ไม่ใช่ของที่มิเรอร์มาจาก Insight
 *
 * 🔴 ห้ามย้ายไปเก็บที่ `app_users.role` เพราะ insight:sync เขียนทับตารางนั้นทุกรอบ
 */
class AccessAdmin extends Model
{
    protected $table = 'access_admins';

    protected $fillable = ['employee_code', 'company', 'note', 'granted_by', 'granted_at'];

    protected $casts = ['granted_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'employee_code', 'employee_code');
    }
}
