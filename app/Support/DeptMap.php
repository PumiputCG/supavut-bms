<?php

namespace App\Support;

/**
 * จับคู่แผนกของ ERP กับแผนกของ SBMS (ที่มาจาก Bplus)
 *
 * 🔴 ที่เดียวของทั้งระบบ — หน้าไหนต้องแปลงรหัสแผนก ERP เป็นชื่อแผนกของเรา ให้เรียกที่นี่
 *    บัญชีส่งไฟล์ยืนยันกลับมาเมื่อไหร่ แก้ตารางในไฟล์นี้ที่เดียว ทุกหน้าตามเอง
 *
 * 🔴 ยังเป็น "ข้อเสนอ" ที่ระบบเดาจากชื่อแผนก 2 ฝั่ง **บัญชียังไม่ยืนยัน**
 *    ไฟล์ที่ส่งให้บัญชีตรวจอยู่ที่ ให้วิเคราะห์/จับคู่แผนกSBMSกับERP/
 *
 * 🔴 หลายแผนกของ SBMS ยุบเป็นรหัสเดียวใน ERP ได้ (เช่น IM1/IM2/IM3 = SPV1-02)
 *    ชื่อที่แสดงจึงรวมไว้ในบรรทัดเดียว ไม่แตกเป็นหลายก้อน เพราะ ERP ไม่ได้แยกยอดให้
 *
 * 🔴 ต้องมีรหัสบริษัทนำหน้าเสมอ — แผนกกลุ่ม Mold อยู่บริษัท pd (โมลด์แวนโต)
 *    รหัสแผนกซ้ำกันข้ามบริษัทได้ ถ้าไม่แยกยอดจะปนกัน
 */
class DeptMap
{
    /**
     * 'บริษัท|รหัสแผนกใน ERP' => 'ชื่อแผนกใน SBMS'
     *
     * @var array<string,string>
     */
    private const TO_BMS = [
        // ── บริษัท si5 (สุภาวุฒิ อินดัสทรี) ──
        'si5|SPV-06' => 'ACC / BOI',
        'si5|SPV-19' => 'HRD',
        'si5|SPV-02' => 'HRM',
        'si5|SPV-11' => 'SHE / QMS',
        'si5|SPV-07' => 'MK / MFG/SL',
        'si5|SPV-28' => 'iNest / R&D/R&I',
        'si5|SPV-08' => 'PU',
        'si5|SPV-13' => 'IT',
        'si5|SPV1-02' => 'MFG/IM1-3',
        'si5|SPV1-06' => 'MFG/MMT',
        'si5|SPV1-10' => 'MFG/PET',
        'si5|SPV1-11' => 'MFG/PDW',
        'si5|SPV1-03' => 'MFG/BM',
        'si5|SPV1-12' => 'MFG/PDA1',
        'si5|SPV2-03' => 'MFG/PDA2',
        'si5|SPV2-02' => 'MFG/PF',
        'si5|SPV-15' => 'MFG/LGD / LGE',
        'si5|SPV-27' => 'MFG/ST1 / ST2',
        'si5|SPV-14' => 'MFG/MT1 / MT2',
        'si5|SPV1-04' => 'Quality/QC / IQ',
        'si5|SPV2-04' => 'Quality/QCP',
        'si5|SPV1-17' => 'Quality/QA',
        'si5|SPV-26' => 'Quality/SQ',
        'si5|SPV-21' => 'MFG/PC',
        'si5|SPV-20' => 'MFG/Plant / PM',
        'si5|SPV-09' => 'PE',
        'si5|SPV-23' => 'R&D / R&D/PD',
        'si5|SPV-25' => 'R&D/QAN',
        'si5|SPV1-16' => 'R&D/ML',
        'si5|SPV-17' => 'ผู้บริหารระดับสูง',

        /*
          🔴 SPV3-04 มีทั้งใน si5 และ pd — เป็นโรงงานแม่พิมพ์เดียวกัน
             จับคู่ทั้ง 2 บริษัทให้ลงแผนกเดียวกัน ยอดจึงรวมเป็นก้อนเดียวเหมือนที่ SBMS มองเห็น
        */
        'si5|SPV3-04' => 'Mold (ทุกแผนก)',

        /*
          รหัสเก่าที่เลิกใช้ปี 2017-2020 — ยุบเข้าแผนกปัจจุบันของ SBMS (เจ้าของสั่ง 2026-09-11)
          🔴 ชื่อที่ ERP ตั้งไว้บอกตรงๆ ว่าเป็นแผนกไหน จึงจับคู่ได้โดยไม่ต้องเดา
             E01 "PD Plastic/Blow" · E02 "PD Plastic/Inj1" · E15 "PD Paint/Part Control"
             B05 "Planing" · B06 "LG/Store Material & Suppart"
          🔴 ยอดของรหัสเก่าจะรวมเข้ากับแผนกปัจจุบัน — ตั้งใจให้เป็นแบบนั้น
             เพราะเป็นแผนกเดียวกัน แค่คนละยุคของรหัส
        */
        'si5|E01' => 'MFG/BM',
        'si5|E02' => 'MFG/IM1-3',
        'si5|E15' => 'MFG/PF',
        'si5|B05' => 'MFG/PC',
        'si5|B06' => 'MFG/ST1 / ST2',

        /*
          🔴 NON ไม่ใช่แผนก — เป็นค่าของช่อง Cost ที่หลุดมาอยู่ในช่องแผนก 1 แถว
             เจ้าของสั่งให้คงคำว่า Non ไว้ตามเดิม จะได้เห็นว่ามีแถวนี้อยู่และไปตามแก้ที่ ERP ได้
        */
        'si5|NON' => 'Non',

        // SI R&D — ชื่อใน ERP ตรงกับแผนก R&D ของ SBMS
        'si5|SI-01' => 'R&D / R&D/PD',

        // ── บริษัท pd (โมลด์แวนโต) ──
        'pd|SPV3-04' => 'Mold (ทุกแผนก)',

        /*
          🔴 แก้จากเดิมที่เดาว่าเป็น ROVENTO (2026-09-11)
             ตรวจทะเบียนมิติของ pd แล้ว SI-01 ชื่อ "SI R&D" เหมือนกับใน si5
             ที่เคยเดาว่าเป็น ROVENTO เพราะเจอคำว่า Rovento ในชื่องบแถวเดียว ซึ่งหลักฐานอ่อนเกินไป
             **ROVENTO (RVT001 ของ SBMS) จึงยังไม่มีคู่ใน ERP — ต้องถามบัญชี**
        */
        'pd|SI-01' => 'R&D / R&D/PD',
    ];

