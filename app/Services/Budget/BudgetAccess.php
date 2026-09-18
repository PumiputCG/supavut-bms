<?php

namespace App\Services\Budget;

use App\Models\Budget\Budget;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Services\Access\AccessService;

/**
 * ใครทำอะไรได้บ้างในโมดูลงบประมาณ
 *
 * 🔴 เจ้าของสั่งยุบมาที่เดียว 2026-09-03 — บทบาททั้งหมดอ่านจากหัวข้อย่อยที่ /access/modules
 *    เลิกใช้ตาราง `budget_roles` แล้ว (เดิมต้องตั้ง 2 ที่ แล้วขัดกันเองจนขึ้น 403 ทั้งที่ให้สิทธิ์ไปแล้ว)
 *
 *    หัวข้อย่อย                    = บทบาท
 *    ─────────────────────────────────────────────────────────
 *    fn.budget.invest             ฝ่ายบัญชี — สร้างและส่ง Invest ได้
 *    fn.budget.inbox              เข้าหน้ารับทราบ/อนุมัติ Invest ได้
 *    fn.budget.approved           เห็นหน้า ลงทะเบียนงบประมาณ (และปรับสถานะงบได้)
 *    fn.budget.history            เห็นประวัติงบประมาณ
 *
 * 🔴 ยุบ "รับทราบ" + "อนุมัติ" เป็น fn.budget.inbox เมื่อ 2026-09-09
 *    เพราะผู้ขอเลือกผู้อนุมัติเองตอนส่งแล้ว ไม่ต้องตั้งรายชื่อผู้ลงนามล่วงหน้าอีก
 *    ใครเซ็นใบไหนได้ ตัดสินจาก "มีชื่ออยู่ในเอกสารฉบับนั้น" ไม่ใช่จากรายชื่อสิทธิ์
 *
 * 🔴 กติกาตั้งแต่ 2026-09-07: หัวข้อที่ตั้งสิทธิ์ได้แต่ยังไม่ระบุใคร = ไม่มีใครใช้ได้
 *    หากต้องการเปิดทุกคนต้องติ๊ก ทุกคนที่เข้าระบบได้ เอง ส่วนหัวข้อรับทราบเป็น no_edit
 *    จึงตรวจจากการมีชื่ออยู่ในเอกสาร ไม่ใช้รายชื่อสิทธิ์ล่วงหน้า ผู้ดูแลระบบผ่านทุกอย่างเสมอ
 */
class BudgetAccess
{
    public const MODULE = 'budget';

    public const FN_PROPOSE = 'fn.budget.invest';

    /** รับทราบ + อนุมัติ ใช้หัวข้อเดียวกันแล้ว (ยุบเมื่อ 2026-09-09) */
    public const FN_INBOX = 'fn.budget.inbox';

    public const FN_BUDGETS = 'fn.budget.approved';

    public function __construct(private readonly AccessService $access) {}

    /** เสนอ Invest ได้ไหม (ฝ่ายบัญชี) */
    public function canPropose(?AppUser $user): bool
    {
        return $this->access->canUseFunction($user, self::MODULE, self::FN_PROPOSE);
    }

    /** ลงนามอนุมัติ Invest ได้ไหม */
    public function canApprove(?AppUser $user): bool
    {
        return $this->access->canUseFunction($user, self::MODULE, self::FN_INBOX);
    }

    /**
     * เห็นงบก้อนนี้ได้ไหม
     *
     * 🔴 ตัดสินจากสิทธิ์หัวข้อย่อยอย่างเดียว (เจ้าของสั่ง 2026-09-03)
     *    เดิมกรองเพิ่มด้วยแผนกของตัวเอง ทำให้คนที่ได้สิทธิ์แล้วยังเห็นจอว่าง — สับสนมาก
     *    ใครควรเห็นบ้างให้ไปกำหนดที่ /access/modules หัวข้อ "ลงทะเบียน"
     */
    public function canViewBudget(?AppUser $user, Budget $budget): bool
    {
        return $this->access->canUseFunction($user, self::MODULE, self::FN_BUDGETS);
    }

