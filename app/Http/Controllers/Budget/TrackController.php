<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Authenticate;
use App\Models\Budget\Approval;
use App\Models\Budget\Budget;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Services\Access\AccessService;
use App\Services\Budget\BudgetAccess;
use App\Support\TrackLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * หน้าติดตามเอกสาร — ปลายทางของ QR Code บนกระดาษ (เจ้าของสั่ง 2026-09-18 · DECISIONS 50.15)
 *
 * 🔴 หน้านี้อยู่ "นอกด่าน bms.auth" โดยตั้งใจ — สแกนแล้วต้องเห็นสถานะทันทีจากมือถือ
 *    จึงต้องอ่าน session เอง (ไม่มี app('current_user') ให้ใช้)
 *
 * 🔴 2 ชั้นตามที่เจ้าของเคาะ
 *      ไม่ล็อกอิน  เห็นเส้นทางเอกสาร (ใครเซ็นแล้ว/รอใคร/เมื่อไหร่) **ไม่เห็นยอดเงินและรูปลายเซ็น**
 *      ล็อกอินแล้ว เห็นยอดเงินและรูปลายเซ็น เท่าที่มีสิทธิ์เปิดเอกสารใบนั้นอยู่แล้ว
 *
 *    เหตุผล: QR อยู่บนกระดาษที่เดินไปหลายโต๊ะ ใครเห็นกระดาษก็สแกนได้
 *    ยอดเงินเป็นข้อมูลธุรกิจ · ลายเซ็นเป็นลายเซ็นจริงที่เซฟไปใช้ที่อื่นได้
 *    ส่วน "ใครเซ็นแล้วเมื่อไหร่" ตอบโจทย์การติดตามได้ครบโดยไม่ต้องเปิด 2 อย่างนั้น
 *
 * 🔴 กุญแจเป็นรหัสสุ่ม ไม่ใช่เลขที่เอกสาร — ไม่งั้นไล่เลขเดาดูเอกสารทั้งบริษัทได้
 */
class TrackController extends Controller
{
    public function __construct(
        private readonly BudgetAccess $access,
        private readonly AccessService $people,
    ) {}

    public function show(Request $request, string $token): Response
    {
        /*
          กุญแจผิดรูป = ไม่ต้องแตะฐานข้อมูลเลย
          🔴 หน้า "ไม่พบเอกสาร" ต้องตอบเหมือนกันทุกกรณี (ผิดรูป / ไม่มีจริง / ถูกลบ)
             ไม่งั้นข้อความที่ต่างกันจะกลายเป็นตัวบอกว่ากุญแจไหน "เกือบถูก"
        */
        $invest = TrackLink::looksValid($token)
            ? Invest::with(['approvals'])->where('track_token', $token)->first()
            : null;

        if (! $invest) {
            return response()->view('budget.track.missing', [], 404);
        }

        $budget = Budget::where('invest_id', $invest->id)->first();
        $me = $this->viewer($request);

        // เห็นยอดเงิน/ลายเซ็นได้ไหม — ใช้กติกาเดียวกับการเปิดเอกสารเต็ม ไม่ได้ตั้งกฎใหม่
        $full = $me !== null && $this->access->canViewInvest($me, $invest);

        return response()->view('budget.track.show', [
            'invest' => $invest,
            'budget' => $budget,
            'full' => $full,
            'me' => $me,
            'steps' => $this->steps($invest, $budget, $full),
            'now' => $this->whereNow($invest, $budget),
        ]);
    }

    /**
     * คนที่กำลังเปิดหน้านี้ (ถ้าล็อกอินอยู่)
     *
     * 🔴 หน้านี้ไม่ได้ผ่าน middleware Authenticate จึงต้องอ่าน session ด้วยคีย์เดียวกันเอง
     *    ใช้ค่าคงที่จากคลาสนั้น ไม่ใช่พิมพ์ชื่อคีย์ซ้ำ — วันเปลี่ยนคีย์จะได้ไม่หลุดเฉพาะหน้านี้
     */
    private function viewer(Request $request): ?AppUser
    {
        $id = $request->session()->get(Authenticate::SESSION_KEY);

        return $id ? AppUser::find($id) : null;
    }

