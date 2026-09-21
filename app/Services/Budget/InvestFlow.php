<?php

namespace App\Services\Budget;

use App\Models\Budget\Approval;
use App\Models\Budget\Budget;
use App\Models\Budget\BudgetTransaction;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Services\Audit\ActivityLogger;
use App\Services\Core\Notifier;
use App\Support\TrackLink;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * เส้นทางของข้อเสนอขอตั้งงบ (Invest)
 *
 * Flow ที่เจ้าของ confirm แล้ว 2026-09-03
 *   ฝ่ายบัญชีเสนอ Invest
 *      ├─ ส่งให้ "ผู้รับทราบ" (หลายคนได้)  →  เห็นเอกสารเฉยๆ **เซ็นไม่ได้ ไม่ขวางเอกสาร**
 *      └─ ส่งให้ "ผู้อนุมัติ"              →  เซ็นจริง ลายเซ็นประทับลงเอกสาร
 *                                            เซ็นครบทุกคน  →  ระบบสร้าง Budget ให้อัตโนมัติ
 *
 * 🔴 แยก 2 บทบาทให้ขาดกัน — ห้ามปนกัน
 *      รับทราบ (acknowledge)  = ผู้รับสำเนา **ไม่มีปุ่มเซ็น ไม่มีปุ่มตีกลับ**
 *      อนุมัติ (approve)      = ผู้ลงนาม เลือกได้ 1 คนหรือหลายคน (บริษัทมีกี่คนยังไม่ confirm)
 *
 * 🔴 ยังไม่ตัดงบตอนไหน — PR / PO / Payment ยังไม่ confirm
 *    ตอนนี้บันทึกรายการ "ตั้งงบ" ลง ledger ไว้เป็นโครงรองรับอนาคตเท่านั้น
 *    ยอดที่แสดงบนหน้าจอใช้ `budgets.approved_amount` ตรงๆ ยังไม่คิดจาก ledger
 */
class InvestFlow
{
    public function __construct(
        private readonly DocNumber $docNumber,
        private readonly BudgetLedger $ledger,
        private readonly ActivityLogger $log,
        private readonly Notifier $notify,
        // รายชื่อคนที่ดูแลหน้า "ลงทะเบียน" อ่านจากสิทธิ์ที่ /access/modules ที่เดียว
        private readonly BudgetAccess $access,
    ) {}

