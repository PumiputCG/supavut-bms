<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Access\FunctionUser;
use App\Services\Access\AccessService;
use App\Services\Audit\ActivityLogger;
use App\Support\NavMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;

/**
 * สิทธิ์การเข้าถึงโมดูล — โมดูลไหนใครเห็นได้บ้าง (ระบุเป็นรายคน)
 *
 * รายชื่อโมดูลอ่านจาก App\Support\NavMenu ที่เดียว (ไม่มีตารางโมดูลแยก)
 * เพิ่มโมดูลใหม่ใน NavMenu แล้วจะโผล่ในหน้านี้เอง ไม่ต้องมาแก้ที่นี่
 */
class ModuleAccessController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly ActivityLogger $log,
    ) {}

    public function index(): ViewContract
    {
        $fnMap = $this->access->functionUserMap();

        $rows = collect(NavMenu::permissionedModules())->map(function (array $module) use ($fnMap) {
            $functions = collect($module['functions'] ?? [])->map(function ($fn) use ($module, $fnMap) {
                $fnRaw = $fnMap[$module['id'].'|'.$fn['key']] ?? [];
                $fnEveryone = in_array(AccessService::EVERYONE, $fnRaw, true);
                $fnCodes = array_values(array_filter($fnRaw, fn ($c) => $c !== AccessService::EVERYONE));

                return [
                    'key' => $fn['key'],
                    'th' => $fn['th'],
                    // ไอคอนของหัวข้อย่อย (เช่น "ประวัติ..." ใช้ fn-history) — ต้องส่งต่อ ไม่งั้นได้เอกสารเปล่าหมด
                    'icon' => $fn['icon'] ?? null,
                    // ข้อความอธิบายตอนยังไม่ระบุใคร — บางหัวข้อไม่ได้แปลว่า "เปิดให้ทุกคน" ตรงๆ
                    'note' => $fn['note'] ?? null,
                    // หัวข้อที่ไม่ได้ตั้งสิทธิ์ล่วงหน้า — ไม่ต้องมีปุ่มแก้ไขให้กด
                    'no_edit' => ! empty($fn['no_edit']),
                    'codes' => $fnCodes,
                    /*
                      🔴 3 สถานะ ไม่ใช่ 2 (เจ้าของสั่งกลับกติกา 2026-09-07)
                         everyone = ติ๊ก "ทุกคน" ไว้ · nobody = ยังไม่ได้กำหนด ไม่มีใครใช้ได้ · ที่เหลือ = รายชื่อ
                    */
                    'everyone' => $fnEveryone,
                    'nobody' => ! $fnEveryone && $fnCodes === [],
                    'people' => $this->access->describeCodes($fnCodes),
                    // สิทธิ์ย่อยของหัวข้อนี้ เช่น "ปรับคอลัมน์เปลี่ยนสถานะ"
                    'extras' => collect($fn['extras'] ?? [])->map(function ($extra) use ($module, $fnMap) {
                        $raw = $fnMap[$module['id'].'|'.$extra['key']] ?? [];
                        $everyone = in_array(AccessService::EVERYONE, $raw, true);
                        $codes = array_values(array_filter($raw, fn ($c) => $c !== AccessService::EVERYONE));

                        return [
                            'key' => $extra['key'],
                            'th' => $extra['th'],
                            'codes' => $codes,
                            'everyone' => $everyone,
                            'nobody' => ! $everyone && $codes === [],
                            'people' => $this->access->describeCodes($codes),
                        ];
                    })->all(),
                ];
            })->all();

            /*
              🔴 แถวโมดูลเป็น "สรุป" — ไม่มีสิทธิ์ระดับโมดูลให้ตั้งเองแล้ว (เจ้าของสั่ง 2026-09-03)
                 มีหัวข้อย่อยไหนเปิดให้ทุกคน = ทั้งโมดูลก็เห็นได้ทุกคน
                 ไม่งั้นรวมรายชื่อจากทุกหัวข้อย่อยที่ระบุตัวคนไว้
            */
            $all = collect($functions);
            $openToAll = $all->contains('everyone', true);
            $codes = $openToAll ? [] : $all->pluck('codes')->flatten()->unique()->values()->all();
            // ไม่มีหัวข้อย่อยไหนเปิดให้ใครเลย = โมดูลนี้ยังไม่มีใครเห็น
            $noBody = ! $openToAll && $codes === [];

            return [
                'id' => $module['id'],
                'key' => $module['key'],
                'th' => $module['th'],
                'icon' => $module['icon'],
                /*
                  🔴 direct = โมดูลที่ไม่มีหัวข้อย่อยจริง (เช่น แดชบอร์ด) ตั้งสิทธิ์ที่แถวตัวเองเลย
                     เจ้าของสั่ง 2026-09-10: กด "ภาพรวมระบบ" แล้วต้องเจอแดชบอร์ดทันที
                     ไม่ต้องกางอีกชั้นให้เสียเวลา เพราะข้างในมีอยู่รายการเดียว
                */
                'direct' => ! empty($module['perm_key']),
                // หัวข้อหลักที่โมดูลนี้สังกัด — ใช้จัดกลุ่มในหน้าจอ
                'group' => [
                    'key' => $module['group']['key'] ?? 'nav.modules',
                    'th' => $module['group']['th'] ?? 'โมดูลระบบงาน',
                    'icon' => $module['group']['icon'] ?? 'group',
                    'id' => $module['group']['id'] ?? 'modules',
                ],
                'functions' => $functions,
                'codes' => $codes,
                'everyone' => $openToAll,
                'nobody' => $noBody,
                'people' => $this->access->describeCodes($codes),
            ];
        })->values();

        /*
          🔴 จัดเป็น 3 ชั้นให้หน้าจอ: หัวข้อหลัก → โมดูล → หัวข้อย่อย (เจ้าของสั่ง 2026-09-10)
             เดิมหน้านี้เป็นรายการโมดูลแบนๆ ไล่หาโมดูลที่ต้องการยาก
        */
        $groups = $rows->groupBy(fn ($m) => $m['group']['id'])->map(fn ($modules) => [
            'id' => $modules->first()['group']['id'],
            'key' => $modules->first()['group']['key'],
            'th' => $modules->first()['group']['th'],
            'icon' => $modules->first()['group']['icon'],
            'modules' => $modules->values()->all(),
        ])->values();

        return View::make('access.modules', [
            'groups' => $groups,
            // ยังส่งรายการแบนไปด้วย — หน้าต่างกำหนดสิทธิ์วนจากตัวนี้ ไม่ต้องไล่ 3 ชั้น
            'modules' => $rows,
            'totalAccounts' => $this->access->totalAccounts(),
        ]);
    }

    /**
     * บันทึกสิทธิ์ของ "หัวข้อย่อย" เดียว (เจ้าของสั่ง 2026-09-03)
     *
     * 🔴 ไม่เลือกใครเลย = ใครเห็นโมดูลก็เข้าหัวข้อย่อยนี้ได้ (กติกาเดียวกับระดับโมดูล)
     */
    public function saveFunction(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'module_id' => ['required', 'string', 'max:50'],
            'function_key' => ['required', 'string', 'max:60'],
            'employee_codes' => ['nullable', 'array'],
            'employee_codes.*' => ['string', 'max:30'],
            // ติ๊ก "ทุกคน" — ส่งมาเป็น 1 แปลว่าเปิดให้ทุกคนที่เข้าระบบได้
            'everyone' => ['nullable', 'boolean'],
            'extras_everyone' => ['nullable', 'array'],
            // สิทธิ์ย่อยของหัวข้อนั้น — ส่งมาเป็น extras[<คีย์>][] = รหัสพนักงาน
            'extras' => ['nullable', 'array'],
            'extras.*' => ['nullable', 'array'],
            'extras.*.*' => ['string', 'max:30'],
        ]);

        // หัวข้อย่อยต้องมีอยู่จริงใน NavMenu — กันคนยิงคีย์มั่วเข้ามา
        $module = collect(NavMenu::permissionedModules())->firstWhere('id', $data['module_id']);
        // นับสิทธิ์ย่อยด้วย (เช่น fn.budget.approved.status)
        $valid = $module ? NavMenu::permissionKeys($module) : [];

        if (! $module || ! in_array($data['function_key'], $valid, true)) {
            $oops = ['th' => 'ไม่พบหัวข้อย่อยนี้', 'en' => 'Function not found'];

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $oops], 422)
                : back()->with('flash_error', $oops);
        }

        $everyone = (bool) ($data['everyone'] ?? false);

        /*
          🔴 ติ๊ก "ทุกคน" แล้วรายชื่อรายคนไม่มีความหมาย — เก็บแถวเดียวคือ EVERYONE
             ถ้าเก็บทั้งคู่ไว้ พอปลดติ๊กทีหลังจะเหลือรายชื่อค้างที่ผู้ดูแลไม่ได้ตั้งใจ
        */
        $codes = $everyone
            ? [AccessService::EVERYONE]
            : array_values(array_unique($data['employee_codes'] ?? []));

        $by = app()->bound('current_user') ? app('current_user')->employee_code : null;

        // สิทธิ์ย่อยที่ส่งมาด้วย — รับเฉพาะคีย์ที่มีอยู่จริงใน NavMenu
        $extras = [];

        $extrasEveryone = (array) ($data['extras_everyone'] ?? []);

        foreach ((array) ($data['extras'] ?? []) as $key => $list) {
            if (! in_array((string) $key, $valid, true)) {
                continue;
            }

            $extras[(string) $key] = ! empty($extrasEveryone[$key])
                ? [AccessService::EVERYONE]
                : array_values(array_unique($list ?? []));
        }

        DB::transaction(function () use ($data, $codes, $extras, $by) {
            $write = function (string $key, array $people) use ($data, $by) {
                // เขียนใหม่ทั้งชุด — เอาคนออกจากรายการก็ต้องหายจริง
                FunctionUser::where('module_id', $data['module_id'])
                    ->where('function_key', $key)
                    ->delete();

                foreach ($people as $code) {
                    FunctionUser::create([
                        'module_id' => $data['module_id'],
                        'function_key' => $key,
                        'employee_code' => $code,
                        'updated_by' => $by,
                    ]);
                }
            };

            $write($data['function_key'], $codes);

            foreach ($extras as $key => $people) {
                $write($key, $people);
            }
        });

        $real = $everyone ? [] : $codes;

        $how = $everyone
            ? ['th' => 'เปิดให้ทุกคนที่เข้าระบบได้', 'en' => 'open to everyone who can sign in']
            : ($real === []
                ? ['th' => 'ไม่ระบุใคร — ไม่มีใครใช้ได้', 'en' => 'nobody selected — no one can use it']
                : ['th' => 'ระบุ '.count($real).' คน', 'en' => count($real).' people']);

        $this->log->record('function_access_saved', [
            'th' => 'แก้สิทธิ์หัวข้อย่อย '.$data['function_key'].' — '.$how['th'],
            'en' => 'Changed function access '.$data['function_key'].' — '.$how['en'],
        ], $data['function_key']);

        $message = $everyone
            ? ['th' => 'ตั้งเป็น "ทุกคนที่เข้าระบบได้" แล้ว', 'en' => 'Set to everyone who can sign in']
            : ($real === []
                ? ['th' => 'บันทึกแล้ว — ยังไม่ได้กำหนดใคร จึงยังไม่มีใครใช้หัวข้อนี้ได้', 'en' => 'Saved — nobody assigned yet, so no one can use it']
                : ['th' => 'บันทึกสิทธิ์หัวข้อย่อยแล้ว ('.count($real).' คน)', 'en' => 'Function rights saved ('.count($real).' people)']);

        /*
          🔴 เรียกแบบ AJAX -> ตอบ JSON กลับไป ไม่ redirect
             เพื่อให้หน้าต่างเลือกคนค้างอยู่ กดบันทึกแล้วเลือกคนต่อได้เลย (เจ้าของสั่ง 2026-09-04)
             ส่งรายชื่อล่าสุดกลับไปด้วย หน้าจะได้อัปเดตรูปในตารางโดยไม่ต้องโหลดใหม่
        */
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'everyone' => $everyone,
                'people' => $this->access->describeCodes($real),
                'extras' => collect($extras)->map(fn ($list) => $this->access->describeCodes(
                    array_values(array_filter($list, fn ($c) => $c !== AccessService::EVERYONE))
                ))->all(),
                'extras_everyone' => collect($extras)->map(fn ($list) => in_array(AccessService::EVERYONE, $list, true))->all(),
            ]);
        }

        return back()->with('flash_success', $message);
    }
}
