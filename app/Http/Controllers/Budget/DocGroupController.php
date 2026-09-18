<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\DocGroup;
use App\Services\Audit\ActivityLogger;
use App\Services\Budget\DocNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\View\View as ViewContract;

/**
 * ตั้งค่าหมายเลขเอกสาร (เจ้าของสั่ง 2026-09-17 · DECISIONS 50.10)
 *
 * แอดมินตั้ง "หมวดงบประมาณ" เอง — ชื่อหมวด 2 ภาษา + โค้ด 2–4 ตัว
 *   New Model · NM  ->  INV-NM-2569-000001  ·  BGT-NM-2569-000001
 *
 * 🔴 ในโค้ดยังเรียกว่า DocGroup / group_id — เปลี่ยนแค่คำที่ผู้ใช้เห็น (เจ้าของสั่ง 2026-09-17)
 *    ไม่ใช้คำว่า "แผนก" เพราะหน้ากรอกมีช่อง "แผนก" (แผนกที่ขอ) อยู่แล้ว คำเดียวกัน 2 ความหมายจะสับสน
 *
 * 🔴 กติกาแก้/ลบ (เจ้าของเคาะ)
 *      ยังไม่มีเอกสารออกเลข   -> แก้ชื่อ แก้โค้ด ลบ ได้ทั้งหมด
 *      มีเอกสารออกเลขแล้ว     -> แก้ได้แค่ชื่อ · โค้ดล็อก · ลบไม่ได้ ให้ปิดใช้งานแทน
 *    เหตุผล: ตัวนับเลขวิ่งนับจากหัวเลขที่ (INV-NM-2569-) ของเอกสารที่มีอยู่
 *    เปลี่ยนโค้ดทีหลังเลขวิ่งจะเริ่มใหม่ที่ 1 เงียบๆ และหมวดเดียวมี 2 โค้ดปนกันในประวัติ
 *
 * 🔴 ลบได้เฉพาะหมวดที่ไม่มีเอกสาร "เลย" รวมร่าง
 *    ลบทั้งที่มีร่างค้าง ร่างจะกำพร้าแล้วออกเลขตอนกดส่งไม่ได้
 *
 * 🔴 ไม่มีช่อง "ลำดับการแสดง" (เจ้าของสั่งเอาออก 2026-09-17)
 *    หมวดเรียงตามลำดับที่สร้าง — คอลัมน์ sort ยังอยู่ ระบบใส่เลขถัดไปให้เองตอนสร้าง
 *    (เผื่อวันหลังทำลากสลับลำดับในตาราง จะได้ไม่ต้องแก้ฐานข้อมูล)
 *
 * 🔒 ทั้งหน้าเป็นของผู้ดูแลระบบ — กันด้วย middleware bms.admin ที่ route
 */
class DocGroupController extends Controller
{
    /** โค้ด: A–Z และ 0–9 · 2–4 ตัว (เจ้าของเคาะ DECISIONS 50.3 ข้อ 7) */
    private const CODE_PATTERN = '/^[A-Z0-9]{2,4}$/';

    public function __construct(
        private readonly DocNumber $docNumber,
        private readonly ActivityLogger $log,
    ) {}

