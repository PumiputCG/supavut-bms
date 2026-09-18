<?php

namespace Tests\Unit;

use App\Services\Erp\ErpBudget;
use App\Services\Erp\ErpDashboard;
use App\Support\TableFilter;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ErpDashboardFixture as Fixture;

class ErpDashboardTest extends TestCase
{
    public function test_only_explicit_consistent_period_codes_assign_a_year(): void
    {
        foreach ([['BG27', 'BG27Q1', 2027], ['BG26.', '', 2026], ['BG26', 'BG27Q1', null],
            ['AC-26-001', 'NON', null], ['MOLD-26-27', '', null], ['PROJECT', 'BG25Q4', 2025], ['', '', null]] as [$m,$p,$expected]) {
            $this->assertSame($expected, ErpDashboard::budget_year($m, $p));
        }
    }

    public function test_money_totals_preserve_credits_and_report_formula_differences(): void
    {
        $rows = [array_fill_keys(['budget', 'actual', 'reserve', 'available'], 0)];
        $rows[0] = ['budget' => ErpDashboard::satang('1000.01'), 'actual' => ErpDashboard::satang('-5.10'),
            'reserve' => ErpDashboard::satang('0.11'), 'available' => ErpDashboard::satang('1005.00')];
        $this->assertSame(0, ErpDashboard::totals($rows)['difference']);
        $rows[0]['available'] += 12;
        $this->assertSame(-12, ErpDashboard::totals($rows)['difference']);
        $this->assertSame(-510, ErpDashboard::totals($rows)['actual']);
    }

    public function test_money_rounding_does_not_lose_decimal_precision(): void
    {
        $this->assertSame(12345678901234568, ErpDashboard::satang('123456789012345.675'));
        $this->assertSame(-101, ErpDashboard::satang('-1.005'));
        $this->assertSame(0, ErpDashboard::satang('.000000'));
        $this->assertSame(1, ErpDashboard::satang('.005'));
    }

    public function test_grouping_does_not_merge_companies_models_or_lose_duplicate_allocations(): void
    {
        $rows = Fixture::rows();
        $groups = ErpDashboard::groups($rows, 'group');
        $this->assertCount(5, $groups);
        $this->assertCount(2, $groups[0]['rows']);
        $this->assertSame(20000000, $groups[0]['total']['budget']);
        $this->assertCount(1, ErpDashboard::filter($rows, ['year' => 'unassigned']));
        $this->assertCount(0, ErpDashboard::filter($rows, ['company' => 'pd', 'dept' => 'si5|SPV-02']));
    }

    /**
     * ยอดรวมของทั้งชุดต้องไม่ขึ้นกับหน้าที่เปิดอยู่
     * 🔴 ตั้งแต่ 2026-09-17 นับยอดมาพร้อมรายการในคิวรีเดียว (COUNT/SUM OVER) เพื่อไม่ยิงงานจับคู่ซ้ำ 2 รอบ
     *    เลขหน้าเกินของจริงถึงจะยิงคิวรีนับอีกตัวเพื่อถอยมาหน้าสุดท้าย
     */
    public function test_actual_total_is_independent_of_page_and_uses_disjoint_matches(): void
    {
        $service = $this->source([[], [(object) ['n' => 81]], [$this->erpActualRow()], [(object) ['n' => 2]]]);
        $result = $service->activity([Fixture::rows()[0]], 'actual', 999);
        $this->assertSame(123456, $result['total']);
        $this->assertSame(3, $result['page'], 'หน้า 999 ไม่มีจริง ต้องถอยมาหน้าสุดท้าย');
        $this->assertSame(2, $result['ambiguous']);
        $this->assertSame(1, $result['fallback']);
        $this->assertCount(1, $result['rows']);
        $sql = $service->queries[0][0];
        $this->assertStringContainsString('COUNT(*) OVER() AS total_n', $sql);
        $this->assertStringContainsString('SUM(AMOUNTMST) OVER()', $sql);
        $this->assertStringContainsString('b.RECID=t.REFLEDGERBUDGETRECID', $sql);
        $this->assertStringContainsString('b.MODELNUM AS budget_model', $sql);
        $this->assertStringContainsString('k.MODELNUM AS budget_model', $sql);
        $this->assertStringContainsString('t.REFLEDGERBUDGETRECID=0 AND k.selected_count=k.total_count', $sql);
        foreach (['DATAAREAID', 'MODELNUM', 'BPC_BUDGETNO', 'DIMENSION', 'DIMENSION2_', 'DIMENSION3_'] as $field) {
            $this->assertStringContainsString('t.'.$field.'=k.'.$field, $sql);
        }
        $this->assertStringContainsString('t.STATUS=2 AND t.CANCEL=0', $sql);
        $this->assertStringContainsString('SELECT COUNT(*) AS n FROM filtered', $service->queries[1][0], 'หน้าว่าง = ถามจำนวนแถวก่อนถอยหน้า');
        $this->assertStringContainsString('OFFSET 80 ROWS FETCH NEXT 40 ROWS ONLY', $service->queries[2][0]);
        $this->assertStringContainsString('k.selected_count<k.total_count', $service->queries[3][0]);
        $this->assertSame('<ids><id>101</id></ids>', $service->queries[0][1][0]);
        $this->assertStringNotContainsString('101', $sql);
        $this->assertContains('%[^a-z]salary[^a-z]%', $service->queries[0][1]);
        $this->assertContains('%[- ]เงินเดือน%', $service->queries[0][1]);
    }

