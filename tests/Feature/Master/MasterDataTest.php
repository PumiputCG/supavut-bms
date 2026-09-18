<?php

namespace Tests\Feature\Master;

use App\Http\Middleware\Authenticate;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Master\MasterData;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * ข้อมูลหลัก (Master Data)
 *
 * ที่ต้องมีเทสต์คุม: 🔴 ตัวเลขบนหน้านี้ต้องมาจากฐานข้อมูลจริงเท่านั้น
 * ถ้าวันหนึ่งมีคนใส่ตัวเลขตัวอย่างลงไป เทสต์ต้องจับได้
 */
class MasterDataTest extends TestCase
{
    use RefreshModuleDatabase;

    private function employee(int $n, string $dept, string $job, string $branch, string $status = '1'): void
    {
        Employee::create([
            'insight_id' => 5000 + $n,
            'company' => 'TEST',
            'employee_code' => 'M'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'name_th' => 'ทดสอบ',
            'dept_code' => $dept, 'dept_th' => 'แผนก '.$dept, 'dept_en' => 'Dept '.$dept,
            'job_code' => $job, 'job_th' => 'ตำแหน่ง '.$job, 'job_en' => 'Job '.$job,
            'branch_code' => $branch, 'branch_th' => 'สาขา '.$branch, 'branch_en' => 'Branch '.$branch,
            'emp_status' => $status,
        ]);
    }

    private function admin(): AppUser
    {
        $user = AppUser::create([
            'insight_id' => 4001,
            'company' => 'TEST',
            'employee_code' => 'ADMIN1',
            'password' => 'secret',
            'role' => 'admin',
            'full_name_th' => 'ผู้ดูแล ทดสอบ',
        ]);

        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $user;
    }

    public function test_counts_come_from_real_rows_and_ignore_resigned_staff(): void
    {
        $this->employee(1, 'D1', 'J1', 'B1');
        $this->employee(2, 'D1', 'J2', 'B1');
        $this->employee(3, 'D2', 'J1', 'B2');
        $this->employee(4, 'D3', 'J3', 'B3', status: '2');   // ลาออกแล้ว ต้องไม่ถูกนับ

        $sets = app(MasterData::class)->sets()->keyBy('id');

        $this->assertSame(2, $sets['department']['count']);
        $this->assertSame(2, $sets['position']['count']);
        $this->assertSame(2, $sets['branch']['count']);
        $this->assertSame(3, $sets['employee']['count']);
    }

    public function test_sets_without_a_table_report_null_not_zero(): void
    {
        // 🔴 ต้องเป็น null เพื่อให้หน้าจอขึ้นว่า "ยังไม่ได้ทำ"
        //    ถ้าคืน 0 ผู้ใช้จะเข้าใจผิดว่ามีตารางแล้วแต่ข้อมูลหาย
        $sets = app(MasterData::class)->sets()->keyBy('id');

        // 🔴 ชุดที่ยังไม่ confirm ต้องติดธง future และไม่โชว์ตัวเลข
        //    เจ้าของเตือนไว้ 2026-09-03 ว่าห้ามทำให้ดูเหมือนตกลงแล้ว
        foreach (['cost_center', 'supplier', 'item', 'unit', 'payment_term', 'warehouse'] as $id) {
            $this->assertTrue($sets[$id]['future'], $id.' ต้องติดธง future');
            $this->assertNull($sets[$id]['count'], $id.' ต้องเป็น null');
        }

        // 🔴 หมวดค่าใช้จ่ายของ SBMS ถูกถอดออกแล้ว 2026-09-09 — ต่อไปใช้ผังบัญชีของ ERP แทน
        $this->assertFalse($sets->has('expense'), 'ชุดหมวดค่าใช้จ่ายต้องไม่มีแล้ว');
    }

    public function test_department_list_shows_real_names_and_headcount(): void
    {
        $this->employee(1, 'D1', 'J1', 'B1');
        $this->employee(2, 'D1', 'J1', 'B1');
        $this->admin();

        $html = $this->get('/master/departments')->assertOk()->getContent();

        $this->assertStringContainsString('แผนก D1', $html);
        $this->assertStringContainsString('Dept D1', $html);   // ต้องมีชื่ออังกฤษให้สลับภาษาได้
        $this->assertStringContainsString('>2<', $html);       // จำนวนคนจริง
    }

    public function test_master_pages_are_admin_only(): void
    {
        $user = AppUser::create([
            'insight_id' => 4002, 'company' => 'TEST', 'employee_code' => 'USER1',
            'password' => 'secret', 'role' => 'user', 'full_name_th' => 'ผู้ใช้ ทดสอบ',
        ]);
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        $this->get('/master')->assertForbidden();
        $this->get('/master/departments')->assertForbidden();
    }
}
