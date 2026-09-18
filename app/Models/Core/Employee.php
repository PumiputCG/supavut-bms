<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ทะเบียนพนักงาน — มิเรอร์ทางเดียวจาก insight.employees
 *
 * ไม่ใช่ตารางล็อกอิน (ตารางล็อกอินคือ app_users / โมเดล AppUser)
 * เก็บคนลาออกไว้ด้วยเพื่อให้ย้อนดูเอกสารเก่าได้ว่าใครเป็นคนขอซื้อ/อนุมัติ
 *
 * 🔴 ห้ามแก้ข้อมูลจากฝั่ง SBMS — แก้ที่ Insight ที่เดียวแล้วรอ insight:sync
 */
class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = [
        'insight_id', 'no',
        'company', 'employee_code', 'license_id',
        'title', 'gender', 'name_th', 'surname_th', 'name_en',
        'job_code', 'job_th', 'job_en',
        'dept_code', 'dept_th', 'dept_en',
        'branch_code', 'branch_th', 'branch_en',
        'hire_date', 'probation_end_date', 'resign_date', 'emp_status',
        'photo_path', 'mirrored_at',
    ];

    protected $casts = [
        'no' => 'integer',
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'resign_date' => 'date',
        'mirrored_at' => 'datetime',
    ];

    public function appUser(): HasOne
    {
        return $this->hasOne(AppUser::class, 'employee_code', 'employee_code');
    }

    /** พนักงานที่ยังทำงานอยู่ (emp_status = 1 และยังไม่ถึงวันลาออก) */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('emp_status', '1')
            ->where(function (Builder $q) {
                $q->whereNull('resign_date')->orWhereDate('resign_date', '>', now()->toDateString());
            });
    }

    public function fullNameTh(): string
    {
        return trim(implode(' ', array_filter([$this->title, $this->name_th, $this->surname_th])));
    }

    public function fullNameEn(): string
    {
        return trim((string) $this->name_en);
    }

    /**
     * URL รูปพนักงาน — ไฟล์อยู่ที่สตอเรจของ Insight เปิดผ่าน HTTP ไม่ได้ก็อปมา
     *
     * ทำไมไม่ก็อปไฟล์: Apache/PHP บนเซิร์ฟรันเป็น service account ที่มักไม่มีสิทธิ์ UNC
     * (บทเรียนจาก QuoteCompare) เปิดผ่าน HTTP จึงเสถียรกว่าและไม่ต้องซิงค์ไฟล์ซ้ำ
     */
    public function photoUrl(): ?string
    {
        $path = trim((string) $this->photo_path);
        if ($path === '') {
            return null;
        }

        return rtrim((string) config('bms.insight_asset_url'), '/').'/'.ltrim($path, '/');
    }
}