    public function test_departments_keep_foreign_currency_out_of_thb_totals(): void
    {
        $rows = array_slice(Fixture::rows(), 0, 2);
        $rows[1]['currency'] = 'JPP';
        $departments = ErpDashboard::departments($rows);
        $this->assertCount(1, $departments);
        $this->assertSame(10000000, $departments[0]['total']['budget']);
        $this->assertSame(1, $departments[0]['total']['count']);
        $this->assertSame(['JPP'], array_keys($departments[0]['foreign']));
        $this->assertSame(10000000, $departments[0]['foreign']['JPP']['budget']);
        $this->assertSame('JPY', ErpDashboard::currency_label('JPP'));
        $this->assertSame('USD (USP)', ErpDashboard::currency_label('USP'));
        $this->assertSame('EUR (EUP)', ErpDashboard::currency_label('EUP'));
        $this->assertSame('USD', ErpDashboard::currency_label('USD'));
    }

    public function test_payroll_policy_hides_employee_pay_but_keeps_look_alike_budgets(): void
    {
        // Real ERP names checked on 2026-09-14.
        foreach (['HR-Salary and Benefits', 'MO-Salary and Benefits -Direct Production', 'MT-Overtime-Indirect Production',
            'Bonus-Office', 'Bonus factory-Direct Production', 'Bonus - Sale', 'Commission', 'Monthly wages',
            'เงินเดือนพนักงาน', 'HR-ค่าล่วงเวลา', 'โบนัสประจำปี'] as $name) {
            $this->assertTrue(ErpDashboard::is_payroll($name), $name);
        }
        foreach (['STOWAGE BOX', 'N1WBE16G041AAW:STOWAGE BOX', 'Box Stowage Puuching  Machine', 'ค่าจ้างทำ Tooling package 1',
            'ค่าจ้างทำ Packaging (Returnable box)', 'โปรแกรมเงินเดือน', 'Social Security Fund', 'Provident Fund',
            'Activity &Welfare for Staff', "Workmen's compensation fund", 'Budget control', ''] as $name) {
            $this->assertFalse(ErpDashboard::is_payroll($name), $name);
        }
        $this->assertTrue(ErpDashboard::is_payroll('Training', 'Bonus-Office'));
    }

    public function test_payroll_buckets_are_recognised_by_budget_number(): void
    {
        // Real ERP budget numbers checked on 2026-09-14; their titles only say "Budget control".
        foreach (['HR-DLAdmin', 'MO-OTDirect', 'QC-DLIndirect', 'LG-OTSale', 'PDP-DLDirect', 'Lean-OTIndirect', 'hr-dladmin '] as $no) {
            $this->assertTrue(ErpDashboard::is_payroll_budget_no($no), $no);
        }
        foreach (['HR-531103', 'MO-516001', 'AC-26-001', 'NM24002TR1', 'SPV-15', 'LG-DLSale-2', 'HR-OTHER', ''] as $no) {
            $this->assertFalse(ErpDashboard::is_payroll_budget_no($no), $no);
        }
    }