    public function index(): ViewContract
    {
        $year = (int) now()->year + 543;

        $groups = DocGroup::query()->ordered()->get()->map(function (DocGroup $group) use ($year) {
            $group->code_locked = $group->codeLocked();
            $group->can_delete = $group->docCount() === 0;
            /*
              🔴 เป็น "รูปแบบ" ไม่ใช่เลขที่จะได้ (เจ้าของสั่ง 2026-09-17)
                 ปีและเลขวิ่งจึงเขียนเป็น xx/xxx — เลขจริงรันเองตอนกดส่ง และหมวดเดียวใช้ข้ามปีงบ
            */
            $group->sample_inv = $this->docNumber->pattern('INV', $year, $group->code);
            $group->sample_bgt = $this->docNumber->pattern('BGT', $year, $group->code);

            return $group;
        });

        return View::make('budget.doc-group.index', [
            'groups' => $groups,
            // ตัวอย่างในหน้าต่างเขียนปีเป็น 25xx — ส่งไปแค่ 2 หลักหน้า ไม่ส่งปีเต็มที่หน้าไม่ได้ใช้แล้ว
            'era' => substr((string) $year, 0, 2),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $group = DocGroup::create($data + [
            // เรียงตามลำดับที่สร้าง — ต่อท้ายหมวดที่มีอยู่เสมอ
            'sort' => (int) DocGroup::max('sort') + 1,
            'active' => true,
            'created_by' => app('current_user')->employee_code,
        ]);

        $this->log->record('doc_group_created', [
            'th' => 'เพิ่มหมวดงบประมาณ '.$group->name_th.' ('.$group->code.')',
            'en' => 'Added budget category '.$group->name_en.' ('.$group->code.')',
        ], $group->code);

        return redirect()->route('budget.docgroup.index')->with('flash_success', [
            'th' => 'เพิ่มหมวด '.$group->name_th.' แล้ว',
            'en' => 'Category '.$group->name_en.' added',
        ]);
    }

    public function update(Request $request, DocGroup $group): RedirectResponse
    {
        $data = $this->validated($request, $group);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $result = DB::transaction(function () use ($group, $data) {
            // ล็อกแถวก่อนตรวจ กันแอดมินแก้โค้ดพร้อมกับที่มีคนกดส่งเอกสารในหมวดนี้
            $locked = DocGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($data['code'] !== $locked->code && $locked->codeLocked()) {
                return false;
            }

            $locked->update($data);

            return $locked;
        });

        if ($result === false) {
            return $this->fail('code', [
                'th' => 'หมวดนี้มีเอกสารออกเลขไปแล้ว แก้โค้ดไม่ได้ — แก้ได้เฉพาะชื่อ',
                'en' => 'This category already has numbered documents — only the name can be changed',
            ], $group);
        }

        $this->log->record('doc_group_updated', [
            'th' => 'แก้ไขหมวดงบประมาณ '.$result->name_th.' ('.$result->code.')',
            'en' => 'Updated budget category '.$result->name_en.' ('.$result->code.')',
        ], $result->code);

        return redirect()->route('budget.docgroup.index')->with('flash_success', [
            'th' => 'บันทึกหมวด '.$result->name_th.' แล้ว',
            'en' => 'Category '.$result->name_en.' saved',
        ]);
    }

    /**
     * เปิด/ปิดใช้งาน
     *
     * ปิดแล้ว = ไม่โผล่ในหน้าเลือกหมวดและ dropdown ของหน้ากรอก
     * เอกสารเก่าของหมวดนั้นยังดูได้ครบ และยังกรองดูในแท็บได้
     */
    public function toggle(DocGroup $group): RedirectResponse
    {
        $group->update(['active' => ! $group->active]);

        $this->log->record($group->active ? 'doc_group_enabled' : 'doc_group_disabled', [
            'th' => ($group->active ? 'เปิดใช้งานหมวดงบประมาณ ' : 'ปิดใช้งานหมวดงบประมาณ ').$group->name_th.' ('.$group->code.')',
            'en' => ($group->active ? 'Enabled budget category ' : 'Disabled budget category ').$group->name_en.' ('.$group->code.')',
        ], $group->code);

        return redirect()->route('budget.docgroup.index')->with('flash_success', $group->active
            ? ['th' => 'เปิดใช้งานหมวด '.$group->name_th.' แล้ว', 'en' => 'Category '.$group->name_en.' enabled']
            : ['th' => 'ปิดใช้งานหมวด '.$group->name_th.' แล้ว', 'en' => 'Category '.$group->name_en.' disabled']);
    }

    public function destroy(DocGroup $group): RedirectResponse
    {
        $deleted = DB::transaction(function () use ($group) {
            $locked = DocGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($locked->docCount() > 0) {
                return false;
            }

            $locked->delete();

            return true;
        });

        if (! $deleted) {
            return redirect()->route('budget.docgroup.index')->with('flash_error', [
                'th' => 'หมวด '.$group->name_th.' มีเอกสารอยู่แล้ว ลบไม่ได้ — ใช้ "ปิดใช้งาน" แทน',
                'en' => 'Category '.$group->name_en.' already has documents — disable it instead',
            ]);
        }

        $this->log->record('doc_group_deleted', [
            'th' => 'ลบหมวดงบประมาณ '.$group->name_th.' ('.$group->code.')',
            'en' => 'Deleted budget category '.$group->name_en.' ('.$group->code.')',
        ], $group->code);

        return redirect()->route('budget.docgroup.index')->with('flash_success', [
            'th' => 'ลบหมวด '.$group->name_th.' แล้ว',
            'en' => 'Category '.$group->name_en.' deleted',
        ]);
    }

    // ── ภายใน ───────────────────────────────────────────────────────

    /**
     * ตรวจค่าที่กรอก
     *
     * 🔴 โค้ดแปลงเป็นตัวพิมพ์ใหญ่และตัดช่องว่างก่อนตรวจเสมอ
     *    หน้าเว็บแปลงให้ระหว่างพิมพ์อยู่แล้ว แต่ต้องไม่เชื่อฝั่งหน้าเว็บอย่างเดียว
     *
     * @return array<string,mixed>|RedirectResponse
     */
    private function validated(Request $request, ?DocGroup $group = null): array|RedirectResponse
    {
        $request->merge([
            'code' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('code'))),
            'name_th' => trim((string) $request->input('name_th')),
            'name_en' => trim((string) $request->input('name_en')),
        ]);

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'regex:'.self::CODE_PATTERN, Rule::unique('budget_doc_groups', 'code')->ignore($group?->id)],
            // 🔴 ชื่อต้องมีครบ 2 ภาษา (กฎโปรเจค) — ไม่งั้นสลับเป็น ENG แล้วชื่อยังเป็นไทยค้าง
            'name_th' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            $failed = $validator->failed();
            $field = array_key_first($failed);

