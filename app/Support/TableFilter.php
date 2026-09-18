<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * ตัวกรองรายคอลัมน์ที่ทำงานฝั่งเซิร์ฟเวอร์ — กรองครบทุกหน้า ไม่ใช่เฉพาะหน้าที่เปิดอยู่
 *
 * 🔴 เจ้าของสั่ง 2026-09-04: ตารางที่แบ่งหน้า เวลากรองต้องได้ผลจาก **ทุกหน้า**
 *    ของเดิมกรองด้วย JavaScript จากแถวที่วาดอยู่ในหน้านั้นเท่านั้น
 *    พิมพ์ค้นหา "IT" แล้วได้แค่รายการ IT ที่บังเอิญอยู่หน้านั้น — ผิดความคาดหวังของผู้ใช้
 *
 * วิธีใช้ในหน้า controller
 *
 *   $map = [
 *       'dept'   => ['column' => 'dept_name'],
 *       'status' => ['column' => 'budget_status', 'labels' => Budget::STATUS_LABELS],
 *       'amount' => ['column' => 'approved_amount', 'format' => 'money'],
 *   ];
 *
 *   $base = Budget::query();                          // เงื่อนไขที่ไม่เกี่ยวกับตัวกรองคอลัมน์
 *   $rows = TableFilter::apply(clone $base, $request, $map)->paginate(20)->withQueryString();
 *
 *   'filterOptions' => TableFilter::options($base, $map),
 *   'filterPicked'  => TableFilter::picked($request, $map),
 *
 * ฝั่ง view ใส่ `data-filter-server` ที่ `<table>` และ `data-filter-key="dept"` ที่ `<th>`
 * แล้ว include `layouts.partials.table-filter-data`
 */
class TableFilter
{
    /** ชื่อพารามิเตอร์ใน URL — `?f[dept]=IT|ACC` */
    public const PARAM = 'f';

    /** ตัวคั่นค่าหลายค่า — ใช้ `|` เพราะชื่อแผนกมี `·` และ `,` ปนอยู่ */
    public const GLUE = '|';

