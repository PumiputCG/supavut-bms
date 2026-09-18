<?php

namespace App\Models\Access;

use Illuminate\Database\Eloquent\Model;

/**
 * ตำแหน่งที่เข้าสู่ระบบ SBMS ได้ (ติ๊กเปิด/ปิดรายตำแหน่ง)
 *
 * รายชื่อตำแหน่งเติมจากตาราง employees ที่มิเรอร์มา — ไม่ได้พิมพ์เอง
 */
class LoginPosition extends Model
{
    protected $table = 'access_login_positions';

    protected $fillable = ['job_code', 'job_th', 'job_en', 'can_login', 'updated_by'];

    protected $casts = ['can_login' => 'boolean'];

    /** ชื่อตำแหน่งตามภาษาที่เลือก — ไม่มีอังกฤษให้ถอยไปใช้ไทย */
    public function title(string $lang = 'th'): string
    {
        $th = trim((string) $this->job_th);
        $en = trim((string) $this->job_en);

        return $lang === 'en' ? ($en ?: $th ?: $this->job_code) : ($th ?: $en ?: $this->job_code);
    }
}
