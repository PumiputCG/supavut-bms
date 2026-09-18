<?php

namespace App\Support;

use App\Services\Access\AccessService;
use Throwable;

/**
 * ผู้อนุมัติลำดับสุดท้าย (CEO) — ต่อท้ายสายอนุมัติเสมอ ถอด/สลับไม่ได้
 *
 * 🔴 ตั้งใจ "ไม่เก็บ" ลงร่างเอกสาร (เจ้าของสั่ง 2026-09-09)
 *    เพราะถ้าเก็บไว้แล้วเปลี่ยนตัว CEO ทีหลัง ร่างเก่าจะค้างชื่อคนเดิม
 *    แล้วตอนกดส่งจะกลายเป็นมี CEO 2 คนในสายเดียวกัน
 *    จึงหยิบจาก config ตอนแสดงผลและตอนส่งเท่านั้น — ที่เดียวกันเสมอ
 *
 * ตัวเลขค่าเริ่มต้นอยู่ที่ config/bms.php (`final_approver`)
 */
class FinalApprover
{
    /** @var array<string,mixed>|null|false false = ยังไม่ได้หา · null = หาแล้วไม่เจอ */
    private static array|null|false $cache = false;

    public static function code(): string
    {
        return trim((string) config('bms.final_approver'));
    }

    /**
     * ข้อมูลคนที่จะเซ็นปิดท้าย — รูป ชื่อ ตำแหน่ง แผนก
     *
     * คืน null เมื่อยังไม่ได้ตั้งค่า หรือหาบัญชีไม่เจอ (หน้าจอจะข้ามไปเงียบๆ)
     *
     * @return array<string,mixed>|null
     */
    public static function person(): ?array
    {
        if (self::$cache !== false) {
            return self::$cache;
        }

        $code = self::code();

        if ($code === '') {
            return self::$cache = null;
        }

        try {
            return self::$cache = app(AccessService::class)->describeCodes([$code])[0] ?? null;
        } catch (Throwable) {
            // หาไม่ได้ก็ไม่ควรทำให้หน้าเอกสารพัง — แค่ไม่โชว์แถวปิดท้าย
            return self::$cache = null;
        }
    }

    /** ล้างแคชในหน่วยความจำ — ใช้ในเทสต์ที่สลับค่า config ระหว่างทาง */
    public static function forget(): void
    {
        self::$cache = false;
    }
}
