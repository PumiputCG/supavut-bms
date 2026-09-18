<?php

namespace App\Services\Access;

use App\Models\Access\AccessAdmin;
use App\Models\Access\FunctionUser;
use App\Models\Access\LoginPosition;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Support\NavMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ศูนย์กลางการตัดสินสิทธิ์ของ SBMS — ทุกจุดที่ถามว่า "คนนี้ทำได้ไหม" ต้องผ่านที่นี่
 *
 * ตอบ 3 คำถาม
 *   1. เป็นผู้ดูแลระบบไหม        isAdmin()
 *   2. ตำแหน่งนี้เข้าระบบได้ไหม   canLogin()
 *   3. ใช้หัวข้อย่อยนี้ได้ไหม     canUseFunction()   <- ตัวตัดสินจริงทั้งหมด
 *
 * 🔴 โครงสิทธิ์ 2 ชั้น (เจ้าของสั่งยุบจาก 3 ชั้นเหลือ 2 เมื่อ 2026-09-03)
 *   ชั้นที่ 1 เข้าระบบได้ไหม     -> คุมด้วย **ตำแหน่ง** (คนเป็นพัน คุมทีละคนไม่ไหว)
 *   ชั้นที่ 2 ใช้หัวข้อย่อยไหน   -> คุมด้วย **รายชื่อคน** ที่หน้า /access/modules
 *
 * 🔴 ไม่มีสิทธิ์ระดับ "โมดูล" ให้ตั้งเองแล้ว — เห็นโมดูลไหม **คำนวณจากหัวข้อย่อย**
 *    ใช้ได้อย่างน้อย 1 หัวข้อ = เห็นโมดูล · ใช้ไม่ได้เลยสักหัวข้อ = ซ่อนโมดูล
 *    เหตุผล: เดิมตั้ง 2 ที่แล้วขัดกันเอง (ให้สิทธิ์เห็นโมดูลแต่ใช้ไม่ได้ / ให้สิทธิ์หัวข้อแต่ไม่เห็นโมดูล)
 *
 * 🔴 ผู้ดูแลระบบผ่านทุกอย่างเสมอ ไม่สนตารางสิทธิ์
 *
 * 🔴 กติกาเริ่มต้น (เจ้าของสั่งกลับด้านเมื่อ 2026-09-07)
 *    **ไม่ระบุใครเลย = ไม่มีใครใช้ได้** — ปลอดภัยไว้ก่อน ต้องตั้งใจให้สิทธิ์ถึงจะใช้ได้
 *    อยากเปิดให้ทุกคน ต้อง **ติ๊ก "ทุกคน" ให้ชัดเจน** ที่หน้า /access/modules
 *    (ของเดิมคือ "ไม่ระบุ = ทุกคน" ซึ่งอ่านไม่ออกว่าตั้งใจเปิดหรือแค่ยังไม่ได้ตั้ง)
 *
 *    เก็บ "ทุกคน" เป็นแถวพิเศษในตารางเดิม employee_code = '*' (ค่าคงที่ EVERYONE)
 *    ไม่ต้องเพิ่มคอลัมน์ใหม่ และรหัสพนักงานจริงไม่มีทางเป็น '*'
 *
 * 🔴 ผู้ดูแลระบบยังผ่านทุกด่านเสมอ — กันเหตุการณ์ล็อกตัวเองจนไม่มีใครเข้าไปแก้คืนได้
 *
 * ทุกเมธอดกันไว้ด้วย try/catch เพราะตารางสิทธิ์อาจยังไม่ถูก migrate
 * (เช่นเพิ่ง deploy แล้วยังไม่ได้รัน migration) — กรณีนั้นให้ถอยไปใช้พฤติกรรมเดิม
 */
class AccessService
{
    /**
     * แถวพิเศษที่แปลว่า "ทุกคนที่เข้าระบบได้"
     *
     * 🔴 ใช้ค่านี้เทียบเสมอ ห้ามเขียน '*' ดิบๆ กระจายตามไฟล์
     */
    public const EVERYONE = '*';

    /** จำคำตอบไว้ในคำขอเดียว — sidebar กับ submenu ถามซ้ำกันหลายรอบต่อหน้า */
    private ?array $adminCodes = null;

    private ?array $functionUserMap = null;

    // ── 1) ผู้ดูแลระบบ ──────────────────────────────────────────────────

    /**
     * เป็นผู้ดูแลระบบไหม
     *
     * นับ 2 ทาง: role ที่มิเรอร์มาจาก Insight (บัญชี Admin เดิม) หรืออยู่ในตาราง access_admins ของ SBMS
     */
    public function isAdmin(?AppUser $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return in_array((string) $user->employee_code, $this->adminCodes(), true);
    }