    public function test_payroll_policy_checks_title_and_account_before_money_is_read(): void
    {
        $service = $this->source([[], []]);
        $service->snapshot();
        [$sql,$params] = $service->queries[0];
        $this->assertStringContainsString("CASE WHEN b.CURRENCY='THB' THEN b.AMOUNTMST ELSE b.AMOUNT END AS budget", $sql);
        $this->assertStringNotContainsString('b.BPC_BUDGET + b.BPC_TRANSFER', $sql);
        $patterns = ErpDashboard::payroll_patterns();
        $buckets = ErpDashboard::payroll_budget_patterns();
        $this->assertSame(['si5', 'pd'], array_slice($params, 0, 2));
        $this->assertCount(2 + 2 * count($patterns) + count($buckets), $params);
        $this->assertSame(2 * count($patterns) + count($buckets), substr_count($sql, 'NOT LIKE ?'));
        $this->assertStringContainsString("LTRIM(RTRIM(COALESCE(b.BPC_BUDGETNO, ''))) NOT LIKE ?", $sql);
        $this->assertContains('%-DLDirect', $params);
        $this->assertContains('%-OTAdmin', $params);
        $this->assertStringContainsString("(' ' + COALESCE(b.COMMENT_, '') + ' ') NOT LIKE ?", $sql);
        $this->assertStringContainsString("(' ' + COALESCE(a.ACCOUNTNAME, '') + ' ') NOT LIKE ?", $sql);
        $this->assertStringNotContainsString('%wage%', implode('|', $params));
    }

    public function test_header_filters_bind_values_in_sql_before_count_and_paging(): void
    {
        $service = $this->source([[(object) ['n' => 1, 'amount' => '10.00', 'CURRENCYCODE' => 'THB']], [], [(object) ['n' => 0]]]);
        $service->activity([Fixture::rows()[0]], 'purchases', 1, 'date_desc', ['supplier' => ['ACME'], 'status' => ['2', '—'], 'bogus' => ['x']]);
        [$summary,$params] = $service->queries[0];
        $this->assertStringContainsString('filtered AS (SELECT m.*', $summary);
        $this->assertStringContainsString('LEFT JOIN dbo.PURCHTABLE p ON p.DATAAREAID=? AND p.PURCHID=m.PURCHID', $summary);
        $this->assertStringContainsString("WHERE (f_supplier IN (?)) AND (f_status IN (?) OR f_status = '')", $summary);
        $this->assertStringNotContainsString('bogus', $summary);
        $this->assertSame(['si5', 'ACME', '2'], array_slice($params, -3));
        $this->assertStringContainsString('FROM filtered WHERE', $service->queries[1][0]);
        // ความกำกวมไม่เกี่ยวกับตัวกรองคอลัมน์ — ต้องไม่มีพารามิเตอร์ตัวกรองปน
        $this->assertNotContains('ACME', $service->queries[2][1]);
    }

    public function test_activity_options_come_from_grouping_sets_with_readable_labels(): void
    {
        $service = $this->source([[
            (object) ['g_date' => 0, 'g_title' => 1, 'g_amount' => 1, 'f_date' => '2026-07-01', 'f_title' => null, 'f_amount' => null],
            (object) ['g_date' => 1, 'g_title' => 0, 'g_amount' => 1, 'f_date' => null, 'f_title' => '', 'f_amount' => null],
            (object) ['g_date' => 1, 'g_title' => 1, 'g_amount' => 0, 'f_date' => null, 'f_title' => null, 'f_amount' => '1500.50'],
            (object) ['g_date' => 1, 'g_title' => 1, 'g_amount' => 0, 'f_date' => null, 'f_title' => null, 'f_amount' => '20.00'],
        ]]);
        $options = $service->activity_options([Fixture::rows()[0]], 'actual');
        $this->assertStringContainsString('GROUP BY GROUPING SETS ((f_date), (f_title), (f_amount))', $service->queries[0][0]);
        $this->assertSame([['v' => '2026-07-01', 't' => '2026-07-01', 'e' => '2026-07-01']], $options['date']);
        $this->assertSame([['v' => '—', 't' => '—', 'e' => '—']], $options['title']);
        $this->assertSame(['20.00', '1500.50'], array_column($options['amount'], 'v'));
        $this->assertSame(['20.00', '1,500.50'], array_column($options['amount'], 't'));
    }

