<?php

namespace App\Services\Budget;

use App\Models\Budget\Budget;
use App\Models\Budget\BudgetTransaction;
use Illuminate\Support\Collection;

/**
 * ตัวคิดยอดเงินของงบ — ทุกที่ที่อยากรู้ยอดต้องถามผ่านที่นี่
 *
 * 🔴 ห้ามคำนวณยอดเองในหน้า/ใน controller (กฎใน DECISIONS 4.1)
 *    ยอดมาจากผลรวมของ budget_transactions เสมอ ไม่มีคอลัมน์ยอดสะสมที่ไหน
 *
 * ตัวเลข 4 ค่าที่ตกลงกันไว้
 *   งบตั้งไว้ (budget)     = initial + adjust
 *   กันไว้ (reserved)      = reserve + release      (release บันทึกเป็นค่าติดลบ)
 *   ใช้จริง (actual)       = actual
 *   คงเหลือ (available)    = budget - reserved - actual
 */
class BudgetLedger
{
    /**
     * ยอด 4 ค่าของงบก้อนเดียว
     *
     * @return array{budget:float,reserved:float,actual:float,available:float,used_percent:float}
     */
    public function totals(Budget $budget): array
    {
        $sums = BudgetTransaction::where('budget_id', $budget->id)
            ->selectRaw('type, SUM(amount) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return $this->shape($sums);
    }

    /**
     * ยอด 4 ค่าของงบหลายก้อนพร้อมกัน — ใช้ในหน้ารายการ กัน N+1
     *
     * @param  Collection<int,Budget>|array<int,Budget>  $budgets
     * @return array<int,array<string,float>> คีย์คือ budget_id
     */
    public function totalsFor($budgets): array
    {
        $ids = collect($budgets)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $rows = BudgetTransaction::whereIn('budget_id', $ids)
            ->selectRaw('budget_id, type, SUM(amount) AS total')
            ->groupBy('budget_id', 'type')
            ->get()
            ->groupBy('budget_id');

        $out = [];
        foreach ($ids as $id) {
            $sums = ($rows[$id] ?? collect())->pluck('total', 'type');
            $out[$id] = $this->shape($sums);
        }

        return $out;
    }

    /** บันทึกรายการลงบัญชีเดินสะพัด — เขียนอย่างเดียว ไม่มีการแก้ย้อนหลัง */
    public function record(
        Budget $budget,
        string $type,
        float $amount,
        ?string $note = null,
        ?string $refType = null,
        ?int $refId = null,
    ): BudgetTransaction {
        $me = app()->bound('current_user') ? app('current_user') : null;

        return BudgetTransaction::create([
            'budget_id' => $budget->id,
            'type' => $type,
            'amount' => $amount,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'note' => $note,
            'created_by' => $me?->employee_code,
            'created_by_name' => $me?->displayName('th'),
            'created_at' => now(),
        ]);
    }

    /**
     * รวมยอดของงบหลายก้อน — ใช้กับแถวสรุปด้านบนหน้ารายการ
     *
     * @param  array<int,array<string,float>>  $totals
     * @return array{budget:float,reserved:float,actual:float,available:float,used_percent:float}
     */
    public function sum(array $totals): array
    {
        $budget = $reserved = $actual = 0.0;

        foreach ($totals as $row) {
            $budget += $row['budget'];
            $reserved += $row['reserved'];
            $actual += $row['actual'];
        }

        return $this->finish($budget, $reserved, $actual);
    }

    /** @param  Collection<string,mixed>  $sums */
    private function shape($sums): array
    {
        $get = fn (string $type) => (float) ($sums[$type] ?? 0);

        return $this->finish(
            $get(BudgetTransaction::INITIAL) + $get(BudgetTransaction::ADJUST),
            $get(BudgetTransaction::RESERVE) + $get(BudgetTransaction::RELEASE),
            $get(BudgetTransaction::ACTUAL),
        );
    }

    private function finish(float $budget, float $reserved, float $actual): array
    {
        $available = $budget - $reserved - $actual;

        return [
            'budget' => $budget,
            'reserved' => $reserved,
            'actual' => $actual,
            'available' => $available,
            // ใช้ไปกี่ % ของงบ (กันไว้ + ใช้จริง) — งบเป็น 0 ให้ถือว่า 0% ไม่ใช่หารด้วยศูนย์
            'used_percent' => $budget > 0 ? round((($reserved + $actual) / $budget) * 100, 1) : 0.0,
        ];
    }
}
