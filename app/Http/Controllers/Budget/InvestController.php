<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\Approval;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Budget\InvestFile;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Access\AccessService;
use App\Services\Audit\ActivityLogger;
use App\Services\Budget\BudgetAccess;
use App\Services\Budget\DocGroupTabs;
use App\Services\Budget\DocNumber;
use App\Services\Budget\InvestFlow;
use App\Services\Core\Notifier;
use App\Support\FileIcon;
use App\Support\FinalApprover;
use App\Support\NavMenu;
use App\Support\PdfPrinter;
use App\Support\TableFilter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View as ViewContract;
use RuntimeException;
use Throwable;

/**
 * เสนอ Invest — ฝ่ายบัญชีสร้างข้อเสนอขอตั้งงบ
 *
 * 🔴 แก้ไขได้เฉพาะตอนเป็นร่าง (DRAFT) — ส่งเรื่องแล้วห้ามแก้
 *    ไม่งั้นคนที่เซ็นรับทราบไปแล้วจะเซ็นให้กับเนื้อหาที่ไม่ตรงกับของจริง
 */
class InvestController extends Controller
{
    public function __construct(
        private readonly BudgetAccess $access,
        private readonly InvestFlow $flow,
        private readonly DocNumber $docNumber,
        private readonly ActivityLogger $log,
        private readonly AccessService $systemAccess,
        private readonly Notifier $notify,
        private readonly DocGroupTabs $groupTabs,
    ) {}