    public function test_array_rows_filter_by_displayed_text(): void
    {
        $map = ['name' => ['value' => fn ($r) => $r['name']], 'amount' => ['value' => fn ($r) => number_format($r['amount'], 2), 'sort' => fn ($r) => $r['amount']]];
        $rows = [['name' => 'B', 'amount' => 1000], ['name' => '', 'amount' => 5], ['name' => 'A', 'amount' => 250]];
        $this->assertSame([['name' => 'A', 'amount' => 250]], TableFilter::filterRows($rows, ['name' => ['A']], $map));
        $this->assertSame([['name' => '', 'amount' => 5]], TableFilter::filterRows($rows, ['name' => ['—']], $map));
        $options = TableFilter::rowOptions($rows, $map);
        $this->assertSame(['A', 'B', '—'], array_column($options['name'], 'v'));
        $this->assertSame(['5.00', '250.00', '1,000.00'], array_column($options['amount'], 'v'));
    }

    public function test_purchase_currencies_are_never_added_together(): void
    {
        $service = $this->source([[(object) ['n' => 2, 'amount' => '100.25', 'CURRENCYCODE' => 'THB'],
            (object) ['n' => 1, 'amount' => '30.00', 'CURRENCYCODE' => 'USD']], [], [(object) ['n' => 0]]]);
        $result = $service->activity([Fixture::rows()[0]], 'purchases');
        $this->assertSame(3, $result['count']);
        $this->assertNull($result['total']);
        $this->assertSame(['THB' => 10025, 'USD' => 3000], $result['currency_totals']);
        $this->assertStringContainsString('GROUP BY CURRENCYCODE', $service->queries[0][0]);
    }

    public function test_cross_company_activity_is_refused_before_any_query(): void
    {
        $service = $this->source([]);
        $this->expectException(\RuntimeException::class);
        $service->activity([Fixture::rows()[0], Fixture::rows()[4]], 'actual');
    }

    public function test_supplier_lookup_is_company_scoped_and_keeps_po_amounts(): void
    {
        $po = (object) ['RECID' => '1', 'PURCHID' => 'PO-TEST', 'LINENUM' => 1, 'ITEMID' => 'ITEM', 'NAME' => 'Test item',
            'PURCHQTY' => 1, 'PURCHUNIT' => 'pcs', 'LINEAMOUNT' => '100.25', 'CURRENCYCODE' => 'THB',
            'CREATEDDATETIME' => '2026-09-14', 'PURCHSTATUS' => 1, 'REQUISITIONER' => '', 'CREATEDBY' => '',
            'PURCHREQID' => '', 'BPC_PURCHASEREQNO' => '', 'budget_model' => 'BG26', 'budget_no' => 'HR-531103',
            'budget_dept' => 'SPV-02', 'budget_period' => 'BG26Q1'];
        $service = $this->source([[(object) ['n' => 1, 'amount' => '100.25', 'CURRENCYCODE' => 'THB']],
            [$po], [(object) ['n' => 0]], [(object) ['PURCHID' => 'PO-TEST', 'PURCHNAME' => ' Test Supplier Ltd. ']]]);
        $result = $service->activity([Fixture::rows()[0]], 'purchases');
        $this->assertSame('Test Supplier Ltd.', $result['rows'][0]['supplier_name']);
        $this->assertSame(10025, $result['rows'][0]['amount']);
        $this->assertSame(['THB' => 10025], $result['currency_totals']);
        $this->assertSame(['si5', 'PO-TEST'], $service->queries[3][1]);
        $this->assertStringContainsString('WHERE DATAAREAID=? AND PURCHID IN (?)', $service->queries[3][0]);
    }

    public function test_erp_department_codes_stay_separate_even_when_names_match(): void
    {
        $modern = ErpDashboard::department_key('si5', 'SPV1-03', 'MFG/BM');
        $legacy = ErpDashboard::department_key('si5', 'E01', 'MFG/BM');
        $this->assertNotSame($modern, $legacy);
        $this->assertSame('si5|SPV1-03', $modern);
        $this->assertNotSame($modern, ErpDashboard::department_key('pd', 'SPV3-04', 'MFG/BM'));
        $rows = Fixture::rows();
        $rows[0]['dept_key'] = 'si5|SPV1-03';
        $rows[0]['dept_group'] = $modern;
        $rows[1]['dept_key'] = 'si5|E01';
        $rows[1]['dept_group'] = $legacy;
        $this->assertCount(1, ErpDashboard::filter($rows, ['dept' => $modern]));
        $this->assertCount(1, ErpDashboard::filter($rows, ['dept' => 'si5|E01']));
    }

