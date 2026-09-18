<?php

namespace App\Services\Master;

use App\Models\Core\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ข้อมูลหลักของระบบ (Master Data) — รวมว่ามีชุดข้อมูลอะไรบ้าง และแต่ละชุดมีกี่รายการ
 *
 * 🔴 ตัวเลขทุกตัวต้องนับจากฐานข้อมูลจริงเท่านั้น ห้ามใส่ตัวเลขตัวอย่าง
 *    ชุดไหนยังไม่มีตาราง ให้บอกตรงๆ ว่า "ยังไม่ได้ทำ" — ดีกว่าโชว์เลขปลอมให้เข้าใจผิด
 *
 * ชุดข้อมูลมี 2 แบบ
 *   mirror  มิเรอร์มาจาก Insight — แก้ที่ Insight ที่เดียว SBMS อ่านอย่างเดียว
 *   bms     SBMS เป็นเจ้าของเอง — ต้องสร้างตารางและหน้าจัดการใน SBMS
 */
class MasterData
{
    /**
     * รายการชุดข้อมูลหลักทั้งหมด
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function sets(): Collection
    {
        return collect([
            [
                'id' => 'department',
                'key' => 'master.department',
                'th' => 'แผนก',
                'source' => 'mirror',
                'count' => $this->distinctCount('dept_code'),
                'future' => false,
                'route' => 'master.departments',
            ],
            [
                'id' => 'position',
                'key' => 'master.position',
                'th' => 'ตำแหน่งงาน',
                'source' => 'mirror',
                'count' => $this->distinctCount('job_code'),
                'future' => false,
                'route' => 'master.positions',
            ],
            [
                'id' => 'branch',
                'key' => 'master.branch',
                'th' => 'สาขา',
                'source' => 'mirror',
                'count' => $this->distinctCount('branch_code'),
                'future' => false,
                'route' => 'master.branches',
            ],
            [
                'id' => 'employee',
                'key' => 'master.employee',
                'th' => 'ทะเบียนพนักงาน',
                'source' => 'mirror',
                'count' => Employee::where('emp_status', '1')->count(),
                'future' => false,
                'route' => null,
            ],
            // ── ชุดที่ SBMS ต้องสร้างเอง — ยังไม่มีตาราง ─────────────────
            // 🔴 ชุดข้างล่างนี้ยัง **ไม่ได้ confirm** ว่าจะเอาอย่างไร — เป็นโมดูลในอนาคต
            //    ห้ามทำให้ดูเหมือนตกลงแล้ว (เจ้าของเตือน 2026-09-03)
            ['id' => 'cost_center', 'key' => 'master.costCenter', 'th' => 'ศูนย์ต้นทุน', 'source' => 'bms', 'count' => null, 'future' => true, 'route' => null],
            ['id' => 'supplier', 'key' => 'master.supplier', 'th' => 'ผู้ขาย / ผู้รับเหมา', 'source' => 'bms', 'count' => null, 'future' => true, 'route' => null],
            ['id' => 'item', 'key' => 'master.item', 'th' => 'สินค้าและบริการ', 'source' => 'bms', 'count' => null, 'future' => true, 'route' => null],
            ['id' => 'unit', 'key' => 'master.unit', 'th' => 'หน่วยนับ', 'source' => 'bms', 'count' => null, 'future' => true, 'route' => null],
            ['id' => 'payment_term', 'key' => 'master.paymentTerm', 'th' => 'เงื่อนไขการชำระเงิน', 'source' => 'bms', 'count' => null, 'future' => true, 'route' => null],
            ['id' => 'warehouse', 'key' => 'master.warehouse', 'th' => 'คลัง / สถานที่จัดเก็บ', 'source' => 'bms', 'count' => null, 'future' => true, 'route' => null],
        ]);
    }

    /** แผนกทั้งหมด + จำนวนคนที่ยังทำงานอยู่ */
    public function departments(): Collection
    {
        return $this->groupBy('dept_code', 'dept_th', 'dept_en');
    }

    /** ตำแหน่งทั้งหมด + จำนวนคน */
    public function positions(): Collection
    {
        return $this->groupBy('job_code', 'job_th', 'job_en');
    }

    /** สาขาทั้งหมด + จำนวนคน */
    public function branches(): Collection
    {
        return $this->groupBy('branch_code', 'branch_th', 'branch_en');
    }