    /**
     * ส่งเอกสารออกจากร่าง
     *
     * 🔴 ผู้รับทราบบันทึกเป็น NOTIFIED ทันทีตั้งแต่ตอนส่ง — ไม่ต้องรอเขาทำอะไร
     *    เพราะเจ้าของกำหนดว่ารับทราบ = เห็นเอกสารเฉยๆ ไม่ใช่การเซ็น
     * 🔴 ผู้อนุมัติเลือกได้มากกว่า 1 คน (ยังไม่ confirm ว่าบริษัทมีผู้ลงนามกี่คน)
     *    เลือกหลายคน = ทุกคนต้องเซ็น ลายเซ็นสุดท้ายที่ครบคือตัวสร้าง Budget
     */
    /**
     * ส่งเอกสารเข้าสายอนุมัติ
     *
     * 🔴 รับรายชื่อ "ชุดเดียวเรียงตามที่ผู้ใช้จัด" แต่ละคนติดบทบาทมาด้วย (เจ้าของสั่ง 2026-09-16)
     *    action = Approval::APPROVE (ต้องลงนาม) หรือ Approval::ACK (แจ้งให้ทราบเฉยๆ)
     *
     *    ของเดิมรับแยกเป็น 2 กอง (ผู้รับทราบ / ผู้อนุมัติ) แล้วเขียนผู้รับทราบขึ้นก่อนทั้งหมด
     *    ทำให้คนที่ผู้ใช้จัดไว้ลำดับ 3 ไปโผล่เป็นลำดับ 1 ในเอกสาร — ลำดับที่เห็นไม่ตรงกับที่จัด
     *
     * 🔴 แถวผู้รับทราบแทรกกลางได้ ไม่กวนคิวการเซ็น เพราะด่านที่กันคิวทั้งหมด
     *    (hasUnsignedEarlierStep · $stillWaiting) กรอง action = APPROVE อยู่แล้ว
     *
     * @param  array<int,array<string,mixed>>  $people
     */
    public function submit(Invest $invest, array $people): void
    {
        $approvers = array_values(array_filter($people, fn ($p) => ($p['action'] ?? Approval::APPROVE) === Approval::APPROVE));

        if ($approvers === []) {
            throw new RuntimeException('ต้องเลือกผู้อนุมัติอย่างน้อย 1 คน / At least one approver is required');
        }

        DB::transaction(function () use (&$invest, $people) {
            // ล็อกเอกสารก่อนเปลี่ยนสถานะ กันการกดส่งพร้อมกันจนเกิดสายอนุมัติซ้ำ
            $invest = Invest::whereKey($invest->id)->lockForUpdate()->firstOrFail();

            if (! $invest->isDraft()) {
                throw new RuntimeException('ส่งได้เฉพาะเอกสารที่เป็นร่างเท่านั้น / Only a draft can be submitted');
            }

            if ((float) $invest->amount <= 0) {
                throw new RuntimeException('ต้องระบุวงเงินที่เสนอก่อนส่งเรื่อง / A proposed amount is required');
            }

            /*
              🔴 ออกเลขที่ "ตอนกดส่ง" ไม่ใช่ตอนบันทึกร่าง (เจ้าของสั่ง 2026-09-17)
                 ร่างที่ได้เลขแล้วไม่ยอมส่งจะจองเลขค้างไว้ถาวร คนที่กรอกทีหลังแต่ส่งก่อน
                 จะได้เลขที่มากกว่า = เลขในระบบไม่เรียงตามลำดับการส่งจริง
                 ออกเลขภายใน transaction เดียวกับการเปลี่ยนสถานะ — ล้มตรงไหนเลขก็ไม่หลุดออกไป
            */
            if (! $invest->hasNumber()) {
                $group = $invest->group_id ? DocGroup::find($invest->group_id) : null;

                if (! $group) {
                    throw new RuntimeException('กรุณาเลือกหมวดงบประมาณก่อนส่ง / Please choose a budget category first');
                }

                // ร่างที่ค้างอยู่ในกลุ่มที่แอดมินเพิ่งปิด — ต้องให้ผู้ใช้เลือกกลุ่มใหม่ ไม่ใช่ออกเลขให้เงียบๆ
                if (! $group->active) {
                    throw new RuntimeException('หมวดงบประมาณ '.$group->name_th.' ถูกปิดใช้งานแล้ว กรุณาเลือกหมวดใหม่ / This budget category has been disabled — please choose another');
                }

                $invest->doc_no = $this->docNumber->next('budget_invests', 'INV', (int) $invest->fiscal_year, $group->code);
            }

            /*
              กุญแจของ QR Code ติดตามเอกสาร (เจ้าของสั่ง 2026-09-18 · DECISIONS 50.15)
              🔴 ออกที่นี่พร้อมเลขที่เอกสาร ด้วยเหตุผลเดียวกัน — ร่างยังแก้เนื้อหาได้และยังไม่มีเลข
                 ปริ้นร่างที่มี QR ออกไปแล้วสแกน จะได้เอกสารที่ยังเปลี่ยนได้ทุกบรรทัด
              🔴 มีกุญแจแล้วไม่ออกใหม่ — เอกสารที่ถูกตีกลับแล้วส่งซ้ำต้องสแกนด้วย QR ใบเดิมได้
            */
            if ((string) $invest->track_token === '') {
                $invest->track_token = TrackLink::newToken();
            }

            /*
              รอบของสายอนุมัติ (เจ้าของสั่ง 2026-09-09)
                รอบก่อนถูกตัดสินไปแล้ว (มีคนเซ็นผ่านหรือตีกลับ) → ขึ้นรอบใหม่ เก็บของเดิมไว้ดูย้อนหลัง
                รอบก่อนยังไม่มีใครแตะ (เป็นแค่ลำดับที่เลือกไว้ตอนร่าง) → เขียนทับรอบเดิม
              🔴 ห้ามลบทิ้งทุกครั้งแบบเดิม ไม่งั้นประวัติรอบที่ถูกตีกลับหายหมด
            */
            $rows = Approval::where('doc_type', Approval::DOC_INVEST)->where('doc_id', $invest->id);
            $lastRound = (int) (clone $rows)->max('round');

            $decided = $lastRound > 0 && (clone $rows)
                ->where('round', $lastRound)
                ->whereIn('status', [Approval::DONE, Approval::REJECTED])
                ->exists();

            if ($decided) {
                $round = $lastRound + 1;
            } else {
                $round = max($lastRound, 1);
                (clone $rows)->where('round', $round)->delete();
            }

            $step = 0;

            // เดินตามลำดับที่ผู้ใช้จัดไว้เป๊ะๆ ไม่แยกกองผู้รับทราบขึ้นก่อนแล้ว
            foreach ($people as $person) {
                $step++;
                $ack = ($person['action'] ?? Approval::APPROVE) === Approval::ACK;
                // ผู้รับทราบไม่ต้องรอคิว ติดสถานะ "รับทราบแล้ว" ตั้งแต่ตอนส่ง
                $this->addStep($invest, $round, $step, $ack ? Approval::ACK : Approval::APPROVE, $person, $ack ? Approval::NOTIFIED : null);
            }

            $invest->update([
                'doc_no' => $invest->doc_no,
                'approval_status' => Invest::PENDING,
                'submitted_at' => now(),
                'reject_reason' => null,
                'decided_at' => null,
            ]);
        });

        $this->log->record('invest_submitted', [
            'th' => 'ส่งข้อเสนอขอตั้งงบ '.$invest->doc_no.' เข้าสายอนุมัติ',
            'en' => 'Submitted budget proposal '.$invest->doc_no.' for approval',
        ], $invest->doc_no);

        /*
          แจ้งเตือน 2 กลุ่มที่ต่างกัน
            ผู้รับทราบ  -> "มีเอกสารส่งมาให้ทราบ"  (ไม่ต้องทำอะไรต่อ)
            ผู้อนุมัติ   -> "รอคุณลงนาม"           (มีงานค้าง)
        */
        $recipients = array_values(array_filter($people, fn ($p) => ($p['action'] ?? Approval::APPROVE) === Approval::ACK));

        /*
          🔴 ขอบเขตการส่งอีเมล (เจ้าของสั่ง 2026-09-21)
             ติดธง `mail` ไว้ 3 เหตุการณ์: ส่งเอกสาร → ผู้รับทราบ → ผู้อนุมัติไล่ทีละคนจนถึง CEO
             ส่วนขั้น "ลงทะเบียนงบประมาณ" จงใจไม่ติดธง (ดูเหตุผลที่ notifyRegistrars)
        */
        $this->notify->send(
            collect($recipients)->pluck('employee_code')->all(),
            $this->card('invest_cc', BudgetAccess::FN_INBOX, $invest, [
                'th' => 'มีเอกสารส่งมาให้ทราบ',
                'en' => 'A document was sent for your information',
            ]) + ['mail' => true]
        );

        /*
          🔴 แจ้งเฉพาะ "คนแรกในสาย" (เจ้าของสั่ง 2026-09-09)
             เพราะเอกสารเห็นได้ตามคิวแล้ว — แจ้งคนลำดับหลังตั้งแต่ตอนนี้
             เขาจะกดเข้าไปแล้วไม่เจอใบนั้น กลายเป็นแจ้งเตือนหลอก
             คนถัดไปจะได้รับแจ้งตอนคนก่อนหน้าเซ็นผ่าน (ดู approve())
        */
        $first = collect($approvers)->first();

        if ($first) {
            $this->notify->send(
                [$first['employee_code']],
                $this->card('invest_to_sign', BudgetAccess::FN_INBOX, $invest, [
                    'th' => 'รอคุณลงนามอนุมัติ',
                    'en' => 'Waiting for your signature',
                ]) + ['mail' => true]
            );
        }
    }

