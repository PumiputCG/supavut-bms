<?php

namespace App\Services\Core;

use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use Illuminate\Support\Facades\DB;

/**
 * คัดลอกข้อมูลพนักงานและบัญชีผู้ใช้จาก Supavut Insight มาเก็บเป็นมิเรอร์ใน SBMS
 *
 *   Bplus ──(Insight ดึงเองทุก 15 นาที)──> insight.employees / insight.app_users
 *                                                   │  อ่านอย่างเดียว
 *                                          InsightMirror (ไฟล์นี้)
 *                                                   ▼
 *                                        bms.employees / bms.app_users
 *
 * หลักการ:
 * - ทางเดียวเท่านั้น อ่านจาก connection `insight` 🔴 ห้ามเขียนกลับเด็ดขาด
 * - จับคู่ด้วย `insight_id` (id ของแถวต้นทาง) แถวไหนหายจาก Insight ก็ลบทิ้งจากมิเรอร์
 * - รหัสผ่าน/รูป/ลายเซ็น คัดลอกมาด้วย ผู้ใช้จึงล็อกอิน SBMS ด้วยรหัสเดียวกับ Insight
 * - ไฟล์รูปไม่ได้ก็อป เปิดจาก public storage ของ Insight ผ่าน HTTP แทน
 *
 * ทำไมมิเรอร์ ไม่อ่านสด: ล็อกอิน/แสดงผลเร็ว ไม่ต้อง join ข้าม DB ทุก request
 * และยังใช้งานต่อได้ถ้า Insight ล่มชั่วคราว
 */
class InsightMirror
{
    /** connection ต้นทาง (config/database.php) */
    private const SOURCE = 'insight';

    /** คอลัมน์ที่คัดลอกจาก insight.employees — ตัด source_raw ที่ไม่ได้ใช้ทิ้ง */
    private const EMPLOYEE_COLUMNS = [
        'no', 'company', 'employee_code', 'license_id',
        'title', 'gender', 'name_th', 'surname_th', 'name_en',
        'job_code', 'job_th', 'job_en',
        'dept_code', 'dept_th', 'dept_en',
        'branch_code', 'branch_th', 'branch_en',
        'hire_date', 'probation_end_date', 'resign_date', 'emp_status',
        'photo_path',
    ];

    /** คอลัมน์ที่คัดลอกจาก insight.app_users — ตัด token ที่เป็นของ session ฝั่ง Insight */
    private const APP_USER_COLUMNS = [
        'id_thai_hash', 'company', 'employee_code', 'companies', 'password', 'role',
        'full_name_th', 'full_name_en', 'position', 'department', 'email',
        'profile_picture', 'signature', 'registered_at',
    ];

    private const CHUNK_EMPLOYEES = 500;

    /** เล็กกว่าเพราะ signature เป็น data URL ที่อาจใหญ่หลาย MB ต่อแถว */
    private const CHUNK_APP_USERS = 100;

    /**
     * ซิงค์ทั้งสองตาราง
     *
     * @param  bool  $fresh  ล้างมิเรอร์ทิ้งก่อนดึงใหม่ — ใช้เฉพาะตอนเปลี่ยนฐานต้นทาง
     * @return array{employees:array<string,int>,app_users:array<string,int>}
     */
    public function sync(bool $fresh = false): array
    {
        if ($fresh) {
            $this->wipe();
        }

        return [
            'employees' => $this->mirrorEmployees(),
            'app_users' => $this->mirrorAppUsers(),
        ];
    }

    /**
     * ล้างมิเรอร์ทิ้ง
     *
     * จำเป็นเมื่อสลับ INSIGHT_DB_HOST เพราะ id ต้นทางชุดใหม่ไม่ตรงของเก่า
     * ถ้าไม่ล้างก่อนจะชน unique (company+employee_code) แล้วซิงค์ล้มกลางคัน
     * (บทเรียนจริงจาก QuoteCompare)
     */
    public function wipe(): void
    {
        AppUser::query()->delete();
        Employee::query()->delete();
    }