    /** @return array<int,string> รหัสพนักงานของผู้ดูแลระบบที่ตั้งใน SBMS */
    public function adminCodes(): array
    {
        if ($this->adminCodes !== null) {
            return $this->adminCodes;
        }

        try {
            $this->adminCodes = AccessAdmin::query()->pluck('employee_code')->map(strval(...))->all();
        } catch (Throwable) {
            $this->adminCodes = [];
        }

        return $this->adminCodes;
    }

    // ── 2) ตำแหน่งที่เข้าระบบได้ ─────────────────────────────────────────

    /**
     * บัญชีนี้เข้าสู่ระบบได้ไหม
     *
     * ผู้ดูแลระบบเข้าได้เสมอ — กันเหตุการณ์ติ๊กปิดตำแหน่งตัวเองแล้วไม่มีใครเข้าไปแก้คืนได้
     * ตำแหน่งที่ยังไม่มีในตาราง ถือว่าเข้าได้ (พนักงานตำแหน่งใหม่ต้องไม่ถูกกันออกโดยบังเอิญ)
     */
    public function canLogin(?AppUser $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        $jobCode = trim((string) $user->job_code);
        if ($jobCode === '') {
            return true;
        }

        try {
            $row = LoginPosition::where('job_code', $jobCode)->first();
        } catch (Throwable) {
            return true;
        }

        return $row === null || $row->can_login;
    }

    // ── 3) โมดูลที่เห็นได้ ──────────────────────────────────────────────

    /**
     * id ของโมดูลที่ผู้ใช้คนนี้เห็นได้
     *
     * @return array<int,string>
     */
    public function visibleModuleIds(?AppUser $user): array
    {
        $modules = NavMenu::permissionedModules();

        if ($this->isAdmin($user)) {
            return collect($modules)->pluck('id')->map(strval(...))->all();
        }

        if (! $user) {
            return [];
        }

        $map = $this->functionUserMap();
        $code = (string) $user->employee_code;
        $visible = [];

        foreach ($modules as $module) {
            $id = (string) $module['id'];

            /*
              เห็นโมดูลได้ถ้าใช้หัวข้อย่อยได้อย่างน้อย 1 หัวข้อ
              🔴 กติกาต้องตรงกับ canUseFunction() เป๊ะๆ — เคยมีสำเนากติกาเก่าค้างที่นี่
                 พอกลับกติกาแล้วลืมแก้ตรงนี้ เมนูกับหน้าจริงจะไม่ตรงกัน (บทเรียน 2026-09-07)
            */
            foreach ($module['functions'] ?? [] as $fn) {
                // หัวข้อที่ตั้งสิทธิ์ไม่ได้ ไม่นับเป็นเหตุผลให้เห็นโมดูล
                // (ไม่งั้นทุกโมดูลที่มีหัวข้อแบบนี้จะโผล่ให้ทุกคนเห็นตลอด)
                if (! empty($fn['no_edit'])) {
                    continue;
                }

                $codes = $map[$id.'|'.$fn['key']] ?? [];

                if (in_array(self::EVERYONE, $codes, true) || in_array($code, $codes, true)) {
                    $visible[] = $id;
                    break;
                }
            }
        }

        return $visible;
    }

    // ── 4) หัวข้อย่อยในโมดูล ────────────────────────────────────────────

