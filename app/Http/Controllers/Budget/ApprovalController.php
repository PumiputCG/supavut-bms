<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\Approval;
use App\Models\Budget\Invest;
use App\Models\Budget\InvestFile;
use App\Services\Access\AccessService;
use App\Services\Budget\BudgetAccess;
use App\Services\Budget\DocGroupTabs;
use App\Services\Budget\InvestFlow;
use App\Services\Core\Notifier;
use App\Support\PdfPrinter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * รับทราบ / อนุมัติ Invest
 *
 * 🔴 เจ้าของแยก 2 บทบาทให้ขาดกัน 2026-09-03
 *      รับทราบ (/budget/acknowledge)  เห็นเอกสารเฉยๆ **เซ็นไม่ได้ ตีกลับไม่ได้**
 *      อนุมัติ (/budget/approval)     เซ็นจริง ลายเซ็นประทับลงเอกสาร แล้วระบบสร้าง Budget
 *
 * สิทธิ์ "เข้าหน้าไหนได้" คุมด้วย middleware bms.fn (อ่านจาก /access/modules)
 * ส่วนหน้าเอกสารตัดสินจาก "มีชื่อในเอกสารใบนี้ไหม" ไม่ได้ตัดสินจากเมนู
 */
class ApprovalController extends Controller
{
    /**
     * สถานะของ "ผู้รับทราบ" ในกล่องงานของตัวเอง (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 มีค่าเดียว ไม่มี "รอการรับทราบ" — เขาไม่มีงานต้องทำ เอกสารไม่ได้รออะไรจากเขา
     *    ส่วน "อ่านหรือยัง" มีเลขแดงประจำแถวบอกอยู่แล้ว ไม่ต้องมีสถานะซ้ำอีกชั้น
     * 🔴 ไม่ใช่สถานะของเอกสาร จึงไม่เอาไปใส่ Invest::STATUS_LABELS
     *    เป็นค่าเฉพาะของหน้านี้ ที่แปลว่า "บทบาทของฉันในใบนี้คือผู้รับทราบ"
     */
    private const ACK_STATUS = 'ACKNOWLEDGED';

    private const ACK_LABEL = ['th' => 'รับทราบ', 'en' => 'Acknowledged', 'cls' => 'st-ack'];

    public function __construct(
        private readonly InvestFlow $flow,
        private readonly Notifier $notify,
        private readonly DocGroupTabs $groupTabs,
        private readonly BudgetAccess $access,
    ) {}