    /**
     * เซ็นผ่านขั้นของตัวเอง — รับทราบ หรือ ลงนามอนุมัติ
     *
     * ถ้าเป็นขั้นสุดท้ายและทุกคนเซ็นครบแล้ว จะสร้าง Budget ให้ในคำสั่งเดียวกัน
     */
    public function approve(Invest $invest, AppUser $user, ?string $comment = null): ?Budget
    {
        if (trim((string) $user->signature) === '') {
            throw new RuntimeException('ไม่พบลายเซ็นของคุณ กรุณาเพิ่มลายเซ็นใน Insight ก่อน / Your signature is missing');
        }

        $budget = null;

        DB::transaction(function () use (&$invest, $user, $comment, &$budget) {
            // ทุกการตัดสินของเอกสารเดียวกันต้องต่อคิวกัน ป้องกันผู้อนุมัติคนสุดท้ายสร้างงบซ้ำ
            $invest = Invest::whereKey($invest->id)->lockForUpdate()->firstOrFail();
            $step = $this->requireMyStep($invest, $user);

            $step->update([
                'status' => Approval::DONE,
                'acted_at' => now(),
                'comment' => $comment,
                // เก็บสำเนาลายเซ็น ณ ตอนเซ็น — ของต้นทางอยู่ที่ Insight และอาจเปลี่ยนทีหลัง
                'signature' => $user->signature,
            ]);

            // ยังมีผู้อนุมัติที่ยังไม่เซ็น = ยังไม่จบ (แถวผู้รับทราบไม่นับ เขาไม่ต้องเซ็น)
            $stillWaiting = Approval::where('doc_type', Approval::DOC_INVEST)
                ->where('doc_id', $invest->id)
                ->where('action', Approval::APPROVE)
                ->where('status', Approval::WAITING)
                ->exists();

            if ($stillWaiting) {
                return;
            }

            $invest->update([
                'approval_status' => Invest::APPROVED,
                'decided_at' => now(),
            ]);

            $budget = $this->createBudgetFrom($invest, $user);
        });

        /*
          แจ้งกลับผู้เสนอทุกครั้งที่เอกสารขยับ
            เซ็นครบ -> อนุมัติแล้ว พร้อมเลขที่งบ
            ยังไม่ครบ -> บอกว่าใครเซ็นไปแล้ว จะได้รู้ว่าไปถึงไหน
        */
        /*
          🔴 ผลลัพธ์ต้องอ่านได้จากสีทันที (เจ้าของสั่ง 2026-09-04)
             อนุมัติ = เขียว · เลขที่งบแยกไปอยู่บรรทัดของตัวเอง ไม่ยัดรวมในข้อความ
        */
        /*
          🔴 ความเห็นที่ผู้อนุมัติพิมพ์ไว้ ต้องขึ้นเป็นบรรทัด "เหตุผล:" ด้วย (เจ้าของแจ้ง 2026-09-07)
             ของเดิมเก็บลงตาราง budget_approvals.comment แต่ไม่เคยเอามาแสดงที่ไหนเลย
        */
        /*
          ลงนามแล้ว = เรื่องนี้ไม่ค้างที่เราอีก ปิดเลขแดงของใบนี้ให้เลย (เจ้าของแจ้ง 2026-09-07)
          🔴 ต้องอ่านก่อนส่งแจ้งเตือนใบใหม่ ไม่งั้นคนที่เสนอเองแล้วเซ็นเอง
             จะโดนลบแจ้งเตือน "อนุมัติแล้ว" ที่เพิ่งส่งให้ตัวเองทิ้งไปด้วย
        */
        $this->notify->markReadForDoc($user, $invest->doc_no);

        /*
          🔴 ไม่แจ้งกลับไปหาผู้ขอแล้ว (เจ้าของสั่ง 2026-09-10)
             แจ้งเตือนมีไว้บอก "คนที่ต้องลงมือทำ" เท่านั้น = ผู้ลงนามตามลำดับ 1, 2, 3, …
             ผู้ขอตามความคืบหน้าเองได้ที่คอลัมน์สถานะและหน้าต่าง "ดูเส้นทาง"
        */

        /*
          🔴 เซ็นผ่านแล้วต้องบอกคนถัดไปว่าถึงคิว (เจ้าของสั่ง 2026-09-09)
             เพราะเอกสารเห็นได้ตามคิว — ถ้าไม่แจ้ง คนลำดับหลังจะไม่รู้ว่าต้องเข้าไปเซ็น
             ไม่มีคนถัดไป (เซ็นครบแล้ว) ก็ไม่ต้องส่ง
        */
        if (! $budget) {
            $this->notifyNextSigner($invest);
        }

        /*
          🔴 คนสุดท้ายเซ็นแล้ว เอกสารเข้าคิวหน้า "ลงทะเบียน" ต้องบอกคนที่ดูแลหน้านั้น
             (เจ้าของสั่ง 2026-09-10) ไม่งั้นบัญชีไม่รู้ว่ามีใบใหม่รอคีย์เข้า ERP
             ต้องคอยเปิดหน้าเช็คเอง ซึ่งขัดกับหลักเดิมว่า "แจ้งเตือน = บอกคนที่ต้องลงมือทำ"
        */
        if ($budget) {
            $this->notifyRegistrars($invest, $budget);
        }

        $this->log->record($budget ? 'invest_approved' : 'invest_acknowledged', [
            'th' => ($budget ? 'อนุมัติข้อเสนอ ' : 'รับทราบข้อเสนอ ').$invest->doc_no
                .($budget ? ' — สร้างงบ '.$budget->doc_no : ''),
            'en' => ($budget ? 'Approved proposal ' : 'Acknowledged proposal ').$invest->doc_no
                .($budget ? ' — created budget '.$budget->doc_no : ''),
        ], $invest->doc_no);

        return $budget;
    }

