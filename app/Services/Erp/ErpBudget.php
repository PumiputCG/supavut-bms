<?php

namespace App\Services\Erp;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * อ่านงบประมาณจาก ERP (Dynamics AX · ตาราง dbo.LEDGERBUDGET)
 *
 * 🔴 อ่านอย่างเดียวเท่านั้น — คลาสนี้ห้ามมีคำสั่งเขียนเด็ดขาด
 *    ERP เป็นระบบบัญชีตัวจริงของบริษัท เขียนพลาดคือกระทบเงินจริง
 *
 * 🔴 ต่อ ERP ไม่ได้ต้องไม่ทำให้หน้าจอ SBMS พัง — ทุกเมธอดครอบ try/catch
 *    แล้วคืนค่าว่าง ให้หน้าจอบอกผู้ใช้ว่า "ตอนนี้ต่อ ERP ไม่ได้" แทนที่จะขึ้น 500
 *
 * ทำไมต้องมีคลาสนี้ (ข้อตกลง DECISIONS ข้อ 44)
 *   บัญชีคีย์งบเข้า ERP แล้วไม่มีอะไรชี้กลับมาที่เอกสารใน SBMS
 *   จึงให้บัญชีกรอก "Model" ตอนติ๊กลงทะเบียน แล้ว SBMS ยิงมาถามว่ามีจริงไหม
 *   ได้แถวไหนบ้าง ให้เลือกแถวที่เป็นของเอกสารใบนั้น แล้วเก็บ RECID ไว้เป็นตัวเชื่อม
 */
class ErpBudget
{
    /** คอลัมน์ที่หน้าจอต้องใช้ — เลือกเท่าที่ใช้ ไม่ดึงทั้ง 52 คอลัมน์ */
    private const COLUMNS = '
        RECID, DATAAREAID, MODELNUM, BPC_BUDGETNO, ACCOUNTNUM, COMMENT_,
        DIMENSION, DIMENSION2_, DIMENSION3_, STARTDATE, ENDDATE,
        AMOUNTMST AS BPC_BUDGET, BPC_ACTUALAMOUNT, BPC_AVAILABLEAMOUNT,
        CREATEDDATETIME, CREATEDBY';

    public function enabled(): bool
    {
        return (bool) config('bms.erp.enabled', true);
    }

    public function company(): string
    {
        return (string) config('bms.erp.company', 'si5');
    }