    public function test_snapshot_uses_erp_description_and_falls_back_to_erp_code(): void
    {
        $base = ['DATAAREAID' => 'si5', 'RECID' => '101', 'MODELNUM' => 'BG26', 'BPC_BUDGETNO' => 'HR-531017',
            'COMMENT_' => 'Social Security Fund', 'DIMENSION' => 'SPV-02', 'DIMENSION2_' => 'NON', 'DIMENSION3_' => 'BG26Q1',
            'STARTDATE' => '2026-01-01', 'ACTIVE' => 1, 'STOP' => 0, 'CURRENCY' => 'THB',
            'budget' => '100.00', 'actual' => '20.00', 'reserve' => '10.00', 'available' => '70.00'];
        $service = $this->source([[(object) $base, (object) array_replace($base, ['RECID' => '102', 'DIMENSION' => 'E01'])],
            [(object) ['DATAAREAID' => 'si5', 'NUM' => 'SPV-02', 'DESCRIPTION' => 'HR : Human Resource']]]);
        $rows = $service->snapshot();
        $this->assertSame('HR : Human Resource', $rows[0]['dept_name']);
        $this->assertSame('si5|SPV-02', $rows[0]['dept_group']);
        $this->assertSame('E01', $rows[1]['dept_name']);
        $this->assertSame('si5|E01', $rows[1]['dept_group']);
        $this->assertSame(20000, ErpDashboard::totals($rows)['budget']);
    }

    public function test_sorting_is_applied_before_pagination_for_both_histories(): void
    {
        foreach (['actual', 'purchases'] as $kind) {
            foreach (['date_asc', 'amount_desc', 'amount_asc'] as $sort) {
                // actual รวมนับกับดึงรายการไว้ในคิวรีเดียว · purchases ยังนับแยกเพราะต้องแยกตามสกุลเงิน
                $service = $kind === 'actual'
                  ? $this->source([[$this->erpActualRow()], [(object) ['n' => 0]]])
                  : $this->source([[(object) ['n' => 81, 'amount' => 0, 'CURRENCYCODE' => 'THB']], [], [(object) ['n' => 0]]]);
                $service->activity([Fixture::rows()[0]], $kind, 2, $sort);
                $date = $kind === 'actual' ? 'TRANSDATE' : 'CREATEDDATETIME';
                $amount = $kind === 'actual' ? 'AMOUNTMST' : 'LINEAMOUNT';
                $order = $sort === 'date_asc' ? "$date ASC, RECID ASC" : "$amount ".($sort === 'amount_desc' ? 'DESC' : 'ASC').", $date DESC, RECID DESC";
                if ($kind === 'purchases' && $sort !== 'date_asc') {
                    $order = 'CURRENCYCODE ASC, '.$order;
                }
                $this->assertStringContainsString("ORDER BY $order OFFSET 40 ROWS FETCH NEXT 40 ROWS ONLY", $service->queries[$kind === 'actual' ? 0 : 1][0]);
            }
        }
    }

    /* ═══ ค่าใช้จ่ายตามวันที่ (เจ้าของสั่ง 2026-09-17) ═══ */

    public function test_paid_window_picks_the_finest_level_and_rejects_impossible_dates(): void
    {
        $this->assertSame(['level' => 'day', 'key' => '2026-05-02', 'from' => '2026-05-02', 'to' => '2026-05-03'],
            ErpDashboard::paid_window(['paid_q' => '2026-2', 'paid_m' => '2026-05', 'paid_d' => '2026-05-02']));
        $this->assertSame(['level' => 'month', 'key' => '2026-12', 'from' => '2026-12-01', 'to' => '2027-01-01'],
            ErpDashboard::paid_window(['paid_m' => '2026-12']));
        $this->assertSame(['level' => 'quarter', 'key' => '2026-4', 'from' => '2026-10-01', 'to' => '2027-01-01'],
            ErpDashboard::paid_window(['paid_q' => '2026-4']));
        $this->assertSame('2024-03-01', ErpDashboard::paid_window(['paid_d' => '2024-02-29'])['to'], 'ปีอธิกสุรทิน');
        foreach ([['paid_d' => '2026-02-30'], ['paid_m' => '2026-13'], ['paid_q' => '2026-5'], []] as $bad) {
            $this->assertNull(ErpDashboard::paid_window($bad));
        }
    }