    /**
     * ไม่อนุมัติ
     *
     * 🔴 เจ้าของสั่งเปลี่ยนกติกา 2026-09-16 — คนกลางทางไม่อนุมัติ "เอกสารยังเดินต่อถึง CEO"
     *    สาย 1,2,3,4,CEO ถ้าคนที่ 3 ไม่อนุมัติ เรื่องยังไปถึง CEO เหมือนเดิม
     *    บันทึกหมายเหตุสีแดงกับสถานะ "ไม่อนุมัติ" ไว้ที่ขั้นของเขา เพื่อเตือน CEO
     *    แล้วให้ **CEO เป็นคนตัดสินคนสุดท้าย** แทน
     *
     *    (กติกาเดิม 2026-09-10 คือใครไม่อนุมัติก็จบทั้งใบ · ยกเลิกแล้ว)
     *
     * 🔴 ทำได้เฉพาะผู้อนุมัติ — ผู้รับทราบตีกลับไม่ได้ (เจ้าของกำหนด 2026-09-03)
     */
    public function reject(Invest $invest, AppUser $user, string $reason): void
    {
        /*
          🔴 ไม่อนุมัติก็ต้องมีลายเซ็น เหมือนตอนอนุมัติ (เจ้าของถาม 2026-09-16)
             กติกาเดียวกันทั้งระบบ: "ตัดสินใจกับเอกสาร = ต้องเซ็นชื่อกำกับ"

             เหตุผลที่ต้องมี — ตั้งแต่ 2026-09-16 คนกลางทางไม่อนุมัติแล้ว
             เอกสารไม่จบ แต่เดินต่อไปหา CEO พร้อมข้อทักท้วงของเขา
             ข้อทักท้วงที่ไปถึงโต๊ะ CEO ต้องรู้ว่าใครเป็นคนทักและเซ็นรับรองไว้จริง
             ไม่ใช่แค่ข้อความลอยๆ ในช่องหมายเหตุ
        */
        if (trim((string) $user->signature) === '') {
            throw new RuntimeException('ไม่พบลายเซ็นของคุณ กรุณาเพิ่มลายเซ็นใน Insight ก่อน / Your signature is missing');
        }

        $ended = false;

        DB::transaction(function () use (&$invest, $user, $reason, &$ended) {
            $invest = Invest::whereKey($invest->id)->lockForUpdate()->firstOrFail();
            $step = $this->requireMyStep($invest, $user);

            if ($step->action !== Approval::APPROVE) {
                throw new RuntimeException('ผู้รับทราบไม่มีสิทธิ์ตีกลับเอกสาร');
            }

            $step->update([
                'status' => Approval::REJECTED,
                'acted_at' => now(),
                'comment' => $reason,
                // เก็บสำเนาลายเซ็น ณ ตอนตัดสิน — ของต้นทางอยู่ที่ Insight และอาจเปลี่ยนทีหลัง
                'signature' => $user->signature,
            ]);

            /*
              🔴 เอกสารจบก็ต่อเมื่อ "ผู้ลงนามปิดท้าย (CEO)" เป็นคนไม่อนุมัติ (เจ้าของสั่ง 2026-09-16)
                 คนกลางทางไม่อนุมัติ = บันทึกไว้เป็นข้อทักท้วง แล้วส่งต่อให้คนถัดไปตามปกติ
                 CEO จะเห็นสถานะ "ไม่อนุมัติ" พร้อมหมายเหตุที่ "ดูเส้นทาง" ก่อนตัดสินใจ

              🔴 เช็คจากธง is_final ไม่ใช่ "เป็นขั้นสุดท้ายไหม"
                 เพราะ CEO คือคนที่ระบบล็อกไว้ท้ายสายเสมอ ส่วนขั้นสุดท้ายอาจเปลี่ยนได้
            */
            $ended = (bool) $step->is_final;

            if (! $ended) {
                return;
            }

            /*
              🔴 พอสถานะไม่ใช่ร่าง ด่านที่มีอยู่แล้วจะกันให้เองทั้งหมด
                 แก้ไขไม่ได้ (InvestController) · ส่งซ้ำไม่ได้ (submit) · ลบไม่ได้ (canDelete)
            */
            $invest->update([
                'approval_status' => Invest::REJECTED,
                'decided_at' => now(),
                'reject_reason' => $reason,
            ]);
        });

        /*
          ตัดสินแล้ว = เรื่องนี้ไม่ค้างที่เราอีก ปิดเลขแดงของใบนี้ให้เลย (เจ้าของแจ้ง 2026-09-07)

          🔴 ไม่แจ้งกลับไปหาผู้ขอ (เจ้าของสั่ง 2026-09-10) — เอกสารจบแล้ว ไม่มีใครต้องลงมือทำต่อ
             ผู้ขอเห็นผลได้ที่คอลัมน์สถานะและหน้าต่าง "ดูเส้นทาง"
        */
        $this->notify->markReadForDoc($user, $invest->doc_no);

        $this->log->record('invest_rejected', [
            'th' => 'ไม่อนุมัติข้อเสนอ '.$invest->doc_no.' — '.$reason,
            'en' => 'Rejected proposal '.$invest->doc_no.' — '.$reason,
        ], $invest->doc_no);

        /*
          🔴 เอกสารยังเดินต่อ ต้องบอกคนถัดไปว่าถึงคิวแล้ว (เจ้าของสั่ง 2026-09-16)
             ถ้าไม่แจ้ง เอกสารจะค้างเงียบๆ ไม่มีใครรู้ว่าต้องเข้าไปตัดสินต่อ
             ตัวเดียวกับที่ approve() ใช้ — กติกาการหา "คนถัดไป" จึงตรงกันเสมอ
        */
        if (! $ended) {
            $this->notifyNextSigner($invest);
        }
    }

