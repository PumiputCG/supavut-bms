<?php

namespace App\Models\Budget;

use App\Support\TrackLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ข้อเสนอขอวงเงินงบประมาณ (Invest Proposal)
 *
 * 🔴 Invest = ขอ "วงเงิน" ระดับแผนก **ไม่ใช่ใบขอซื้อสินค้า**
 *    จึงไม่มีรายการสินค้า/หน่วย/จำนวน/ราคาต่อหน่วย — ของพวกนั้นเป็นเรื่องของ PR (เจ้าของยืนยัน 2026-09-03)
 *
 * เส้นทางของเอกสาร (เจ้าของกำหนด 2026-09-03)
 *   DRAFT  ->  PENDING_APPROVAL  ->  APPROVED   แล้วระบบสร้าง Budget ให้
 *                               \->  REJECTED
 *
 * 🔴 แก้ไขได้เฉพาะตอนเป็น DRAFT — ส่งเรื่องแล้วห้ามแก้
 *    ไม่งั้นคนที่เซ็นรับทราบไปแล้วจะเซ็นให้กับเนื้อหาที่ไม่ตรงกับของจริง
 */
class Invest extends Model
{
    protected $table = 'budget_invests';

    protected $fillable = [
        'doc_no', 'track_token', 'group_id', 'fiscal_year', 'proposed_at', 'proposer_code', 'proposer_name', 'proposer_name_en',
        'dept_code', 'dept_name',
        'title', 'description', 'amount', 'approval_status',
        'created_by', 'created_by_name', 'created_by_name_en', 'submitted_at', 'decided_at', 'reject_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'proposed_at' => 'date',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public const DRAFT = 'DRAFT';

    public const PENDING = 'PENDING_APPROVAL';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    /**
     * ลิงก์ของหน้าติดตามเอกสาร — ตัวที่ฝังใน QR Code (เจ้าของสั่ง 2026-09-18)
     *
     * 🔴 คืน null เมื่อยังไม่มีกุญแจ (= ร่างที่ยังไม่ได้กดส่ง) ให้หน้าจอไม่ต้องวาด QR
     *    ร่างไม่มีเลขที่เอกสารและยังแก้เนื้อหาได้ ปริ้นไปสแกนก็ไม่มีประโยชน์
     */
    public function trackUrl(): ?string
    {
        $token = (string) $this->track_token;

        return $token === '' ? null : TrackLink::url($token);
    }

    /** ป้ายสถานะ 2 ภาษา — เก็บไว้ที่เดียว หน้าไหนก็เรียกอันนี้ */
    public const STATUS_LABELS = [
        self::DRAFT => ['th' => 'ร่าง', 'en' => 'Draft'],
        self::PENDING => ['th' => 'รออนุมัติ', 'en' => 'Pending approval'],
        self::APPROVED => ['th' => 'อนุมัติแล้ว', 'en' => 'Approved'],
        self::REJECTED => ['th' => 'ไม่อนุมัติ', 'en' => 'Rejected'],
    ];

    /**
     * กลุ่มเอกสาร — ตัวกำหนดโค้ดในเลขที่ (เจ้าของสั่ง 2026-09-17)
     * 🔴 ร่างเปลี่ยนกลุ่มได้อิสระ · ส่งแล้วล็อก เพราะเลขที่ออกตอนกดส่งจากโค้ดของกลุ่มนี้
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(DocGroup::class, 'group_id');
    }

    /**
     * เลขที่สำหรับแสดงผล — ร่างยังไม่มีเลข จึงคืนค่าว่าง ให้หน้าจอขึ้น "ยังไม่ออกเลข" เอง
     * 🔴 เลขออกตอนกดส่งเท่านั้น (เจ้าของสั่ง 2026-09-17)
     */
    public function hasNumber(): bool
    {
        return (string) $this->doc_no !== '';
    }

    /**
     * ลบเอกสารนี้ได้ไหม (เจ้าของกำหนด 2026-09-03)
     *
     *   ร่าง            ลบได้
     *   รออนุมัติ        ลบได้ — ยังไม่มีใครตัดสิน
     *   อนุมัติแล้ว      🔴 ลบไม่ได้ — มีงบผูกอยู่แล้ว
     *   ไม่อนุมัติ       🔴 ลบไม่ได้ — ต้องเก็บไว้ตรวจย้อนว่าใครตีกลับเพราะอะไร
     */
    public function canDelete(): bool
    {
        if ($this->approval_status === self::DRAFT) {
            /*
              🔴 ร่างที่ "เคยถูกตีกลับ" ลบไม่ได้ (เจ้าของสั่ง 2026-09-09)
                 ไม่อนุมัติแล้วเอกสารกลับมาเป็นร่างให้แก้ ถ้าปล่อยให้ลบได้
                 ประวัติว่าใครตีกลับเพราะอะไรจะหายไปด้วย
            */
            return ! $this->allApprovals()->where('status', Approval::REJECTED)->exists();
        }

        if ($this->approval_status !== self::PENDING) {
            return false;
        }

        /*
          สถานะหัวเอกสารยังเป็น PENDING ระหว่างรอผู้อนุมัติคนที่เหลือ
          จึงต้องตรวจสายอนุมัติด้วย ไม่เช่นนั้นลายเซ็นที่ประทับแล้วจะถูกลบตามเอกสาร
        */
        return ! $this->approvals()
            ->where('action', Approval::APPROVE)
            ->whereIn('status', [Approval::DONE, Approval::REJECTED])
            ->exists();
    }

    /**
     * ผู้เสนอ — คนที่เป็นเจ้าของเรื่องจริง ไม่ใช่คนที่นั่งกรอก
     *
     * 🔴 เอกสารก่อน 2026-09-04 ยังไม่มีคอลัมน์นี้ ต้องถอยไปใช้ผู้สร้างเสมอ
     *    ไม่งั้นเอกสารเก่าจะโชว์ช่องว่างในช่องลงชื่อ
     */
    public function proposerCode(): string
    {
        return (string) ($this->proposer_code ?: $this->created_by);
    }

    public function proposerName(): string
    {
        return (string) ($this->proposer_name ?: $this->created_by_name ?: $this->proposerCode());
    }

    /**
     * ชื่อผู้ขอภาษาอังกฤษ — ไม่มีให้ถอยไปใช้ชื่อไทย
     *
     * 🔴 ห้ามคืนค่าว่าง ไม่งั้นสลับเป็น ENG แล้วชื่อหายทั้งช่อง
     */
    public function proposerNameEn(): string
    {
        return (string) ($this->proposer_name_en ?: $this->proposerName());
    }

    /** ชื่อคนที่กรอกเอกสาร (คนละคนกับผู้ขอได้) — ภาษาอังกฤษ ไม่มีก็ถอยไปใช้ไทย */
    public function creatorNameEn(): string
    {
        return (string) ($this->created_by_name_en ?: $this->created_by_name ?: $this->created_by);
    }

    /** วันที่เสนอ — ไม่ได้กรอกไว้ให้ใช้วันที่สร้างเอกสารแทน */
    public function proposedDate(): ?Carbon
    {
        return $this->proposed_at ?: $this->created_at;
    }

    public function files(): HasMany
    {
        return $this->hasMany(InvestFile::class, 'invest_id');
    }

    /**
     * สายอนุมัติ "รอบล่าสุด" เท่านั้น
     *
     * 🔴 เอกสารที่ถูกตีกลับแล้วส่งใหม่จะมีสายเซ็นหลายรอบ (คอลัมน์ round)
     *    ทุกหน้าจอต้องเห็นเฉพาะรอบปัจจุบัน ไม่งั้นจะโชว์ผู้เซ็นซ้ำกัน 2 ชุด
     *    กรองด้วย subquery เพื่อให้ eager load — with('approvals') — ยังทำงานได้ตามปกติ
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'doc_id')
            ->where('doc_type', Approval::DOC_INVEST)
            ->whereRaw('budget_approvals.round = (
                select max(r.round) from budget_approvals r
                where r.doc_type = budget_approvals.doc_type and r.doc_id = budget_approvals.doc_id
            )')
            ->orderBy('step');
    }

    /** สายอนุมัติ "ทุกรอบ" — ใช้ย้อนดูประวัติรอบที่ถูกตีกลับ */
    public function allApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'doc_id')
            ->where('doc_type', Approval::DOC_INVEST)
            ->orderBy('round')
            ->orderBy('step');
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'id', 'invest_id');
    }

    public function statusLabel(string $lang = 'th'): string
    {
        return self::STATUS_LABELS[$this->approval_status][$lang] ?? $this->approval_status;
    }

    public function isDraft(): bool
    {
        return $this->approval_status === self::DRAFT;
    }

    /** ขั้นที่กำลังรออยู่ — ใช้บอกว่าตอนนี้ถึงคิวใคร */
    public function currentStep(): ?Approval
    {
        return $this->approvals->firstWhere('status', Approval::WAITING);
    }
}