    /**
     * เส้นทางเอกสารทั้งเส้น เรียงตามลำดับจริง
     *
     * 🔴 ประกอบที่นี่ ไม่ประกอบในหน้า (กฎโปรเจค: ตรรกะอยู่ที่ controller/service ไม่ใช่ Blade)
     *
     * @return array<int,array<string,mixed>>
     */
    private function steps(Invest $invest, ?Budget $budget, bool $full): array
    {
        $round = (int) $invest->approvals->max('round');
        $rows = $invest->approvals->where('round', $round);

        $signers = $rows->where('action', Approval::APPROVE)->sortBy('step')->values();
        $acks = $rows->where('action', Approval::ACK)->sortBy('step')->values();

        // ชื่อ/ตำแหน่ง/แผนกของผู้เสนอ — ตัวเอกสารเก็บแค่ชื่อ ไม่ได้เก็บตำแหน่ง
        $proposer = $this->describe($invest->proposerCode());

        $steps = [[
            'role' => ['th' => 'ผู้ขอ', 'en' => 'Requester'],
            'name' => ['th' => $invest->proposer_name, 'en' => $invest->proposer_name_en ?: $invest->proposer_name],
            'position' => $proposer['position'] ?? '',
            'dept' => (string) $invest->dept_name,
            'state' => ['cls' => 'st-approved', 'th' => 'ส่งเรื่องแล้ว', 'en' => 'Submitted'],
            'at' => $invest->submitted_at?->format('d/m/Y H:i'),
            'note' => '',
            'signature' => null,
        ]];

        foreach ($signers as $row) {
            $steps[] = [
                'role' => $row->is_final
                    ? ['th' => 'ผู้ลงนามปิดท้าย', 'en' => 'Final approver']
                    : ['th' => 'ผู้ลงนาม', 'en' => 'Approver'],
                'name' => ['th' => $row->employee_name, 'en' => $row->employee_name_en ?: $row->employee_name],
                'position' => (string) $row->position,
                'dept' => (string) $row->department,
                'state' => $row->outcome($invest->approval_status),
                'at' => $row->acted_at?->format('d/m/Y H:i'),
                'note' => (string) $row->comment,
                // 🔴 รูปลายเซ็นเฉพาะคนที่ล็อกอินและมีสิทธิ์เปิดเอกสารใบนี้ (เจ้าของเคาะ)
                'signature' => $full ? $row->signature : null,
            ];
        }

        /*
          ขั้นสุดท้าย: ฝ่ายบัญชีคีย์เข้า ERP
          🔴 ขึ้นเฉพาะเมื่อเกิดใบงบแล้ว (= เซ็นครบแล้ว) ตามที่เจ้าของกำหนดเรื่องเลข BGT
             เอกสารที่ถูกตีกลับไม่มีขั้นนี้ เพราะเรื่องจบไปแล้ว ไม่มีอะไรให้ลงทะเบียน
        */
        if ($budget) {
            $done = $budget->isRegistered();
            $who = $done ? $this->describe((string) $budget->registered_by) : [];

            $steps[] = [
                'role' => ['th' => 'ผู้ลงทะเบียน', 'en' => 'Registrar'],
                // ยังไม่ลงทะเบียน = ไม่ระบุชื่อใคร (เป็นคิวของฝ่ายบัญชีทั้งกลุ่ม ไม่ใช่ของคนใดคนหนึ่ง)
                'name' => $done
                    ? ['th' => $who['name_th'] ?? (string) $budget->registered_by, 'en' => $who['name_en'] ?? (string) $budget->registered_by]
                    : ['th' => 'ฝ่ายบัญชี', 'en' => 'Accounting'],
                'position' => $who['position'] ?? '',
                'dept' => $who['department'] ?? '',
                'state' => $done
                    ? ['cls' => 'st-approved', 'th' => 'ลงทะเบียนแล้ว', 'en' => 'Registered']
                    : ['cls' => 'st-pending', 'th' => 'รอลงทะเบียน', 'en' => 'Awaiting registration'],
                'at' => $budget->registered_at?->format('d/m/Y H:i'),
                'note' => '',
                'signature' => null,
            ];
        }

        // ผู้รับทราบไม่ได้อยู่ในคิว จึงแยกไว้ท้ายสุดเป็นก้อนของตัวเอง (กติกาเดิมของตารางเส้นทาง)
        foreach ($acks as $row) {
            $steps[] = [
                'group' => 'ack',
                'role' => ['th' => 'ผู้รับทราบ', 'en' => 'For information'],
                'name' => ['th' => $row->employee_name, 'en' => $row->employee_name_en ?: $row->employee_name],
                'position' => (string) $row->position,
                'dept' => (string) $row->department,
                'state' => $row->outcome($invest->approval_status),
                'at' => $row->acted_at?->format('d/m/Y H:i'),
                'note' => (string) $row->comment,
                'signature' => null,
            ];
        }

        return $steps;
    }

    /**
     * บรรทัด "ตอนนี้อยู่ที่ใคร" — ใจความของหน้านี้
     *
     * 🔴 คนที่สแกนต้องได้คำตอบนี้ก่อนอย่างอื่น ไม่ต้องไล่อ่านตารางเอง
     *
     * @return array{cls:string, th:string, en:string}
     */
    private function whereNow(Invest $invest, ?Budget $budget): array
    {
        if ($invest->approval_status === Invest::REJECTED) {
            $who = $invest->approvals->firstWhere('status', Approval::REJECTED);

            return [
                'cls' => 'st-rejected',
                'th' => 'ไม่อนุมัติ'.($who ? ' โดย '.$who->employee_name : ''),
                'en' => 'Rejected'.($who ? ' by '.($who->employee_name_en ?: $who->employee_name) : ''),
            ];
        }

        if ($invest->approval_status === Invest::DRAFT) {
            return ['cls' => 'st-draft', 'th' => 'ยังเป็นร่าง ยังไม่ได้ส่งเรื่อง', 'en' => 'Still a draft — not submitted'];
        }

        if ($invest->approval_status === Invest::PENDING) {
            $round = (int) $invest->approvals->max('round');
            $next = $invest->approvals
                ->where('round', $round)
                ->where('action', Approval::APPROVE)
                ->where('status', Approval::WAITING)
                ->sortBy('step')
                ->first();

            return [
                'cls' => 'st-pending',
                'th' => $next ? 'รอ '.$next->employee_name.' ลงนาม' : 'รอลงนาม',
                'en' => $next ? 'Waiting for '.($next->employee_name_en ?: $next->employee_name).' to sign' : 'Waiting for signature',
            ];
        }

        if ($budget && ! $budget->isRegistered()) {
            return ['cls' => 'st-pending', 'th' => 'อนุมัติครบแล้ว — รอฝ่ายบัญชีลงทะเบียนเข้า ERP', 'en' => 'Fully approved — awaiting registration into ERP'];
        }

        return ['cls' => 'st-approved', 'th' => 'เสร็จสิ้นทุกขั้นตอน', 'en' => 'All steps complete'];
    }

    /** ชื่อ/ตำแหน่ง/แผนกของพนักงานรายคน (เท่าที่มีในมิเรอร์) */
    private function describe(string $code): array
    {
        if ($code === '') {
            return [];
        }

        $found = $this->people->describeCodes([$code]);

        return $found[0] ?? [];
    }
}