    /** ต่อ ERP ได้อยู่ไหม — ใช้บอกผู้ใช้ก่อนเปิดหน้าต่างค้นหา */
    public function reachable(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            DB::connection('erp')->selectOne('SELECT 1 AS ok');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * ค้นแถวงบใน ERP ด้วยเลข Model (และเลขงบถ้ามี)
     *
     * 🔴 Model เดียวมีได้หลายแถว — ตรวจจริงแล้ว NM-26-015 แตกเป็น 3 แถว
     *    (ค่าทำแม่พิมพ์ · Transport · Trial Cost) จึงคืนเป็นรายการเสมอ ไม่ใช่แถวเดียว
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $model, ?string $budgetNo = null, ?string $company = null): array
    {
        $model = trim($model);

        if ($model === '' || ! $this->enabled()) {
            return [];
        }

        try {
            $sql = 'SELECT TOP 200'.self::COLUMNS.'
                    FROM dbo.LEDGERBUDGET
                    WHERE DATAAREAID = ? AND LTRIM(RTRIM(MODELNUM)) = ?';
            $args = [$company ?: $this->company(), $model];

            if (($budgetNo = trim((string) $budgetNo)) !== '') {
                $sql .= ' AND LTRIM(RTRIM(BPC_BUDGETNO)) = ?';
                $args[] = $budgetNo;
            }

            $sql .= ' ORDER BY CREATEDDATETIME DESC, RECID';

            return array_map($this->shape(...), DB::connection('erp')->select($sql, $args));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * ดึงแถวตาม RECID ที่เก็บไว้ — ใช้ตอนแสดงว่าเอกสารใบนี้ผูกกับงบแถวไหนใน ERP
     *
     * @param  array<int,string|int>  $recIds
     * @return array<int,array<string,mixed>>
     */
    public function byRecIds(array $recIds, ?string $company = null): array
    {
        $recIds = array_values(array_filter(array_map('strval', $recIds)));

        if ($recIds === [] || ! $this->enabled()) {
            return [];
        }

        try {
            $marks = implode(',', array_fill(0, count($recIds), '?'));

            $rows = DB::connection('erp')->select(
                'SELECT'.self::COLUMNS.' FROM dbo.LEDGERBUDGET
                 WHERE DATAAREAID = ? AND RECID IN ('.$marks.')',
                array_merge([$company ?: $this->company()], $recIds)
            );

            return array_map($this->shape(...), $rows);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * บริษัทที่แดชบอร์ดต้องอ่าน
     *
     * 🔴 แผนกกลุ่ม Mold ของ SBMS มีงบอยู่ในบริษัท pd (โมลด์แวนโต) ไม่ใช่ si5
     *    อ่านแค่บริษัทเดียวแผนกเหล่านั้นจะขึ้นยอดศูนย์ทั้งที่มีงบจริง
     *
     * @return array<int,string>
     */
    public function companies(): array
    {
        return ['si5', 'pd'];
    }

    /**
     * ชื่อบริษัทที่เอาไปขึ้นหัวการ์ด
     *
     * 🔴 ใช้ชื่อสั้น ไม่ใช่ชื่อเต็มจาก COMPANYINFO
     *    ของจริงคือ "บริษัท สุภาวุฒิ อินดัสทรี จำกัด" ซึ่งยาวเกินหัวการ์ดครึ่งหน้า
     * 🔴 ERP มีแต่ชื่อไทย — ฝั่งอังกฤษใช้ชื่อทับศัพท์ที่บริษัทใช้จริง
     *    (กติกาภาษา: ไม่มีค่า EN ให้ถอยไปใช้ TH ห้ามปล่อยว่าง)
     *
     * @return array{th: string, en: string}
     */
    public function companyName(string $company): array
    {
        return match ($company) {
            'si5' => ['th' => 'สุภาวุฒิ อินดัสทรี', 'en' => 'Supavut Industry'],
            'pd' => ['th' => 'โมลด์แวนโต', 'en' => 'Moldvanto'],
            default => ['th' => $company, 'en' => $company],
        };
    }

    /**
     * ชื่อแผนกตามที่ ERP ตั้งไว้ — คีย์เป็น "บริษัท|รหัส"
     *
     * 🔴 ต้องกรอง DIMENSIONCODE = 0 เสมอ (บั๊กจริง 2026-09-11)
     *    ตาราง DIMENSIONS เก็บมิติ 3 ชนิดปนกันในตารางเดียว
     *      0 = แผนก · 1 = Cost centre · 2 = Purpose
     *    และ **รหัสเดียวกันมีได้ทั้ง 3 ชนิด** เช่น B05
     *      code 0 = "Planing"                        ← แผนกจริง
     *      code 1 = "B05#BLOW SINCO 30L,TYPE.80F..." ← ชื่อเครื่องจักร
     *    ไม่กรองแล้วหน้าจอจะขึ้นชื่อเครื่องจักรแทนชื่อแผนกแบบเงียบๆ
     *
     * @return array<string,string>
     */
    public function departmentNames(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $out = [];

        foreach ($this->companies() as $co) {
            try {
                foreach (DB::connection('erp')->select(
                    'SELECT NUM, DESCRIPTION FROM dbo.DIMENSIONS
                     WHERE DATAAREAID = ? AND DIMENSIONCODE = 0', [$co]
                ) as $r) {
                    $out[$co.'|'.trim((string) $r->NUM)] = trim((string) $r->DESCRIPTION);
                }
            } catch (Throwable) {
                // ต่อไม่ได้ก็แค่ไม่มีชื่อ ไม่ทำให้หน้าจอพัง
            }
        }

        return $out;
    }

    /**
     * ปีงบที่มีข้อมูล — เรียงปัจจุบันขึ้นก่อน ใช้ทำตัวกรองปี
     *
     * @return array<int,int>
     */
    public function years(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $out = [];

        foreach ($this->companies() as $co) {
            try {
                foreach (DB::connection('erp')->select(
                    'SELECT DISTINCT YEAR(STARTDATE) AS y FROM dbo.LEDGERBUDGET
                     WHERE DATAAREAID = ? AND STARTDATE IS NOT NULL', [$co]
                ) as $r) {
                    if ($r->y) {
                        $out[(int) $r->y] = true;
                    }
                }
            } catch (Throwable) {
                // ข้ามไป
            }
        }

        $years = array_keys($out);
        rsort($years);

        return $years;
    }

    /**
     * ยอดงบรวมตามแผนก — ตัวเลขตั้งต้นของแดชบอร์ด
     *
     * วงเงินฐานใช้ AMOUNTMST ตาม BPC_LEDGERBUDGETVIEW (ตรวจ 2026-09-14)
     * Dashboard ปัจจุบันใช้ ErpDashboard ซึ่งแยกสกุลเงินและกรองขอบเขตเพิ่มเติม
     *
     * 🔴 แยกตามปีด้วยเสมอ เพื่อให้หน้าจอกางดูรายปีของแต่ละแผนกได้
     *
     * @return array<int,array<string,mixed>>
     */
    public function byDepartment(?int $year = null): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $rows = [];

        foreach ($this->companies() as $co) {
            try {
                $sql = 'SELECT LTRIM(RTRIM(DIMENSION)) AS dept,
                               YEAR(STARTDATE) AS y,
                               COUNT(*) AS rows_,
                               SUM(AMOUNTMST) AS budget
                        FROM dbo.LEDGERBUDGET
                        WHERE DATAAREAID = ?';
                $args = [$co];

                if ($year) {
                    $sql .= ' AND YEAR(STARTDATE) = ?';
                    $args[] = $year;
                }

                $sql .= ' GROUP BY LTRIM(RTRIM(DIMENSION)), YEAR(STARTDATE)';

                foreach (DB::connection('erp')->select($sql, $args) as $r) {
                    $rows[] = [
                        'company' => $co,
                        'dept' => (string) $r->dept,
                        'year' => (int) $r->y,
                        'rows' => (int) $r->rows_,
                        'budget' => (float) $r->budget,
                    ];
                }
            } catch (Throwable) {
                // ข้ามบริษัทที่อ่านไม่ได้
            }
        }

        return $rows;
    }

    /**
     * งบ + การหักลบ แยกตามแผนก — สำหรับแท็บ "การใช้งบ" (เจ้าของสั่ง 2026-09-11 · DECISIONS 49.5)
     *
     * 🔴 ERP เก็บยอดหักลบไว้ให้แล้วทั้ง 3 ตัว ไม่ต้องคำนวณเอง
     *      ใช้ไป   = BPC_ACTUALAMOUNT
     *      กันไว้   = BPC_RESERVEAMOUNT
     *      คงเหลือ  = BPC_AVAILABLEAMOUNT
     *    ตรวจแล้วสูตร งบ − ใช้ไป − กันไว้ = คงเหลือ ตรง 12,098/12,115 แถว (99.9%)
     *
     * 🔴 ยึด BPC_ACTUALAMOUNT เป็นตัวจริง (เจ้าของเคาะ) — ตรงกับที่บัญชีเห็นในหน้าจอ ERP
     *    ถ้าไปบวกเองจากสมุดเดินงบจะได้มากกว่านี้ ~400 ล้าน (2.7%) ดู DECISIONS 49.3 ข.
     *
     * 🔴 ปีที่กรองคือ "ปีของงบ" (YEAR(STARTDATE)) ไม่ใช่ปีที่จ่ายเงิน (เจ้าของเคาะ)
     *
     * @return array<int,array<string,mixed>>
     */
    public function usageByDepartment(?int $year = null): array
    {
        return $this->usage('LTRIM(RTRIM(DIMENSION)) AS k', 'dept', $year);
    }

    /** งบ + การหักลบ แยกตามปีงบ — ไม่กรองปี เพราะตัวมันเองคือการแจกแจงรายปี */
    public function usageByYear(): array
    {
        return $this->usage('YEAR(STARTDATE) AS k', 'year', null);
    }

    /**
     * ตัวรวมยอดร่วมของ 2 เมธอดข้างบน — SQL ชุดเดียวกัน ต่างแค่จัดกลุ่มด้วยอะไร
     *
     * 🔴 `$groupBy` มาจากโค้ดของเราเองเท่านั้น ห้ามรับค่าจากผู้ใช้มาต่อสตริง
     *
     * @return array<int,array<string,mixed>>
     */
    private function usage(string $groupBy, string $keyName, ?int $year): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $expr = explode(' AS ', $groupBy)[0];
        $out = [];

        foreach ($this->companies() as $co) {
            try {
                $sql = "SELECT $groupBy,
                               SUM(AMOUNTMST) AS budget,
                               SUM(BPC_ACTUALAMOUNT) AS actual,
                               SUM(BPC_RESERVEAMOUNT) AS reserve,
                               SUM(BPC_AVAILABLEAMOUNT) AS available,
                               COUNT(*) AS rows_
                        FROM dbo.LEDGERBUDGET
                        WHERE DATAAREAID = ?";
                $args = [$co];

                if ($year) {
                    $sql .= ' AND YEAR(STARTDATE) = ?';
                    $args[] = $year;
                }

                $sql .= " GROUP BY $expr";

                foreach (DB::connection('erp')->select($sql, $args) as $r) {
                    $out[] = [
                        'company' => $co,
                        $keyName => $keyName === 'year' ? (int) $r->k : trim((string) $r->k),
                        'budget' => (float) $r->budget,
                        'actual' => (float) $r->actual,
                        'reserve' => (float) $r->reserve,
                        'available' => (float) $r->available,
                        'rows' => (int) $r->rows_,
                    ];
                }
            } catch (Throwable) {
                // ข้ามบริษัทที่อ่านไม่ได้
            }
        }

        return $out;
    }

    /**
     * รายการที่จ่ายจริงของบรรทัดงบหนึ่งก้อน — "ใช้เงินไปกับอะไรบ้าง"
     *
     * 🔴 ผูกด้วย Model + Budget No. ไม่ใช่ REFLEDGERBUDGETRECID
     *    ตรวจแล้วคีย์นี้จับได้ 99.8% ส่วน REFLEDGERBUDGETRECID มีค่าแค่ 14.6% ของแถว
     *
     * 🔴 STATUS 2 = ใช้จริง (ตอนลงใบแจ้งหนี้) · 1 = กันไว้ · 3 = กลับรายการกันไว้
     *    หน้านี้เอาเฉพาะ "ใช้จริง" เพราะหัวหน้าถามว่าเงินหายไปกับอะไร
     *
     * 🔴 ห้ามเขียน correlated subquery บนตารางนี้ — 1.35 ล้านแถว เคยค้างจนหลุดการเชื่อมต่อ
     *
     * @return array<int,array<string,mixed>>
     */
    public function spendLines(string $company, string $model, string $budgetNo, int $limit = 300): array
    {
        if (! $this->enabled() || trim($model) === '') {
            return [];
        }

        try {
            $sql = 'SELECT TOP '.max(1, min($limit, 1000)).'
                           TRANSDATE, COMMENT_, ITEMID, QTY, PRICE, AMOUNTMST,
                           PURCHID, PURCHREQID, INVOICEID, INVOICEDATE
                    FROM dbo.BPC_LEDGERBUDGETTRANS
                    WHERE DATAAREAID = ? AND STATUS = 2
                      AND LTRIM(RTRIM(MODELNUM)) = ?
                      AND LTRIM(RTRIM(BPC_BUDGETNO)) = ?
                    ORDER BY TRANSDATE DESC, RECID DESC';

            return array_map(fn ($r) => [
                'date' => $r->TRANSDATE ? substr((string) $r->TRANSDATE, 0, 10) : null,
                'title' => trim((string) $r->COMMENT_),
                'item' => trim((string) $r->ITEMID),
                'qty' => (float) $r->QTY,
                'price' => (float) $r->PRICE,
                'amount' => (float) $r->AMOUNTMST,
                'po' => trim((string) $r->PURCHID),
                'pr' => trim((string) $r->PURCHREQID),
                'invoice' => trim((string) $r->INVOICEID),
                'invoiceDate' => $r->INVOICEDATE ? substr((string) $r->INVOICEDATE, 0, 10) : null,
            ], DB::connection('erp')->select($sql, [$company, trim($model), trim($budgetNo)]));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * รายการงบทีละแถว สำหรับหน้าที่กดเข้าไปดูในแผนก
     *
     * 🔴 เรียงปัจจุบันขึ้นก่อนแล้วค่อยย้อนอดีต (เจ้าของสั่ง 2026-09-11)
     *
     * @return array<int,array<string,mixed>>
     */
    public function rows(?int $year = null): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $out = [];

        foreach ($this->companies() as $co) {
            try {
                /*
                  🔴 ดึงยอดหักลบมาด้วยตั้งแต่ตรงนี้ (เจ้าของสั่ง 2026-09-11)
                     แท็บ "การใช้งบ" ต้องกดจากแผนกลงมาเห็นบรรทัดงบพร้อม ใช้ไป/คงเหลือ
                     ถ้าไม่เอามาด้วยจะต้องยิงถาม ERP อีกรอบตอนกด ซึ่งช้ากว่าและไม่จำเป็น
                */
                $sql = 'SELECT LTRIM(RTRIM(DIMENSION)) AS dept, MODELNUM, BPC_BUDGETNO,
                               COMMENT_, STARTDATE, CREATEDDATETIME,
                               AMOUNTMST AS budget,
                               BPC_ACTUALAMOUNT AS actual,
                               BPC_RESERVEAMOUNT AS reserve,
                               BPC_AVAILABLEAMOUNT AS available
                        FROM dbo.LEDGERBUDGET
                        WHERE DATAAREAID = ?';
                $args = [$co];

                if ($year) {
                    $sql .= ' AND YEAR(STARTDATE) = ?';
                    $args[] = $year;
                }

                $sql .= ' ORDER BY STARTDATE DESC, CREATEDDATETIME DESC';

                foreach (DB::connection('erp')->select($sql, $args) as $r) {
                    $out[] = [
                        'company' => $co,
                        'dept' => trim((string) $r->dept),
                        'year' => $r->STARTDATE ? (int) substr((string) $r->STARTDATE, 0, 4) : 0,
                        'model' => trim((string) $r->MODELNUM),
                        'budget_no' => trim((string) $r->BPC_BUDGETNO),
                        'title' => trim((string) $r->COMMENT_),
                        'budget' => (float) $r->budget,
                        'actual' => (float) $r->actual,
                        'reserve' => (float) $r->reserve,
                        'available' => (float) $r->available,
                        'start_date' => $r->STARTDATE ? substr((string) $r->STARTDATE, 0, 10) : null,
                        'created_at' => $r->CREATEDDATETIME ? substr((string) $r->CREATEDDATETIME, 0, 16) : null,
                    ];
                }
            } catch (Throwable) {
                // ข้ามบริษัทที่อ่านไม่ได้
            }
        }

        return $out;
    }

    /** แปลงแถวดิบจาก AX ให้เป็นรูปที่หน้าจอใช้ง่าย */
    private function shape(object $r): array
    {
        return [
            'recid' => (string) $r->RECID,        // 🔴 เป็น bigint — ส่งเป็น string กัน JS ปัดเลข
            'company' => trim((string) $r->DATAAREAID),
            'model' => trim((string) $r->MODELNUM),
            'budget_no' => trim((string) $r->BPC_BUDGETNO),
            'account' => trim((string) $r->ACCOUNTNUM),
            'comment' => trim((string) $r->COMMENT_),
            'dept' => trim((string) $r->DIMENSION),
            'cost' => trim((string) $r->DIMENSION2_),
            'purpose' => trim((string) $r->DIMENSION3_),
            'start_date' => $r->STARTDATE ? substr((string) $r->STARTDATE, 0, 10) : null,
            'end_date' => $r->ENDDATE ? substr((string) $r->ENDDATE, 0, 10) : null,
            'budget' => (float) $r->BPC_BUDGET,
            'actual' => (float) $r->BPC_ACTUALAMOUNT,
            'available' => (float) $r->BPC_AVAILABLEAMOUNT,
            'created_at' => $r->CREATEDDATETIME ? substr((string) $r->CREATEDDATETIME, 0, 19) : null,
            'created_by' => trim((string) $r->CREATEDBY),
        ];
    }
}