    /**
     * บอกผู้อนุมัติคนถัดไปว่าถึงคิวแล้ว
     *
     * 🔴 ใช้ร่วมกันทั้งตอนอนุมัติผ่านและตอนไม่อนุมัติแล้วเดินต่อ
     *    ห้ามเขียนตรรกะหา "คนถัดไป" ซ้ำ 2 ที่ ไม่งั้นวันหนึ่งจะไม่ตรงกัน
     */
    private function notifyNextSigner(Invest $invest): void
    {
        $next = $invest->approvals()
            ->where('action', Approval::APPROVE)
            ->where('status', Approval::WAITING)
            ->orderBy('step')
            ->first();

        if (! $next) {
            return;
        }

        $this->notify->send([(string) $next->employee_code], $this->card(
            'invest_to_sign',
            BudgetAccess::FN_INBOX,
            $invest,
            ['th' => 'รอคุณลงนามอนุมัติ', 'en' => 'Waiting for your signature'],
        ) + ['mail' => true]);
    }

    /**
     * ขั้นที่รอ "คนนี้" เซ็นอยู่ตอนนี้ — คืน null ถ้าไม่มี
     *
     * 🔴 มีแต่ผู้อนุมัติเท่านั้นที่มีคิว — ผู้รับทราบไม่ต้องทำอะไร จึงไม่เคยมีขั้นค้าง
     * 🔴 ต้องเซ็นเรียงตามลำดับ (เจ้าของสั่ง 2026-09-09) — ยังไม่ถึงคิวก็ยังไม่คืนขั้นให้
     *    ไม่งั้นปุ่มลงนามจะโผล่ให้ผู้อนุมัติลำดับหลังตั้งแต่เอกสารเพิ่งถูกส่ง
     */
    public function myPendingStep(Invest $invest, ?AppUser $user): ?Approval
    {
        if (! $user || $invest->approval_status !== Invest::PENDING) {
            return null;
        }

        $step = $invest->approvals
            ->where('action', Approval::APPROVE)
            ->where('status', Approval::WAITING)
            ->firstWhere('employee_code', $user->employee_code);

        if (! $step || $this->hasUnsignedEarlierStep($invest, $step)) {
            return null;
        }

        return $step;
    }

