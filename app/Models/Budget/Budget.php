<?php

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * งบประมาณที่อนุมัติแล้ว — เกิดขึ้นเองเมื่อ CEO ลงนามอนุมัติ Invest
 *
 * 🔴 มี 2 สถานะแยกกัน ห้ามยุบรวม (กฎใน DECISIONS 4.3)
 *      approval_status  เอกสารผ่านการอนุมัติหรือยัง — ที่นี่เป็น APPROVED เสมอ
 *      budget_status    ตอนนี้ใช้เงินก้อนนี้ได้ไหม (ACTIVE / HOLD / STOPPED / CLOSED)
 *    สั่งพักงบต้องไม่ไปแตะ approval_status
 *
 * 🔴 เฟสนี้เก็บวงเงินเป็นคอลัมน์ `approved_amount` ตรงๆ
 *    ยังไม่คิดยอดจาก ledger เพราะ **ยังไม่ confirm ว่าจะตัดงบตอน PR / PO / Payment**
 *    ตาราง budget_transactions ยังอยู่เป็นโครงรองรับอนาคต แต่ยังไม่เอามาแสดงบนหน้าจอ
 */
class Budget extends Model
{
    protected $table = 'budgets';

    protected $fillable = [
        'doc_no', 'group_id', 'invest_id', 'fiscal_year', 'dept_code', 'dept_name',
        'title', 'approved_amount',
        'approval_status', 'budget_status', 'approved_at', 'approved_by', 'status_note',
        // ใครติ๊กว่าคีย์เข้า ERP แล้ว เมื่อไหร่ (เจ้าของสั่ง 2026-09-10)
        'registered_at', 'registered_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'registered_at' => 'datetime',
        'approved_amount' => 'decimal:2',
    ];

    /*
      🔴 สถานะของงบ = "บัญชีคีย์เข้า ERP หรือยัง" (เจ้าของสั่ง 2026-09-10 · DECISIONS ข้อ 37.2)

         ของเดิมคือ ใช้งานอยู่ / พักใช้ / หยุดใช้ / ปิดงบ ซึ่งตอบว่า "เงินก้อนนี้ยังใช้ได้ไหม"
         — นั่นเป็นเรื่องของ ERP ไม่ใช่ของ SBMS จึงตัดทิ้งทั้งชุด ไม่ใช่เพิ่มเข้าไป
         เก็บทั้งคู่ไว้จะขัดกันเองและซ้ำหน้าที่กับ ERP
    */
    public const PENDING_REGISTER = 'PENDING_REGISTER';

    public const REGISTERED = 'REGISTERED';

    public const STATUS_LABELS = [
        self::PENDING_REGISTER => ['th' => 'รอลงทะเบียน', 'en' => 'Awaiting registration'],
        self::REGISTERED => ['th' => 'ลงทะเบียนแล้ว', 'en' => 'Registered'],
    ];

    /** กลุ่มเอกสาร — สำเนามาจากข้อเสนอ ตอนสร้างงบ (กรองแท็บได้โดยไม่ต้อง join) */
    public function group(): BelongsTo
    {
        return $this->belongsTo(DocGroup::class, 'group_id');
    }

    /**
     * ลิงก์ของหน้าติดตามเอกสาร — ตัวที่ฝังใน QR Code (เจ้าของสั่ง 2026-09-18)
     *
     * 🔴 ใบงบ **ไม่มีกุญแจของตัวเอง** อ่านจากใบ INV ต้นทางเสมอ (DECISIONS 50.15.2)
     *    เพราะ INV กับ BGT เป็น "เรื่องเดียว" เส้นเดียว (1 ใบ INV = 1 ใบ BGT · สายเซ็นอยู่ที่ INV)
     *    สแกนจากใบไหนก็ต้องเข้าหน้าติดตามหน้าเดียวกัน
     *
     * 🔴 คืน null เมื่อไม่มีใบ INV ต้นทาง (โครงตารางอนุญาตให้ว่างได้ แม้ปัจจุบันไม่มีแถวแบบนั้น)
     *    หน้าจอจะข้ามบล็อก QR ไปเฉยๆ ไม่ใช่หน้าพัง
     */
    public function trackUrl(): ?string
    {
        return $this->invest?->trackUrl();
    }

    public function invest(): BelongsTo
    {
        return $this->belongsTo(Invest::class, 'invest_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class, 'budget_id')->orderByDesc('created_at');
    }

    public function statusLabel(string $lang = 'th'): string
    {
        return self::STATUS_LABELS[$this->budget_status][$lang] ?? $this->budget_status;
    }

    /**
     * บัญชีคีย์งบก้อนนี้เข้า ERP แล้วหรือยัง
     *
     * 🔴 เลิกถามว่า "ใช้เงินได้ไหม" แล้ว (เจ้าของสั่ง 2026-09-10)
     *    เรื่องเงินใช้ได้/ไม่ได้เป็นของ ERP · SBMS ตอบได้แค่ว่าลงทะเบียนหรือยัง
     */
    public function isRegistered(): bool
    {
        return $this->budget_status === self::REGISTERED;
    }
}