    /**
     * ทะเบียนพนักงาน — เฉพาะคนที่ยังทำงานอยู่ (emp_status = 1) ตามที่เจ้าของสั่ง 2026-09-03
     *
     * 🔴 ห้ามส่งคอลัมน์ต้องห้ามออกไปเด็ดขาด
     *    - `license_id` = เลขบัตรประชาชน (เป็นรหัสผ่านเริ่มต้นของ Insight ด้วย)
     *    - เงินเดือน/ค่าจ้าง — ตารางนี้ไม่มีคอลัมน์พวกนั้นอยู่แล้ว และห้ามเพิ่มเข้ามา (กฎใน CLAUDE.md)
     *    เลือกคอลัมน์ทีละตัวโดยตั้งใจ ไม่ใช้ select * เพื่อไม่ให้คอลัมน์ใหม่หลุดออกไปเองในอนาคต
     *
     * @return array{rows:array<int,array<string,mixed>>,total:int,has_more:bool}
     */
    public function employees(string $term = '', int $page = 1, int $perPage = 60): array
    {
        $query = Employee::query()->where('emp_status', '1');

        $term = trim($term);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(function ($q) use ($like) {
                $q->where('employee_code', 'like', $like)
                    ->orWhere('name_th', 'like', $like)
                    ->orWhere('surname_th', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('dept_th', 'like', $like)
                    ->orWhere('job_th', 'like', $like);
            });
        }

        $total = (clone $query)->count();
        $page = max(1, $page);

        $rows = $query->orderBy('employee_code')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get([
                'employee_code', 'title', 'name_th', 'surname_th', 'name_en',
                'job_code', 'job_th', 'job_en', 'dept_code', 'dept_th', 'dept_en',
                'branch_code', 'branch_th', 'branch_en', 'company', 'hire_date', 'photo_path',
            ])
            ->map(fn ($e) => [
                'code' => (string) $e->employee_code,
                'name_th' => trim($e->title.' '.$e->name_th.' '.$e->surname_th),
                'name_en' => trim((string) $e->name_en) ?: trim($e->name_th.' '.$e->surname_th),
                'job_th' => (string) $e->job_th,
                'job_en' => (string) $e->job_en,
                'job_code' => (string) $e->job_code,
                'dept_th' => (string) $e->dept_th,
                'dept_en' => (string) $e->dept_en,
                'branch_th' => (string) $e->branch_th,
                'branch_en' => (string) $e->branch_en,
                'company' => (string) $e->company,
                'hire_date' => $e->hire_date?->format('d/m/Y'),
                'photo' => $e->photoUrl(),
                'initial' => mb_strtoupper(mb_substr(trim((string) $e->name_th) ?: (string) $e->employee_code, 0, 1)),
            ])
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'has_more' => $page * $perPage < $total,
        ];
    }

    /**
     * นับแถวในตารางของ SBMS เอง
     *
     * 🔴 ตารางยังไม่ถูกสร้างให้คืน null ไม่ใช่ 0 — หน้าจอจะได้ขึ้นว่า "ยังไม่ได้ทำ"
     *    ถ้าคืน 0 ผู้ใช้จะเข้าใจผิดว่ามีตารางแล้วแต่ข้อมูลหาย
     */
    private function countTable(string $table): ?int
    {
        try {
            return Schema::hasTable($table) ? (int) DB::table($table)->count() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * นับจำนวนค่าที่ไม่ซ้ำของคอลัมน์รหัส (เฉพาะพนักงานที่ยังทำงานอยู่)
     */
    private function distinctCount(string $column): int
    {
        return Employee::query()
            ->where('emp_status', '1')
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->count($column);
    }

    /**
     * รวมรายการตามรหัส พร้อมชื่อไทย/อังกฤษ และจำนวนคน
     *
     * ข้อมูลชุดนี้มาจากทะเบียนพนักงานที่มิเรอร์จาก Insight
     * ยังไม่มีตารางแผนก/ตำแหน่งของตัวเอง จึงสรุปจากพนักงานที่มีอยู่จริง
     *
     * @return Collection<int,array<string,mixed>>
     */
    private function groupBy(string $code, string $th, string $en): Collection
    {
        return Employee::query()
            ->where('emp_status', '1')
            ->whereNotNull($code)
            ->where($code, '<>', '')
            ->selectRaw("{$code} AS code, MAX({$th}) AS name_th, MAX({$en}) AS name_en, COUNT(*) AS headcount")
            ->groupBy($code)
            ->orderBy($code)
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->code,
                'name_th' => (string) $row->name_th,
                'name_en' => (string) $row->name_en,
                'headcount' => (int) $row->headcount,
            ]);
    }
}