    public function test_previous_window_crosses_year_boundaries(): void
    {
        $prev = fn (array $f) => ErpDashboard::previous_window(ErpDashboard::paid_window($f))['key'];
        $this->assertSame('2025-12-31', $prev(['paid_d' => '2026-01-01']));
        $this->assertSame('2024-02-29', $prev(['paid_d' => '2024-03-01']));
        $this->assertSame('2025-12', $prev(['paid_m' => '2026-01']));
        $this->assertSame('2026-02', $prev(['paid_m' => '2026-03']));
        $this->assertSame('2025-4', $prev(['paid_q' => '2026-1']));
        $this->assertSame('2026-2', $prev(['paid_q' => '2026-3']));
        $this->assertSame('2026-2', ErpDashboard::quarter_key('2026-06-30'));
        $this->assertSame('2026-3', ErpDashboard::quarter_key('2026-07-01'));
    }

    public function test_spend_totals_periods_and_departments_follow_the_window(): void
    {
        $days = [
            ['dept_key' => 'si5|A', 'date' => '2025-12-31', 'n' => 1, 'amount' => 100],
            ['dept_key' => 'si5|A', 'date' => '2026-01-01', 'n' => 2, 'amount' => 200],
            ['dept_key' => 'si5|B', 'date' => '2026-01-31', 'n' => 1, 'amount' => -50],
            ['dept_key' => 'si5|B', 'date' => '2026-04-01', 'n' => 3, 'amount' => 900],
        ];
        $jan = ErpDashboard::paid_window(['paid_m' => '2026-01']);
        $this->assertSame(['n' => 7, 'amount' => 1150], ErpDashboard::spend_total($days, null));
        // 🔴 ขอบบนไม่รวม — 1 ก.พ. ไม่อยู่ในเดือน ม.ค. · 31 ธ.ค. ก็ไม่อยู่
        $this->assertSame(['n' => 3, 'amount' => 150], ErpDashboard::spend_total($days, $jan));
        $this->assertSame(['si5|A' => ['n' => 2, 'amount' => 200], 'si5|B' => ['n' => 1, 'amount' => -50]], ErpDashboard::spend_by_dept($days, $jan));
        $this->assertSame(['si5|B', 'si5|A'], array_keys(ErpDashboard::spend_by_dept($days, null)), 'เรียงมากไปน้อย');

        $all = ErpDashboard::spend_periods($days);
        $this->assertSame(['2025-4', '2026-1', '2026-2'], $all['quarters']);
        $this->assertSame(['2025-12', '2026-01', '2026-04'], $all['months']);
        // 🔴 ช่องวันเลือกได้เสมอ (เจ้าของสั่ง 2026-09-17) — ไม่เลือกเดือน = เสนอทุกวันที่มีรายการ
        $this->assertSame(['2025-12-31', '2026-01-01', '2026-01-31', '2026-04-01'], $all['days']);
        // เลือกแค่ไตรมาส = วันในไตรมาสนั้น
        $this->assertSame(['2026-01-01', '2026-01-31'], ErpDashboard::spend_periods($days, '2026-1')['days']);
        $q1 = ErpDashboard::spend_periods($days, '2026-1', '2026-01');
        $this->assertSame(['2025-4', '2026-1', '2026-2'], $q1['quarters'], 'ไตรมาสยังเสนอครบเพื่อให้เปลี่ยนได้');
        $this->assertSame(['2026-01'], $q1['months']);
        $this->assertSame(['2026-01-01', '2026-01-31'], $q1['days']);
    }

