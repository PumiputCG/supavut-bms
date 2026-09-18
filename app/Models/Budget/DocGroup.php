<?php

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

/**
 * หมวดงบประมาณ — ตัวกำหนดโค้ดที่แทรกอยู่ในเลขที่เอกสาร (เจ้าของสั่ง 2026-09-17)
 *
 * 🔴 ผู้ใช้เห็นเป็น "หมวดงบประมาณ" · ในโค้ดยังชื่อ DocGroup / group_id (เปลี่ยนแค่คำที่ผู้ใช้เห็น)
 *
 *   New Model + โค้ด NM  ->  INV-NM-2569-000001  ·  BGT-NM-2569-000001
 *
 * 🔴 โค้ดช่องเดียวใช้กับเอกสารทั้ง 2 ชนิด (เจ้าของยืนยัน)
 */
class DocGroup extends Model
{
    protected $table = 'budget_doc_groups';

    protected $fillable = ['code', 'name_th', 'name_en', 'sort', 'active', 'created_by'];

    protected $casts = ['active' => 'boolean', 'sort' => 'integer'];

    /** ชื่อกลุ่มตามภาษาที่เปิดอยู่ — หน้าเว็บสลับเองด้วย data-loc-th/en */
    public function name(string $lang = 'th'): string
    {
        return $lang === 'en' ? ($this->name_en ?: $this->name_th) : $this->name_th;
    }

    /**
     * เอกสารที่ "ออกเลขแล้ว" ของกลุ่มนี้ — ร่างไม่นับ เพราะยังไม่มีเลข
     * ใช้ตัดสินว่าโค้ดล็อกหรือยัง (เลขที่ออกไปแล้วเปลี่ยนไม่ได้)
     */
    public function issuedCount(): int
    {
        return Invest::where('group_id', $this->id)->whereNotNull('doc_no')->count()
            + Budget::where('group_id', $this->id)->count();
    }

    /**
     * ทุกแถวที่อ้างกลุ่มนี้ **รวมร่าง** — ใช้ตัดสินว่าลบกลุ่มได้ไหม
     * ลบทั้งที่ยังมีร่างค้างอยู่ ร่างจะกำพร้าและออกเลขตอนกดส่งไม่ได้
     */
    public function docCount(): int
    {
        return Invest::where('group_id', $this->id)->count()
            + Budget::where('group_id', $this->id)->count();
    }

    /** 🔴 มีเอกสารที่ออกเลขแล้ว = แก้โค้ดไม่ได้ แก้ได้แค่ชื่อ (เจ้าของสั่ง) */
    public function codeLocked(): bool
    {
        return $this->issuedCount() > 0;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** เรียงตามลำดับที่สร้าง (เจ้าของสั่งเอาช่อง "ลำดับการแสดง" ออก 2026-09-17) — sort ใส่เลขถัดไปให้เองตอนสร้าง */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