    /**
     * ของบประมาณ
     *
     * 🔴 มี 2 หน้าจอใน route เดียว (เจ้าของสั่ง 2026-09-17)
     *      ไม่มี ?group=   -> หน้าคั่น "เลือกหมวดงบประมาณ" อย่างเดียว
     *      มี ?group=      -> หน้าตารางของหมวดนั้น (all = ทุกหมวด) · ปุ่มเสนอรายการใหม่อยู่ที่นี่
     *    ผู้ใช้เห็นคำว่า "หมวดงบประมาณ" · ในโค้ดยังชื่อ group (เปลี่ยนแค่คำที่ผู้ใช้เห็น 2026-09-17)
     *    ใช้ route เดิมเพื่อให้เมนูย่อยกับเส้นทางนำทางไฮไลต์ "ของบประมาณ" ได้ทั้ง 2 หน้าโดยไม่ต้องแตะของกลาง
     */
    public function index(Request $request): ViewContract
    {
        $this->requireProposer($request);

        if (! $request->has('group')) {
            return $this->chooser();
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        // เส้นทางเอกสาร (timeline) ต้องใช้ approvals + budget — โหลดมาพร้อมกัน กันยิงคิวรีทีละแถว
        $query = $this->visibleInvests()->with(['approvals', 'budget', 'group'])->orderByDesc('id');

        /*
          แท็บกลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17)
          🔴 นับก่อนกรองกลุ่ม ทุกแท็บจะได้โชว์ยอดของตัวเอง · แล้วค่อยกรองตามแท็บที่เลือก
        */
        $groupTabs = $this->groupTabs->tabs($this->groupTabs->countQuery($query), $this->groupTabs->picked($request));
        $this->groupTabs->applyQuery($query, $groupTabs['picked']);

        // กลุ่มที่กำลังดู — ปุ่ม "เสนอรายการใหม่" ขึ้นเฉพาะกลุ่มที่ยังเปิดใช้งาน
        $currentGroup = ctype_digit($groupTabs['picked']) ? DocGroup::find((int) $groupTabs['picked']) : null;

        if (! empty($filters['status'])) {
            $query->where('approval_status', $filters['status']);
        }

        // ตัวกรองรายคอลัมน์ทำที่เซิร์ฟเวอร์ — กรองได้ครบทุกหน้า (เจ้าของสั่ง 2026-09-04)
        $map = self::filterMap();
        $options = TableFilter::options(clone $query, $map);
        $picked = TableFilter::picked($request, $map);
        TableFilter::apply($query, $request, $map);

        /*
          🔴 นับจาก "ชุดที่ผ่านตัวกรองแล้ว" เสมอ (บทเรียน 2026-09-07)
             กรองอยู่แล้วตัวเลขข้างบนยังเป็นของทั้งหมด ผู้ใช้จะอ่านผิดทันที
          🔴 ต้อง reorder() ก่อน groupBy — คิวรีมี orderByDesc('id') ติดมา
             MySQL ไม่ยอมให้ ORDER BY คอลัมน์ที่ไม่ได้อยู่ใน SELECT ตอนจัดกลุ่ม
        */
        $byStatus = (clone $query)->reorder()
            ->selectRaw('approval_status, COUNT(*) AS n')
            ->groupBy('approval_status')
            ->pluck('n', 'approval_status')
            ->all();

        $rows = $query->paginate(20)->withQueryString();

        return View::make('budget.invest.index', [
            'rows' => $rows,
            'summary' => [
                'total' => array_sum($byStatus),
                'draft' => $byStatus[Invest::DRAFT] ?? 0,
                'pending' => $byStatus[Invest::PENDING] ?? 0,
                'approved' => $byStatus[Invest::APPROVED] ?? 0,
                'rejected' => $byStatus[Invest::REJECTED] ?? 0,
            ],
            'filters' => $filters,
            'filterOptions' => $options,
            'filterPicked' => $picked,
            'statuses' => Invest::STATUS_LABELS,
            'groupTabs' => $groupTabs,
            'currentGroup' => $currentGroup,
            // รูปคนในสายเอกสาร — ดึงรวดเดียวให้ timeline ใช้
            'signerPhotos' => $this->photosFor($rows->getCollection()),
        ]);
    }

    /**
     * หน้าคั่น "เลือกหมวดงบประมาณ" (เจ้าของสั่ง 2026-09-17)
     *
     * มีแต่การ์ดหมวด — กดแล้วเข้าหน้าตารางของหมวดนั้น
     * 🔴 เจ้าของสั่งให้กระชับ — การ์ดไม่โชว์จำนวนเอกสาร นับแค่ว่ามีเอกสารไหม (ลิงก์ดูทุกหมวด)
     */
    private function chooser(): ViewContract
    {
        $counts = $this->groupTabs->countQuery($this->visibleInvests());

        return View::make('budget.invest.choose', [
            'pickGroups' => $this->pickableGroups(),
            // ทางเข้าเอกสารทุกหมวด — เอกสารของหมวดที่ถูกปิดไปแล้วจะได้ยังเปิดดูได้
            'totalDocs' => array_sum($counts),
            // ไม่มีหมวดให้เลือก — แอดมินได้ลิงก์ไปหน้าตั้งค่า คนทั่วไปได้ข้อความให้ติดต่อผู้ดูแลระบบ
            'canManageGroups' => $this->systemAccess->isAdmin(app('current_user')),
        ]);
    }

    /**
     * เอกสารที่คนนี้เห็นในหน้าของบประมาณ
     *
     * หน้าผู้เสนอเป็นพื้นที่จัดการเอกสารของคนกรอก ไม่ใช่รายการรวมของทั้งฝ่าย — แอดมินเห็นทุกใบ
     * 🔴 ใช้ร่วมหน้าคั่นกับหน้าตาราง ขอบเขตจะได้ไม่หลุดจากกัน
     */
    private function visibleInvests()
    {
        $me = app('current_user');
        $query = Invest::query();

        if (! $this->systemAccess->isAdmin($me)) {
            $query->where('created_by', $me->employee_code);
        }

        return $query;
    }

    /** หน้าตารางของกลุ่มที่เอกสารนี้อยู่ — ใช้เป็นปลายทางหลังส่ง/ลบ */
    private function listUrl(Invest $invest): string
    {
        return route('budget.invest.index', ['group' => $invest->group_id ?: DocGroupTabs::ALL]);
    }

    /**
     * คอลัมน์ที่กรองได้ของหน้านี้
     *
     * @return array<string,array<string,mixed>>
     */
    private static function filterMap(): array
    {
        return [
            'doc' => ['column' => 'doc_no'],
            'year' => ['column' => 'fiscal_year'],
            'title' => ['column' => 'title'],
            'dept' => ['column' => 'dept_name'],
            'amount' => ['column' => 'amount', 'format' => 'money'],
            'status' => ['column' => 'approval_status', 'labels' => Invest::STATUS_LABELS],
        ];
    }

    public function create(Request $request): ViewContract|RedirectResponse
    {
        $this->requireProposer($request);

        /*
          🔴 เข้าหน้ากรอกต้องมาพร้อมกลุ่มที่เลือกเสมอ (เจ้าของสั่ง 2026-09-17)
             กลุ่มที่คลิกมา = ค่าเริ่มต้นของ dropdown ในหน้ากรอก
             พิมพ์ URL ตรงโดยไม่เลือกกลุ่ม หรือกลุ่มถูกปิดไปแล้ว -> พากลับไปหน้าเลือกกลุ่ม
        */
        $groups = DocGroup::query()->active()->ordered()->get();

        if ($groups->isEmpty()) {
            return redirect()->route('budget.invest.index')->with('flash_error', [
                'th' => 'ยังไม่มีหมวดงบประมาณที่เปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
                'en' => 'No active budget category yet — please contact the administrator',
            ]);
        }

        $groupId = (int) $request->query('group');

        if (! $groups->contains('id', $groupId)) {
            return redirect()->route('budget.invest.index')->with('flash_error', [
                'th' => 'กรุณาเลือกหมวดงบประมาณก่อน',
                'en' => 'Please choose a budget category first',
            ]);
        }

        /*
          🔴 ไม่เติมชื่อคนที่ล็อกอินเป็นผู้เสนอ (เจ้าของย้ำ 2026-09-04)
             ผู้เสนอ = คนที่เดินมาขอตั้งงบ ไม่ใช่ฝ่ายบัญชีที่นั่งกรอกให้
             เติมไว้ให้จะทำให้กดผ่านไปโดยไม่ได้เลือก แล้วเอกสารจะบอกผิดคน
        */
        return View::make('budget.invest.form', $this->formData(new Invest([
            'group_id' => $groupId,
            'fiscal_year' => $this->defaultFiscalYear(),
            'proposed_at' => now()->toDateString(),
        ])));
    }

    /**
     * หน้าเอกสารของผู้เสนอ
     *
     * เปิดได้ตอนร่างและตอนรออนุมัติ — ตอนรออนุมัติเข้ามาได้เพื่อกดลบ แต่แก้ไขไม่ได้
     * (ตัวกันการแก้ไขจริงอยู่ที่ update() ซึ่งเรียก requireDraft)
     */
    public function edit(Request $request, Invest $invest): ViewContract
    {
        $this->requireProposer($request);
        $this->requireOwner($invest);

        // เปิดเอกสารเอง = อ่านแจ้งเตือนของใบนี้แล้ว (เจ้าของแจ้ง 2026-09-07)
        $this->notify->markReadForDoc(app()->bound('current_user') ? app('current_user') : null, $invest->doc_no);

        if (! $invest->canDelete()) {
            abort(403, 'เอกสารที่ตัดสินไปแล้วเปิดแก้ไขไม่ได้ / This document can no longer be edited');
        }

        return View::make('budget.invest.form', $this->formData($invest->load('files')));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireProposer($request);
        $data = $this->validated($request);

        $invest = DB::transaction(function () use ($data, $request) {
            $me = app('current_user');
            $dept = $this->department($data['dept_code']);

            /*
              🔴 ยังไม่ออกเลขที่ตอนบันทึกร่าง (เจ้าของสั่ง 2026-09-17)
                 เลขออกตอนกดส่งใน InvestFlow::submit() — ร่างที่ไม่ยอมส่งจะได้ไม่จองเลขค้าง
            */
            $invest = Invest::create($data['head'] + [
                'doc_no' => null,
                'dept_name' => $dept,
                'approval_status' => Invest::DRAFT,
                'created_by' => $me->employee_code,
                'created_by_name' => $me->displayName('th'),
                'created_by_name_en' => $me->displayName('en'),
            ]);

            $this->storeFiles($request, $invest);
            $this->saveApprovers($invest, $data['approvers'], $data['roles']);

            return $invest;
        });

        $label = $this->docLabel($invest);
        $this->log->record('invest_created', [
            'th' => 'สร้างร่างของบประมาณ '.$label['th'],
            'en' => 'Created budget request draft '.$label['en'],
        ], $invest->doc_no);

        return redirect()->route('budget.invest.edit', $invest)->with('flash_success', [
            'th' => 'บันทึกร่างแล้ว — เลขที่จะออกให้เมื่อกดส่งเอกสาร',
            'en' => 'Draft saved — a number will be issued when you submit',
        ]);
    }

    public function update(Request $request, Invest $invest): RedirectResponse
    {
        $this->requireProposer($request);
        $this->requireOwner($invest);
        $this->requireDraft($invest);
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request, $invest) {
            $invest->update($data['head'] + ['dept_name' => $this->department($data['dept_code'])]);
            $this->storeFiles($request, $invest);
            $this->saveApprovers($invest, $data['approvers'], $data['roles']);
        });

