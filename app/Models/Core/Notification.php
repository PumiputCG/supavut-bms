<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * แจ้งเตือน 1 รายการ — ใช้ร่วมทุกโมดูล
 *
 * ข้อความเก็บเป็นคู่ th/en ตั้งแต่ตอนสร้าง (ไม่แปลตอนแสดง)
 * เพราะข้อความมีชื่อเอกสาร/ชื่อคนปนอยู่ แปลทีหลังไม่ได้
 *
 * 🔴 ทุกใบต้องมี "หัวข้อ" กำกับครบ 4 บรรทัด (เจ้าของสั่ง 2026-09-04)
 *      เลขที่    doc_no
 *      <ป้าย>    subject_key = คีย์แปลของป้าย · title_th/en = ค่า
 *                (โมดูลงบประมาณใช้ "แผนก" · โมดูลอื่นใช้ป้ายของตัวเองได้)
 *      แจ้งเตือน  body_th/en   (สีมาจาก tone: ok = เขียว · no = แดง · null = สีปกติ)
 *      <ป้าย>    note_key + note_th/en  (บรรทัดเสริม เช่น "เหตุผล" ไม่มีก็ได้)
 *      วันเวลา   created_at
 *    โมดูลใหม่ที่จะส่งแจ้งเตือน ต้องส่งครบแบบเดียวกันนี้เสมอ
 *
 * 🔴 ป้ายเป็นสีดำเสมอ สีบอกผลอยู่ที่ "ค่า" ไม่ใช่ที่ป้าย
 */
class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'employee_code', 'module_id', 'function_key', 'event',
        'doc_no', 'subject_key', 'url', 'route_name', 'route_param', 'title_th', 'title_en', 'body_th', 'body_en',
        'tone', 'note_key', 'note_th', 'note_en',
        // บรรทัดเสริมที่ 2 — เช่น อนุมัติแล้วบอกทั้งเลขที่งบและความเห็นของผู้อนุมัติ
        'note2_key', 'note2_th', 'note2_en', 'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * ลิงก์ไปยังเอกสารของแจ้งเตือนใบนี้
     *
     * 🔴 สร้างตอนแสดงผลเสมอ ห้ามเก็บที่อยู่เต็มลงฐาน (บั๊กจริง 2026-09-10)
     *    ของเดิมเก็บ http://127.0.0.1:8000/... ที่สร้างไว้ตอนส่ง เปิดเว็บจากที่อยู่อื่น
     *    ลิงก์ก็พาไปคนละเครื่อง แล้วผู้ใช้เจอ "หาเอกสารไม่เจอ"
     *
     * คืน null เมื่อสร้างลิงก์ไม่ได้ (route ถูกถอดไปแล้ว) ให้หน้าจอถอยไปใช้ลิงก์ของเมนูแทน
     */
    public function link(): ?string
    {
        if ($this->route_name === null || $this->route_name === '') {
            return $this->url ?: null;
        }

        try {
            return route($this->route_name, $this->route_param);
        } catch (\Throwable) {
            return $this->url ?: null;
        }
    }
}