    /**
     * 'บริษัท|รหัสแผนกใน ERP' => 'รหัสแผนกใน SBMS' (หลายรหัสคั่นด้วย ", ")
     *
     * 🔴 อยู่คู่กับ TO_BMS เสมอ — แก้ชื่อเมื่อไหร่ต้องแก้รหัสด้วย
     *    แยกเป็น 2 ตารางเพราะหน้าจอใช้แต่ชื่อ ส่วนรหัสใช้ตอนออกไฟล์ให้บัญชีตรวจ
     *
     * @var array<string,string>
     */
    private const TO_BMS_CODE = [
        'si5|SPV-06' => 'SPV001, SPV002',
        'si5|SPV-19' => 'SPV003',
        'si5|SPV-02' => 'SPV004',
        'si5|SPV-11' => 'SPV005, SPV006',
        'si5|SPV-07' => 'SPV007, SPV022',
        'si5|SPV-28' => 'SPV008, SPV043',
        'si5|SPV-08' => 'SPV009',
        'si5|SPV-13' => 'RVT002',
        'si5|SPV1-02' => 'SPV010, SPV011, SPV012, SPV035',
        'si5|SPV1-06' => 'SPV013',
        'si5|SPV1-10' => 'SPV014',
        'si5|SPV1-11' => 'SPV015',
        'si5|SPV1-03' => 'SPV016',
        'si5|SPV1-12' => 'SPV017',
        'si5|SPV2-03' => 'SPV018',
        'si5|SPV2-02' => 'SPV019',
        'si5|SPV-15' => 'SPV020, SPV021',
        'si5|SPV-27' => 'SPV023, SPV024',
        'si5|SPV-14' => 'SPV025, SPV026',
        'si5|SPV1-04' => 'SPV027, SPV029',
        'si5|SPV2-04' => 'SPV028',
        'si5|SPV1-17' => 'SPV030',
        'si5|SPV-26' => 'SPV031',
        'si5|SPV-21' => 'SPV033',
        'si5|SPV-20' => 'SPV034, SPV037',
        'si5|SPV-09' => 'SPV036',
        'si5|SPV-23' => 'SPV038, SPV040',
        'si5|SPV-25' => 'SPV039',
        'si5|SPV1-16' => 'SPV041',
        'si5|SPV-17' => 'SPV042',

        // รหัสเก่า — ยุบเข้าแผนกปัจจุบัน จึงใช้รหัสของแผนกนั้น
        'si5|E01' => 'SPV016',
        'si5|E02' => 'SPV010, SPV011, SPV012',
        'si5|E15' => 'SPV019',
        'si5|B05' => 'SPV033',
        'si5|B06' => 'SPV023, SPV024',

        'si5|SI-01' => 'SPV038, SPV040',
        'si5|NON' => '',
        'si5|SPV3-04' => 'MVT001-MVT013',

        'pd|SPV3-04' => 'MVT001-MVT013',
        'pd|SI-01' => 'SPV038, SPV040',
    ];

    /**
     * รหัสแผนกฝั่ง SBMS ที่ตรงกับรหัสของ ERP — คืนค่าว่างเมื่อยังจับคู่ไม่ได้
     */
    public static function codes(string $company, string $dept): string
    {
        return self::TO_BMS_CODE[$company.'|'.trim($dept)] ?? '';
    }

    /**
     * ชื่อแผนกที่เอาไปแสดงบนหน้าจอ
     *
     * 🔴 จับคู่ไม่ได้ให้ใช้ชื่อของ ERP ไปก่อน — ห้ามซ่อนแถวทิ้ง
     *    ยอดเงินจะหายไปเงียบๆ แล้วยอดรวมไม่ตรงกับที่บัญชีเห็น
     *    (ERP มีแผนกที่ SBMS ไม่มี เช่น GA, Lean และรหัสเก่า E01/E02/E15)
     */
    public static function label(string $company, string $dept, string $erpName = ''): string
    {
        $dept = trim($dept);

        if ($dept === '') {
            return 'ไม่ระบุแผนก';
        }

        $bms = self::TO_BMS[$company.'|'.$dept] ?? null;

        if ($bms !== null) {
            return $bms;
        }

        // ชื่อของ ERP มักเขียนแบบ "GA : Genaral Affair" — เอาเฉพาะส่วนหน้าให้สั้นพอดีตาราง
        $erpName = trim($erpName);

        if ($erpName !== '') {
            $short = trim(explode(':', $erpName)[0]);

            return ($short !== '' ? $short : $erpName).' (ERP)';
        }

        return $dept.' (ERP)';
    }

    /** จับคู่กับแผนกของ SBMS ได้ไหม — ใช้บอกผู้ใช้ว่าแถวไหนยังไม่ได้จับคู่ */
    public static function matched(string $company, string $dept): bool
    {
        return isset(self::TO_BMS[$company.'|'.trim($dept)]);
    }
}