    /**
     * มีชื่ออยู่ในเอกสาร Invest ฉบับนี้ไหม (ผู้เสนอ · คนกรอก · คนในสายอนุมัติ · ผู้ดูแลระบบ)
     *
     * 🔴 ยกมาไว้ที่นี่เมื่อ 2026-09-18 เพราะเดิมเขียนอยู่ใน ApprovalController::show() ที่เดียว
     *    พอเพิ่มหน้าพิมพ์ · ไฟล์ PDF · หน้าติดตาม ต้องใช้กติกาเดียวกัน 4 ที่
     *    ปล่อยให้ก็อปไปวางคือทางตรงที่สุดที่กติกาจะหลุดจากกันภายหลัง
     */
    public function involvesInvest(?AppUser $user, Invest $invest): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->access->isAdmin($user)) {
            return true;
        }

        $code = (string) $user->employee_code;

        $inFlow = $invest->approvals->contains(fn ($row) => (string) $row->employee_code === $code);

        return $inFlow || in_array($code, [(string) $invest->created_by, $invest->proposerCode()], true);
    }

    /**
     * เปิดดูเอกสาร Invest ฉบับนี้ได้ไหม
     *
     * 🔴 ร่างที่ยังไม่กดส่ง เปิดได้เฉพาะเจ้าของเรื่องกับผู้ดูแลระบบ (บั๊กที่เจ้าของแจ้ง 2026-09-10)
     *    ผู้อนุมัติมีแถวในสายเอกสารตั้งแต่ตอนบันทึกร่าง จึงผ่าน involvesInvest() ตั้งแต่ยังไม่ส่ง
     */
    public function canViewInvest(?AppUser $user, Invest $invest): bool
    {
        if (! $this->involvesInvest($user, $invest)) {
            return false;
        }

        if ($invest->approval_status !== Invest::DRAFT) {
            return true;
        }

        $code = (string) $user->employee_code;

        return $this->access->isAdmin($user)
            || in_array($code, [(string) $invest->created_by, $invest->proposerCode()], true);
    }

    /** เห็นหน้า/ข้อมูล ลงทะเบียนได้ไหม */
    public function canUseBudgetList(?AppUser $user): bool
    {
        return $this->access->canUseFunction($user, self::MODULE, self::FN_BUDGETS);
    }

    /** เปลี่ยนสถานะงบได้ไหม — สิทธิ์ย่อยที่ต้องระบุตัวคน */
    public function canChangeStatus(?AppUser $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->access->isAdmin($user)) {
            return true;
        }

        /*
          🔴 ถอดสิทธิ์ย่อย "ปรับสถานะงบประมาณ" ออกแล้ว (เจ้าของสั่ง 2026-09-09)
             ใครเห็นหน้า "ลงทะเบียน" ได้ ก็ปรับสถานะได้เลย ไม่ต้องมีสิทธิ์ซ้อนอีกชั้น
        */
        return $this->access->canUseFunction($user, self::MODULE, self::FN_BUDGETS);
    }

    /**
     * รหัสพนักงาน "ตัวจริง" ที่เลือกได้ในหัวข้อย่อยนี้
     *
     * 🔴 คืน [] ได้ 2 กรณี — ยังไม่ระบุใคร (ไม่มีใครใช้ได้) หรือติ๊ก "ทุกคน" ไว้
     *    ถ้าต้องแยกให้ถาม isEveryone() ต่อ
     *
     * @return array<int,string>
     */
    public function codesForFunction(string $functionKey): array
    {
        return $this->access->codesForFunction(self::MODULE, $functionKey);
    }

    /** หัวข้อย่อยนี้ตั้งเป็น "ทุกคน" ไว้ไหม */
    public function isEveryone(string $functionKey): bool
    {
        return $this->access->isEveryone(self::MODULE, $functionKey);
    }

    /**
     * แปลงรหัสพนักงานเป็นชื่อ/ตำแหน่ง/รูป — ส่งต่อไปที่ตัวกลางของระบบสิทธิ์
     *
     * @param  array<int,string>  $codes
     * @return array<int,array<string,mixed>>
     */
    public function describeCodes(array $codes): array
    {
        return $this->access->describeCodes($codes);
    }
}