            $message = match (true) {
                isset($failed['code']['Unique']) => ['th' => 'โค้ด '.$request->input('code').' มีหมวดอื่นใช้อยู่แล้ว', 'en' => 'Code '.$request->input('code').' is already used by another category'],
                isset($failed['code']) => ['th' => 'โค้ดต้องเป็นตัวอักษร A–Z หรือตัวเลข 0–9 จำนวน 2–4 ตัว', 'en' => 'Code must be 2–4 characters of A–Z or 0–9'],
                isset($failed['name_th']) => ['th' => 'กรุณากรอกชื่อหมวดภาษาไทย (ไม่เกิน 80 ตัวอักษร)', 'en' => 'Please enter the Thai category name (max 80 characters)'],
                default => ['th' => 'กรุณากรอกชื่อหมวดภาษาอังกฤษ (ไม่เกิน 80 ตัวอักษร)', 'en' => 'Please enter the English category name (max 80 characters)'],
            };

            return $this->fail($field, $message, $group);
        }

        $data = $validator->validated();

        return [
            'code' => $data['code'],
            'name_th' => $data['name_th'],
            'name_en' => $data['name_en'],
        ];
    }

    /**
     * กลับไปหน้าเดิมพร้อมเปิดหน้าต่างที่กรอกค้างไว้
     *
     * 🔴 คืนค่าที่กรอกกลับไปด้วยเสมอ ไม่งั้นพิมพ์ผิดตัวเดียวต้องกรอกใหม่ทั้งหมด
     *
     * @param  array{th:string,en:string}  $message
     */
    private function fail(string $field, array $message, ?DocGroup $group): RedirectResponse
    {
        return redirect()->route('budget.docgroup.index')
            ->withInput()
            ->with('flash_error', $message)
            ->with('dg_reopen', $group?->id ?? 'new')
            ->with('dg_field', $field);
    }
}
