<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * บัญชีผู้ใช้ — มิเรอร์ทางเดียวจาก insight.app_users
 *
 * นี่คือตารางที่ใช้ล็อกอินจริง (ไม่ใช่ employees)
 * รหัสผ่านเก็บเป็น plaintext ตาม decision D-007 ของ Insight — SBMS แค่คัดลอกมา ไม่ได้ตัดสินใจเอง
 * รหัสผ่านเริ่มต้นของพนักงาน = เลขบัตรประชาชน · เปลี่ยนรหัสได้ที่ Insight ที่เดียว
 *
 * 🔴 ห้ามแก้จากฝั่ง SBMS — แก้ที่ Insight แล้วรอ insight:sync
 */
class AppUser extends Model
{
    protected $table = 'app_users';

    protected $fillable = [
        'insight_id',
        'id_thai_hash', 'company', 'employee_code', 'companies', 'password', 'role',
        'full_name_th', 'full_name_en', 'position', 'department', 'email',
        // รหัสองค์กร — เติมตอนซิงค์โดยจับคู่กับตาราง employees (insight.app_users ไม่มีคอลัมน์พวกนี้)
        'job_code', 'dept_code', 'branch_code',
        'profile_picture', 'signature', 'registered_at', 'mirrored_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'companies' => 'array',
        'registered_at' => 'datetime',
        'mirrored_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'employee_code');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** ชื่อที่แสดงตามภาษาที่เลือก — ไม่มีชื่ออังกฤษให้ถอยไปใช้ไทย */
    public function displayName(string $lang = 'th'): string
    {
        $th = trim((string) $this->full_name_th);
        $en = trim((string) $this->full_name_en);

        return $lang === 'en' ? ($en ?: $th ?: $this->employee_code) : ($th ?: $en ?: $this->employee_code);
    }

    /** ตัวอักษรแรกของชื่อ ใช้แทนรูปเมื่อยังไม่มีรูปโปรไฟล์ */
    public function initial(): string
    {
        $name = $this->displayName();

        return mb_strtoupper(mb_substr($name !== '' ? $name : (string) $this->employee_code, 0, 1));
    }

    /** URL รูปโปรไฟล์ — เปิดจากสตอเรจของ Insight ผ่าน HTTP (ดูเหตุผลใน Employee::photoUrl) */
    public function avatarUrl(): ?string
    {
        $path = trim((string) $this->profile_picture);
        if ($path === '') {
            return null;
        }

        return rtrim((string) config('bms.insight_asset_url'), '/').'/'.ltrim($path, '/');
    }
}
