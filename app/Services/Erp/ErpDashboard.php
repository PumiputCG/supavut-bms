<?php

namespace App\Services\Erp;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Read-only dashboard data. Money is summed in integer satang. */
class ErpDashboard
{
    private const MONEY = ['budget', 'actual', 'reserve', 'available'];

    /*
      เงินที่จ่ายตรงให้พนักงาน (กฎเด็ดขาดของโปรเจค: ห้ามแสดง)
      - คำอังกฤษต้องเป็นทั้งคำ เพราะ "STOWAGE BOX" มีคำว่า wage อยู่ข้างใน
      - คำไทยต้องขึ้นต้นบรรทัดหรือตามหลังช่องว่าง/ขีด เพราะ "โปรแกรมเงินเดือน" คือซอฟต์แวร์
      - ไม่ใช้ "ค่าจ้าง"/"ค่าแรง" เพราะใน ERP คือจ้างผู้รับเหมา เช่น "ค่าจ้างทำ Tooling"
      - ประกันสังคม · กองทุนสำรองเลี้ยงชีพ · สวัสดิการ ไม่ใช่ค่าจ้าง จึงยังแสดง
      ตรวจกับ ERP สด 2026-09-14: ตัด 69 แถว (เงินเดือน/OT 24 · โบนัส 41 · คอมมิชชั่น 4) ไม่มีแถวอื่นโดนลูกหลง
    */
    private const PAYROLL_WORDS = ['salary', 'salaries', 'wage', 'wages', 'overtime', 'bonus', 'bonuses', 'commission', 'commissions', 'payroll'];

    private const PAYROLL_THAI = ['เงินเดือน', 'ค่าล่วงเวลา', 'โบนัส', 'ค่าคอมมิชชั่น'];

    /*
      🔴 ถังงบค่าแรง (DL = Direct Labor) และค่าล่วงเวลา (OT) ของทุกแผนก BG17–BG25 (พบ 2026-09-14)
         ชื่องบเขียนแค่ "Budget control" ความหมายอยู่ที่ "เลขที่งบ" เท่านั้น เช่น HR-DLAdmin · MO-OTDirect
         ตัวกรองชื่องบ/ชื่อบัญชีจับไม่ได้ จึงต้องกรองท้ายเลขที่งบด้วย
         ตรวจแล้วไม่มีเลขที่งบอื่นในระบบที่มี OT หรือ DL อยู่เลย จึงไม่มีงบอื่นโดนลูกหลง
    */
    private const PAYROLL_BUDGET_SUFFIXES = ['DLAdmin', 'DLDirect', 'DLIndirect', 'DLSale', 'OTAdmin', 'OTDirect', 'OTIndirect', 'OTSale'];

    public function __construct(private readonly ErpBudget $erp) {}