    /**
     * ค่าที่ผู้ใช้เลือกไว้ของแต่ละคอลัมน์
     *
     * @param  array<string,array<string,mixed>>  $map
     * @return array<string,array<int,string>>
     */
    public static function picked(Request $request, array $map): array
    {
        $raw = $request->query(self::PARAM);
        $out = [];

        if (! is_array($raw)) {
            return $out;
        }

        foreach ($map as $key => $_) {
            $value = $raw[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            // ตัดค่าว่างทิ้ง เผื่อ URL ถูกแก้มือมาแปลกๆ
            $values = array_values(array_filter(explode(self::GLUE, $value), fn ($v) => $v !== ''));

            if ($values) {
                $out[$key] = $values;
            }
        }

        return $out;
    }

    /**
     * ใส่เงื่อนไขที่เลือกไว้ลงใน query
     *
     * @param  array<string,array<string,mixed>>  $map
     */
    public static function apply(Builder $query, Request $request, array $map): Builder
    {
        foreach (self::picked($request, $map) as $key => $values) {
            $def = $map[$key];
            $column = $def['column'];

            /*
              ค่าว่างในตารางแสดงเป็น "—" ผู้ใช้ก็ต้องกรอง "—" ได้เหมือนกัน
              จึงต้องแปลงกลับเป็นเงื่อนไข null/ค่าว่างของฐานข้อมูล
            */
            $wantsBlank = in_array('—', $values, true);
            $plain = array_values(array_filter($values, fn ($v) => $v !== '—'));

            /*
              คอลัมน์ที่อยู่ในตารางอื่น (เช่น สถานะการลงทะเบียนที่อยู่ในตาราง budgets)
              🔴 "—" ของคอลัมน์แบบนี้แปลว่า "ยังไม่มีแถวที่เกี่ยวข้องเลย" ไม่ใช่ค่าว่างในคอลัมน์
            */
            if (! empty($def['relation'])) {
                $relation = $def['relation'];

                $query->where(function ($q) use ($relation, $column, $plain, $wantsBlank) {
                    if ($plain) {
                        $q->whereHas($relation, fn ($r) => $r->whereIn($column, $plain));
                    }

                    if ($wantsBlank) {
                        $q->orWhereDoesntHave($relation);
                    }
                });

                continue;
            }

            $query->where(function ($q) use ($column, $plain, $wantsBlank) {
                if ($plain) {
                    $q->whereIn($column, $plain);
                }

                if ($wantsBlank) {
                    $q->orWhereNull($column)->orWhere($column, '');
                }
            });
        }

        return $query;
    }

    /**
     * รายการค่าที่เลือกได้ของแต่ละคอลัมน์ — ดึงจาก **ทั้งชุดข้อมูล** ไม่ใช่เฉพาะหน้าปัจจุบัน
     *
     * @param  array<string,array<string,mixed>>  $map
     * @return array<string,array<int,array{v:string,t:string,e:string}>>
     */
    public static function options(Builder $base, array $map): array
    {
        $out = [];

        foreach ($map as $key => $def) {
            $column = $def['column'];

            /*
              🔴 ต้องล้าง order เดิมก่อน — MySQL ไม่ยอมให้ ORDER BY คอลัมน์ที่ไม่ได้อยู่ใน SELECT
                 ตอนใช้ DISTINCT (error 3065) ซึ่งคิวรีของหน้าตารางมี orderByDesc('id') ติดมาเสมอ
            */
            if (! empty($def['relation'])) {
                /*
                  คอลัมน์ของตารางอื่น — ไล่จากแถวที่โหลดมาแล้ว
                  จำนวนค่าที่เป็นไปได้มีไม่กี่ตัว (สถานะ) จึงไม่คุ้มที่จะยิงคิวรี join เพิ่ม
                */
                $values = (clone $base)->reorder()->with($def['relation'])->get()
                    ->map(fn ($row) => $row->{$def['relation']}?->{$column})
                    ->unique()->sort()->values()->all();
            } else {
                $values = (clone $base)
                    ->reorder()
                    ->select($column)
                    ->distinct()
                    ->orderBy($column)
                    ->pluck($column)
                    ->all();
            }

            $rows = [];

            foreach ($values as $value) {
                [$th, $en] = self::label($value, $def);

                $rows[] = ['v' => $value === null ? '—' : (string) $value, 't' => $th, 'e' => $en];
            }

            // เรียงตามข้อความที่ผู้ใช้เห็น ไม่ใช่ค่าดิบ
            usort($rows, fn ($a, $b) => strcoll($a['t'], $b['t']));

            $out[$key] = $rows;
        }

        return $out;
    }

    /*
      ── ข้อมูลที่เป็น array (ไม่ได้มาจาก Eloquent) เช่น กลุ่มงบของแดชบอร์ด ERP (เจ้าของสั่ง 2026-09-14) ──
      map: ['key' => ['value' => fn ($row) => 'ข้อความที่เห็นในตาราง', 'sort' => fn ($row) => ค่าสำหรับเรียง (ไม่ใส่ก็ได้)]]
      🔴 เทียบด้วย "ข้อความที่ผู้ใช้เห็น" เสมอ — ค่าที่ติ๊กในแผงต้องตรงกับที่อยู่ในช่องตาราง
    */

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,array<int,string>>  $picked
     * @param  array<string,array<string,callable>>  $map
     * @return array<int,array<string,mixed>>
     */
    public static function filterRows(array $rows, array $picked, array $map): array
    {
        if (! $picked) {
            return $rows;
        }

        return array_values(array_filter($rows, static function ($row) use ($picked, $map) {
            foreach ($picked as $key => $values) {
                $text = (string) ($map[$key]['value'])($row);

                if (! in_array($text === '' ? '—' : $text, $values, true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * ค่าที่เลือกได้ของแต่ละคอลัมน์ — จากทุกแถว (ไม่ใช่เฉพาะหน้าที่เปิด)
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,array<string,callable>>  $map
     * @return array<string,array<int,array{v:string,t:string,e:string}>>
     */
    public static function rowOptions(array $rows, array $map): array
    {
        $out = [];

        foreach ($map as $key => $def) {
            $seen = [];

            foreach ($rows as $row) {
                $text = (string) ($def['value'])($row);
                $text = $text === '' ? '—' : $text;
                $seen[$text] ??= isset($def['sort']) ? ($def['sort'])($row) : $text;
            }

            uksort($seen, static function ($a, $b) use ($seen) {
                return is_string($seen[$a]) || is_string($seen[$b]) ? strcoll((string) $seen[$a], (string) $seen[$b]) : $seen[$a] <=> $seen[$b];
            });

            $out[$key] = array_map(fn ($text) => ['v' => (string) $text, 't' => (string) $text, 'e' => (string) $text], array_keys($seen));
        }

        return $out;
    }

    /**
     * ข้อความที่โชว์ในแผงเลือกค่า — ต้องตรงกับที่เห็นในตาราง ไม่งั้นผู้ใช้หาไม่เจอ
     * 🔴 คืนทั้งไทยและอังกฤษ ตามกฎ 2 ภาษาของระบบ
     *
     * @return array{0:string,1:string}
     */
    private static function label(mixed $value, array $def): array
    {
        if ($value === null || $value === '') {
            return ['—', '—'];
        }

        if (isset($def['labels'][$value])) {
            $l = $def['labels'][$value];

            if (is_array($l)) {
                return [(string) ($l['th'] ?? reset($l)), (string) ($l['en'] ?? $l['th'] ?? reset($l))];
            }

            return [(string) $l, (string) $l];
        }

        if (($def['format'] ?? null) === 'money') {
            $m = number_format((float) $value, 2);

            return [$m, $m];
        }

        return [(string) $value, (string) $value];
    }
}