    /**
     * เช็คว่าต่อฐาน Insight ได้ไหม — ใช้ก่อนขึ้นปุ่มซิงค์ในหน้าเว็บ
     */
    public function sourceReachable(): bool
    {
        try {
            DB::connection(self::SOURCE)->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ดึงบัญชีเดียวจาก Insight ทันที
     *
     * ใช้ตอนล็อกอินแล้วหารหัสในมิเรอร์ไม่เจอ — พนักงานที่เพิ่งเข้าใหม่จึงเข้าระบบได้เลย
     * ไม่ต้องรอรอบซิงค์
     */
    public function pullUserByCode(string $code): ?AppUser
    {
        // รหัสหลักก่อน (ใช้ index ได้) แล้วค่อยหาในรหัสของบริษัทอื่นด้วย JSON_SEARCH
        $row = DB::connection(self::SOURCE)->table('app_users')->where('employee_code', $code)->first()
            ?? DB::connection(self::SOURCE)->table('app_users')
                ->whereRaw("JSON_SEARCH(companies, 'one', ?) IS NOT NULL", [$code])
                ->first();

        if ($row === null) {
            return null;
        }

        // ดึงทะเบียนพนักงานของคนนี้มาด้วย เพื่อให้หน้าโปรไฟล์มีข้อมูลครบตั้งแต่ล็อกอินแรก
        $this->pullEmployeeByCode((string) $row->employee_code);

        return $this->upsertAppUser($row);
    }

    /** ดึงทะเบียนพนักงานรายคน (ใช้คู่กับ pullUserByCode) */
    public function pullEmployeeByCode(string $code): void
    {
        $rows = DB::connection(self::SOURCE)->table('employees')
            ->where('employee_code', $code)
            ->get();

        foreach ($rows as $row) {
            $this->upsertEmployee($row);
        }
    }

    /**
     * @return array{synced:int,deleted:int}
     */
    private function mirrorEmployees(): array
    {
        $seen = [];
        $synced = 0;

        DB::connection(self::SOURCE)->table('employees')
            ->select(array_merge(['id'], self::EMPLOYEE_COLUMNS))
            ->orderBy('id')
            ->chunk(self::CHUNK_EMPLOYEES, function ($rows) use (&$seen, &$synced) {
                foreach ($rows as $row) {
                    $this->upsertEmployee($row);
                    $seen[] = (int) $row->id;
                    $synced++;
                }
            });

        return ['synced' => $synced, 'deleted' => $this->deleteMissing(Employee::query(), $seen)];
    }

    /**
     * @return array{synced:int,deleted:int}
     */
    private function mirrorAppUsers(): array
    {
        $seen = [];
        $synced = 0;

        // โหลดรหัสองค์กรของพนักงานทั้งหมดมาไว้ในหน่วยความจำก่อน (แค่ ~1,700 แถว)
        // ดีกว่ายิงคิวรีทีละบัญชี 1,500 ครั้งตอนวนลูป
        $codes = $this->employeeCodeMap();

        DB::connection(self::SOURCE)->table('app_users')
            ->select(array_merge(['id'], self::APP_USER_COLUMNS))
            ->orderBy('id')
            ->chunk(self::CHUNK_APP_USERS, function ($rows) use (&$seen, &$synced, $codes) {
                foreach ($rows as $row) {
                    $this->upsertAppUser($row, $codes);
                    $seen[] = (int) $row->id;
                    $synced++;
                }
            });

        return ['synced' => $synced, 'deleted' => $this->deleteMissing(AppUser::query(), $seen)];
    }

    /**
     * map: "บริษัท|รหัสพนักงาน" และ "รหัสพนักงาน" -> รหัสตำแหน่ง/แผนก/สาขา
     *
     * ใส่คีย์แบบไม่ระบุบริษัทไว้ด้วย เพราะบางบัญชี (เช่น admin กลาง) ตั้งค่า company
     * ไม่ตรงกับบริษัทในทะเบียนพนักงาน จะได้ยังจับคู่ได้
     *
     * @return array<string,array<string,?string>>
     */
    private function employeeCodeMap(): array
    {
        $map = [];

        Employee::query()
            ->select(['company', 'employee_code', 'job_code', 'dept_code', 'branch_code'])
            ->cursor()
            ->each(function (Employee $e) use (&$map) {
                $codes = [
                    'job_code' => $e->job_code,
                    'dept_code' => $e->dept_code,
                    'branch_code' => $e->branch_code,
                ];
                $map[$e->company.'|'.$e->employee_code] = $codes;
                $map[(string) $e->employee_code] ??= $codes;
            });

        return $map;
    }

    private function upsertEmployee(object $row): Employee
    {
        $values = ['mirrored_at' => now()];
        foreach (self::EMPLOYEE_COLUMNS as $column) {
            $values[$column] = $row->{$column} ?? null;
        }

        return Employee::updateOrCreate(['insight_id' => (int) $row->id], $values);
    }

    /**
     * @param  array<string,array<string,?string>>|null  $codeMap
     */
    private function upsertAppUser(object $row, ?array $codeMap = null): AppUser
    {
        $values = ['mirrored_at' => now()];
        foreach (self::APP_USER_COLUMNS as $column) {
            $values[$column] = $row->{$column} ?? null;
        }

        // companies มาเป็นสตริง JSON จาก DB ต้นทาง แต่โมเดลฝั่งนี้ cast เป็น array
        if (is_string($values['companies'] ?? null)) {
            $values['companies'] = json_decode($values['companies'], true) ?: null;
        }

        // เติมรหัสองค์กรจากทะเบียนพนักงาน — insight.app_users ไม่มีคอลัมน์พวกนี้
        $codes = $this->codesFor((string) $row->company, (string) $row->employee_code, $codeMap);
        $values['job_code'] = $codes['job_code'] ?? null;
        $values['dept_code'] = $codes['dept_code'] ?? null;
        $values['branch_code'] = $codes['branch_code'] ?? null;

        return AppUser::updateOrCreate(['insight_id' => (int) $row->id], $values);
    }

    /**
     * @param  array<string,array<string,?string>>|null  $codeMap  ส่งมาตอนซิงค์ทั้งชุด · null = ค้นทีละคน
     * @return array<string,?string>
     */
    private function codesFor(string $company, string $code, ?array $codeMap): array
    {
        if ($codeMap !== null) {
            return $codeMap[$company.'|'.$code] ?? $codeMap[$code] ?? [];
        }

        $employee = Employee::where('employee_code', $code)
            ->when($company !== '', fn ($q) => $q->where('company', $company))
            ->first()
            ?? Employee::where('employee_code', $code)->first();

        return $employee ? [
            'job_code' => $employee->job_code,
            'dept_code' => $employee->dept_code,
            'branch_code' => $employee->branch_code,
        ] : [];
    }

    /**
     * ลบแถวที่หายไปจากต้นทางแล้ว (เช่น พนักงานลาออกจนบัญชีถูกลบที่ Insight)
     *
     * @param  array<int,int>  $seenInsightIds
     */
    private function deleteMissing($query, array $seenInsightIds): int
    {
        if ($seenInsightIds === []) {
            return 0;
        }

        // ตัดเป็นก้อนกัน SQL ยาวเกินขนาดที่ MySQL รับได้ตอนข้อมูลเยอะ
        $keep = array_chunk($seenInsightIds, 1000);
        $query->where(function ($q) use ($keep) {
            foreach ($keep as $chunk) {
                $q->whereNotIn('insight_id', $chunk);
            }
        });

        return $query->delete();
    }
}