    /**
     * ยังมีผู้อนุมัติลำดับก่อนหน้าที่ยังไม่ลงนามอยู่ไหม
     *
     * อ่านจากคอลเลกชันที่โหลดมาแล้ว เพื่อไม่ยิงคิวรีซ้ำตอนวาดตารางหลายแถว
     */
    private function hasUnsignedEarlierStep(Invest $invest, Approval $step): bool
    {
        /*
          🔴 "ตัดสินแล้ว" นับทั้งอนุมัติและไม่อนุมัติ (เจ้าของสั่งเปลี่ยน 2026-09-16)
             ของเดิมนับเฉพาะ DONE — พอมีคนกลางทางไม่อนุมัติ เอกสารเดินต่อได้แล้ว
             คนลำดับถัดไปจะติดค้างตลอดกาล เพราะขั้นก่อนหน้าไม่มีทางเป็น DONE
        */
        return $invest->approvals
            ->where('action', Approval::APPROVE)
            ->where('step', '<', $step->step)
            ->contains(fn (Approval $earlier) => ! in_array($earlier->status, [Approval::DONE, Approval::REJECTED], true));
    }

    // ── ภายใน ───────────────────────────────────────────────────────

    /**
     * บอกคนที่ดูแลหน้า "ลงทะเบียน" ว่ามีงบใหม่รอคีย์เข้า ERP
     *
     * รายชื่อมาจากสิทธิ์หัวข้อ "ลงทะเบียนงบประมาณ" (fn.budget.approved) ที่ /access/modules ที่เดียว
     * ตรงกับรายชื่อที่ขึ้นเป็นขั้นสุดท้ายในตาราง "ดูเส้นทาง" อยู่แล้ว จะได้ไม่มีรายชื่อ 2 ชุด
     *
     * 🔴 หัวข้อนี้ตั้งเป็น "ทุกคน" ได้ แต่จงใจไม่ยิงแจ้งเตือนทั้งบริษัทในกรณีนั้น
     *    เพราะแจ้งเตือนของระบบนี้แปลว่า "งานที่ต้องลงมือทำ" ไม่ใช่ประกาศให้ทราบทั่วกัน
     *    และ Notifier ยิงทีละคน (คิวรีเช็คซ้ำ + insert ต่อคน) เอกสารใบเดียวจะกลายเป็น
     *    แถวแจ้งเตือนพันกว่าแถว ทำให้จังหวะกดอนุมัติค้างจนผู้ใช้รอไม่ไหว
     *    อยากให้ใครได้รับ ให้ระบุตัวคนที่ /access/modules
     *
     * 🔴 ขั้นนี้ "ไม่ส่งอีเมล" (เจ้าของสั่ง 2026-09-21) — จึงไม่ติดธง `mail` ให้ Notifier
     *    เหตุผลเดียวกับย่อหน้าบน: ตั้งเป็น "ทุกคน" ได้ ถ้าส่งเมลด้วยจะกลายเป็น
     *    เมลพันกว่าฉบับต่อเอกสาร 1 ใบ และขั้นนี้เป็นงานประจำของบัญชีที่เปิดหน้าคิวดูอยู่แล้ว
     */
    private function notifyRegistrars(Invest $invest, Budget $budget): void
    {
        if ($this->access->isEveryone(BudgetAccess::FN_BUDGETS)) {
            return;
        }

        $codes = $this->access->codesForFunction(BudgetAccess::FN_BUDGETS);

        if ($codes === []) {
            return;
        }

        $card = $this->card(
            'budget_to_register',
            BudgetAccess::FN_BUDGETS,
            $invest,
            ['th' => 'รอลงทะเบียนเข้า ERP', 'en' => 'Awaiting ERP registration'],
            null,
            // บอกเลขที่ใบต้นทางไว้ด้วย จะได้ตามกลับไปดูเอกสารขออนุมัติได้
            ['key' => 'budget.investNo', 'th' => $invest->doc_no, 'en' => $invest->doc_no],
        );

        /*
          🔴 ใบนี้เป็นเรื่องของ "ก้อนงบ" ไม่ใช่ใบ Invest — เลขที่กับลิงก์จึงต้องเป็นของงบ
             เลขแดงในตารางหน้า /budget/list จับคู่จากเลขที่งบ ถ้าใส่เลขที่ Invest จะไม่ขึ้น
        */
        $card['doc_no'] = $budget->doc_no;
        $card['route_name'] = 'budget.list.show';
        $card['route_param'] = (string) $budget->id;

        $this->notify->send($codes, $card);
    }

