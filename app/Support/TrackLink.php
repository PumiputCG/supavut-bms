<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * กุญแจและลิงก์ของหน้าติดตามเอกสาร (ตัวที่อยู่ใน QR Code)
 *
 * 🔴 ห้ามใช้ "เลขที่เอกสาร" เป็นกุญแจ (กติกาเดิม DECISIONS 50.4)
 *    เลขที่เอกสารเดาได้ตรงๆ — ใครก็ไล่ INV-NM-2569-000001, 000002, … เปิดดูเอกสารทั้งบริษัทได้
 *    จึงใช้รหัสสุ่มที่ไม่มีความหมายในตัวเอง
 *
 * 🔴 ห้ามสร้างลิงก์ QR จาก APP_URL (กติกาเดิม DECISIONS 50.4)
 *    APP_URL ของเครื่อง dev เป็น http://localhost ซึ่งพิมพ์ลงกระดาษแล้วสแกนจากมือถือไม่ได้เลย
 *    และห้ามใส่พาธลง APP_URL ด้วย (เทสต์พัง 168 ตัว · บทเรียน 2026-09-15)
 *    → ที่อยู่ของ QR จึงมีค่าตั้งของตัวเองแยกออกมา `BMS_QR_BASE_URL`
 */
final class TrackLink
{
    /**
     * กุญแจใหม่ — 32 ตัวอักษรจากตัวสุ่มที่เข้ารหัสได้ (ไม่ใช่ rand ธรรมดา)
     *
     * ใช้แค่ [0-9a-f] เพราะกุญแจนี้ถูกพิมพ์ลงกระดาษและอาจต้องอ่าน/พิมพ์ตามด้วยมือ
     * ตัวพิมพ์ใหญ่-เล็กปนกันจะอ่านผิดง่าย (l กับ I · O กับ 0)
     */
    public static function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * ที่อยู่เต็มของหน้าติดตาม — ตัวนี้คือข้อมูลที่ฝังใน QR
     *
     * ไม่ได้ตั้ง `BMS_QR_BASE_URL` ไว้ → ถอยไปใช้รากของคำขอที่กำลังทำงานอยู่
     * ซึ่งใช้ได้จริงตอนทดสอบในเครื่อง แต่ 🔴 **ก่อนขึ้นเซิร์ฟต้องตั้งค่านี้ให้เป็นที่อยู่ของเซิร์ฟ**
     * ไม่งั้น QR ที่พิมพ์จากเครื่อง dev จะชี้กลับมาที่ localhost ซึ่งมือถือเปิดไม่ได้
     */
    public static function url(string $token): string
    {
        $base = rtrim((string) config('bms.qr_base'), '/');

        if ($base === '') {
            return url('/track/'.$token);
        }

        return $base.'/track/'.$token;
    }

    /** กุญแจที่รับมาอยู่ในรูปที่เป็นไปได้ไหม — กันคิวรีฐานข้อมูลด้วยขยะจาก URL */
    public static function looksValid(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f]{32}$/D', $token);
    }

    /** ตัดกุญแจให้สั้นลงเพื่อ "พูดถึง" ในหน้าจอ/บันทึก — ห้ามใช้เป็นกุญแจจริง */
    public static function short(string $token): string
    {
        return Str::limit($token, 8, '');
    }
}
