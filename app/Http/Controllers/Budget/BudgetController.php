<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\Budget;
use App\Services\Audit\ActivityLogger;
use App\Services\Budget\BudgetAccess;
use App\Services\Budget\DocGroupTabs;
use App\Services\Core\Notifier;
use App\Support\PdfPrinter;
use App\Support\TableFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ลงทะเบียน — ดูวงเงิน ใช้ไป คงเหลือ และสถานะ
 *
 * 🔴 เฟสนี้แสดงแค่ "วงเงินที่อนุมัติ" (`approved_amount`)
 *    ยังไม่แสดง กันไว้ / ใช้จริง / คงเหลือ / % ใช้งบ เพราะ **ยังไม่ confirm ว่าจะตัดงบตอน PR / PO / Payment**
 *    ตาราง budget_transactions กับ BudgetLedger ยังอยู่เป็นโครงรองรับ พอ confirm แล้วค่อยเปิดใช้
 *
 * 🔴 เปลี่ยน budget_status ต้องไม่ไปแตะ approval_status (กฎใน DECISIONS 4.3)
 */
class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetAccess $access,
        private readonly ActivityLogger $log,
        private readonly Notifier $notify,
        private readonly DocGroupTabs $groupTabs,
    ) {}

    public function index(Request $request): ViewContract
    {
        $me = app('current_user');

        // 🔴 เหลือตัวกรองเดียว = สถานะการลงทะเบียน (เจ้าของสั่งเอาปีงบ/ค้นหาออก 2026-09-10)
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        // 🔴 โหลดสายอนุมัติมาด้วย — ตาราง "ดูเส้นทาง" ต้องใช้ ไม่งั้นยิงคิวรีทีละแถว
        $query = Budget::query()->with(['invest.approvals', 'group'])->orderByDesc('id');

        /*
          🔴 ไม่กรองตามแผนกแล้ว (เจ้าของแจ้งปัญหา 2026-09-03)
             เดิมคนที่ไม่ใช่ฝ่ายบัญชีเห็นเฉพาะงบแผนกตัวเอง — ได้สิทธิ์แล้วแต่ยังเจอจอว่าง
             ใครควรเห็นบ้างกำหนดที่ /access/modules หัวข้อ "ลงทะเบียน" ที่เดียว
        */

        /*
          แท็บกลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17)
          🔴 นับก่อนกรองกลุ่ม ทุกแท็บจะได้โชว์ยอดของตัวเอง · แล้วค่อยกรองตามแท็บที่เลือก
        */
        $groupTabs = $this->groupTabs->tabs($this->groupTabs->countQuery($query), $this->groupTabs->picked($request));
        $this->groupTabs->applyQuery($query, $groupTabs['picked']);

        if (! empty($filters['status'])) {
            $query->where('budget_status', $filters['status']);
        }

        /*
          ตัวกรองรายคอลัมน์ — กรองที่เซิร์ฟเวอร์ จึงได้ผลครบทุกหน้า (เจ้าของสั่ง 2026-09-04)
          🔴 รายการค่าที่เลือกได้ต้องคิดจาก **ก่อนใส่ตัวกรองคอลัมน์**
             ไม่งั้นพอกรองแล้วตัวเลือกอื่นหายหมด จนกลับไปเลือกค่าเดิมไม่ได้
        */
        $map = self::filterMap();
        $options = TableFilter::options(clone $query, $map);
        $picked = TableFilter::picked($request, $map);
        TableFilter::apply($query, $request, $map);

        /*
          แถบสรุปหัวตาราง — 🔴 นับจากชุดที่ผ่านตัวกรองแล้วเสมอ (บทเรียน 2026-09-07)
          🔴 ต้อง reorder() ก่อน groupBy เพราะคิวรีมี orderByDesc('id') ติดมา
        */
        $byStatus = (clone $query)->reorder()
            ->selectRaw('budget_status, COUNT(*) AS n')
            ->groupBy('budget_status')
            ->pluck('n', 'budget_status')
            ->all();

        $rows = $query->paginate(20)->withQueryString();

        return View::make('budget.list.index', [
            'rows' => $rows,
            'summary' => [
                'total' => array_sum($byStatus),
                'waitRegister' => $byStatus[Budget::PENDING_REGISTER] ?? 0,
                'registered' => $byStatus[Budget::REGISTERED] ?? 0,
            ],
            /*
              ยอดรวม — คิดจาก **ทุกแถวที่ผ่านตัวกรอง** ไม่ใช่เฉพาะหน้าที่เปิดอยู่ (เจ้าของสั่ง 2026-09-04)
              🔴 ยังไม่คิด กันไว้/ใช้จริง/คงเหลือ เพราะยังไม่ confirm ว่าตัดงบตอน PR/PO/Payment
            */
            'pageTotal' => (float) (clone $query)->reorder()->sum('approved_amount'),
            'filterOptions' => $options,
            'filterPicked' => $picked,
            'isFiltered' => $picked !== [],
            'filters' => $filters,
            'statuses' => Budget::STATUS_LABELS,
            'groupTabs' => $groupTabs,

            // เปลี่ยนสถานะได้เฉพาะคนที่ถูกระบุในสิทธิ์ย่อย "ปรับคอลัมน์เปลี่ยนสถานะ"
            'canManage' => $this->access->canChangeStatus($me),
        ]);
    }

    /**
     * คอลัมน์ที่กรองได้ของหน้านี้
     *
     * 🔴 คอลัมน์วันที่ไม่ใส่ — ค่าดิบเป็น datetime แต่ตารางโชว์ d/m/Y เลือกแล้วจะงง
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
            'amount' => ['column' => 'approved_amount', 'format' => 'money'],
            'status' => ['column' => 'budget_status', 'labels' => Budget::STATUS_LABELS],
        ];
    }

    /**
     * หน้าพิมพ์ / บันทึก PDF — เห็นแต่ตัวกระดาษ ไม่มีเมนู
     *
     * 🔴 ใช้ด่านตรวจสิทธิ์ตัวเดียวกับหน้าปกติ — เปิดแท็บใหม่ไม่ใช่ทางลัดข้ามสิทธิ์
     */
    public function print(Request $request, Budget $budget): ViewContract
    {
        $me = app('current_user');

        if (! $this->access->canViewBudget($me, $budget)) {
            abort(403, 'คุณไม่มีสิทธิ์ดูงบก้อนนี้ / You cannot view this budget');
        }

        $budget->load(['invest.approvals']);

        return View::make('budget.list.print', [
            'budget' => $budget,
            'signerPhotos' => $this->photosFor($budget),
            // มีเบราว์เซอร์แปลง PDF ในเครื่องไหม — ไม่มีก็ยังพิมพ์ผ่านเบราว์เซอร์ได้ตามเดิม
            'canPdf' => PdfPrinter::available(),
            'forPdf' => false,
        ]);
    }

    /**
     * ดาวน์โหลดเอกสารเป็นไฟล์ PDF จริง (เจ้าของสั่ง 2026-09-10)
     *
     * 🔴 กดแล้วต้องได้ไฟล์เลย ไม่ใช่เด้งหน้าต่างพิมพ์ให้ผู้ใช้เลือกปลายทางเอง
     *    วิธีแปลงและเหตุผลที่ไม่ลงไลบรารี PDF อยู่ใน App\Support\PdfPrinter
     *
     * แปลงไม่สำเร็จ (เครื่องไม่มีเบราว์เซอร์) ให้ถอยไปหน้าพิมพ์พร้อมบอกเหตุผล
     * ไม่ปล่อยให้ผู้ใช้เจอหน้าจอ error เปล่าๆ
     */
    public function pdf(Request $request, Budget $budget): BinaryFileResponse|RedirectResponse
    {
        $me = app('current_user');

        if (! $this->access->canViewBudget($me, $budget)) {
            abort(403, 'คุณไม่มีสิทธิ์ดูงบก้อนนี้ / You cannot view this budget');
        }

        $budget->load(['invest.approvals']);

        $html = View::make('budget.list.print', [
            'budget' => $budget,
            'signerPhotos' => $this->photosFor($budget),
            'canPdf' => true,
            // 🔴 แถบปุ่มต้องไม่ติดลงไปในไฟล์ — เอกสารต้องมีแต่ตัวกระดาษ
            'forPdf' => true,
        ])->render();

        try {
            $file = PdfPrinter::fromHtml($html, $budget->doc_no);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('budget.list.print', $budget)
                ->with('flash_error', $e->getMessage());
        }

        return response()
            ->download($file, $budget->doc_no.'.pdf')
            ->deleteFileAfterSend();
    }

    public function show(Request $request, Budget $budget): ViewContract
    {
        /*
          🔴 หน้านี้ "เปิดดูเอกสาร" ไม่นับว่าอ่านแล้ว (เจ้าของสั่ง 2026-09-10)

             ต่างจากหน้าอื่นในระบบที่เปิดเอกสาร = รับรู้เรื่องแล้ว
             เพราะแจ้งเตือนของหน้านี้แปลว่า "ยังมีงานค้าง — ต้องลงทะเบียนเข้า ERP"
             เปิดดูเฉยๆ งานยังไม่เสร็จ · เจ้าของเผลอกด "ดูเอกสาร" แล้วเลขแดงหายไปทั้งที่ยังไม่ได้ทำ

             เลขแดงหายที่จุดเดียวคือตอนติ๊ก "ลงทะเบียนแล้ว" ใน changeStatus()
        */
        $me = app('current_user');

        if (! $this->access->canViewBudget($me, $budget)) {
            abort(403, 'คุณไม่มีสิทธิ์ดูงบก้อนนี้ / You cannot view this budget');
        }

        $budget->load(['invest.approvals']);

        return View::make('budget.list.show', [
            // โหลดสายอนุมัติมาด้วย — หน้ากระดาษต้องโชว์ลายเซ็นทุกคนที่อนุมัติ
            'budget' => $budget,
            'canManage' => $this->access->canChangeStatus($me),
            // รูปผู้เสนอ — กระดาษต้องมีรูปข้างชื่อ และกดขยายได้
            'signerPhotos' => $this->photosFor($budget),
        ]);
    }

    /** เปลี่ยนสถานะการใช้งบ — พัก / หยุด / ปิด / กลับมาใช้ */
    public function changeStatus(Request $request, Budget $budget): RedirectResponse
    {
        $me = app('current_user');

        if (! $this->access->canChangeStatus($me)) {
            abort(403, 'คุณไม่มีสิทธิ์เปลี่ยนสถานะงบ / You cannot change budget status');
        }

        $data = $request->validate([
            'budget_status' => ['required', 'string', 'in:'.implode(',', array_keys(Budget::STATUS_LABELS))],
            'status_note' => ['nullable', 'string', 'max:255'],
        ]);

        $from = $budget->budget_status;
        $isRegistering = $data['budget_status'] === Budget::REGISTERED;

        /*
          🔴 อัปเดตเฉพาะ budget_status — approval_status ต้องคงเป็น APPROVED ตามเดิม

          🔴 เก็บ "ใครติ๊ก เมื่อไหร่" ด้วย (เจ้าของสั่ง 2026-09-10)
             ติ๊กออก = ล้างทิ้ง ไม่งั้นตารางเส้นทางจะโชว์วันเวลาของการติ๊กครั้งก่อนค้างไว้
        */
        $budget->update([
            'budget_status' => $data['budget_status'],
            'status_note' => $data['status_note'] ?? null,
            'registered_at' => $isRegistering ? now() : null,
            'registered_by' => $isRegistering ? (string) $me->employee_code : null,
        ]);

        /*
          🔴 ติ๊กลงทะเบียนแล้ว = จัดการเรื่องนี้เสร็จ ปิดแจ้งเตือนของใบนี้ให้เลย (เจ้าของสั่ง 2026-09-10)
             วางไว้ที่ "จุดที่งานสำเร็จ" จุดเดียว จึงครอบทั้งติ๊กจากตารางและติ๊กในตัวเอกสาร
             (บทเรียนเดิม 2026-09-07: เคยไปแปะไว้ที่หน้าจอ แล้วทางเข้าอื่นทำให้เลขแดงค้าง)

          🔴 ล้างทั้ง 2 เลขที่ เพราะแจ้งเตือนของโมดูลนี้ผูกได้ทั้งเลขที่งบและเลขที่ Invest ต้นทาง
        */
        $this->notify->markReadForDoc($me, $budget->doc_no);
        $this->notify->markReadForDoc($me, $budget->invest?->doc_no);

        $this->log->record('budget_status_changed', [
            'th' => 'เปลี่ยนสถานะงบ '.$budget->doc_no.' จาก '.$from.' เป็น '.$data['budget_status'],
            'en' => 'Budget '.$budget->doc_no.' status changed from '.$from.' to '.$data['budget_status'],
        ], $budget->doc_no);

        return back()->with('flash_success', [
            'th' => 'เปลี่ยนสถานะงบแล้ว',
            'en' => 'Budget status updated',
        ]);
    }
    // ── ภายใน ───────────────────────────────────────────────────────

    /**
     * รูปพนักงานของคนที่อยู่ในเอกสารงบก้อนนี้ — ดึงรวดเดียว ไม่ยิงทีละคน
     *
     * @return array<string,?string>
     */
    private function photosFor(Budget $budget): array
    {
        $codes = collect($budget->invest?->approvals ?? [])->pluck('employee_code')
            ->push($budget->invest?->proposerCode())
            ->push($budget->approved_by)
            ->filter()->unique()->values()->all();

        if ($codes === []) {
            return [];
        }

        return collect($this->access->describeCodes($codes))->pluck('photo', 'employee_code')->all();
    }
}
