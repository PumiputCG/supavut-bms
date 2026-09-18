<?php

namespace App\Services\Budget;

use App\Models\Budget\DocGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * แท็บกรอง "กลุ่มเอกสาร" — ใช้ร่วม 4 หน้า (เจ้าของสั่ง 2026-09-17)
 *   ของบประมาณ · สถานะการดำเนินการ · ลงทะเบียน · ประวัติงบประมาณ
 *
 * 🔴 ตรรกะอยู่ที่นี่ที่เดียว หน้าไหนก็เรียกชุดเดียวกัน
 *    ไม่งั้น 4 หน้าจะนับ/กรองไม่ตรงกันสักวัน (บทเรียนเดิมจากตัวหารสัดส่วน 2026-09-07)
 *
 * 🔴 ลำดับที่ต้องทำในทุกหน้า
 *    1) นับจำนวนต่อกลุ่ม "ก่อน" กรองกลุ่ม — ทุกแท็บจะได้โชว์ยอดของตัวเองเสมอ
 *    2) ค่อยกรองกลุ่มที่เลือก — ตัวเลขสรุปและตารางข้างล่างเดินตามแท็บที่เลือก
 *    ถ้ากรองก่อนนับ เลือกแท็บหนึ่งแล้วแท็บอื่นกลายเป็น 0 หมด
 */
class DocGroupTabs
{
    /** แท็บ "ทั้งหมด" — เป็นค่าเริ่มต้น (เจ้าของเคาะ DECISIONS 50.3 ข้อ 8) */
    public const ALL = 'all';

    /** เอกสารที่ไม่ได้ผูกกลุ่ม — ปกติไม่ควรมี แท็บนี้โผล่เฉพาะเมื่อมีจริง */
    public const NONE = 'none';

    /** แท็บที่ผู้ใช้เลือก — ค่าแปลกปลอมทุกชนิดถอยไปเป็น "ทั้งหมด" ไม่ใช่ 500 */
    public function picked(Request $request): string
    {
        $value = (string) $request->query('group', self::ALL);

        return ctype_digit($value) || $value === self::NONE ? $value : self::ALL;
    }

    public function applyQuery(Builder $query, string $picked, string $column = 'group_id'): void
    {
        match (true) {
            $picked === self::NONE => $query->whereNull($column),
            ctype_digit($picked) => $query->where($column, (int) $picked),
            default => null,
        };
    }

    public function applyCollection(Collection $rows, string $picked): Collection
    {
        return match (true) {
            $picked === self::NONE => $rows->filter(fn ($row) => $row->group_id === null)->values(),
            ctype_digit($picked) => $rows->filter(fn ($row) => (int) $row->group_id === (int) $picked)->values(),
            default => $rows,
        };
    }

    /**
     * จำนวนเอกสารต่อกลุ่ม
     *
     * 🔴 reorder() ก่อน groupBy — คิวรีหน้ารายการมี ORDER BY ติดมา
     *    MySQL ไม่ยอมให้ ORDER BY คอลัมน์ที่ไม่ได้อยู่ใน SELECT ตอนจัดกลุ่ม (บทเรียนเดิม)
     *
     * @return array<string,int> รหัสกลุ่ม (ข้อความ) => จำนวน · ไม่มีกลุ่มใช้คีย์ 'none'
     */
    public function countQuery(Builder $query, string $column = 'group_id'): array
    {
        $out = [];

        (clone $query)->reorder()
            ->selectRaw($column.' AS g, COUNT(*) AS n')
            ->groupBy($column)
            ->get()
            ->each(function ($row) use (&$out) {
                $out[$row->g === null ? self::NONE : (string) $row->g] = (int) $row->n;
            });

        return $out;
    }

    /** @return array<string,int> */
    public function countCollection(Collection $rows): array
    {
        return $rows->countBy(fn ($row) => $row->group_id === null ? self::NONE : (string) $row->group_id)->all();
    }

    /**
     * ข้อมูลแท็บสำหรับวาด
     *
     * @param  array<string,int>  $counts
     * @return array{picked:string,tabs:array<int,array<string,mixed>>}
     */
    public function tabs(array $counts, string $picked): array
    {
        $tabs = [[
            'key' => self::ALL,
            'i18n' => 'budget.group.all',
            'th' => 'ทั้งหมด',
            'en' => 'All',
            'code' => null,
            'n' => array_sum($counts),
            'active' => true,
        ]];

        foreach (DocGroup::query()->ordered()->get() as $group) {
            $n = (int) ($counts[(string) $group->id] ?? 0);

            /*
              🔴 กลุ่มที่ปิดใช้งานแล้วยังต้องมีแท็บ ถ้ายังมีเอกสารอยู่
                 ไม่งั้นเอกสารเก่าของกลุ่มนั้นจะกรองดูแยกไม่ได้อีกเลย
                 ไม่มีเอกสารแล้วค่อยซ่อน — ไม่งั้นแท็บรกด้วยกลุ่มที่เลิกใช้
            */
            if (! $group->active && $n === 0) {
                continue;
            }

            $tabs[] = [
                'key' => (string) $group->id,
                'i18n' => null,
                'th' => $group->name_th,
                'en' => $group->name_en ?: $group->name_th,
                'code' => $group->code,
                'n' => $n,
                'active' => $group->active,
            ];
        }

        if (($counts[self::NONE] ?? 0) > 0) {
            $tabs[] = [
                'key' => self::NONE,
                'i18n' => 'budget.group.none',
                'th' => 'ไม่ระบุกลุ่ม',
                'en' => 'No group',
                'code' => null,
                'n' => (int) $counts[self::NONE],
                'active' => true,
            ];
        }

        // เลือกกลุ่มที่ไม่มีแท็บแล้ว (ลิงก์เก่า) — ถอยไปทั้งหมด ไม่ปล่อยให้หน้าว่างโดยไม่มีแท็บไหนติด
        if (! collect($tabs)->contains('key', $picked)) {
            $picked = self::ALL;
        }

        return ['picked' => $picked, 'tabs' => $tabs];
    }
}
