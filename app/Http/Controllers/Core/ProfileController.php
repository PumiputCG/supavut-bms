<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Employee;
use App\Services\Core\InsightMirror;
use Illuminate\View\View as ViewContract;
use Throwable;

/**
 * หน้าข้อมูลส่วนตัวของผู้ใช้ที่ล็อกอินอยู่
 *
 * 🔴 หน้านี้ **อ่านอย่างเดียว** — ข้อมูลทั้งหมดเป็นมิเรอร์จาก Insight
 * ถ้าจะแก้ชื่อ/อีเมล/ลายเซ็น/รูป/รหัสผ่าน ต้องไปแก้ที่ Insight ที่เดียว
 * แก้ที่นี่ไม่ได้ เพราะรอบซิงค์ถัดไปจะทับกลับเป็นค่าของ Insight อยู่ดี
 *
 * 🔴 ดึงข้อมูลของ "คนที่เปิดหน้านี้" ใหม่จาก Insight ทุกครั้ง (เจ้าของแจ้งปัญหา 2026-09-03)
 *    เดิมต้องรอรอบ `insight:sync` ทั้งก้อน แก้อีเมล/ลายเซ็นที่ Insight แล้วหน้านี้ยังเป็นค่าเก่า
 *    ดึงทีละคนเป็นคิวรีเดียว ถูกกว่าซิงค์ทั้งระบบมาก และ Insight ไม่ต้องแก้อะไรเลย
 */
class ProfileController extends Controller
{
    public function __construct(private readonly InsightMirror $mirror) {}

    public function show(): ViewContract
    {
        $me = app('current_user');

        try {
            // ต่อ Insight ไม่ติดก็ไม่เป็นไร — ถอยไปใช้ข้อมูลที่มิเรอร์ไว้แล้ว หน้าต้องไม่ล้ม
            $fresh = $this->mirror->pullUserByCode((string) $me->employee_code);

            if ($fresh) {
                $me = $fresh;
                app()->instance('current_user', $fresh);
                view()->share('me', $fresh);
            }
        } catch (Throwable) {
            // เงียบไว้ — ข้อมูลเก่ายังใช้งานได้ ไม่ควรทำให้เปิดหน้าโปรไฟล์ไม่ได้
        }

        // ทะเบียนพนักงานอยู่คนละตารางกับบัญชี — จับคู่ด้วยรหัสพนักงาน + บริษัท
        $employee = Employee::where('employee_code', $me->employee_code)
            ->when($me->company, fn ($q) => $q->where('company', $me->company))
            ->first()
            ?? Employee::where('employee_code', $me->employee_code)->first();

        return view('core.profile', [
            'employee' => $employee,
        ]);
    }
}