    /**
     * ประกอบข้อมูลแจ้งเตือน 1 ใบ
     *
     * หัวข้อ = เลขที่เอกสาร + แผนกที่ขอ จะได้รู้ทันทีว่าเรื่องของใคร โดยไม่ต้องกดเข้าไปดู
     *
     * @param  array{th:string,en:string}  $body
     * @param  string|null  $tone  ok = เขียว · no = แดง · null = สีปกติ
     * @param  array{key:string,th:string,en:string}|null  $note  บรรทัดเสริมที่ 1 เช่น เลขที่งบ
     * @param  array{key:string,th:string,en:string}|null  $note2  บรรทัดเสริมที่ 2 เช่น ความเห็นของผู้อนุมัติ
     * @return array<string,mixed>
     */
    private function card(string $event, string $functionKey, Invest $invest, array $body, ?string $tone = null, ?array $note = null, ?array $note2 = null): array
    {
        // แผนกที่ขอตั้งงบ — เก็บพร้อมรหัสนำหน้าอยู่แล้ว เช่น "IT · เทคโนโลยีสารสนเทศ"
        $dept = $invest->dept_name ?: $invest->dept_code;

        return [
            'module_id' => BudgetAccess::MODULE,
            'function_key' => $functionKey,
            'event' => $event,
            'doc_no' => $invest->doc_no,
            // ป้ายของบรรทัดที่ 2 ในแผงกระดิ่ง — โมดูลนี้ใช้ "แผนก"
            'subject_key' => 'budget.dept',
            /*
              🔴 เก็บ "ชื่อ route + เลขเอกสาร" ไม่ใช่ที่อยู่เต็ม (บั๊กจริง 2026-09-10)
                 ที่อยู่เต็มจะติดชื่อโฮสต์ของตอนที่ส่ง เปิดเว็บจากที่อื่นแล้วลิงก์พัง
            */
            'route_name' => 'budget.doc.show',
            'route_param' => (string) $invest->id,
            // ค่าของบรรทัด "แผนก" — เลขที่เอกสารอยู่คนละบรรทัดแล้ว ไม่ต้องใส่ซ้ำ
            'title_th' => $dept,
            'title_en' => $dept,
            'body_th' => $body['th'],
            'body_en' => $body['en'],
            // สีของข้อความผล + บรรทัดเหตุผล (ถ้ามี)
            'tone' => $tone,
            'note_key' => $note['key'] ?? null,
            'note_th' => $note['th'] ?? null,
            'note_en' => $note['en'] ?? null,
            'note2_key' => $note2['key'] ?? null,
            'note2_th' => $note2['th'] ?? null,
            'note2_en' => $note2['en'] ?? null,
        ];
    }

    /**
     * คนที่ต้องรู้ความคืบหน้าของเอกสารฉบับนี้
     *
     * 🔴 ต้องแจ้งทั้ง 2 คน — ฝ่ายบัญชีกรอกแทนหัวหน้าแผนกได้ (เจ้าของสั่ง 2026-09-04)
     *    คนกรอกต้องรู้ว่าเรื่องไปถึงไหน · ผู้เสนอต้องรู้ว่าเรื่องของตัวเองถูกอนุมัติหรือตีกลับ
     *    เป็นคนเดียวกันก็ไม่ซ้ำ เพราะ Notifier ตัดรหัสซ้ำให้อยู่แล้ว
     *
     * @return array<int,string>
     */

    /** @param  array{employee_code:string,employee_name:?string,position:?string}  $person */
    private function addStep(Invest $invest, int $round, int $step, string $action, array $person, ?string $status = null): void
    {
        Approval::create([
            'doc_type' => Approval::DOC_INVEST,
            'doc_id' => $invest->id,
            'round' => $round,
            'step' => $step,
            'action' => $action,
            'employee_code' => $person['employee_code'],
            'employee_name' => $person['employee_name'] ?? null,
            'employee_name_en' => $person['employee_name_en'] ?? null,
            'position' => $person['position'] ?? null,
            'department' => $person['department'] ?? null,
            // 🔴 ธงปิดท้ายต้องติดมากับคนด้วย ไม่งั้นพอส่งจริงแล้วธงหาย (บั๊กจริง 2026-09-09)
            'is_final' => (bool) ($person['is_final'] ?? false),
            'status' => $status ?? Approval::WAITING,
            // ผู้รับทราบถือว่าได้รับเอกสารตั้งแต่วินาทีที่ส่ง
            'acted_at' => $status === Approval::NOTIFIED ? now() : null,
        ]);
    }