    /**
     * ตารางเดียว กรองเอาในหน้า (เจ้าของสั่งยุบ 3 ตารางเหลือตารางเดียว 2026-09-03)
     *
     * ติดป้ายบทบาทของเราไว้ที่แต่ละแถว เพื่อให้ตัวกรองในหน้าใช้ได้โดยไม่ต้องยิงกลับเซิร์ฟเวอร์
     *   mine   = เราเป็นผู้อนุมัติและยังไม่ได้เซ็น
     *   signed = เราเป็นผู้อนุมัติและเซ็นไปแล้ว (หรือเอกสารจบแล้ว)
     *   cc     = เราเป็นผู้รับทราบ ดูได้อย่างเดียว
     */
    public function index(Request $request): ViewContract
    {
        $me = app('current_user');

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            // ตัวกรองบทบาท — แยกเอกสารที่ "ต้องเซ็น" ออกจากที่ "แค่ให้ทราบ" (เจ้าของสั่ง 2026-09-16)
            'role' => ['nullable', 'in:approve,notice'],
        ]);

        $rows = $this->myDocs()->each(function ($doc) use ($me) {
            $mine = $this->myRow($doc, $me->employee_code);

            // ไม่มีชื่อในเอกสารเลย = ผู้ดูแลระบบที่เข้ามาดู — ดูได้อย่างเดียว ไม่มีปุ่มเซ็น
            if (! $mine) {
                $doc->my_state = 'watch';
                $doc->my_duty = null;
                $doc->my_status = $doc->approval_status;
                // ผู้ดูแลระบบที่เข้ามาดูเฉยๆ ไม่ได้ทำอะไรกับใบนี้ — ใช้เวลาที่เอกสารถูกส่งแทน
                $doc->my_acted_at = $doc->submitted_at;

                return;
            }

            // เอกสารฉบับนี้เราต้องทำอะไร — รับทราบ หรือ ลงนาม
            $doc->my_duty = $mine->action;

            /*
              🔴 แยก "ยังไม่ถึงคิว" ออกจาก "เซ็นไปแล้ว" (เจ้าของสั่ง 2026-09-16)
                 พอเลิกกรองตามคิว ใบที่ยังไม่ถึงคิวจะไหลเข้ามาในกล่องด้วย
                 ถ้าไม่แยกสถานะ มันจะถูกตีเป็น 'signed' แล้วขึ้นว่าเราเซ็นไปแล้วทั้งที่ยังไม่ได้เซ็น

                 mine    = ถึงคิวเราแล้ว ยังไม่ได้ตัดสิน  -> ติ๊กเลือกได้ มีปุ่มอนุมัติ
                 waiting = เห็นได้ แต่ยังไม่ถึงคิว        -> เปิดอ่านได้อย่างเดียว
                 signed  = เราตัดสินไปแล้ว                -> ย้อนดูได้
            */
            $doc->my_state = match (true) {
                $mine->action !== Approval::APPROVE => 'cc',
                $this->flow->myPendingStep($doc, $me) !== null => 'mine',
                in_array($mine->status, [Approval::DONE, Approval::REJECTED], true) => 'signed',
                default => 'waiting',
            };

            /*
              🔴 คอลัมน์ "สถานะ" ของคนที่ต้องเซ็น = สถานะของ "ตัวเขาเอง" (เจ้าของสั่ง 2026-09-10)
                 หน้านี้คือกล่องงานของแต่ละคน สิ่งที่เขาอยากรู้คือ "ฉันเซ็นไปหรือยัง"
                 ไม่ใช่สถานะรวมของเอกสารที่ยังรอคนอื่นอยู่ (ดูภาพรวมได้ที่ "สถานะทั้งหมด")

                 คนที่ไม่ได้อยู่ในสายลงนาม (ผู้ดู) ยังเห็นสถานะของทั้งเอกสารเหมือนเดิม
            */
            /*
              🔴 คอลัมน์ "วันที่ดำเนินการ" = เวลาที่ "เรา" ทำอะไรกับใบนี้ (เจ้าของสั่ง 2026-09-10)
                 ยังไม่ได้ลงนาม = null แล้วให้หน้าจอขึ้นขีดกลาง
            */
            $doc->my_acted_at = $mine->acted_at;

            /*
              🔴 ผู้รับทราบมีสถานะของตัวเองว่า "รับทราบ" (เจ้าของสั่ง 2026-09-16)
                 ของเดิมยืมสถานะของเอกสารมาแสดง ทำให้ขึ้นว่า "รออนุมัติ"
                 ซึ่งอ่านผิดว่าเอกสารค้างอยู่ที่เขา ทั้งที่เขาไม่มีอะไรต้องทำ
            */
            $doc->my_status = $mine->action === Approval::APPROVE
                ? match ($mine->status) {
                    Approval::DONE => Invest::APPROVED,
                    Approval::REJECTED => Invest::REJECTED,
                    default => Invest::PENDING,
                }
            : self::ACK_STATUS;
        });

        /*
          แท็บกลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17)
          🔴 นับก่อนกรองกลุ่ม ทุกแท็บจะได้โชว์ยอดของตัวเอง
             แล้วค่อยกรอง — ตัวเลขสรุปข้างล่างจึงเป็นของแท็บที่เลือก
        */
        $groupTabs = $this->groupTabs->tabs($this->groupTabs->countCollection($rows), $this->groupTabs->picked($request));
        $rows = $this->groupTabs->applyCollection($rows, $groupTabs['picked']);

        // บทบาทของเราในใบนั้น — ใช้ทั้งคอลัมน์ "บทบาท" และตัวกรองข้างบน
        $rows->each(function ($doc) {
            $doc->my_role = match ($doc->my_duty) {
                Approval::ACK => 'notice',
                Approval::APPROVE => 'approve',
                default => null,   // ผู้ดูแลระบบที่เข้ามาดู ไม่มีบทบาทในเอกสาร
            };
        });

        /*
          🔴 นับด้วย "สถานะของเราเอง" ให้ตรงกับคอลัมน์สถานะในตาราง (เจ้าของสั่ง 2026-09-10)
             ถ้าใช้สถานะของเอกสาร ตัวเลขข้างบนจะไม่ตรงกับที่เห็นในแถว
          🔴 นับก่อนกรอง แล้วค่อยกรอง — ไม่งั้นเลือก "อนุมัติแล้ว" ทีเดียว
             ตัวเลขช่องอื่นจะกลายเป็น 0 หมด จนกดกลับไปดูสถานะอื่นไม่ได้
        */
        $byStatus = $rows->countBy('my_status');

        $summary = [
            'total' => $rows->count(),
            'pending' => $byStatus[Invest::PENDING] ?? 0,
            'approved' => $byStatus[Invest::APPROVED] ?? 0,
            'rejected' => $byStatus[Invest::REJECTED] ?? 0,
        ];

        /*
          🔴 กรองบทบาทก่อนสถานะ แต่ "นับ" ไปแล้วข้างบน — ตัวเลขสรุปจึงเป็นของทั้งกล่องเสมอ
             (กติกาเดิม 2026-09-10: นับก่อนกรอง ไม่งั้นเลือกช่องหนึ่งแล้วช่องอื่นเป็น 0 จนกดกลับไม่ได้)
        */
        if (! empty($filters['role'])) {
            $rows = $rows->where('my_role', $filters['role'])->values();
        }

        if (! empty($filters['status'])) {
            $rows = $rows->where('my_status', $filters['status'])->values();
        }

        return View::make('budget.approval.index', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'groupTabs' => $groupTabs,
            /*
              ตัวกรองใช้คำชุดเดียวกับคอลัมน์สถานะ — ร่างไม่มีทางโผล่ในกล่องนี้ จึงตัดออก
              🔴 ต่อท้ายด้วย "รับทราบ" ของผู้รับทราบ ไม่งั้นเลือกกรองสถานะนั้นไม่ได้
            */
            'statuses' => collect(Invest::STATUS_LABELS)->except(Invest::DRAFT)
                ->put(self::ACK_STATUS, self::ACK_LABEL)->all(),
            'roles' => [
                'approve' => ['th' => 'อนุมัติ', 'en' => 'Approve'],
                'notice' => ['th' => 'แจ้งให้ทราบ', 'en' => 'Notice'],
            ],
            // รูปพนักงานสำหรับเส้นทางเอกสาร — ดึงรวดเดียว ไม่ยิงทีละแถว
            'signerPhotos' => $this->photosFor($rows),
        ]);
    }

    /** หน้าเอกสาร — ผู้รับทราบและผู้อนุมัติเปิดได้เหมือนกัน ต่างกันแค่มีปุ่มเซ็นไหม */
    public function show(Request $request, Invest $invest): ViewContract
    {
        /*
          🔴 เปิดเอกสารเองก็ถือว่าอ่านแจ้งเตือนของเอกสารใบนี้แล้ว (เจ้าของแจ้ง 2026-09-07)
             ของเดิมต้องกดผ่านกระดิ่งเท่านั้น เลขแดงเลยค้างทั้งที่ดูเอกสารไปแล้ว
        */
        $this->notify->markReadForDoc(app()->bound('current_user') ? app('current_user') : null, $invest->doc_no);

        $me = app('current_user');
        $this->guard($invest, $me);

        return View::make('budget.approval.show', [
            'invest' => $invest,
            'myStep' => $this->flow->myPendingStep($invest, $me),
            // ลายเซ็นผู้เสนอดึงสดจากบัญชี — เอกสารยังไม่จบ ยังไม่ได้เก็บสำเนา
            'mySignature' => $me->signature,
            // รูปผู้รับทราบ/ผู้อนุมัติ — เจ้าของสั่งให้เอกสารแสดงรูปด้วย
            'signerPhotos' => $this->photosFor(collect([$invest])),
            // มีเบราว์เซอร์แปลง PDF ในเครื่องไหม — ไม่มีก็ยังพิมพ์ผ่านเบราว์เซอร์ได้ตามเดิม
            'canPdf' => PdfPrinter::available(),
        ]);
    }

    /**
     * หน้าพิมพ์ใบ INV — เปิดแท็บใหม่ เห็นแต่ตัวกระดาษ (เจ้าของสั่ง 2026-09-18)
     *
     * 🔴 มีเพราะใบ INV คือใบที่เดินเซ็นจริง แต่เดิม **พิมพ์/ดาวน์โหลดไม่ได้เลย**
     *    ไม่มีกระดาษ = QR บนหัวเอกสารไม่มีใครได้ใช้ (เจ้าของเป็นคนจับได้)
     */
    public function print(Request $request, Invest $invest): ViewContract
    {
        $me = app('current_user');
        $this->guard($invest, $me);

        return View::make('budget.approval.print', [
            'invest' => $invest,
            'signerPhotos' => $this->photosFor(collect([$invest])),
            'canPdf' => PdfPrinter::available(),
            'forPdf' => false,
        ]);
    }

    /** ดาวน์โหลดใบ INV เป็นไฟล์ PDF จริง — กดแล้วได้ไฟล์เลย ไม่ต้องผ่านหน้าต่างพิมพ์ */
    public function pdf(Request $request, Invest $invest): BinaryFileResponse|RedirectResponse
    {
        $me = app('current_user');
        $this->guard($invest, $me);

        $html = View::make('budget.approval.print', [
            'invest' => $invest,
            'signerPhotos' => $this->photosFor(collect([$invest])),
            'canPdf' => true,
            // 🔴 แถบปุ่มและปุ่มอนุมัติต้องไม่ติดลงไปในไฟล์ — เอกสารต้องมีแต่ตัวกระดาษ
            'forPdf' => true,
        ])->render();

        try {
            $file = PdfPrinter::fromHtml($html, (string) $invest->doc_no);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('budget.doc.print', $invest)
                ->with('flash_error', $e->getMessage());
        }

        return response()
            ->download($file, $invest->doc_no.'.pdf')
            ->deleteFileAfterSend();
    }

    /**
     * ด่านสิทธิ์ของเอกสาร Invest — ใช้ร่วมทั้งหน้าจอ · หน้าพิมพ์ · ไฟล์ PDF
     *
     * 🔴 กติกาจริงอยู่ที่ BudgetAccess ที่เดียว (ยกไปเมื่อ 2026-09-18)
     *    เดิมเขียนอยู่ในเมธอด show() พอมีทางเข้าใหม่ 3 ทาง การก็อปไปวางคือทางตรงที่สุด
     *    ที่กติกาจะหลุดจากกันภายหลัง — และด่านสิทธิ์ที่หลุดจากกันคือช่องโหว่ ไม่ใช่ความไม่สวย
     */
    private function guard(Invest $invest, $me): void
    {
        $invest->load(['files', 'approvals']);

        if (! $this->access->involvesInvest($me, $invest)) {
            abort(403, 'คุณไม่ได้อยู่ในเอกสารฉบับนี้ / You are not part of this document');
        }

        if (! $this->access->canViewInvest($me, $invest)) {
            abort(403, 'เอกสารนี้ยังเป็นร่าง ยังไม่ได้ส่ง / This document is still a draft');
        }
    }

    /**
     * อนุมัติ/ปฏิเสธหลายฉบับพร้อมกันจากหน้าตาราง (เจ้าของสั่ง 2026-09-03)
     *
     * 🔴 ลงนามให้ทีละฉบับด้วย InvestFlow เหมือนกดในเอกสาร — ไม่มีทางลัดที่ข้ามกฎ
     *    ฉบับไหนไม่ถึงคิวเราหรือถูกตัดสินไปแล้ว จะข้ามไปเงียบๆ แล้วรายงานตอนท้าย
     * 🔴 ปฏิเสธต้องมีเหตุผลรายฉบับเสมอ — ฉบับที่ไม่ได้กรอกจะไม่ถูกแตะ
     */
    public function download(InvestFile $file): StreamedResponse
    {
        $file->loadMissing('invest.approvals');
        $invest = $file->invest;

        abort_unless($invest, 404);

        $me = app('current_user');
        $inFlow = $invest->approvals->contains(
            fn (Approval $step) => (string) $step->employee_code === (string) $me->employee_code
        );
        $isOwner = in_array((string) $me->employee_code, [
            (string) $invest->created_by,
            $invest->proposerCode(),
        ], true);
        $isAdmin = app(AccessService::class)->isAdmin($me);

        abort_unless($inFlow || $isOwner || $isAdmin, 403, 'You are not part of this document');

        abort_unless(Storage::disk('local')->exists($file->path), 404, 'Attachment not found');
        $mime = Storage::disk('local')->mimeType($file->path) ?: 'application/octet-stream';

        return Storage::disk('local')->response($file->path, $file->original_name, [
            'Content-Type' => $mime,
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'docs' => ['required', 'array', 'min:1'],
            'docs.*' => ['integer'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $me = app('current_user');
        $reject = $data['action'] === 'reject';
        $done = 0;
        $skipped = 0;
        $created = [];

        foreach ($data['docs'] as $id) {
            $invest = Invest::with('approvals')->find($id);
            $note = trim((string) ($data['notes'][$id] ?? ''));

            // ไม่ถึงคิวเรา = ข้าม · ปฏิเสธโดยไม่มีเหตุผล = ข้าม (เหตุผลบังคับ)
            if (! $invest || ! $this->flow->myPendingStep($invest, $me) || ($reject && $note === '')) {
                $skipped++;

                continue;
            }

            try {
                if ($reject) {
                    $this->flow->reject($invest, $me, $note);
                } else {
                    $budget = $this->flow->approve($invest, $me, $note ?: null);

                    if ($budget) {
                        $created[] = $budget->doc_no;
                    }
                }

                $done++;
            } catch (RuntimeException) {
                $skipped++;
            }
        }

        if ($done === 0) {
            return back()->with('flash_error', [
                'th' => 'ไม่มีเอกสารที่ดำเนินการได้',
                'en' => 'No document could be processed',
            ]);
        }

        $tail = $skipped > 0 ? ' · ข้าม '.$skipped : '';
        $tailEn = $skipped > 0 ? ' · '.$skipped.' skipped' : '';
        $budgets = $created === [] ? '' : ' · สร้างงบ '.implode(', ', $created);
        $budgetsEn = $created === [] ? '' : ' · created '.implode(', ', $created);

        return back()->with('flash_success', $reject
            ? ['th' => 'ปฏิเสธ '.$done.' ฉบับ'.$tail, 'en' => 'Rejected '.$done.$tailEn]
            : ['th' => 'อนุมัติ '.$done.' ฉบับ'.$tail.$budgets,
                'en' => 'Approved '.$done.$tailEn.$budgetsEn]);
    }

    public function approve(Request $request, Invest $invest): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:255']]);

        try {
            $budget = $this->flow->approve($invest->load('approvals'), app('current_user'), $data['comment'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('flash_error', ['th' => $e->getMessage(), 'en' => $e->getMessage()]);
        }

        return redirect()->route('budget.approval.index')->with('flash_success', $budget
            ? ['th' => 'ลงนามอนุมัติแล้ว — ระบบสร้างงบ '.$budget->doc_no.' ให้เรียบร้อย',
                'en' => 'Signed — budget '.$budget->doc_no.' created']
            : ['th' => 'ลงนามแล้ว รอผู้อนุมัติคนอื่นเซ็นให้ครบ',
                'en' => 'Signed — waiting for the other signers']);
    }

    public function reject(Request $request, Invest $invest): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->flow->reject($invest->load('approvals'), app('current_user'), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('flash_error', ['th' => $e->getMessage(), 'en' => $e->getMessage()]);
        }

        return redirect()->route('budget.approval.index')->with('flash_success', [
            'th' => 'บันทึกการไม่อนุมัติแล้ว',
            'en' => 'Rejection recorded',
        ]);
    }

    // ── ภายใน ───────────────────────────────────────────────────────

    /**
     * เอกสารที่มีชื่อเราอยู่ ไม่ว่าบทบาทไหน
     *
     * @return Collection<int,Invest>
     */
    private function myDocs()
    {
        $me = app('current_user');

        /*
          โหลดไฟล์แนบมาด้วย — หน้าต่างอนุมัติหลายฉบับต้องโชว์ไฟล์ของแต่ละฉบับ

          🔴 ร่างที่ยังไม่กด "ส่งรายงาน" ต้องไม่โผล่ในกล่องของใครทั้งนั้น (เจ้าของแจ้งบั๊ก 2026-09-10)
             ผู้อนุมัติถูกเขียนลงสายเอกสารตั้งแต่ตอนบันทึกร่าง (เพื่อให้ผู้ขอเลือกค้างไว้ได้)
             แต่ "มีชื่ออยู่ในสายเอกสาร" ยังไม่ได้แปลว่าเรื่องถึงเขาแล้ว — ต้องกดส่งก่อน
             ตัวกรองต้องอยู่ก่อนแยกทางแอดมิน/คนทั่วไป ไม่งั้นเผลอกันแค่ทางเดียวเหมือนของเดิม
        */
        $query = Invest::with(['approvals', 'budget', 'files', 'group'])
            ->where('approval_status', '<>', Invest::DRAFT);

        /*
          🔴 ผู้ดูแลระบบเห็นทุกฉบับที่ส่งออกแล้ว (เจ้าของแจ้ง 2026-09-04)
             เดิมกรองด้วยรหัสพนักงานอย่างเดียว แอดมินจึงเจอตารางว่างทั้งที่เข้าหน้าได้
             แต่แอดมินไม่ได้อยู่ในสายเอกสาร จึงเป็น "ผู้ดู" ไม่มีปุ่มเซ็น
        */
        if (app(AccessService::class)->isAdmin($me)) {
            return $query->orderByDesc('id')->get();
        }

        $docIds = Approval::where('doc_type', Approval::DOC_INVEST)
            ->where('employee_code', $me->employee_code)
            ->pluck('doc_id')
            ->unique()
            ->all();

        /*
          🔴 เห็นทุกใบที่มีชื่อเราอยู่ "พร้อมกัน" ไม่ต้องรอถึงคิว (เจ้าของสั่ง 2026-09-16)
             ของเดิมกรองด้วย isMyTurnOrDone() — ผู้ลงนามลำดับ 3 มองไม่เห็นใบนั้นเลย
             จนกว่าลำดับ 1 กับ 2 จะเซ็นครบ ทำให้เตรียมตัวล่วงหน้าไม่ได้

             🔴 "เห็น" ไม่เท่ากับ "กดได้" — ตัวกันการลงนามอยู่ที่
                InvestFlow::myPendingStep() (คืน null เมื่อยังมีขั้นก่อนหน้าไม่ได้เซ็น)
                และ InvestFlow::requireMyStep() ฝั่งเซิร์ฟเวอร์ ซึ่งไม่ได้แตะเลย
                คนที่ยังไม่ถึงคิวจึงเปิดอ่านได้ แต่ไม่มีปุ่มอนุมัติ และยิง POST ตรงก็ไม่ผ่าน
        */
        return $query->whereIn('id', $docIds)
            ->orderByDesc('id')
            ->get()
            ->values();
    }

    /**
     * แถวของคนนี้ในเอกสารใบนี้ — 🔴 เอาของ "รอบล่าสุด" เสมอ
     *
     * เอกสารที่ถูกตีกลับแล้วส่งใหม่จะมีแถวของคนเดิมหลายรอบ (คอลัมน์ round)
     * ถ้าหยิบแถวแรกที่เจอ จะได้ผลของรอบเก่ามาแสดง เช่นขึ้น "ไม่อนุมัติ"
     * ทั้งที่รอบใหม่ยังรอเขาเซ็นอยู่
     */
    private function myRow(Invest $doc, string $code): ?Approval
    {
        return $doc->approvals
            ->where('employee_code', $code)
            ->sortByDesc('round')
            ->first();
    }

    /**
     * รูปพนักงานของทุกคนที่อยู่ในเอกสารพวกนี้
     *
     * ดึงรวดเดียวแล้วส่งเป็นแผนที่ให้ view — ไม่งั้นตาราง 50 แถวยิงคิวรีเป็นร้อยครั้ง
     *
     * @param  Collection<int,Invest>  $docs
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

        return collect(app(AccessService::class)->describeCodes($codes))
            ->pluck('photo', 'employee_code')
            ->all();
    }
}