    /**
     * ใช้หัวข้อย่อยข้อใดข้อหนึ่งในรายการนี้ได้ไหม
     *
     * เมนูที่ยุบมาจากหลายบทบาท (เช่น รับทราบ / อนุมัติ) ใช้ตัวนี้ตัดสินว่าจะโชว์ไหม
     *
     * @param  array<int,string>  $functionKeys
     */
    public function canUseAnyFunction(?AppUser $user, string $moduleId, array $functionKeys): bool
    {
        foreach ($functionKeys as $key) {
            if ($this->canUseFunction($user, $moduleId, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ใช้หัวข้อย่อยนี้ได้ไหม — ตัวตัดสินสิทธิ์ตัวจริงตัวเดียวของระบบ
     *
     * 🔴 ยังไม่ระบุคนไว้เลย = **ไม่มีใครใช้ได้** (เจ้าของสั่งกลับด้าน 2026-09-07)
     * 🔴 ติ๊ก "ทุกคน" ไว้ (แถว EVERYONE) = ทุกคนที่เข้าระบบได้ใช้ได้
     * 🔴 ไม่เช็คสิทธิ์ระดับโมดูลแล้ว เพราะการเห็นโมดูลคำนวณกลับมาจากตรงนี้ (ดูหัวคลาส)
     */
    public function canUseFunction(?AppUser $user, string $moduleId, string $functionKey): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        /*
          🔴 หัวข้อที่ไม่มีหน้าจอให้ตั้งสิทธิ์ (no_edit) ต้องไม่กั้นใคร
             เช่น "รับทราบ" — ผู้เสนอเลือกผู้รับทราบเองตอนส่งแต่ละฉบับ ไม่มีรายชื่อล่วงหน้า
             ตัวหน้าจอกรองรายการตามบทบาทของผู้ดูอยู่แล้ว คนที่ไม่เกี่ยวข้องจึงเห็นตารางว่าง
             (ถ้ากั้นตรงนี้ = คนที่ถูกขอให้รับทราบเปิดเอกสารของตัวเองไม่ได้เลย)
        */
        if (NavMenu::isUnsettable($functionKey)) {
            return true;
        }

        $codes = $this->functionUserMap()[$moduleId.'|'.$functionKey] ?? [];

        if (in_array(self::EVERYONE, $codes, true)) {
            return true;
        }

        return in_array((string) $user->employee_code, $codes, true);
    }

    /** หัวข้อย่อยนี้ถูกตั้งเป็น "ทุกคน" ไว้ไหม */
    public function isEveryone(string $moduleId, string $functionKey): bool
    {
        $codes = $this->functionUserMap()[$moduleId.'|'.$functionKey] ?? [];

        return in_array(self::EVERYONE, $codes, true);
    }

    /**
     * รหัสพนักงาน "ตัวจริง" ที่ระบุไว้ในหัวข้อย่อยนี้ — ตัด EVERYONE ออกให้แล้ว
     *
     * คืน [] ได้ 2 กรณี ต้องแยกด้วย isEveryone()
     *   · ยังไม่ระบุใครเลย  = ไม่มีใครใช้ได้
     *   · ติ๊ก "ทุกคน" ไว้   = ทุกคนใช้ได้ แต่ไม่มีรายชื่อคนให้หยิบ
     *
     * @return array<int,string>
     */
    public function codesForFunction(string $moduleId, string $functionKey): array
    {
        $codes = $this->functionUserMap()[$moduleId.'|'.$functionKey] ?? [];

        return array_values(array_filter($codes, fn ($c) => $c !== self::EVERYONE));
    }

    /**
     * แผนที่ "module_id|function_key" -> รายชื่อรหัสพนักงาน
     *
     * @return array<string,array<int,string>>
     */
    public function functionUserMap(): array
    {
        if ($this->functionUserMap !== null) {
            return $this->functionUserMap;
        }

        try {
            return $this->functionUserMap = FunctionUser::query()
                ->get(['module_id', 'function_key', 'employee_code'])
                ->groupBy(fn ($r) => $r->module_id.'|'.$r->function_key)
                ->map(fn ($rows) => $rows->pluck('employee_code')->map(strval(...))->values()->all())
                ->all();
        } catch (Throwable) {
            return $this->functionUserMap = [];
        }
    }

    // ── ข้อมูลประกอบหน้าจัดการสิทธิ์ ────────────────────────────────────

    /**
     * รายชื่อตำแหน่งทั้งหมด พร้อมจำนวนคนและสถานะเข้าระบบ
     *
     * เติมตำแหน่งที่ยังไม่มีในตารางสิทธิ์ให้อัตโนมัติ (ค่าเริ่มต้น = เข้าได้)
     * จะได้ไม่ต้องมี seeder แยก และตำแหน่งใหม่โผล่ในหน้านี้เองเมื่อซิงค์มา
     */
    public function positions(): Collection
    {
        try {
            $saved = LoginPosition::query()->get()->keyBy('job_code');
        } catch (Throwable) {
            $saved = collect();
        }

        return $this->positionHeadcount()->map(function (array $row) use ($saved) {
            $model = $saved->get($row['job_code']);

            return $row + ['can_login' => $model === null ? true : (bool) $model->can_login];
        })->values();
    }

    /** ตำแหน่งทั้งหมดจากทะเบียนพนักงาน + จำนวนคนที่ยังทำงานอยู่ และจำนวนบัญชีที่ล็อกอินได้ */
    private function positionHeadcount(): Collection
    {
        $employees = Employee::query()
            ->where('emp_status', '1')
            ->whereNotNull('job_code')
            ->where('job_code', '<>', '')
            ->selectRaw('job_code, MAX(job_th) AS job_th, MAX(job_en) AS job_en, COUNT(*) AS headcount')
            ->groupBy('job_code')
            ->orderBy('job_code')
            ->get();

        $accounts = AppUser::query()
            ->whereNotNull('job_code')
            ->selectRaw('job_code, COUNT(*) AS n')
            ->groupBy('job_code')
            ->pluck('n', 'job_code');

        return $employees->map(fn ($row) => [
            'job_code' => (string) $row->job_code,
            'job_th' => (string) $row->job_th,
            'job_en' => (string) $row->job_en,
            'headcount' => (int) $row->headcount,
            'accounts' => (int) ($accounts[$row->job_code] ?? 0),
        ]);
    }

    /**
     * รายชื่อผู้ดูแลระบบพร้อมข้อมูลคน (รูป · ตำแหน่ง · แผนก)
     *
     * รวมบัญชี role=admin ที่มิเรอร์มาด้วย เพื่อให้หน้าเดียวเห็นครบว่าใครเป็นแอดมินบ้าง
     * แถวที่มาจากมิเรอร์ถอดสิทธิ์ที่นี่ไม่ได้ (ต้องไปแก้ที่ Insight) — ทำเครื่องหมาย from_insight ไว้
     */
    public function admins(): Collection
    {
        try {
            $granted = AccessAdmin::query()->orderByDesc('granted_at')->get();
        } catch (Throwable) {
            $granted = collect();
        }

        $rows = collect();

        foreach (AppUser::with('employee')->where('role', 'admin')->get() as $user) {
            $rows->push($this->describeUser($user) + ['from_insight' => true, 'granted_at' => null, 'row_id' => null]);
        }

        foreach ($granted as $row) {
            if ($rows->firstWhere('employee_code', (string) $row->employee_code)) {
                continue;   // เป็นแอดมินจาก Insight อยู่แล้ว ไม่ต้องขึ้นซ้ำ
            }

            $user = AppUser::with('employee')->where('employee_code', $row->employee_code)->first();
            if (! $user) {
                continue;
            }

            $rows->push($this->describeUser($user) + [
                'from_insight' => false,
                'granted_at' => $row->granted_at?->format('d/m/Y H:i'),
                'row_id' => $row->id,
            ]);
        }

        return $rows->values();
    }

    /**
     * ค้นหาพนักงาน — ค้นด้วยรหัสพนักงานหรือชื่อ-สกุล
     *
     * ค้นจาก app_users เพราะสิทธิ์ผูกกับ "บัญชีที่ล็อกอินได้" ไม่ใช่ทะเบียนพนักงานทั้งหมด
     */
    public function searchUsers(string $term, int $limit = 12): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return AppUser::query()
            ->with('employee')
            ->where(function ($q) use ($like) {
                $q->where('employee_code', 'like', $like)
                    ->orWhere('full_name_th', 'like', $like)
                    ->orWhere('full_name_en', 'like', $like)
                    // ค้นจากทะเบียนพนักงานด้วย — พิมพ์เฉพาะนามสกุลก็ต้องเจอ
                    ->orWhereHas('employee', function ($e) use ($like) {
                        $e->where('name_th', 'like', $like)
                            ->orWhere('surname_th', 'like', $like)
                            ->orWhere('name_en', 'like', $like);
                    });
            })
            ->orderBy('employee_code')
            ->limit($limit)
            ->get()
            ->map(fn ($u) => $this->describeUser($u));
    }

    /**
     * ข้อมูลคนจากรหัสพนักงาน — ใช้วาดรายชื่อที่เลือกไว้แล้ว
     *
     * @param  array<int,string>  $codes
     * @return array<int,array<string,mixed>>
     */
    public function describeCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return AppUser::with('employee')
            ->whereIn('employee_code', $codes)
            ->orderBy('employee_code')
            ->get()
            ->map(fn ($u) => $this->describeUser($u))
            ->all();
    }

    /** จำนวนบัญชีทั้งหมด — ใช้บอกว่า "ทุกคน" คือกี่คน */
    public function totalAccounts(): int
    {
        return AppUser::query()->count();
    }

    /** ย่อข้อมูลบัญชีให้เหลือเท่าที่หน้าจอต้องใช้ — 🔴 ห้ามส่งรหัสผ่านหรือเลขบัตรออกไป */
    private function describeUser(AppUser $user): array
    {
        return [
            'employee_code' => (string) $user->employee_code,
            'name_th' => $user->displayName('th'),
            'name_en' => $user->displayName('en'),
            'position' => (string) ($user->position ?: $user->job_code),
            'job_code' => (string) $user->job_code,
            'department' => (string) $user->department,
            'company' => (string) $user->company,
            // รูปเก็บอยู่ 2 ที่ — บัญชี (app_users.profile_picture) กับทะเบียนพนักงาน (employees.photo_path)
            // Insight ย้ายไปเก็บที่ทะเบียนพนักงานแล้ว รูปจึงไม่หายตอนลาออก จึงต้องถอยไปดูที่นั่นด้วย
            'photo' => $user->avatarUrl() ?? $user->employee?->photoUrl(),
            'initial' => $user->initial(),
        ];
    }

    /** ตารางสิทธิ์ถูก migrate แล้วหรือยัง */
    public function installed(): bool
    {
        try {
            return Schema::hasTable('access_admins');
        } catch (Throwable) {
            return false;
        }
    }
}
