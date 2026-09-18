<?php

namespace App\Services\Budget;

use Illuminate\Support\Facades\DB;

/**
 * เลขที่เอกสาร — `INV-NM-2569-000001` / `BGT-NM-2569-000001`
 *
 * กติกาที่ตกลงไว้ (DECISIONS 4.2 · 50.10)
 *   ปี พ.ศ. + เลขวิ่ง 6 หลัก · รีเซ็ตทุกปีงบ · รูปแบบต้องตั้งค่าได้ไม่ hard-code
 *   โค้ดกลุ่มมาจากหน้า "ตั้งค่าหมายเลขเอกสาร" ที่แอดมินตั้งเอง
 *
 * 🔴 เลขวิ่ง "แยกต่อกลุ่ม" ได้ฟรี เพราะนับด้วย LIKE บนหัวเลขที่
 *    พอหัวกลายเป็น INV-NM-2569- ตัวนับก็แยกจาก INV-MS-2569- เองทันที
 *
 * 🔴 ต้องล็อกแถวตอนหาเลขล่าสุด (lockForUpdate) ไม่งั้นคน 2 คนกดพร้อมกันจะได้เลขซ้ำ
 *
 * 🔴 เรียกตอน "กดส่ง" เท่านั้น ไม่ใช่ตอนบันทึกร่าง (เจ้าของสั่ง 2026-09-17)
 *    ร่างที่ได้เลขแล้วไม่ยอมส่งจะจองเลขค้างไว้ถาวร ทำให้เลขไม่เรียงตามลำดับการส่งจริง
 */
class DocNumber
{
    /** ตัวแทนเลขวิ่งในเลขที่ตัวอย่าง — ใช้ตัวเล็กเพื่อให้ต่างจากโค้ดหมวดที่เป็นตัวใหญ่ */
    public const MASK = 'xxx';

    /** ตัวแทน "ท้ายปี พ.ศ." — 2569/2570/2571 ใช้หมวดเดียวกัน ตัวอย่างจึงไม่ตรึงปีใดปีหนึ่ง */
    public const MASK_YEAR = 'xx';

    /**
     * ขอเลขถัดไปของเอกสารชนิดหนึ่งในปีงบหนึ่ง
     *
     * @param  string  $table  ตารางที่เก็บเอกสาร
     * @param  string  $prefix  คำนำหน้า เช่น INV, BGT
     * @param  string  $groupCode  โค้ดกลุ่ม เช่น NM — ว่างได้ (เอกสารที่ออกก่อนมีระบบกลุ่ม)
     */
    public function next(string $table, string $prefix, int $fiscalYear, string $groupCode = ''): string
    {
        $head = $this->head($prefix, $fiscalYear, $groupCode);
        $digits = (int) config('bms.doc_running_digits', 6);

        // นับเฉพาะเอกสารปีงบนี้ของกลุ่มนี้ แล้วเอาเลขท้ายสุด +1 — ล็อกไว้กันสองคนได้เลขเดียวกัน
        $last = DB::table($table)
            ->where('fiscal_year', $fiscalYear)
            ->where('doc_no', 'like', $head.'%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $running = $last ? ((int) substr($last, -$digits)) + 1 : 1;

        return $head.str_pad((string) $running, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * ตัวอย่างเลขที่ของหมวด — ลงท้าย 000001 เสมอ
     * 🔴 ห้ามเอาเลขถัดไปจริงไปโชว์บนหน้าจอ เพราะ 2 คนเปิดพร้อมกันจะเห็นเลขเดียวกัน
     *    แล้วได้คนละเลขตอนบันทึก = หน้าจอโกหก
     * ใช้ในหน้า "ตั้งค่าหมายเลขเอกสาร" ซึ่งต้องบอกด้วยว่าเลขเริ่มนับที่ 1
     */
    public function sample(string $prefix, int $fiscalYear, string $groupCode = ''): string
    {
        $digits = (int) config('bms.doc_running_digits', 6);

        return $this->head($prefix, $fiscalYear, $groupCode).str_pad('1', $digits, '0', STR_PAD_LEFT);
    }

    /**
     * รูปแบบเลขที่ของหมวด — เลขวิ่งเขียนเป็น xxx (`INV-NM-2569-000xxx`)
     *
     * 🔴 ใช้ในหน้าคั่นเลือกหมวด (เจ้าของสั่ง 2026-09-17) เพราะหน้านั้นบอก "รูปแบบ" ไม่ใช่ "เลขที่จะได้"
     *    ตัวเลขจริงออกตอนกดส่งเท่านั้น — เขียนเป็น 000001 จะอ่านเหมือนเป็นเลขของใบถัดไป
     */
    public function pattern(string $prefix, int $fiscalYear, string $groupCode = ''): string
    {
        $digits = (int) config('bms.doc_running_digits', 6);
        $no = $this->head($prefix, $fiscalYear, $groupCode).str_pad(self::MASK, $digits, '0', STR_PAD_LEFT);

        /*
          🔴 ปีก็เป็น xx ด้วย (เจ้าของสั่ง 2026-09-17)
             หมวดหนึ่งใช้ต่อเนื่องหลายปีงบ (2569 · 2570 · 2571) การ์ดบอก "รูปแบบ" ไม่ใช่ปีใดปีหนึ่ง
             โค้ดหมวดเป็น A–Z/0–9 ล้วน จึงไม่มีทางไปตรงกับเลขปีจนถูกแทนผิดตัว
        */
        return str_replace((string) $fiscalYear, substr((string) $fiscalYear, 0, 2).self::MASK_YEAR, $no);
    }

    /** หัวของเลขที่ (ทุกอย่างที่อยู่หน้าเลขวิ่ง) */
    private function head(string $prefix, int $fiscalYear, string $groupCode): string
    {
        $format = config('bms.doc_formats.'.strtolower($prefix), '{prefix}-{group}{year}-{running}');

        return str_replace(
            ['{prefix}', '{group}', '{year}', '{running}'],
            [$prefix, $groupCode !== '' ? $groupCode.'-' : '', (string) $fiscalYear, ''],
            $format
        );
    }
}
