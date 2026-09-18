<?php

namespace Tests\Fixtures;

use App\Services\Erp\ErpDashboard;

/** Synthetic data only; never connects to ERP. Amounts are integer satang. */
class ErpDashboardFixture
{
    public static function rows(): array
    {
        $base = ['company' => 'si5', 'dept' => 'SPV-02', 'dept_name' => 'HR : Human Resource', 'cost' => 'NON',
            'model' => 'BG26', 'budget_no' => 'HR-531103', 'title' => 'Training expense',
            'purpose' => 'BG26Q1', 'start_date' => '2025-10-01', 'active' => true, 'stopped' => false,
            'currency' => 'THB', 'budget' => 10000000, 'actual' => 6000000, 'reserve' => 1000000, 'available' => 3000000];
        $specs = [
            ['id' => '101'],
            ['id' => '102', 'purpose' => 'BG26Q2', 'start_date' => '2026-01-01'],
            ['id' => '103', 'model' => 'BG27', 'purpose' => 'BG27Q1', 'start_date' => '2026-08-31'],
            ['id' => '104', 'model' => 'AC-26-001', 'purpose' => 'NON', 'dept' => 'SPV-06', 'dept_name' => 'AC', 'budget_no' => 'AC-26-001', 'title' => 'Printer'],
            ['id' => '105', 'company' => 'pd', 'dept' => 'SPV-02', 'dept_name' => 'Mold'],
            ['id' => '106', 'model' => 'BG25', 'purpose' => 'BG25Q4'],
        ];

        return array_map(static function ($spec) use ($base) {
            $row = array_replace($base, $spec);
            $row['dept_key'] = $row['company'].'|'.$row['dept'];
            $row['dept_group'] = ErpDashboard::department_key($row['company'], $row['dept'], $row['dept_name']);
            $row['year'] = ErpDashboard::budget_year($row['model'], $row['purpose']);
            $row['quarter'] = ErpDashboard::budget_quarter($row['model'], $row['purpose']);
            $row['group'] = ErpDashboard::group_key($row);

            return $row;
        }, $specs);
    }

    public static function activity(string $kind = 'actual'): array
    {
        return ['count' => 41, 'total' => $kind === 'actual' ? 11500000 : null, 'currency_totals' => ['THB' => 11500000],
            'page' => 1, 'pages' => 2, 'ambiguous' => 0, 'direct' => 40, 'fallback' => 1, 'rows' => [[
                'id' => '901', 'date' => '2026-07-01', 'title' => 'Training workshop', 'item' => 'TRAIN-01', 'qty' => 1,
                'budget_model' => 'BG26', 'budget_no' => 'HR-531103', 'budget_period' => 'BG26Q1', 'budget_group' => self::rows()[0]['group'],
                'amount' => 11500000, 'currency' => 'THB', 'po' => 'PO26-TEST', 'pr' => 'PR26-TEST', 'invoice' => 'INV-TEST',
                'voucher' => 'V-TEST', 'journal' => 'J-TEST', 'line' => '1', 'requester' => 'TEST123', 'requester_name' => 'Test Requester',
                'creator' => 'TEST456', 'creator_name' => 'Test Creator', 'status' => 1, 'unit' => 'Course', 'direct' => true,
                'supplier_name' => 'Test Supplier Ltd.',
            ]]];
    }
}