    public function test_spending_reads_each_company_once_in_baht_through_the_payroll_policy(): void
    {
        $rows = array_values(array_filter(Fixture::rows(), fn ($r) => in_array($r['id'], ['101', '102', '105'], true)));
        $grouped = [(object) ['budget_dept' => 'SPV-02', 'paid' => '20260502', 'n' => 3, 'amount' => '500.005']];
        // pd อ่านก่อน (เรียงตามรหัสบริษัท) แล้วค่อย si5 · แต่ละบริษัท = ผลรวม 1 ครั้ง + นับรายการกำกวม 1 ครั้ง
        $service = $this->source([[], [(object) ['n' => 0]], $grouped, [(object) ['n' => 2]]]);
        $out = $service->spending($rows);
        $this->assertCount(4, $service->queries);
        $this->assertSame([['company' => 'si5', 'dept' => 'SPV-02', 'dept_key' => 'si5|SPV-02', 'date' => '2026-05-02', 'n' => 3, 'amount' => 50001]], $out['days']);
        $this->assertSame(2, $out['ambiguous']);

        $sql = $service->queries[2][0];
        $this->assertStringContainsString('GROUP BY budget_dept, paid', $sql);
        $this->assertStringContainsString('CONVERT(char(8), TRANSDATE, 112)', $sql);
        // 🔴 บาทจาก AMOUNTMST เท่านั้น — AMOUNT คือเงินสกุลต้นทาง
        $this->assertStringContainsString('SUM(AMOUNTMST)', $sql);
        $this->assertStringNotContainsString('SUM(AMOUNT)', $sql);
        $this->assertStringContainsString('t.STATUS=2 AND t.CANCEL=0', $sql);
        // 🔴 ถังเงินเดือน/ค่าแรงถูกตัดก่อนอ่านยอด
        $this->assertContains('%[^a-z]salary[^a-z]%', $service->queries[2][1]);
        $this->assertContains('%-DLDirect', $service->queries[2][1]);
        $this->assertSame('<ids><id>101</id><id>102</id></ids>', $service->queries[2][1][0]);
        $this->assertSame('<ids><id>105</id></ids>', $service->queries[0][1][0]);

        // ขอชุดเดิมซ้ำในคำขอเดียว → ไม่ยิง ERP อีก
        $service->spending($rows);
        $this->assertCount(4, $service->queries);
    }

    public function test_activity_window_is_bound_to_both_match_paths_and_the_ambiguity_count(): void
    {
        // หน้าแรกไม่มีรายการ = ยิงแค่คิวรีรายการกับคิวรีนับรายการกำกวม
        $service = $this->source([[], [(object) ['n' => 0]]]);
        $service->activity([Fixture::rows()[0]], 'actual', 1, 'date_desc', [], ErpDashboard::paid_window(['paid_m' => '2026-05']));
        $sql = $service->queries[0][0];
        $this->assertSame(2, substr_count($sql, 't.TRANSDATE >= ? AND t.TRANSDATE < ?'));
        // 🔴 รูปแบบ Ymd ตีความเหมือนกันทุก DATEFORMAT ของ SQL Server
        $this->assertSame(['20260501', '20260601', '20260501', '20260601'], array_slice($service->queries[0][1], -4));
        $this->assertStringContainsString('k.selected_count<k.total_count AND t.TRANSDATE >= ? AND t.TRANSDATE < ?', $service->queries[1][0]);
        $this->assertSame(['20260501', '20260601'], array_slice($service->queries[1][1], -2));

        // ไม่ส่งช่วงวันที่ = SQL เหมือนเดิมทุกตัวอักษร
        $plain = $this->source([[], [(object) ['n' => 0]]]);
        $plain->activity([Fixture::rows()[0]], 'actual');
        $this->assertStringNotContainsString('TRANSDATE >= ?', $plain->queries[0][0]);
    }

    /** แถวดิบจาก ERP แบบย่อ — พอให้ตัวแปลงรายการทำงาน และพกยอดรวมของทั้งชุดมาด้วย (COUNT/SUM OVER) */
    private function erpActualRow(array $extra = []): object
    {
        return (object) array_merge([
            'RECID' => 9001, 'budget_model' => 'BG26', 'budget_no' => 'HR-531103', 'budget_period' => 'BG26Q1',
            'budget_dept' => 'SPV-02', 'TRANSDATE' => '2026-05-02 00:00:00.000', 'COMMENT_' => 'Paint thinner',
            'ITEMID' => 'IT-01', 'QTY' => 1, 'AMOUNTMST' => '1234.56', 'PURCHID' => 'PO26-1', 'PURCHREQID' => '',
            'VOUCHER' => 'V-1', 'INVOICEID' => '', 'JOURNALNUM' => '', 'direct_match' => 1,
            'total_n' => 81, 'total_amount' => '1234.56', 'total_direct' => 80,
        ], $extra);
    }

    private function source(array $answers): ErpDashboard
    {
        return new class(new ErpBudget, $answers) extends ErpDashboard
        {
            public array $queries = [];

            public function __construct(ErpBudget $erp, private array $answers)
            {
                parent::__construct($erp);
            }

            protected function select(string $sql, array $params = []): array
            {
                $this->queries[] = [$sql, $params];
                if (! $this->answers) {
                    throw new \RuntimeException('Unexpected query');
                }

                return array_shift($this->answers);
            }
        };
    }
}