        $label = $this->docLabel($invest);
        $this->log->record('invest_updated', [
            'th' => 'แก้ไขร่างของบประมาณ '.$label['th'],
            'en' => 'Updated budget request draft '.$label['en'],
        ], $invest->doc_no);

        return back()->with('flash_success', [
            'th' => 'บันทึกแล้ว',
            'en' => 'Saved',
        ]);
    }

    /** ส่งเอกสาร — เลือกผู้รับทราบ (เห็นเฉยๆ) + ผู้อนุมัติ (เซ็นจริง) */
    public function submit(Request $request, Invest $invest): RedirectResponse
    {
        $this->requireProposer($request);
        $this->requireOwner($invest);
        $this->requireDraft($invest);

        $data = $request->validate([
            'approvers' => ['nullable', 'array', 'max:'.config('bms.max_approvers', 8)],
            'approvers.*' => $this->activeAccountRules(),
            // บทบาทรายคน — คู่ index กับ approvers (เจ้าของสั่ง 2026-09-16)
            'roles' => ['nullable', 'array', 'max:'.config('bms.max_approvers', 8)],
            'roles.*' => ['nullable', 'in:approve,notice'],
        ]);

        /*
          🔴 ผู้ขอเลือกผู้อนุมัติเองตามลำดับ 1,2,3,… (เจ้าของสั่ง 2026-09-09 — ยกกติกามาจาก Memo)
             ไม่ได้ส่งมาใหม่ = ใช้ลำดับที่บันทึกไว้ตอนร่าง
        */
        $approvers = $this->cleanCodes($data['approvers'] ?? []);
        /*
          🔴 บทบาทต้องจับคู่กับรหัสด้วย "ตำแหน่งในรายการเดิม" ก่อน cleanCodes จะตัดตัวซ้ำ/ว่างทิ้ง
             ไม่งั้น index จะเลื่อน แล้วบทบาทไปตกใส่คนผิดแบบเงียบๆ
        */
        $roles = $this->rolesByCode($data['approvers'] ?? [], $data['roles'] ?? []);

        if ($approvers === []) {
            $approvers = $this->savedApproverCodes($invest);
            $roles = [];
        }

        /*
          🔴 เลือกผู้อนุมัติได้จากพนักงานทุกคน (เจ้าของสั่ง 2026-09-09)
             จึงไม่กันด้วยทะเบียนสิทธิ์อีก — ยังกันด้วย "ต้องเป็นบัญชีที่ใช้งานอยู่" ข้างล่าง
        */
        /*
          🔴 ต่อท้ายด้วย CEO เสมอ ถอดหรือสลับลำดับไม่ได้ (เจ้าของสั่ง 2026-09-09)
             ตัดออกจากกลางสายก่อน เผื่อผู้ขอเผลอเลือกไว้ด้วย จะได้ไม่เซ็น 2 รอบ
        */
        $final = FinalApprover::code();

        if ($final !== '') {
            $approvers = array_values(array_diff($approvers, [$final]));
            $approvers[] = $final;
            // 🔴 CEO เป็นผู้ลงนามปิดท้ายเสมอ เปลี่ยนเป็น "แจ้งให้ทราบ" ไม่ได้
            $roles[$final] = Approval::APPROVE;
        }

        if ($approvers === []) {
            return back()->with('flash_error', [
                'th' => 'ต้องเลือกผู้อนุมัติอย่างน้อย 1 คน',
                'en' => 'At least one approver is required',
            ]);
        }

        // เลือกเป็น "แจ้งให้ทราบ" กันหมดจนไม่เหลือคนเซ็น = ส่งไม่ได้
        if (! array_filter($approvers, fn (string $code) => ($roles[$code] ?? Approval::APPROVE) === Approval::APPROVE)) {
            return back()->with('flash_error', [
                'th' => 'ต้องมีผู้อนุมัติที่ต้องลงนามอย่างน้อย 1 คน',
                'en' => 'At least one person must be set to approve',
            ]);
        }

        $this->requireActiveAccounts($approvers, 'ผู้อนุมัติ / Approver');

        /*
          🔴 ผู้ขอไม่ต้องลงนามแล้ว (เจ้าของสั่ง 2026-09-10)
             ช่องลงชื่อในเอกสารมีแต่ผู้อนุมัติ — คนกรอกไม่ได้เป็นคนอนุมัติอะไร
             จึงไม่ต้องประทับลายเซ็นให้ และไม่ต้องมีลายเซ็นใน Insight ถึงจะส่งได้
        */

        try {
            /*
              🔴 ติดธง is_final ให้คนที่เป็น CEO ไปด้วย
                 ไม่งั้นแถวที่เขียนตอนส่งจะไม่มีธง แล้วป้าย "ผู้ลงนามปิดท้าย" หาย
                 และตอนตีกลับส่งใหม่จะแยกไม่ออกว่าใครคือแถวปิดท้ายเก่า
            */
            $people = array_map(
                fn (string $code) => $this->person($code) + [
                    'is_final' => $final !== '' && $code === $final,
                    'action' => $roles[$code] ?? Approval::APPROVE,
                ],
                $approvers,
            );

            $this->flow->submit($invest, $people);
        } catch (RuntimeException $e) {
            return back()->with('flash_error', ['th' => $e->getMessage(), 'en' => $e->getMessage()]);
        }

        // 🔴 เลขที่เพิ่งออกใน InvestFlow — ตัวแปรในนี้ยังเป็นค่าก่อนส่ง ต้องอ่านกลับจากฐานข้อมูลก่อน
        $invest->refresh();

        // กลับไปหน้าตารางของกลุ่มเอกสารนี้ ไม่ใช่หน้าคั่นเลือกกลุ่ม
        return redirect()->to($this->listUrl($invest))->with('flash_success', [
            'th' => 'ส่งเรื่องเข้าสายอนุมัติแล้ว: '.$invest->doc_no,
            'en' => 'Submitted for approval: '.$invest->doc_no,
        ]);
    }

    /**
     * ลบเอกสาร
     *
     * 🔴 ลบได้เฉพาะร่าง หรือส่งแล้วแต่ยังไม่มีใครตัดสิน (เจ้าของกำหนด 2026-09-03)
     *    อนุมัติแล้ว/ไม่อนุมัติแล้ว ลบไม่ได้ — เอกสารที่ตัดสินไปแล้วต้องเก็บไว้ตรวจย้อนได้
     */
    public function destroy(Request $request, Invest $invest): RedirectResponse
    {
        $this->requireProposer($request);
        $this->requireOwner($invest);

        $no = $invest->doc_no;
        $label = $this->docLabel($invest);
        $filePaths = [];
        $deleted = false;

        DB::transaction(function () use ($invest, &$filePaths, &$deleted) {
            /*
              ล็อกหัวเอกสารก่อนตรวจสายอนุมัติ เพื่อไม่ให้การเซ็นกับการลบเกิดพร้อมกัน
              InvestFlow ล็อกหัวเอกสารตัวเดียวกันก่อนเซ็น จึงตัดสินได้เพียงฝั่งเดียว
            */
            $locked = Invest::whereKey($invest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canDelete()) {
                return;
            }

            $filePaths = $locked->files()->pluck('path')->filter()->all();
            Approval::where('doc_type', Approval::DOC_INVEST)->where('doc_id', $locked->id)->delete();
            $locked->delete();
            $deleted = true;
        });

        if (! $deleted) {
            return back()->with('flash_error', [
                'th' => 'เอกสารที่มีผู้อนุมัติดำเนินการแล้วลบไม่ได้',
                'en' => 'A document cannot be deleted after an approver has acted',
            ]);
        }

        foreach ($filePaths as $path) {
            $this->deleteStoredFile($path);
        }
        Storage::disk('local')->deleteDirectory('budget/'.$invest->id);
        Storage::disk('public')->deleteDirectory('budget/'.$invest->id);

        $this->log->record('invest_deleted', [
            'th' => 'ลบเอกสาร '.$label['th'],
            'en' => 'Deleted document '.$label['en'],
        ], $no);

        return redirect()->to($this->listUrl($invest))->with('flash_success', [
            'th' => 'ลบเอกสาร '.$label['th'].' แล้ว',
            'en' => 'Document '.$label['en'].' deleted',
        ]);
    }

    public function removeFile(Request $request, InvestFile $file): RedirectResponse
    {
        $this->requireProposer($request);
        $invest = $file->invest_id ? Invest::findOrFail($file->invest_id) : new Invest;
        $this->requireOwner($invest);
        $this->requireDraft($invest);

        $path = $file->path;
        $name = $file->original_name;
        $file->delete();
        $this->deleteStoredFile($path);

        $label = $this->docLabel($invest);
        $this->log->record('invest_file_removed', [
            'th' => 'ลบไฟล์แนบ '.$name.' จาก '.$label['th'],
            'en' => 'Removed attachment '.$name.' from '.$label['en'],
        ], $invest->doc_no);

        return back()->with('flash_success', ['th' => 'ลบไฟล์แนบแล้ว', 'en' => 'Attachment removed']);
    }

    // ── ภายใน ───────────────────────────────────────────────────────

    private function requireProposer(Request $request): void
    {
        $me = app()->bound('current_user') ? app('current_user') : null;

        if (! $this->access->canPropose($me)) {
            abort(403, 'เฉพาะฝ่ายบัญชีเท่านั้น / Accounting only');
        }
    }

    private function requireDraft(Invest $invest): void
    {
        if (! $invest->isDraft()) {
            abort(403, 'แก้ไขได้เฉพาะเอกสารที่เป็นร่าง / Only drafts can be edited');
        }
    }

    private function requireOwner(Invest $invest): void
    {
        $me = app('current_user');

        if (! $this->systemAccess->isAdmin($me) && (string) $invest->created_by !== (string) $me->employee_code) {
            abort(403, 'แก้ไขได้เฉพาะเอกสารที่คุณสร้าง / You can only manage documents you created');
        }
    }

    /** @return array{head:array<string,mixed>,dept_code:string} */
    private function validated(Request $request): array
    {
        /*
          🔴 ตัดลูกน้ำออกก่อน validate เสมอ
             ช่องกรอกเงินโชว์เป็น "180,000.00" ปกติ JS จะส่งตัวเลขล้วนมาให้ในช่องซ่อนอยู่แล้ว
             แต่ถ้า JS ไม่ทำงาน หรือมีคนยิงค่าตรงเข้ามา ต้องไม่ตกม้าตายที่กฎ numeric
        */
        if ($request->filled('amount')) {
            $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        }

        $data = $request->validate([
            // กลุ่มเอกสาร — ตัวกำหนดโค้ดในเลขที่ (เจ้าของสั่ง 2026-09-17) · ตรวจว่าเปิดใช้งานอยู่ข้างล่าง
            'group_id' => ['required', 'integer'],
            'fiscal_year' => ['required', 'integer', 'min:2500', 'max:2600'],
            // 🔴 "ผู้ขอ" = คนที่เดินมาขอตั้งงบ ไม่ใช่คนที่นั่งกรอกให้ (เจ้าของเปลี่ยนคำเรียก 2026-09-09)
            'proposer_code' => ['required', ...$this->activeEmployeeRules(30)],
            'dept_code' => [
                'required',
                'string',
                'max:20',
                Rule::exists('employees', 'dept_code')->where(fn ($query) => $this->activeEmployeeQuery($query)),
            ],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999'],
            'files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,xls,xlsx,doc,docx,csv', 'max:10240'],
            /*
              สำเนาเรียน = ผู้อนุมัติ "ตามลำดับ" ที่ผู้ขอเลือกเอง (เจ้าของสั่ง 2026-09-09)
              🔴 เก็บตั้งแต่ตอนบันทึกร่าง และ "ลำดับสำคัญ" ห้าม sort ทับ
            */
            'approvers' => ['nullable', 'array', 'max:'.config('bms.max_approvers', 8)],
            'approvers.*' => $this->activeAccountRules(),
            // บทบาทรายคน คู่ index กับ approvers (เจ้าของสั่ง 2026-09-16)
            'roles' => ['nullable', 'array', 'max:'.config('bms.max_approvers', 8)],
            'roles.*' => ['nullable', 'in:approve,notice'],
        ]);

        /*
          🔴 กลุ่มต้องมีอยู่จริงและยังเปิดใช้งาน — ตอบกลับเป็นข้อความ 2 ภาษา ไม่ใช่ตก validate เงียบๆ
             (หน้ากรอกไม่ได้แสดง $errors — ปล่อยให้ตกกฎ exists ผู้ใช้จะเห็นหน้าเดิมโดยไม่รู้สาเหตุ)
             เคสจริง: เปิดหน้ากรอกค้างไว้ แล้วแอดมินปิดกลุ่มนั้นก่อนกดบันทึก
        */
        if (! DocGroup::query()->active()->whereKey((int) $data['group_id'])->exists()) {
            throw new HttpResponseException(back()->withInput()->with('flash_error', [
                'th' => 'หมวดงบประมาณที่เลือกถูกปิดใช้งานหรือไม่มีอยู่แล้ว กรุณาเลือกหมวดใหม่',
                'en' => 'The selected budget category is disabled or no longer exists — please choose another',
            ]));
        }

        return [
            'head' => [
                'group_id' => (int) $data['group_id'],
                'fiscal_year' => (int) $data['fiscal_year'],
                'proposer_code' => (string) $data['proposer_code'],
                // 🔴 ชื่อหาเองจากรหัสเสมอ ห้ามเชื่อชื่อที่ส่งมาจากหน้าเว็บ
                //    (ยิงตรงมาแล้วใส่ชื่อคนอื่นได้ เอกสารจะโกหกว่าใครเป็นคนเสนอ)
                'proposer_name' => $this->person($data['proposer_code'])['employee_name']
                    ?: (string) $data['proposer_code'],
                'proposer_name_en' => $this->person($data['proposer_code'])['employee_name_en']
                    ?: (string) $data['proposer_code'],
                'dept_code' => $data['dept_code'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
            ],
            'dept_code' => $data['dept_code'],
            /*
              🔴 ตัดคนซ้ำออกแต่ "คงลำดับเดิม" — array_unique รักษาลำดับให้อยู่แล้ว
                 ห้ามใช้ sort/array_flip ที่นี่ เพราะลำดับคือสายอนุมัติ
            */
            'approvers' => array_values(array_unique(array_filter(
                array_map(strval(...), $data['approvers'] ?? []),
                fn (string $code) => $code !== '',
            ))),
            // 🔴 จับคู่บทบาทจากรายการ "ดิบ" ก่อนตัดตัวว่าง/ซ้ำ ไม่งั้น index เลื่อนแล้วตกใส่คนผิด
            'roles' => $this->rolesByCode($data['approvers'] ?? [], $data['roles'] ?? []),
        ];
    }

    /**
     * คนพวกนี้อยู่ในรายชื่อที่อนุญาตของหัวข้อย่อยนี้ไหม
     *
     * 🔴 ติ๊ก "ทุกคน" ไว้ = เลือกใครก็ได้ · ระบุรายชื่อไว้ = ต้องอยู่ในรายชื่อนั้น
     *    ยังไม่ระบุใครเลย = ไม่มีใครมีสิทธิ์ขั้นนี้ เลือกใครมาก็ไม่ผ่าน (กติกาใหม่ 2026-09-07)
     *
     * @param  array<int,string>  $codes
     */
    private function requireAllowed(array $codes, string $functionKey, string $label): void
    {
        /*
          🔴 หัวข้อที่ตั้งสิทธิ์ไม่ได้ (no_edit) ห้ามเอามากั้นใคร
             เช่น "รับทราบ" ที่ผู้เสนอเลือกคนเองตอนส่ง — รายชื่อว่างตลอด
             ถ้าไม่ดักตรงนี้ จะกลายเป็นส่งเอกสารไม่ได้เลย (บั๊กจริง 2026-09-07)
        */
        if (NavMenu::isUnsettable($functionKey)) {
            return;
        }

        if ($this->access->isEveryone($functionKey)) {
            return;
        }

        $allowed = $this->access->codesForFunction($functionKey);

        foreach ($codes as $code) {
            if (! in_array((string) $code, $allowed, true)) {
                abort(403, 'เลือก'.$label.'ที่ไม่มีสิทธิ์ไม่ได้ / Selected person is not allowed for this step');
            }
        }
    }

    /**
     * รูปพนักงานของทุกคนที่อยู่ในเอกสารพวกนี้
     *
     * ดึงรวดเดียวแล้วส่งเป็นแผนที่ให้ view — ไม่งั้นตารางยิงคิวรีทีละแถว
     *
     * @return array<string,?string>
     */
    private function photosFor($docs): array
    {
        // 🔴 ต้องรวมผู้เสนอด้วย ไม่งั้นด่าน "สร้างเอกสาร" ในเส้นทางเอกสารจะไม่มีรูป
        $codes = $docs->pluck('approvals')->flatten()->pluck('employee_code')
            ->merge($docs->pluck('created_by'))
            ->merge($docs->pluck('proposer_code'))
            ->filter()->unique()->values()->all();

        if ($codes === []) {
            return [];
        }

        return collect($this->access->describeCodes($codes))->pluck('photo', 'employee_code')->all();
    }

    /**
     * เก็บรายชื่อผู้รับทราบที่เลือกไว้ตั้งแต่ตอนบันทึกร่าง
     *
     * 🔴 เขียนใหม่ทั้งชุดทุกครั้ง — ผู้ใช้เอาคนออกจากรายการก็ต้องหายจริง
     *    สถานะยังเป็น WAITING ("จะส่งให้ทราบ") จนกว่าจะกดส่ง แล้ว InvestFlow ค่อยเปลี่ยนเป็น NOTIFIED
     *
     * @param  array<int,string>  $codes
     */
    /**
     * ตัดค่าว่างและคนซ้ำออก แต่ "คงลำดับเดิม" ไว้เสมอ
     *
     * @param  array<int,mixed>  $codes
     * @return array<int,string>
     */
    private function cleanCodes(array $codes): array
    {
        return array_values(array_unique(array_filter(
            array_map(strval(...), $codes),
            fn (string $code) => trim($code) !== '',
        )));
    }

    /**
     * จับคู่ "บทบาท" กับ "รหัสพนักงาน" จากฟอร์ม (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 ต้องจับคู่ด้วยตำแหน่งในรายการ "ดิบ" ก่อน cleanCodes() จะตัดตัวว่าง/ตัวซ้ำทิ้ง
     *    ไม่งั้น index จะเลื่อน แล้วบทบาทไปตกใส่คนผิดแบบเงียบๆ ซึ่งจับได้ยากมาก
     *
     * @param  array<int,mixed>  $codes  รหัสตามที่ฟอร์มส่งมา (อาจมีช่องว่างปนอยู่)
     * @param  array<int,mixed>  $roles  บทบาทคู่ index กับ $codes
     * @return array<string,string> รหัสพนักงาน => Approval::APPROVE|ACK
     */
    private function rolesByCode(array $codes, array $roles): array
    {
        $out = [];

        foreach ($codes as $i => $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            // เลือกคนเดิมซ้ำ 2 แถว — เอาบทบาทของแถวแรกไว้ ให้ตรงกับ cleanCodes() ที่เก็บตัวแรก
            $out[$code] ??= (($roles[$i] ?? 'approve') === 'notice') ? Approval::ACK : Approval::APPROVE;
        }

        return $out;
    }

    /**
     * ลำดับผู้อนุมัติที่บันทึกไว้ตอนร่าง
     *
     * @return array<int,string>
     */
    private function savedApproverCodes(Invest $invest): array
    {
        /*
          🔴 ไม่เอาแถวปิดท้ายมาด้วย — ตอนกดส่งจะต่อ CEO "คนปัจจุบัน" ให้ใหม่เสมอ
             ถ้าเอามาด้วย ร่างเก่าที่ค้างชื่อ CEO คนเดิมจะกลายเป็นผู้อนุมัติกลางสาย
        */
        return Approval::where('doc_type', Approval::DOC_INVEST)
            ->where('doc_id', $invest->id)
            ->where('action', Approval::APPROVE)
            ->where('is_final', false)
            ->orderBy('round')
            ->orderBy('step')
            ->pluck('employee_code')
            ->map(strval(...))
            ->all();
    }

    /**
     * เก็บลำดับผู้อนุมัติตั้งแต่ตอนบันทึกร่าง
     *
     * 🔴 ลำดับที่ส่งเข้ามาคือสายอนุมัติ ห้ามเรียงใหม่
     *    CEO ไม่ถูกเก็บตรงนี้ — ระบบต่อท้ายให้ตอนกดส่งเท่านั้น จะได้ไม่ค้างในร่างถ้าเปลี่ยนตัว CEO
     *
     * @param  array<int,string>  $codes
     */
    /**
     * เก็บสายอนุมัติตอนบันทึกร่าง
     *
     * 🔴 เก็บ "บทบาท" ลงไปด้วยตั้งแต่ตอนร่าง (เจ้าของสั่ง 2026-09-16)
     *    ไม่งั้นบันทึกร่างแล้วเปิดกลับมา บทบาทที่เลือกไว้จะหายกลายเป็นผู้อนุมัติหมด
     *
     * @param  array<string,string>  $roles  รหัสพนักงาน => Approval::APPROVE|ACK
     */
    private function saveApprovers(Invest $invest, array $codes, array $roles = []): void
    {
        Approval::where('doc_type', Approval::DOC_INVEST)
            ->where('doc_id', $invest->id)
            ->delete();

        /*
          🔴 ล็อก CEO ไว้เป็นแถวสุดท้ายตั้งแต่ตอนบันทึกร่าง (เจ้าของสั่ง 2026-09-09)
             เพื่อให้ทุกหน้าอ่านสายอนุมัติจากฐานข้อมูลที่เดียว — รูป ลายเซ็น ลำดับ ได้เองหมด
             ไม่ต้องให้แต่ละหน้าจำเติม CEO เอง (เคยทำแบบนั้นแล้วรูปหายไปหน้าหนึ่ง)

             ตัดออกจากกลางสายก่อนเสมอ เผื่อผู้ขอเผลอเลือก CEO ไว้ด้วย จะได้ไม่เซ็น 2 รอบ
        */
        $final = FinalApprover::code();
        $codes = $final === '' ? $codes : array_values(array_diff($codes, [$final]));

        $step = 0;
        $write = function (string $code, bool $isFinal) use ($invest, &$step, $roles) {
            $person = $this->person($code);
            $step++;

            Approval::create([
                'doc_type' => Approval::DOC_INVEST,
                'doc_id' => $invest->id,
                'round' => 1,
                'step' => $step,
                // 🔴 CEO เป็นผู้ลงนามปิดท้ายเสมอ เปลี่ยนเป็น "แจ้งให้ทราบ" ไม่ได้
                'action' => $isFinal ? Approval::APPROVE : ($roles[$code] ?? Approval::APPROVE),
                'is_final' => $isFinal,
                'employee_code' => $person['employee_code'],
                'employee_name' => $person['employee_name'],
                'employee_name_en' => $person['employee_name_en'] ?? null,
                'position' => $person['position'],
                'department' => $person['department'] ?? null,
                'status' => Approval::WAITING,
            ]);
        };

        foreach ($codes as $code) {
            $write($code, false);
        }

        if ($final !== '') {
            $write($final, true);
        }
    }

    private function storeFiles(Request $request, Invest $invest): void
    {
        foreach ($request->file('files', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            InvestFile::create([
                'invest_id' => $invest->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('budget/'.$invest->id, 'local'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => app('current_user')->employee_code,
            ]);
        }
    }

    /** ลบทั้งตำแหน่ง private ปัจจุบันและ public เดิม เพื่อรองรับเอกสารก่อน migration */
    private function deleteStoredFile(string $path): void
    {
        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }

    /**
     * ชื่อแผนก ณ ตอนสร้าง — เก็บไว้กันแผนกเปลี่ยนชื่อทีหลังแล้วเอกสารเก่าเพี้ยน
     *
     * 🔴 เก็บพร้อมรหัสนำหน้าเสมอ เช่น "IT · เทคโนโลยีสารสนเทศ" (เจ้าของสั่ง 2026-09-03)
     *    ชื่อแผนกซ้ำกันได้ แต่รหัสไม่ซ้ำ — มีรหัสนำหน้าแล้วดูออกทันทีว่าแผนกไหน
     */
    private function department(string $code): ?string
    {
        $name = Employee::where('dept_code', $code)->value('dept_th');

        return $name ? $code.' · '.$name : $code;
    }

    /** @return array{employee_code:string,employee_name:?string,position:?string} */
    private function person(string $code): array
    {
        $user = AppUser::where('employee_code', $code)->first();

        return [
            'employee_code' => $code,
            'employee_name' => $user?->displayName('th'),
            // 🔴 เก็บชื่ออังกฤษคู่กันเสมอ ไม่งั้นสลับเป็น ENG แล้วชื่อยังเป็นไทย
            'employee_name_en' => $user?->displayName('en'),
            'position' => $user?->position,
            /*
              เก็บสำเนาแผนกไว้ในเอกสารด้วย — ย้ายแผนกทีหลังเอกสารเก่าต้องไม่เปลี่ยนตาม
              🔴 บางบัญชีในมิเรอร์ไม่มีชื่อแผนกเต็ม ให้ถอยไปใช้รหัสแผนก
                 ดีกว่าปล่อยว่างจนตารางเส้นทางขึ้นขีดกลางทั้งคอลัมน์
            */
            'department' => $user?->department ?: $user?->dept_code,
        ];
    }

    /** @return array<int,mixed> */
    private function activeEmployeeRules(int $max): array
    {
        return [
            'string',
            'max:'.$max,
            Rule::exists('employees', 'employee_code')->where(fn ($query) => $this->activeEmployeeQuery($query)),
        ];
    }

    /** @return array<int,mixed> */
    private function activeAccountRules(): array
    {
        return [
            ...$this->activeEmployeeRules(30),
            Rule::exists('app_users', 'employee_code'),
        ];
    }

    private function activeEmployeeQuery($query)
    {
        return $query->where('emp_status', '1')
            ->where(function ($nested) {
                $nested->whereNull('resign_date')
                    ->orWhereDate('resign_date', '>', now()->toDateString());
            });
    }

    /** @param  array<int,string>  $codes */
    private function requireActiveAccounts(array $codes, string $label): void
    {
        $codes = array_values(array_unique(array_map('strval', $codes)));
        $activeEmployees = Employee::active()->whereIn('employee_code', $codes)
            ->pluck('employee_code')->map(strval(...))->all();
        $accounts = AppUser::whereIn('employee_code', $codes)
            ->pluck('employee_code')->map(strval(...))->all();

        $invalid = array_values(array_diff($codes, array_intersect($activeEmployees, $accounts)));
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'approvers' => $label.' ไม่พร้อมใช้งาน: '.implode(', ', $invalid).' กรุณาตรวจสิทธิ์และข้อมูลพนักงาน / Invalid or inactive account',
            ]);
        }
    }

    /** ปีงบเริ่มต้น = ปี พ.ศ. ปัจจุบัน (ยังไม่ตัดสินว่าปีงบเริ่มเดือนไหน — DECISIONS ข้อ 8) */
    private function defaultFiscalYear(): int
    {
        return (int) now()->year + 543;
    }

    /** @return array<string,mixed> */
    private function formData(Invest $invest): array
    {
        return [
            'invest' => $invest,
            /*
              กลุ่มเอกสารใน dropdown ของหน้ากรอก (เจ้าของสั่ง 2026-09-17)
              ค่าเริ่มต้น = กลุ่มที่คลิกมาจากหน้าเลือกกลุ่ม · ร่างเปลี่ยนได้อิสระ
            */
            'groups' => $this->pickableGroups(),
            'sampleYear' => $this->defaultFiscalYear(),
            /*
              สำเนาเรียน = ผู้อนุมัติตามลำดับที่ผู้ขอเลือกเอง (เจ้าของสั่ง 2026-09-09)
                🔴 ไม่ส่งรายชื่อไปกับหน้า — ค้นที่เซิร์ฟเวอร์ผ่าน people.search (พนักงานทุกคน)
                chosenApprovers  ลำดับที่บันทึกไว้ตอนร่าง — ลำดับสำคัญ ห้ามเรียงใหม่
                finalApprover    CEO ที่ระบบต่อท้ายให้เสมอ ถอด/สลับไม่ได้
            */
            'chosenApprovers' => $this->chosenPeople($invest),
            // บทบาทรายคน เรียงคู่กับ chosenApprovers — 'approve' ต้องเซ็น · 'notice' แจ้งให้ทราบ
            'chosenRoles' => $this->chosenRows($invest)
                ->map(fn (Approval $row) => $row->action === Approval::ACK ? 'notice' : 'approve')
                ->values()->all(),
            'finalApprover' => FinalApprover::person(),
            // ผู้ขอ = คนที่มาขอตั้งงบ (ข้อมูลอย่างเดียว ไม่เกี่ยวกับลายเซ็น)
            'chosenProposer' => $this->proposerChip($invest),
            // รูปของคนในเอกสาร — โหมด "ดูเอกสาร" ต้องใช้วาดรูปข้างชื่อ
            'signerPhotos' => $this->formPhotos($invest),
            'canDelete' => $invest->exists && $invest->canDelete(),
            /*
              ปุ่มดาวน์โหลด PDF ในโหมด "ดูเอกสาร" (เจ้าของสั่ง 2026-09-21)
              เครื่องไม่มีเบราว์เซอร์สำหรับแปลงไฟล์ = ไม่ขึ้นปุ่ม ดีกว่าให้กดแล้วพัง
            */
            'canPdf' => PdfPrinter::available(),
            'departments' => $this->departments(),
            'years' => range($this->defaultFiscalYear() - 1, $this->defaultFiscalYear() + 2),
            // ไอคอนไฟล์แนบตามนามสกุล — หน้าเว็บวาดเองตอนผู้ใช้เพิ่งเลือกไฟล์ ยังไม่ได้อัปโหลด
            'fileIcons' => FileIcon::urlMap(),
        ];
    }

    /**
     * รูปของคนที่อยู่ในเอกสารฉบับนี้ — ใช้ในโหมด "ดูเอกสาร"
     *
     * @return array<string,?string>
     */
    private function formPhotos(Invest $invest): array
    {
        /*
          🔴 ต้องมีรูปของ "ผู้อนุมัติ" ด้วย ไม่ใช่แค่ผู้ขอ (เจ้าของสั่ง 2026-09-09)
             เพราะตัวเอกสารขึ้นรายการ "สำเนาเรียน (ผู้อนุมัติตามลำดับ)" พร้อมรูปทุกคน
             ไม่ส่งมาให้ person-chip จะวาดเป็นตัวอักษรแรกของชื่อแทนรูป
        */
        $codes = collect([$invest->proposerCode()])
            ->merge($invest->exists ? $invest->approvals->pluck('employee_code') : [])
            ->map(strval(...))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $codes === []
            ? []
            : collect($this->access->describeCodes($codes))->pluck('photo', 'employee_code')->all();
    }

    /**
     * ผู้เสนอที่เลือกไว้ — ส่งให้ตัวเลือกพนักงานแสดงเป็นชิปตั้งต้น
     *
     * เอกสารเก่าที่ยังไม่มีคอลัมน์นี้จะถอยไปใช้ผู้สร้าง (ดู Invest::proposerCode)
     *
     * @return array<int,array<string,mixed>>
     */
    private function proposerChip(Invest $invest): array
    {
        $code = $invest->proposerCode();

        /*
          🔴 เอกสารใหม่ให้ตั้งต้นเป็นคนที่ล็อกอิน (เจ้าของสั่ง 2026-09-09)
             กลับกติกาเดิมของ DECISIONS ข้อ 26 ที่ห้ามเติมให้ — ส่วนใหญ่คนกรอกคือคนขอเอง
             ถ้าเป็นการกรอกแทนคนอื่น ค่อยเปลี่ยนในช่องนี้ได้ตามปกติ
        */
        if ($code === '' && ! $invest->exists) {
            $code = (string) app('current_user')->employee_code;
        }

        return $code === '' ? [] : $this->access->describeCodes([$code]);
    }

    /**
     * คนที่เคยถูกเลือกไว้ในเอกสารนี้ — เอาไปเติมกลับในตัวเลือกพนักงาน
     *
     * @return array<int,array<string,mixed>>
     */
    /**
     * แถวสายอนุมัติที่บันทึกไว้ เรียงตามลำดับที่ผู้ขอจัด
     *
     * 🔴 CEO ไม่ส่งให้ฟอร์ม — ฟอร์มวาดแถวล็อกของตัวเอง ถ้าส่งไปจะซ้ำและลบได้
     *
     * @return Collection<int,Approval>
     */
    private function chosenRows(Invest $invest): Collection
    {
        if (! $invest->exists) {
            return collect();
        }

        return Approval::where('doc_type', Approval::DOC_INVEST)
            ->where('doc_id', $invest->id)
            ->where('is_final', false)
            ->orderBy('round')
            ->orderBy('step')
            ->get();
    }

    /**
     * รายชื่อในสายอนุมัติ — ไม่ระบุ action = เอาทุกบทบาทตามลำดับที่จัดไว้
     *
     * 🔴 หน้าฟอร์มต้องได้ "ทั้งผู้อนุมัติและผู้รับทราบเรียงปนกันตามลำดับจริง"
     *    (เจ้าของสั่ง 2026-09-16) ไม่งั้นบันทึกร่างแล้วเปิดกลับมา ลำดับที่จัดไว้จะสลับ
     */
    private function chosenPeople(Invest $invest, ?string $action = null): array
    {
        if (! $invest->exists) {
            return [];
        }

        $rows = $this->chosenRows($invest);
        $codes = ($action === null ? $rows : $rows->where('action', $action))
            ->pluck('employee_code')
            ->map(strval(...))
            ->all();

        if ($codes === []) {
            return [];
        }

        /*
          🔴 describeCodes เรียงตามรหัสพนักงาน ซึ่งทำให้ "ลำดับสายอนุมัติ" หายไป
             ต้องเรียงกลับตามลำดับที่เก็บไว้ ไม่งั้นผู้ขอเปิดร่างมาแล้วเห็นลำดับสลับ
        */
        $byCode = collect($this->access->describeCodes($codes))->keyBy('employee_code');

        return collect($codes)
            ->map(fn (string $code) => $byCode->get($code))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * หมวดที่ผู้ใช้เลือกได้ — เฉพาะที่เปิดใช้งาน เรียงตามลำดับที่สร้าง
     * ติดตัวอย่างเลขที่มาด้วย ให้หน้าเลือกกลุ่มกับ dropdown ใช้ชุดเดียวกัน
     *
     * 🔴 เลขที่บนการ์ดเป็น "รูปแบบ" เลขวิ่งเขียนเป็น xxx (เจ้าของสั่ง 2026-09-17)
     *    ไม่ใช่เลขถัดไปจริง — เลขออกตอนกดส่งเท่านั้น (ดู DocNumber::pattern)
     *
     * @return Collection<int,DocGroup>
     */
    private function pickableGroups(): Collection
    {
        $year = $this->defaultFiscalYear();

        return DocGroup::query()->active()->ordered()->get()->each(function (DocGroup $group) use ($year) {
            $group->sample_inv = $this->docNumber->pattern('INV', $year, $group->code);
            $group->sample_bgt = $this->docNumber->pattern('BGT', $year, $group->code);
        });
    }

    /**
     * ชื่อเรียกเอกสารในข้อความแจ้งผล/บันทึกการใช้งาน
     *
     * 🔴 ร่างยังไม่มีเลขที่ (เลขออกตอนกดส่ง) — ใช้ชื่อเอกสารแทน ไม่งั้นข้อความจะมีช่องโหว่ๆ
     *
     * @return array{th:string,en:string}
     */
    private function docLabel(Invest $invest): array
    {
        if ($invest->hasNumber()) {
            return ['th' => (string) $invest->doc_no, 'en' => (string) $invest->doc_no];
        }

        return ['th' => 'ร่าง "'.$invest->title.'"', 'en' => 'draft "'.$invest->title.'"'];
    }

    /** @return array<int,array{code:string,name:string}> */
    private function departments(): array
    {
        try {
            return Employee::query()
                ->where('emp_status', '1')
                ->whereNotNull('dept_code')
                ->where('dept_code', '<>', '')
                ->selectRaw('dept_code, MAX(dept_th) AS name')
                ->groupBy('dept_code')
                ->orderBy('dept_code')
                ->get()
                ->map(fn ($r) => ['code' => (string) $r->dept_code, 'name' => (string) $r->name])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