    private function requireMyStep(Invest $invest, AppUser $user): Approval
    {
        if ($invest->approval_status !== Invest::PENDING) {
            throw new RuntimeException('เอกสารนี้ถูกดำเนินการไปแล้ว / This document has already been processed');
        }

        $step = Approval::where('doc_type', Approval::DOC_INVEST)
            ->where('doc_id', $invest->id)
            ->where('action', Approval::APPROVE)
            ->where('status', Approval::WAITING)
            ->where('employee_code', $user->employee_code)
            ->lockForUpdate()
            ->first();

        if (! $step) {
            throw new RuntimeException('ยังไม่ถึงคิวของคุณ หรือเอกสารนี้ถูกดำเนินการไปแล้ว / Not your pending step');
        }

        /*
          🔴 บังคับเซ็นตามลำดับ (เจ้าของสั่ง 2026-09-09) — ยกกติกามาจากระบบ Memo เดิม
             ถึงคิวเมื่อ "เป็นลำดับแรก" หรือ "ผู้อนุมัติก่อนหน้าลงนามครบแล้ว" เท่านั้น
             อ่านจากฐานตรงๆ เพราะอยู่ในทรานแซกชันที่ล็อกแถวอยู่ คอลเลกชันที่โหลดไว้อาจเก่าแล้ว
        */
        /*
          🔴 "ตัดสินแล้ว" นับทั้งอนุมัติและไม่อนุมัติ (เจ้าของสั่งเปลี่ยน 2026-09-16)
             ต้องตรงกับ hasUnsignedEarlierStep() เสมอ — กติกาเดียวกันเขียนไว้ 2 ที่
             (ที่นั่นใช้ตัดสินว่าจะโชว์ปุ่มไหม · ที่นี่กันจริงฝั่งเซิร์ฟเวอร์)
             แก้ที่เดียวแล้วลืมอีกที่ = ปุ่มโผล่แต่กดแล้วขึ้น "ยังไม่ถึงคิวของคุณ"
        */
        $waitingBefore = Approval::where('doc_type', Approval::DOC_INVEST)
            ->where('doc_id', $invest->id)
            ->where('round', $step->round)
            ->where('action', Approval::APPROVE)
            ->where('step', '<', $step->step)
            ->whereNotIn('status', [Approval::DONE, Approval::REJECTED])
            ->exists();

        if ($waitingBefore) {
            throw new RuntimeException('ยังไม่ถึงคิวของคุณ ต้องรอผู้อนุมัติลำดับก่อนหน้าลงนามก่อน / Waiting for an earlier approver to sign');
        }

        return $step;
    }

    /**
     * สร้างงบจากข้อเสนอที่อนุมัติแล้ว
     *
     * 🔴 วงเงินเก็บเป็นคอลัมน์ `approved_amount` ตรงๆ ในเฟสนี้
     *    รายการใน ledger บันทึกไว้เป็นโครงรองรับอนาคต แต่ยังไม่เอามาคิดยอดบนหน้าจอ
     *    เพราะยังไม่ confirm ว่าจะตัดงบตอน PR / PO / Payment
     */
    private function createBudgetFrom(Invest $invest, AppUser $approver): Budget
    {
        /*
          🔴 เลขที่งบใช้โค้ดกลุ่มเดียวกับข้อเสนอ (เจ้าของสั่ง 2026-09-17)
             INV-NM-2569-000001 -> BGT-NM-2569-00000x · เลขวิ่งของ BGT นับแยกจาก INV
             เพราะงบเกิดเฉพาะใบที่อนุมัติครบ ลำดับจึงไม่ตรงกับเลข INV และไม่ควรตรง
        */
        $group = $invest->group_id ? DocGroup::find($invest->group_id) : null;

        $budget = Budget::create([
            'doc_no' => $this->docNumber->next('budgets', 'BGT', (int) $invest->fiscal_year, (string) $group?->code),
            'group_id' => $invest->group_id,
            'invest_id' => $invest->id,
            'fiscal_year' => $invest->fiscal_year,
            'dept_code' => $invest->dept_code,
            'dept_name' => $invest->dept_name,
            'title' => $invest->title,
            'approved_amount' => $invest->amount,
            'approval_status' => Invest::APPROVED,
            // เซ็นครบแล้วเข้าคิวรอบัญชีคีย์เข้า ERP (เจ้าของสั่ง 2026-09-10)
            'budget_status' => Budget::PENDING_REGISTER,
            'approved_at' => now(),
            'approved_by' => $approver->employee_code,
        ]);

        $this->ledger->record(
            $budget,
            BudgetTransaction::INITIAL,
            (float) $invest->amount,
            'ตั้งงบจากข้อเสนอ '.$invest->doc_no,
            'invest',
            $invest->id,
        );

        return $budget;
    }
}