    // Enrich the current page without multiplying PO rows or changing totals.
    private function suppliers(array $rows, string $company): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'po'))));
        if (! $ids) {
            return $rows;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $names = [];
        foreach ($this->select("SELECT PURCHID, PURCHNAME FROM dbo.PURCHTABLE WHERE DATAAREAID=? AND PURCHID IN ($marks)", [$company, ...$ids]) as $vendor) {
            $names[trim($vendor->PURCHID)] = trim((string) $vendor->PURCHNAME);
        }
        foreach ($rows as &$row) {
            $row['supplier_name'] = $names[$row['po']] ?? '';
        }
        unset($row);

        return $rows;
    }

    protected function select(string $sql, array $params = []): array
    {
        if (! $this->erp->enabled()) {
            throw new RuntimeException('ERP_DISABLED');
        }
        $connection = DB::connection('erp');
        $connection->getPdo()->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 30);

        return $connection->select($sql, $params);
    }

    /** LIKE patterns matched against the text padded with one space on each side. */
    public static function payroll_patterns(): array
    {
        return array_merge(
            array_map(fn ($word) => '%[^a-z]'.$word.'[^a-z]%', self::PAYROLL_WORDS),
            array_map(fn ($word) => '%[- ]'.$word.'%', self::PAYROLL_THAI),
        );
    }

    /** PHP twin of the SQL policy, so tests can prove which ERP names are hidden. */
    public static function is_payroll(string ...$texts): bool
    {
        foreach ($texts as $text) {
            $padded = ' '.$text.' ';
            foreach (self::PAYROLL_WORDS as $word) {
                if (preg_match('/[^a-z]'.preg_quote($word, '/').'[^a-z]/iu', $padded)) {
                    return true;
                }
            }
            foreach (self::PAYROLL_THAI as $word) {
                if (preg_match('/[\- ]'.preg_quote($word, '/').'/u', $padded)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** LIKE patterns for payroll budget buckets identified only by their budget number. */
    public static function payroll_budget_patterns(): array
    {
        return array_map(fn ($suffix) => '%-'.$suffix, self::PAYROLL_BUDGET_SUFFIXES);
    }

    /** PHP twin of the budget-number part of the SQL policy. */
    public static function is_payroll_budget_no(string $budget_no): bool
    {
        foreach (self::PAYROLL_BUDGET_SUFFIXES as $suffix) {
            if (preg_match('/-'.preg_quote($suffix, '/').'$/i', trim($budget_no))) {
                return true;
            }
        }

        return false;
    }

    /** Filter protected payroll budgets before selecting monetary fields. */
    private function scope_policy(): array
    {
        $parts = [];
        $params = [];
        foreach (self::payroll_patterns() as $pattern) {
            $parts[] = "(' ' + COALESCE(b.COMMENT_, '') + ' ') NOT LIKE ?";
            $parts[] = "(' ' + COALESCE(a.ACCOUNTNAME, '') + ' ') NOT LIKE ?";
            array_push($params, $pattern, $pattern);
        }
        foreach (self::payroll_budget_patterns() as $pattern) {
            $parts[] = "LTRIM(RTRIM(COALESCE(b.BPC_BUDGETNO, ''))) NOT LIKE ?";
            $params[] = $pattern;
        }

        return [implode(' AND ', $parts), $params];
    }

    /*
      ═══════════ อ่านถังงบจาก ERP ═══════════

      🔴 วัดจริง 2026-09-17: ตัวที่ทำให้หน้าแดชบอร์ดช้าคือ "การขนแถวข้ามแลน" ไม่ใช่การค้นหาใน ERP
         นับอย่างเดียว 0.07 วิ · ดึงทั้งตาราง 12,136 แถว 3.97 วิ · ดึงเฉพาะปีเดียว 659 แถว 0.23 วิ
         จึงแยกเป็น 2 คิวรี — รายการตัวเลือกอ่านแบบรวมกลุ่ม (0.62 วิ) + ข้อมูลเต็มเฉพาะปีที่เลือก (0.15 วิ)
      🔴 ยังอ่านสดทุกครั้ง ไม่มีแคช (กติกาเดิมของเจ้าของ) — แค่ขนของน้อยลง
    */

    /** ชื่อแผนกจากตาราง DIMENSIONS — คำขอเดียวอ่านครั้งเดียว */
    private array $dimension_names = [];

    private function dimension_names(array $companies): array
    {
        $key = implode(',', $companies);
        if (isset($this->dimension_names[$key])) {
            return $this->dimension_names[$key];
        }
        $marks = implode(',', array_fill(0, count($companies), '?'));
        $names = [];
        foreach ($this->select("SELECT DATAAREAID, NUM, DESCRIPTION FROM dbo.DIMENSIONS
      WHERE DATAAREAID IN ($marks) AND DIMENSIONCODE=0", $companies) as $name) {
            $names[trim($name->DATAAREAID).'|'.trim($name->NUM)] = trim($name->DESCRIPTION);
        }

        return $this->dimension_names[$key] = $names;
    }

    /**
     * ค่าที่มีอยู่จริงของตัวกรอง (บริษัท · ปีงบ · แผนก · งวดงบ) — รวมกลุ่มมาจาก ERP
     * ไม่มียอดเงินและชื่องบ เพราะตัวกรองไม่ได้ใช้ · คิดปี/ไตรมาส/รหัสแผนกด้วยตัวเดียวกับ snapshot()
     *
     * @return list<array>
     */
    public function scopes(): array
    {
        [$policy, $params] = $this->scope_policy();
        $companies = $this->erp->companies();
        $marks = implode(',', array_fill(0, count($companies), '?'));
        $rows = $this->select("SELECT b.DATAAREAID, b.DIMENSION, b.MODELNUM, b.DIMENSION3_, b.CURRENCY, COUNT(*) AS n
      FROM dbo.LEDGERBUDGET b LEFT JOIN dbo.LEDGERTABLE a
        ON a.DATAAREAID=b.DATAAREAID AND a.ACCOUNTNUM=b.ACCOUNTNUM
      WHERE b.DATAAREAID IN ($marks) AND $policy
      GROUP BY b.DATAAREAID, b.DIMENSION, b.MODELNUM, b.DIMENSION3_, b.CURRENCY", array_merge($companies, $params));
        $names = $this->dimension_names($companies);
        $out = [];
        foreach ($rows as $r) {
            $row = ['company' => trim($r->DATAAREAID), 'dept' => trim($r->DIMENSION),
                'model' => trim($r->MODELNUM), 'purpose' => trim($r->DIMENSION3_),
                'currency' => trim($r->CURRENCY), 'rows' => (int) $r->n];
            $row['year'] = self::budget_year($row['model'], $row['purpose']);
            $row['quarter'] = self::budget_quarter($row['model'], $row['purpose']);
            $row['dept_key'] = $row['company'].'|'.$row['dept'];
            $row['dept_name'] = ($names[$row['dept_key']] ?? '') ?: ($row['dept'] ?: '—');
            $row['dept_group'] = self::department_key($row['company'], $row['dept'], $row['dept_name']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * ถังงบพร้อมยอดเงิน — ส่งปีงบมาด้วยเพื่อให้ ERP ส่งกลับเฉพาะปีนั้น
     *
     * 🔴 เงื่อนไขปีใน SQL เป็น "ตัวกรองหยาบ" ที่ได้ชุดกว้างกว่าหรือเท่ากับของจริงเสมอ
     *    ตัวตัดสินปีจริงยังเป็น budget_year() ฝั่ง PHP เหมือนเดิม (แถวที่ Model กับ Propose ขัดกันจึงไม่หาย)
     * ไม่ส่งปีมา (ทุกปี / งบที่ยังไม่จัดปี) = อ่านทั้งหมดเหมือนเดิม
     *
     * @param  list<int|string>  $years
     */
    public function snapshot(array $years = []): array
    {
        [$policy, $params] = $this->scope_policy();
        $companies = $this->erp->companies();
        $marks = implode(',', array_fill(0, count($companies), '?'));
        $where = "b.DATAAREAID IN ($marks) AND $policy";
        $args = array_merge($companies, $params);
        $years = array_values(array_unique(array_filter($years, fn ($y) => preg_match('/^20\d{2}$/D', (string) $y))));
        if ($years) {
            $parts = [];
            foreach ($years as $year) {
                $parts[] = '(b.MODELNUM LIKE ? OR b.DIMENSION3_ LIKE ?)';
                array_push($args, 'BG'.substr((string) $year, 2, 2).'%', 'BG'.substr((string) $year, 2, 2).'Q%');
            }
            $where .= ' AND ('.implode(' OR ', $parts).')';
        }
        $rows = $this->select("SELECT b.DATAAREAID, b.RECID, b.MODELNUM, b.BPC_BUDGETNO,
      b.COMMENT_, b.DIMENSION, b.DIMENSION2_, b.DIMENSION3_, b.STARTDATE,
      CASE WHEN b.CURRENCY='THB' THEN b.AMOUNTMST ELSE b.AMOUNT END AS budget, b.BPC_ACTUALAMOUNT AS actual,
      b.BPC_RESERVEAMOUNT AS reserve, b.BPC_AVAILABLEAMOUNT AS available,
      b.ACTIVE, b.STOP, b.CURRENCY
      FROM dbo.LEDGERBUDGET b LEFT JOIN dbo.LEDGERTABLE a
        ON a.DATAAREAID=b.DATAAREAID AND a.ACCOUNTNUM=b.ACCOUNTNUM
      WHERE $where", $args);
        $names = $this->dimension_names($companies);
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'company' => trim($r->DATAAREAID), 'id' => (string) $r->RECID,
                'model' => trim($r->MODELNUM), 'budget_no' => trim($r->BPC_BUDGETNO),
                'title' => trim($r->COMMENT_), 'dept' => trim($r->DIMENSION),
                'cost' => trim($r->DIMENSION2_), 'purpose' => trim($r->DIMENSION3_),
                'start_date' => substr((string) $r->STARTDATE, 0, 10),
                'active' => (bool) $r->ACTIVE, 'stopped' => (bool) $r->STOP,
                'currency' => trim($r->CURRENCY),
            ];
            foreach (self::MONEY as $key) {
                $row[$key] = self::satang((string) $r->$key);
            }
            $row['year'] = self::budget_year($row['model'], $row['purpose']);
            $row['quarter'] = self::budget_quarter($row['model'], $row['purpose']);
            $row['dept_key'] = $row['company'].'|'.$row['dept'];
            $row['dept_name'] = ($names[$row['dept_key']] ?? '') ?: ($row['dept'] ?: '—');
            $row['dept_group'] = self::department_key($row['company'], $row['dept'], $row['dept_name']);
            $row['group'] = self::group_key($row);
            $out[] = $row;
        }

        return $out;
    }

    public static function satang(string $amount): int
    {
        // SQL decimals must not pass through a binary float before rounding to satang.
        if (! preg_match('/^([+-]?)([0-9]*)(?:\.([0-9]*))?$/D', trim($amount), $parts)
          || (($parts[2] ?? '') === '' && ($parts[3] ?? '') === '')) {
            throw new RuntimeException('INVALID_ERP_MONEY');
        }
        $whole = ltrim($parts[2], '0') ?: '0';
        if (strlen($whole) > 15) {
            throw new RuntimeException('ERP_MONEY_OVERFLOW');
        }
        $fraction = str_pad($parts[3] ?? '', 3, '0');
        $value = (int) $whole * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $parts[1] === '-' ? -$value : $value;
    }

    /** Only explicit BG identifiers imply a period. Project serial numbers do not. */
    public static function budget_year(string $model, string $purpose): ?int
    {
        preg_match('/^BG(\d{2})\.?$/i', trim($model), $m);
        preg_match('/^BG(\d{2})Q[1-4]$/i', trim($purpose), $p);
        if (isset($m[1], $p[1]) && $m[1] !== $p[1]) {
            return null;
        }
        $short = $p[1] ?? $m[1] ?? null;

        return $short === null ? null : 2000 + (int) $short;
    }

    /**
     * ไตรมาสของแถวงบ — อ่านจาก DIMENSION3_ (ช่อง Propose) เท่านั้น
     *
     * 🔴 ห้ามเดาไตรมาสจาก STARTDATE เด็ดขาด (ตรวจแล้ว 2026-09-16)
     *    STARTDATE = วันที่บัญชี "ตั้งงบ" ไม่ใช่ขอบเขตไตรมาส · ตั้งล่วงหน้าข้ามปีได้
     *    เช่น BG26Q1 ตั้งไว้ตั้งแต่ 15 ต.ค. 2025 แต่เงินจ่ายจริง ม.ค.–มี.ค. 2026
     *    ลองเดาจากวันที่แล้วผิด 17.9% (1,000 จาก 5,584 แถวที่รู้ไตรมาสจริงอยู่แล้ว)
     *
     * 🔴 คืน null เมื่อไม่มีรหัสไตรมาส ซึ่ง "ปกติ" ไม่ใช่ข้อมูลเสีย
     *    งบโปรเจค/รุ่นรถ (NM- · MY21 · IP · DUCT) ตั้งทั้งโครงการข้ามปี จึงไม่มีไตรมาสจริงๆ
     *    วัดแล้ว: งบดำเนินงาน BGxx มีไตรมาสครบ 99.9% · งบโปรเจคไม่มีเลย
     *
     * ใช้เงื่อนไขปีเดียวกับ budget_year() — Model กับ Propose ต้องเป็นปีเดียวกัน
     * ไม่งั้นถือว่าจับคู่ไม่ได้ ไม่เดาให้
     */
    public static function budget_quarter(string $model, string $purpose): ?int
    {
        if (self::budget_year($model, $purpose) === null) {
            return null;
        }

        return preg_match('/^BG\d{2}Q([1-4])$/i', trim($purpose), $m) ? (int) $m[1] : null;
    }

    public static function group_key(array $row): string
    {
        return hash('sha256', json_encode([$row['company'], $row['dept'], $row['model'], $row['budget_no']]));
    }

    /** ERP department identity only. SBMS mapping is deferred by the owner. */
    public static function department_key(string $company, string $dept, string $name): string
    {
        return $company.'|'.$dept;
    }

    public static function totals(array $rows): array
    {
        $total = array_fill_keys(self::MONEY, 0);
        foreach ($rows as $row) {
            foreach (self::MONEY as $key) {
                $total[$key] += $row[$key];
            }
        }
        $total['count'] = count($rows);
        $total['difference'] = $total['budget'] - $total['actual'] - $total['reserve'] - $total['available'];

        return $total;
    }

    public static function filter(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function ($row) use ($filters) {
            foreach (['company' => 'company', 'model' => 'model', 'budget' => 'group', 'record' => 'id'] as $filter => $field) {
                if (($filters[$filter] ?? '') !== '' && (string) ($row[$field] ?? '') !== (string) $filters[$filter]) {
                    return false;
                }
            }
            if (($filters['dept'] ?? '') !== '' && $filters['dept'] !== $row['dept_key'] && $filters['dept'] !== ($row['dept_group'] ?? $row['dept_key'])) {
                return false;
            }
            /*
              ไตรมาส — 'all'/ค่าว่าง = ทุกไตรมาส · 'unassigned' = งบที่ไม่มีรหัสไตรมาส (งบโปรเจค)
              🔴 ต้องมีตัวเลือก unassigned เสมอ ไม่งั้นงบโปรเจค 6,548 แถวจะหายเงียบๆ
            */
            $quarter = $filters['quarter'] ?? '';
            if ($quarter !== '' && $quarter !== 'all') {
                $mine = $row['quarter'] ?? null;
                if ($quarter === 'unassigned' ? $mine !== null : $mine !== (int) $quarter) {
                    return false;
                }
            }
            $year = $filters['year'] ?? '';

            // 'all' = ทุกปี (ค่าว่างก็ถือว่าทุกปีสำหรับผู้เรียกที่ไม่ได้ส่งปี)
            return $year === '' || $year === 'all' || ($year === 'unassigned' ? $row['year'] === null : $row['year'] === (int) $year);
        }));
    }

    /*
      ERP เก็บเงินเยนไว้ใต้รหัสเก่า JPP (ตาราง CURRENCY.TXT = "JPY") ตรวจแล้ว 2026-09-14
      แสดงเป็นรหัสสากลให้คนอ่านรู้เรื่อง แต่คีย์ข้อมูลยังเป็นรหัสจริงของ ERP
    */
    private const CURRENCY_LABELS = ['JPP' => 'JPY', 'USP' => 'USD (USP)', 'USS' => 'USD (USS)', 'EUP' => 'EUR (EUP)', 'EUS' => 'EUR (EUS)', 'JPS' => 'JPY (JPS)', 'MYP' => 'MYR (MYP)', 'MYS' => 'MYR (MYS)'];

    public static function currency_label(string $code): string
    {
        return self::CURRENCY_LABELS[$code] ?? ($code !== '' ? $code : '—');
    }

    /** Totals per non-THB currency. Foreign money is never converted or added to THB. */
    public static function foreign(array $rows): array
    {
        $by = [];
        foreach ($rows as $row) {
            if ($row['currency'] !== 'THB') {
                $by[$row['currency']][] = $row;
            }
        }
        ksort($by);

        return array_map([self::class, 'totals'], $by);
    }

    /**
     * Departments carry THB totals plus separate foreign-currency totals
     * (owner 2026-09-14: show the yen budget inside its department, never inside baht).
     */
    public static function departments(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['dept_group']][] = $row;
        }
        $out = [];
        foreach ($groups as $key => $items) {
            $thb = array_values(array_filter($items, fn ($row) => $row['currency'] === 'THB'));
            $out[] = ['key' => $key, 'first' => $items[0], 'rows' => $thb, 'total' => self::totals($thb), 'foreign' => self::foreign($items)];
        }
        usort($out, fn ($a, $b) => $b['total']['budget'] <=> $a['total']['budget']);

        return $out;
    }

    public static function groups(array $rows, string $field): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row[$field]][] = $row;
        }
        $out = [];
        foreach ($groups as $key => $items) {
            $out[] = ['key' => $key, 'first' => $items[0], 'titles' => array_values(array_unique(array_column($items, 'title'))), 'rows' => $items, 'total' => self::totals($items)];
        }
        usort($out, fn ($a, $b) => $b['total']['budget'] <=> $a['total']['budget']);

        return $out;
    }

    /**
     * Build a read-only scope using IDs already obtained from the permitted snapshot.
     * A bound XML list avoids SQL Server's 2100-parameter limit for old departments.
     * All values remain bound parameters. No request can supply this ID list.
     */
    private function cte(array $rows, string $company): array
    {
        $ids = array_values(array_unique(array_column($rows, 'id')));
        foreach ($ids as $id) {
            if (! preg_match('/^[1-9][0-9]{0,18}$/D', (string) $id)) {
                throw new RuntimeException('INVALID_SOURCE_ID');
            }
        }
        $id_xml = '<ids><id>'.implode('</id><id>', $ids ?: ['0']).'</id></ids>';
        [$policy, $params] = $this->scope_policy();

        return ["WITH requested_ids AS (
      SELECT t.i.value('.', 'bigint') AS RECID FROM (SELECT CAST(? AS XML) AS doc) x
      CROSS APPLY x.doc.nodes('/ids/id') t(i)
    ), chosen AS (
      SELECT b.RECID, b.DATAAREAID, b.MODELNUM, b.BPC_BUDGETNO, b.DIMENSION, b.DIMENSION2_, b.DIMENSION3_
      FROM dbo.LEDGERBUDGET b JOIN requested_ids r ON r.RECID=b.RECID LEFT JOIN dbo.LEDGERTABLE a
        ON a.DATAAREAID=b.DATAAREAID AND a.ACCOUNTNUM=b.ACCOUNTNUM
      WHERE b.DATAAREAID=? AND $policy
    ), keys_ AS (
      SELECT DATAAREAID, MODELNUM, BPC_BUDGETNO, DIMENSION, DIMENSION2_, DIMENSION3_, COUNT(*) AS selected_count
      FROM chosen GROUP BY DATAAREAID, MODELNUM, BPC_BUDGETNO, DIMENSION, DIMENSION2_, DIMENSION3_
    ), scoped_keys AS (
      SELECT k.*, COUNT(b.RECID) AS total_count
      FROM keys_ k JOIN dbo.LEDGERBUDGET b ON ".self::tuple_join('b', 'k').'
      GROUP BY k.DATAAREAID, k.MODELNUM, k.BPC_BUDGETNO, k.DIMENSION, k.DIMENSION2_, k.DIMENSION3_, k.selected_count
    )', array_merge([$id_xml, $company], $params)];
    }

    private static function tuple_join(string $left, string $right, bool $po = false): string
    {
        $parts = [];
        foreach (['DATAAREAID', 'MODELNUM', 'BPC_BUDGETNO', 'DIMENSION', 'DIMENSION2_', 'DIMENSION3_'] as $field) {
            $left_field = $po && $field === 'MODELNUM' ? 'BPC_MODELNUM' : $field;
            $parts[] = "$left.$left_field=$right.$field";
        }

        return implode(' AND ', $parts);
    }

    /** Header column filters available per activity kind (owner 2026-09-14). */
    public static function activity_filter_keys(string $kind): array
    {
        return $kind === 'purchases' ? ['date', 'title', 'po', 'supplier', 'amount', 'status'] : ['date', 'title', 'amount'];
    }

    /**
     * Wrap matched entries with the display values used by header filters.
     * Values are compared as bound parameters; keys come only from activity_filter_keys().
     */
    private function filtered_sql(string $matched, array $params, string $kind, string $company, array $filters): array
    {
        $po = $kind === 'purchases';
        $columns = [
            'date' => 'CONVERT(char(10), m.'.($po ? 'CREATEDDATETIME' : 'TRANSDATE').', 23)',
            'title' => 'LTRIM(RTRIM(COALESCE(m.'.($po ? 'NAME' : 'COMMENT_').", '')))",
            'amount' => 'CAST(CAST(m.'.($po ? 'LINEAMOUNT' : 'AMOUNTMST').' AS decimal(38,2)) AS varchar(40))',
        ];
        if ($po) {
            $columns['po'] = "LTRIM(RTRIM(COALESCE(m.PURCHID, '')))";
            $columns['supplier'] = "LTRIM(RTRIM(COALESCE(p.PURCHNAME, '')))";
            $columns['status'] = 'CAST(m.PURCHSTATUS AS varchar(4))';
        }
        $select = implode(', ', array_map(fn ($key, $expr) => "$expr AS f_$key", array_keys($columns), $columns));
        $join = $po ? 'LEFT JOIN dbo.PURCHTABLE p ON p.DATAAREAID=? AND p.PURCHID=m.PURCHID' : '';
        $sql = "$matched, filtered AS (SELECT m.*, $select FROM matched m $join)";
        $fparams = $po ? array_merge($params, [$company]) : $params;
        $where = [];
        foreach ($filters as $key => $values) {
            if (! isset($columns[$key]) || ! $values) {
                continue;
            }
            $plain = array_values(array_filter($values, fn ($v) => $v !== '—'));
            $parts = [];
            if ($plain) {
                $parts[] = "f_$key IN (".implode(',', array_fill(0, count($plain), '?')).')';
                array_push($fparams, ...$plain);
            }
            if (in_array('—', $values, true)) {
                $parts[] = "f_$key = ''";
            }
            $where[] = '('.implode(' OR ', $parts).')';
        }

        return [$sql, $fparams, $where ? 'WHERE '.implode(' AND ', $where) : '', array_keys($columns)];
    }

    /** Distinct header-filter values across every matched entry (not only the current page). */
    public function activity_options(array $rows, string $kind, ?array $window = null): array
    {
        if (! $rows) {
            return [];
        }
        $companies = array_values(array_unique(array_column($rows, 'company')));
        if (count($companies) !== 1) {
            throw new RuntimeException('SELECT_ONE_COMPANY_DEPARTMENT');
        }
        [, $params, $matched] = $this->matched_sql($rows, $companies[0], $kind, $window);
        [$sql, $fparams, , $keys] = $this->filtered_sql($matched, $params, $kind, $companies[0], []);
        $grouping = implode(', ', array_map(fn ($key) => "GROUPING(f_$key) AS g_$key", $keys));
        $sets = implode(', ', array_map(fn ($key) => "(f_$key)", $keys));
        $status = [1 => ['ค้างส่งของ', 'Open order'], 2 => ['รับของแล้ว', 'Received'], 3 => ['ลงใบแจ้งหนี้แล้ว', 'Invoiced'], 4 => ['ยกเลิก', 'Canceled']];
        $out = array_fill_keys($keys, []);
        foreach ($this->select("$sql SELECT $grouping, ".implode(', ', array_map(fn ($key) => "f_$key", $keys))." FROM filtered GROUP BY GROUPING SETS ($sets)", $fparams) as $r) {
            foreach ($keys as $key) {
                if ((int) $r->{"g_$key"} !== 0) {
                    continue;
                }
                $value = trim((string) $r->{"f_$key"});
                [$th, $en] = match (true) {
                    $value === '' => ['—', '—'],
                    $key === 'amount' => [number_format((float) $value, 2), number_format((float) $value, 2)],
                    $key === 'status' => $status[(int) $value] ?? [$value, $value],
                    default => [$value, $value],
                };
                $out[$key][] = ['v' => $value === '' ? '—' : $value, 't' => $th, 'e' => $en, 's' => $key === 'amount' ? (float) $value : $th];
            }
        }
        foreach ($out as &$list) {
            usort($list, fn ($a, $b) => is_float($a['s']) ? $a['s'] <=> $b['s'] : strcoll((string) $a['s'], (string) $b['s']));
            $list = array_map(fn ($o) => ['v' => $o['v'], 't' => $o['t'], 'e' => $o['e']], $list);
        }
        unset($list);

        return $out;
    }

    /**
     * Matched activity SQL shared by the entry list and its header-filter options.
     *
     * $window = ช่วงวันที่จ่ายจริง [from, to) ของแท็บ "ค่าใช้จ่ายตามวันที่" — ใช้กับรายการตัดงบเท่านั้น
     * 🔴 ส่งวันที่เป็นรูปแบบ Ymd (ไม่มีขีด) เพราะ SQL Server แปลง 'Y-m-d' เป็น datetime
     *    ตามค่า DATEFORMAT ของ session ได้ — รูปแบบ Ymd ตีความเหมือนกันทุกการตั้งค่า
     *
     * @return array{0:string,1:array,2:string,3:string,4:string,5:array}
     */
    private function matched_sql(array $rows, string $company, string $kind, ?array $window = null): array
    {
        [$cte, $params] = $this->cte($rows, $company);
        $when = '';
        $wparams = [];
        if ($window !== null && $kind !== 'purchases') {
            $when = ' AND t.TRANSDATE >= ? AND t.TRANSDATE < ?';
            $wparams = [str_replace('-', '', $window['from']), str_replace('-', '', $window['to'])];
        }
        // Follow the matched allocation, including direct references with legacy transaction dimensions.
        $origin = static fn (string $alias) => "$alias.MODELNUM AS budget_model, $alias.BPC_BUDGETNO AS budget_no, $alias.DIMENSION AS budget_dept, $alias.DIMENSION3_ AS budget_period";
        $direct_origin = $origin('b');
        $tuple_origin = $origin('k');
        if ($kind === 'purchases') {
            $from = 'FROM dbo.PURCHLINE t JOIN scoped_keys k ON '.self::tuple_join('t', 'k', true);
            $matched = "$cte, matched AS (SELECT t.RECID, t.PURCHID, t.LINENUM, t.ITEMID, t.NAME,
        t.PURCHQTY, t.PURCHUNIT, t.LINEAMOUNT, t.CURRENCYCODE, t.CREATEDDATETIME, t.PURCHSTATUS,
        t.REQUISITIONER, t.CREATEDBY, t.PURCHREQID, t.BPC_PURCHASEREQNO, $tuple_origin
        $from WHERE k.selected_count=k.total_count)";
            $amount = 'LINEAMOUNT';
            $ambiguous_sql = "$cte SELECT COUNT(*) AS n $from WHERE k.selected_count<k.total_count";
            $matched_params = $ambiguous_params = $params;
        } else {
            $columns = 't.RECID, t.TRANSDATE, t.COMMENT_, t.ITEMID, t.QTY, t.AMOUNTMST, t.CURRENCY, t.PURCHID, t.PURCHREQID, t.INVOICEID, t.VOUCHER, t.JOURNALNUM, t.REFLEDGERBUDGETRECID';
            $join = self::tuple_join('t', 'k');
            $matched = "$cte, matched AS (
        SELECT $columns, $direct_origin, 1 AS direct_match FROM dbo.BPC_LEDGERBUDGETTRANS t JOIN chosen b
          ON b.DATAAREAID=t.DATAAREAID AND b.RECID=t.REFLEDGERBUDGETRECID
          WHERE t.STATUS=2 AND t.CANCEL=0$when
        UNION ALL
        SELECT $columns, $tuple_origin, 0 AS direct_match FROM dbo.BPC_LEDGERBUDGETTRANS t JOIN scoped_keys k ON $join
          WHERE t.STATUS=2 AND t.CANCEL=0 AND t.REFLEDGERBUDGETRECID=0 AND k.selected_count=k.total_count$when
      )";
            $amount = 'AMOUNTMST';
            $ambiguous_sql = "$cte SELECT COUNT(*) AS n FROM dbo.BPC_LEDGERBUDGETTRANS t JOIN scoped_keys k ON $join
        WHERE t.STATUS=2 AND t.CANCEL=0 AND t.REFLEDGERBUDGETRECID=0 AND k.selected_count<k.total_count$when";
            // ช่วงวันที่ใช้ 2 ครั้งใน matched (2 ทางของการจับคู่) และ 1 ครั้งใน ambiguous
            $matched_params = array_merge($params, $wparams, $wparams);
            $ambiguous_params = array_merge($params, $wparams);
        }

        return [$cte, $matched_params, $matched, $amount, $ambiguous_sql, $ambiguous_params];
    }

    public function activity(array $rows, string $kind, int $page = 1, string $sort = 'date_desc', array $column_filters = [], ?array $window = null): array
    {
        $empty = ['rows' => [], 'count' => 0, 'total' => 0, 'currency_totals' => [], 'page' => 1, 'pages' => 1, 'ambiguous' => 0, 'direct' => 0, 'fallback' => 0];
        if (! $rows) {
            return $empty;
        }
        $companies = array_values(array_unique(array_column($rows, 'company')));
        if (count($companies) !== 1) {
            throw new RuntimeException('SELECT_ONE_COMPANY_DEPARTMENT');
        }
        [, $params, $matched, $amount, $ambiguous_sql, $ambiguous_params] = $this->matched_sql($rows, $companies[0], $kind, $window);
        // Header column filters narrow counts, totals and paging together; ambiguity stays unfiltered.
        [$filtered, $fparams, $where] = $this->filtered_sql($matched, $params, $kind, $companies[0], $column_filters);
        // Whitelisted SQL ordering applies to all matched rows before OFFSET pagination.
        $date_column = $kind === 'purchases' ? 'CREATEDDATETIME' : 'TRANSDATE';
        $order = match ($sort) {
            'date_asc' => "$date_column ASC, RECID ASC",
            'amount_desc' => "$amount DESC, $date_column DESC, RECID DESC",
            'amount_asc' => "$amount ASC, $date_column DESC, RECID DESC",
            default => "$date_column DESC, RECID DESC",
        };
        // PO amounts in different currencies are ordered separately, never compared as equivalent money.
        if ($kind === 'purchases' && in_array($sort, ['amount_desc', 'amount_asc'], true)) {
            $order = 'CURRENCYCODE ASC, '.$order;
        }
        // Totals are independent of the paginated rows; footer never copies header Actual.
        $currency_totals = [];
        $page = max(1, $page);
        $rows_sql = fn (string $extra, int $offset) => "$filtered SELECT *$extra FROM filtered $where ORDER BY $order OFFSET $offset ROWS FETCH NEXT 40 ROWS ONLY";
        if ($kind === 'purchases') {
            // PO แยกยอดตามสกุลเงิน จึงต้องรวมยอดเป็นคิวรีของตัวเอง (ห้ามบวกข้ามสกุล)
            $summary = (object) ['n' => 0, 'amount' => null];
            foreach ($this->select("$filtered SELECT CURRENCYCODE, COUNT(*) AS n, SUM(LINEAMOUNT) AS amount FROM filtered $where GROUP BY CURRENCYCODE", $fparams) as $currency) {
                $summary->n += (int) $currency->n;
                $currency_totals[trim($currency->CURRENCYCODE)] = self::satang((string) $currency->amount);
            }
            $count = (int) $summary->n;
            $pages = max(1, (int) ceil($count / 40));
            $page = min($page, $pages);
            $items = $this->select($rows_sql('', ($page - 1) * 40), $fparams);
        } else {
            /*
              🔴 นับยอดรวมพร้อมดึงรายการในคิวรีเดียว (เจ้าของแจ้งว่าหน้าโหลดนาน 2026-09-17)
                 ของเดิมยิงงานจับคู่รายการกับถังงบซ้ำ 2 รอบ — วัดจริง นับ 2.75 วิ + ดึงรายการ 1.37 วิ
                 COUNT/SUM OVER() คิดจาก "ทุกแถวที่ผ่านตัวกรอง" ไม่ใช่แค่ 40 แถวในหน้า (OFFSET ทำทีหลัง)
            */
            $totals = ', COUNT(*) OVER() AS total_n, COALESCE(SUM(AMOUNTMST) OVER(), 0) AS total_amount, SUM(direct_match) OVER() AS total_direct';
            $items = $this->select($rows_sql($totals, ($page - 1) * 40), $fparams);
            $count = (int) ($items[0]->total_n ?? 0);
            // เลขหน้าเกินของจริง (ลิงก์เก่า/กดย้อน) — หน้าว่างแบบนี้ต้องรู้ให้ได้ว่ามีกี่แถว แล้วถอยมาหน้าสุดท้าย
            if (! $items && $page > 1) {
                $count = (int) $this->select("$filtered SELECT COUNT(*) AS n FROM filtered $where", $fparams)[0]->n;
                if ($count) {
                    $page = (int) ceil($count / 40);
                    $items = $this->select($rows_sql($totals, ($page - 1) * 40), $fparams);
                }
            }
            $summary = (object) ['n' => $count, 'amount' => $items[0]->total_amount ?? 0, 'direct_count' => $items[0]->total_direct ?? 0];
            $pages = max(1, (int) ceil($count / 40));
            $page = min($page, $pages);
        }
        $ambiguous = (int) $this->select($ambiguous_sql, $ambiguous_params)[0]->n;
        $result = [];
        foreach ($items as $r) {
            $po = $kind === 'purchases';
            $result[] = [
                'id' => (string) $r->RECID,
                'company' => $companies[0],
                'budget_model' => trim($r->budget_model),
                'budget_no' => trim($r->budget_no),
                'budget_period' => trim($r->budget_period),
                'budget_group' => self::group_key(['company' => $companies[0], 'dept' => trim($r->budget_dept), 'model' => trim($r->budget_model), 'budget_no' => trim($r->budget_no)]),
                'date' => substr((string) ($po ? $r->CREATEDDATETIME : $r->TRANSDATE), 0, 10),
                'title' => trim((string) ($po ? $r->NAME : $r->COMMENT_)),
                'item' => trim($r->ITEMID), 'qty' => (float) ($po ? $r->PURCHQTY : $r->QTY),
                'amount' => self::satang((string) $r->$amount),
                'currency' => $po ? trim($r->CURRENCYCODE) : 'THB',
                'po' => trim($r->PURCHID),
                'pr' => trim($r->PURCHREQID) ?: ($po ? trim($r->BPC_PURCHASEREQNO) : ''),
                'voucher' => $po ? '' : trim($r->VOUCHER), 'invoice' => $po ? '' : trim($r->INVOICEID),
                'journal' => $po ? '' : trim($r->JOURNALNUM), 'line' => $po ? (string) $r->LINENUM : '',
                'requester' => $po ? trim($r->REQUISITIONER) : '',
                'creator' => $po ? trim($r->CREATEDBY) : '',
                'status' => $po ? (int) $r->PURCHSTATUS : null,
                'unit' => $po ? trim($r->PURCHUNIT) : '',
                'direct' => $po ? null : (bool) $r->direct_match,
            ];
        }
        if ($kind === 'purchases') {
            $result = $this->suppliers($result, $companies[0]);
            $result = $this->people($result, $companies[0]);
        }

        return ['rows' => $result, 'count' => $count, 'total' => $kind === 'purchases' ? null : self::satang((string) $summary->amount),
            'currency_totals' => $currency_totals,
            'page' => $page, 'pages' => $pages, 'ambiguous' => $ambiguous,
            'direct' => (int) ($summary->direct_count ?? 0),
            'fallback' => $kind === 'purchases' ? 0 : $count - (int) ($summary->direct_count ?? 0)];
    }

    /*
      ═══════════ ค่าใช้จ่ายตามวันที่ (เจ้าของสั่ง 2026-09-17 · DECISIONS 50.11) ═══════════

      แท็บ "ค่าใช้จ่ายตามวันที่" นับเงินชุดเดียวกับแท็บ "งบประมาณตามงวดงบ" ทุกบาท
      ต่างกันแค่แกนเวลา: แท็บงวดงบแบ่งตามถัง (BG26Q1) · แท็บนี้แบ่งตาม "วันที่จ่ายจริง" (TRANSDATE)

      🔴 ขอบเขตมาจากถังงบที่ผ่านตัวกรองแล้ว (บริษัท · ปีงบ · แผนก) เหมือนแท็บงวดงบ
         **แล้วตัดด้วยวันที่จ่ายให้อยู่ในปีนั้นอีกชั้น** (เจ้าของสั่ง 2026-09-17: "ปี 2025 ต้องมีแค่ 2025")
      🔴 เงินของถังปีหนึ่งจ่ายจริงในปีถัดไปได้ (ถัง BG25 จ่ายในปี 2026 = 761 รายการ)
         **รายการพวกนี้ไม่ขึ้นในแท็บนี้เลย** เพราะแท็บนี้ตอบคำถามว่า "ปีนั้นจ่ายเงินออกไปเท่าไร"
         ผลที่ตามมาโดยตั้งใจ: ยอดทั้งปีของแท็บนี้ **ไม่เท่ากับ "ใช้ไป" ของแท็บงวดงบ**
         (ตรวจ ERP สด 2026-09-17: ปีงบ 2025 แท็บนี้ 1,439,968,137.40 · แท็บงวดงบ 1,469,915,515.00)
      🔴 งบเงินเดือน/ค่าแรงถูกตัดตั้งแต่ cte() — รายการที่ผูกกับถังเหล่านั้นไม่มีทางหลุดมา
      🔴 ยอดเป็นบาทจาก AMOUNTMST เสมอ — AMOUNT คือเงินสกุลต้นทาง (USD · CNY) ห้ามเอามาบวกกัน
    */

    /** ผลอ่านของถังงบชุดเดียวกัน — กันยิง query ซ้ำในคำขอเดียว (หน้าหลัก + หน้าเปรียบเทียบ) */
    private array $spend_cache = [];

    /**
     * ค่าใช้จ่ายของถังงบที่เลือก จัดกลุ่มเป็น แผนก × วันที่จ่าย
     * ใช้ทำกราฟ ตัวกรองไตรมาส/เดือน/วัน และตัวเลขเทียบช่วงก่อนหน้า
     *
     * @return array{days: list<array>, ambiguous: int}
     */
    public function spending(array $rows): array
    {
        $out = ['days' => [], 'ambiguous' => 0];
        $by = [];
        foreach ($rows as $row) {
            $by[$row['company']][] = $row;
        }
        ksort($by);
        foreach ($by as $company => $company_rows) {
            $ids = array_column($company_rows, 'id');
            sort($ids);
            $key = $company.'|'.sha1(implode(',', $ids));
            $this->spend_cache[$key] ??= $this->spending_of($company_rows, (string) $company);
            array_push($out['days'], ...$this->spend_cache[$key]['days']);
            $out['ambiguous'] += $this->spend_cache[$key]['ambiguous'];
        }

        return $out;
    }

    /** อ่านทีละบริษัท — ตัวจับคู่รายการกับถังงบรองรับบริษัทเดียวต่อครั้ง */
    private function spending_of(array $rows, string $company): array
    {
        [, $params, $matched, , $ambiguous_sql, $ambiguous_params] = $this->matched_sql($rows, $company, 'actual');
        // CONVERT แบบ 112 = YYYYMMDD ไม่ขึ้นกับภาษา/รูปแบบวันที่ของ session
        $sql = "$matched, dated AS (
      SELECT budget_dept, AMOUNTMST, CONVERT(char(8), TRANSDATE, 112) AS paid
      FROM matched
    )
    SELECT budget_dept, paid, COUNT(*) AS n, SUM(AMOUNTMST) AS amount
    FROM dated
    GROUP BY budget_dept, paid";
        $days = [];
        foreach ($this->select($sql, $params) as $r) {
            $dept = trim((string) $r->budget_dept);
            $paid = (string) $r->paid;
            $days[] = ['company' => $company, 'dept' => $dept, 'dept_key' => self::department_key($company, $dept, ''),
                'date' => substr($paid, 0, 4).'-'.substr($paid, 4, 2).'-'.substr($paid, 6, 2),
                'n' => (int) $r->n, 'amount' => self::satang((string) $r->amount)];
        }

        return ['days' => $days,
            'ambiguous' => (int) ($this->select($ambiguous_sql, $ambiguous_params)[0]->n ?? 0)];
    }

    /**
     * ช่วงวันที่จ่ายที่เลือก — ละเอียดสุดชนะ: วัน > เดือน > ไตรมาส · null = ไม่จำกัดวันที่
     *
     * @return array{level:string,key:string,from:string,to:string}|null
     *                                                                   from = วันแรกของช่วง · to = วันถัดจากวันสุดท้าย (ไม่รวม)
     */
    public static function paid_window(array $filters): ?array
    {
        $day = (string) ($filters['paid_d'] ?? '');
        $month = (string) ($filters['paid_m'] ?? '');
        $quarter = (string) ($filters['paid_q'] ?? '');

        // 🔴 เช็ควันที่มีจริงด้วย ไม่ใช่แค่รูปแบบ — 2026-02-30 ต้องไม่ผ่าน
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) ? DateTimeImmutable::createFromFormat('!Y-m-d', $day) : false;
        if ($date && $date->format('Y-m-d') === $day) {
            return ['level' => 'day', 'key' => $day, 'from' => $day, 'to' => $date->modify('+1 day')->format('Y-m-d')];
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            $start = new DateTimeImmutable($month.'-01');

            return ['level' => 'month', 'key' => $month, 'from' => $start->format('Y-m-d'), 'to' => $start->modify('+1 month')->format('Y-m-d')];
        }
        if (preg_match('/^(\d{4})-([1-4])$/D', $quarter, $m)) {
            $start = new DateTimeImmutable(sprintf('%s-%02d-01', $m[1], ((int) $m[2] - 1) * 3 + 1));

            return ['level' => 'quarter', 'key' => $quarter, 'from' => $start->format('Y-m-d'), 'to' => $start->modify('+3 months')->format('Y-m-d')];
        }

        return null;
    }

    /**
     * ช่วงก่อนหน้าในระดับเดียวกัน — ใช้คิด DoD / MoM / QoQ
     * ข้ามปีได้เสมอ: 1 ม.ค. → 31 ธ.ค. ปีก่อน · Q1 → Q4 ปีก่อน
     */
    public static function previous_window(array $window): array
    {
        $from = new DateTimeImmutable($window['from']);

        return match ($window['level']) {
            'day' => self::paid_window(['paid_d' => $from->modify('-1 day')->format('Y-m-d')]),
            'month' => self::paid_window(['paid_m' => $from->modify('-1 month')->format('Y-m')]),
            default => self::paid_window(['paid_q' => self::quarter_key($from->modify('-3 months')->format('Y-m-d'))]),
        };
    }

    /** '2026-05-02' → '2026-2' (ปีบัญชีของ ERP = ปีปฏิทิน ตรวจจาก LEDGERPERIOD แล้ว) */
    public static function quarter_key(string $date): string
    {
        return substr($date, 0, 4).'-'.(intdiv((int) substr($date, 5, 2) - 1, 3) + 1);
    }

    /**
     * ช่วง "ทั้งปีงบ" ของแท็บค่าใช้จ่าย = 1 ม.ค. – 31 ธ.ค. ของปีนั้น
     *
     * 🔴 เจ้าของสั่ง 2026-09-17: **แท็บนี้นับเฉพาะรายการที่จ่ายภายในปีที่เลือกเท่านั้น**
     *    รายการที่จ่ายข้ามปี (จ่าย ก.พ. 2026 แต่ไปตัดถัง BG25Q4) **ไม่เอามาแสดงในปี 2025**
     *    ผลที่ตามมาโดยตั้งใจ: ยอดทั้งปีของแท็บนี้จึงไม่เท่ากับ "ใช้ไป" ของแท็บงวดงบ
     *    เพราะแท็บงวดงบนับตามถัง (รวมที่จ่ายปีถัดไป) ส่วนแท็บนี้นับตามวันที่จ่าย
     *
     * @return array{level:string,key:string,from:string,to:string}|null
     */
    public static function year_window(string $year): ?array
    {
        if (! preg_match('/^\d{4}$/D', $year)) {
            return null;
        }

        return ['level' => 'year', 'key' => $year, 'from' => $year.'-01-01', 'to' => ((int) $year + 1).'-01-01'];
    }

    /** ตัดรายการที่อยู่นอกช่วงทิ้งตั้งแต่ต้น เพื่อให้ทั้งตัวเลือกตัวกรองและยอดเดินตามช่วงเดียวกัน */
    public static function spend_within(array $days, ?array $window): array
    {
        if ($window === null) {
            return $days;
        }

        return array_values(array_filter($days, fn ($d) => $d['date'] >= $window['from'] && $d['date'] < $window['to']));
    }

    /** @return array{n:int, amount:int} ผลรวมค่าใช้จ่ายในช่วงวันที่ที่เลือก */
    public static function spend_total(array $days, ?array $window): array
    {
        $total = ['n' => 0, 'amount' => 0];
        foreach ($days as $d) {
            if ($window !== null && ($d['date'] < $window['from'] || $d['date'] >= $window['to'])) {
                continue;
            }
            $total['n'] += $d['n'];
            $total['amount'] += $d['amount'];
        }

        return $total;
    }

    /** ค่าใช้จ่ายแยกแผนกในช่วงวันที่ที่เลือก เรียงมากไปน้อย · คีย์ = dept_key */
    public static function spend_by_dept(array $days, ?array $window): array
    {
        $out = [];
        foreach ($days as $d) {
            if ($window !== null && ($d['date'] < $window['from'] || $d['date'] >= $window['to'])) {
                continue;
            }
            $out[$d['dept_key']] ??= ['n' => 0, 'amount' => 0];
            $out[$d['dept_key']]['n'] += $d['n'];
            $out[$d['dept_key']]['amount'] += $d['amount'];
        }
        uasort($out, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $out;
    }

    /**
     * ปฏิทินของช่วงที่ "มีรายการจ่ายจริง" ทั้งปี — ใช้เติมช่องเลือกในหน้าต่างเปรียบเทียบ
     *
     * 🔴 ต่างจาก spend_periods() ตรงที่ส่ง "วันแยกตามเดือน" มาทั้งก้อน
     *    หน้าต่างเปรียบเทียบจึงเปลี่ยนไตรมาส/เดือนได้ทันทีโดยไม่ต้องถามเซิร์ฟเวอร์ซ้ำ
     *
     * @return array{quarters: list<string>, months: list<string>, days: array<string, list<string>>}
     */
    public static function spend_calendar(array $days): array
    {
        $q = $m = $d = [];
        foreach ($days as $row) {
            $q[self::quarter_key($row['date'])] = true;
            $month = substr($row['date'], 0, 7);
            $m[$month] = true;
            $d[$month][$row['date']] = true;
        }
        ksort($q);
        ksort($m);
        ksort($d);
        foreach ($d as $month => $dates) {
            $keys = array_keys($dates);
            sort($keys);
            $d[$month] = $keys;
        }

        return ['quarters' => array_keys($q), 'months' => array_keys($m), 'days' => $d];
    }

    /**
     * ไตรมาส / เดือน / วัน ที่มีรายการจริง — ตัวเลือกของตัวกรองในแท็บค่าใช้จ่าย
     * 🔴 แคบลงทีละชั้นเหมือนตัวกรองอื่นของหน้า: เดือนคิดจากไตรมาสที่เลือก · วันคิดจากเดือนที่เลือก
     *    ไม่มีข้อมูลเดือนไหน ก็ไม่มีเดือนนั้นให้เลือก — ไม่ใช่เลือกได้แล้วเจอจอว่าง
     * 🔴 ช่องวันมีให้เลือกเสมอ (เจ้าของสั่ง 2026-09-17) — ยังไม่เลือกเดือนก็เลือกวันได้เลย
     *    รายการวันจึงกว้างตามชั้นที่เลือกไว้: เลือกเดือน = วันในเดือนนั้น · เลือกแค่ไตรมาส = วันในไตรมาส · ไม่เลือกเลย = ทั้งปีงบ
     *
     * @return array{quarters: list<string>, months: list<string>, days: list<string>}
     */
    public static function spend_periods(array $days, string $quarter = '', string $month = ''): array
    {
        $q = $m = $d = [];
        foreach ($days as $row) {
            $qk = self::quarter_key($row['date']);
            $q[$qk] = true;
            if ($quarter !== '' && $qk !== $quarter) {
                continue;
            }
            $mk = substr($row['date'], 0, 7);
            $m[$mk] = true;
            if ($month === '' || $mk === $month) {
                $d[$row['date']] = true;
            }
        }
        $sorted = function (array $set): array {
            $keys = array_map('strval', array_keys($set));
            sort($keys);

            return $keys;
        };

        return ['quarters' => $sorted($q), 'months' => $sorted($m), 'days' => $sorted($d)];
    }

    private function people(array $rows, string $company): array
    {
        if (! $rows) {
            return [];
        }
        $codes = array_values(array_unique(array_filter(array_merge(array_column($rows, 'requester'), array_column($rows, 'creator')))));
        $users = $employees = [];
        if ($codes) {
            $marks = implode(',', array_fill(0, count($codes), '?'));
            foreach ($this->select("SELECT ID, NAME FROM dbo.USERINFO WHERE ID IN ($marks)", $codes) as $u) {
                $users[trim($u->ID)] = trim($u->NAME);
            }
            foreach ($this->select("SELECT e.EMPLID, p.NAME FROM dbo.EMPLTABLE e LEFT JOIN dbo.DIRPARTYTABLE p
        ON p.DATAAREAID=e.DATAAREAID AND p.PARTYID=e.PARTYID
        WHERE e.DATAAREAID=? AND e.EMPLID IN ($marks)", array_merge([$company], $codes)) as $e) {
                $employees[trim($e->EMPLID)] = trim((string) $e->NAME);
            }
        }
        foreach ($rows as &$row) {
            $row['requester_name'] = ($employees[$row['requester']] ?? '') ?: ($users[$row['requester']] ?? '');
            $row['creator_name'] = $users[$row['creator']] ?? '';
        }

        return $rows;
    }
}
