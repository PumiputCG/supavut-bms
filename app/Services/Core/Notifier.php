<?php

namespace App\Services\Core;

use App\Jobs\Core\SendNotificationEmail;
use App\Models\Core\AppUser;
use App\Models\Core\Notification;
use App\Support\NavMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * ศูนย์กลางการแจ้งเตือน — ทุกโมดูลส่งผ่านที่นี่
 *
 * 🔴 ส่งแจ้งเตือนต้องไม่ทำให้งานหลักพัง
 *    ครอบ try/catch ทุกจุด — บันทึกเอกสารไม่สำเร็จเพราะแจ้งเตือนล้มถือว่ารับไม่ได้
 *    (กติกาเดียวกับ ActivityLogger)
 *
 * 🔴 ไม่ส่งซ้ำให้คนเดิมในเรื่องเดิม
 *    เช่นผู้อนุมัติหลายคน เอกสารถูกส่งใหม่หลังตีกลับ ต้องไม่ได้แจ้งเตือนซ้อน
 */
class Notifier
{
    /** แคชแผนที่จำนวนที่ยังไม่อ่านไว้ในคำขอเดียว — เมนูซ้ายกับเมนูย่อยถามซ้ำกันหลายรอบ */
    private ?array $unreadMap = null;

    /** แคชจำนวนที่ยังไม่อ่านรายเอกสาร — ตารางเรียกทุกแถว */
    private ?array $unreadDocs = null;

    /**
     * ส่งแจ้งเตือนถึงคนกลุ่มหนึ่ง
     *
     * @param  array<int,string>  $codes  รหัสพนักงานผู้รับ
     * @param  array<string,mixed>  $data  module_id · function_key · event · doc_no · url · title/body คู่ th-en
     *                                     + `mail` = true ถ้าเหตุการณ์นี้ต้องส่งอีเมลด้วย
     */
    public function send(array $codes, array $data): void
    {
        /*
          🔴 `mail` เป็น "ธงของเหตุการณ์" ไม่ใช่คอลัมน์ในตาราง — ต้องถอดออกก่อนบันทึกเสมอ
             ออกแบบเป็นธงที่ผู้เรียกติดมาเอง (ไม่ใช่เช็คชื่อ event ที่นี่) เพราะ
             ① ขั้น "ลงทะเบียนงบประมาณ" ต้องไม่ส่งเมล (เจ้าของสั่ง 2026-09-21)
                และหัวข้อนั้นตั้งเป็น "ทุกคน" ได้ = ~1,494 ฉบับต่อเอกสารใบเดียว
             ② โมดูลอื่นในอนาคตเปิดใช้เองได้ โดยไม่ต้องกลับมาแก้ไฟล์นี้
        */
        $wantsMail = (bool) ($data['mail'] ?? false);
        unset($data['mail']);

        /*
          🔴 จำไว้ในแถวด้วย ไม่ใช่รู้แค่ตอนนี้
             ผู้รับอาจยังไม่มีอีเมลในวินาทีนี้ แล้วไปเพิ่มที่ Insight ทีหลัง
             ตัวกวาด bms:mail-pending จะย้อนกลับมาส่งให้ — แต่มันต้องรู้ก่อนว่าใบไหนอยากได้เมล
        */
        $data['wants_mail'] = $wantsMail;

        try {
            foreach (array_unique(array_filter($codes)) as $code) {
                // กันแจ้งซ้ำ — เรื่องเดียวกัน คนเดียวกัน เหตุการณ์เดียวกัน มีได้ครั้งเดียวตอนที่ยังไม่อ่าน
                $exists = Notification::where('employee_code', $code)
                    ->where('event', $data['event'])
                    ->where('doc_no', $data['doc_no'] ?? null)
                    ->whereNull('read_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                $row = Notification::create($data + ['employee_code' => (string) $code]);

                if ($wantsMail) {
                    $this->queueMail($row);
                }
            }
        } catch (Throwable) {
            // เงียบไว้ — แจ้งเตือนล้มต้องไม่ทำให้บันทึกเอกสารพัง
        }
    }

    /**
     * เอาอีเมลของแจ้งเตือนใบนี้เข้าคิว
     *
     * 🔴 ครอบ try/catch ของตัวเองอีกชั้น ทั้งที่ send() ครอบอยู่แล้ว
     *    เพราะถ้าปล่อยให้หลุดขึ้นไป ตัวที่อยู่ข้างบนจะจบทั้งลูป = คนที่เหลือในรอบนั้น
     *    ไม่ได้แม้แต่แจ้งเตือนในระบบ ทั้งที่เรื่องที่ล้มเป็นแค่ "เมล"
     */
    private function queueMail(Notification $row): void
    {
        try {
            SendNotificationEmail::dispatch($row->id);
        } catch (Throwable) {
            // เงียบไว้ — เมลเป็นของเพิ่ม เลขแดงบนกระดิ่งยังทำงานตามปกติ
        }
    }

    /** จำนวนที่ยังไม่อ่านของคนนี้ — ใช้ทำตัวเลขแดงบนกระดิ่ง */
    public function unreadCount(?AppUser $user): int
    {
        if (! $user) {
            return 0;
        }

        try {
            return Notification::where('employee_code', $user->employee_code)
                ->whereNull('read_at')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * แจ้งเตือนที่ยังไม่อ่าน จัดกลุ่มเป็น โมดูล -> หัวข้อย่อย
     *
     * โครงที่คืน (เรียงตามลำดับเมนู เพื่อให้กระดิ่งเรียงเหมือนเมนูซ้าย)
     *   [ ['id','th','key','icon','count','functions' => [ ['key','th','count','url','items'] ]] ]
     *
     * @return array<int,array<string,mixed>>
     */
    public function grouped(?AppUser $user): array
    {
        if (! $user) {
            return [];
        }

        try {
            $rows = Notification::where('employee_code', $user->employee_code)
                ->whereNull('read_at')
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        } catch (Throwable) {
            return [];
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $out = [];

        foreach (NavMenu::permissionedModules() as $module) {
            $mine = $rows->where('module_id', $module['id']);

            if ($mine->isEmpty()) {
                continue;
            }

            $functions = [];

            foreach (NavMenu::menuFunctions($module) as $fn) {
                // หัวข้อย่อยที่ยุบรวมกันในเมนู ต้องรวมจำนวนของทุกคีย์ที่ยุบมาด้วย
                $keys = $fn['keys'] ?? [$fn['key']];
                $hits = $mine->whereIn('function_key', $keys);

                if ($hits->isEmpty()) {
                    continue;
                }

                $functions[] = [
                    'key' => $fn['key'],
                    'th' => $fn['th'],
                    'count' => $hits->count(),
                    'url' => $this->urlFor($fn['route'] ?? null),
                    'items' => $hits->take(6)->values(),
                ];
            }

            if ($functions === []) {
                continue;
            }

            $out[] = [
                'id' => $module['id'],
                'key' => $module['key'],
                'th' => $module['th'],
                'icon' => $module['icon'],
                'count' => $mine->count(),
                'functions' => $functions,
            ];
        }

        return $out;
    }

    /**
     * ทำเครื่องหมายว่าอ่านแล้ว
     *
     * ระบุ $functionKey = อ่านเฉพาะหัวข้อย่อยนั้น · ไม่ระบุ = อ่านทั้งหมดของคนนี้
     */
    public function markRead(?AppUser $user, ?string $moduleId = null, ?string $functionKey = null): int
    {
        // 🔴 อ่านแล้วตัวเลขเปลี่ยน — ต้องทิ้งแคชในคำขอนี้ ไม่งั้นตอบกลับเป็นค่าก่อนอ่าน
        $this->unreadMap = null;
        $this->unreadDocs = null;

        if (! $user) {
            return 0;
        }

        try {
            return Notification::where('employee_code', $user->employee_code)
                ->whereNull('read_at')
                ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
                ->when($functionKey, fn ($q) => $q->where('function_key', $functionKey))
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * อ่านแจ้งเตือนของ "เอกสารใบนี้" ทั้งหมด
     *
     * 🔴 เปิดเอกสารเองก็ต้องถือว่าอ่านแล้ว (เจ้าของแจ้ง 2026-09-07)
     *    ของเดิมต้องกดผ่านกระดิ่งเท่านั้น เลขแดงเลยค้างทั้งที่ดูเอกสารไปแล้ว
     */
    public function markReadForDoc(?AppUser $user, ?string $docNo): int
    {
        $this->unreadMap = null;
        $this->unreadDocs = null;

        if (! $user || ! $docNo) {
            return 0;
        }

        try {
            return Notification::where('employee_code', $user->employee_code)
                ->where('doc_no', $docNo)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * จำนวนที่ยังไม่อ่าน แยกตามโมดูลและหัวข้อย่อย — ไว้ทำเลขแดงที่เมนู
     *
     * คืนเป็น ['budget' => 3, 'budget|fn.budget.invest' => 2, ...]
     * 🔴 คิวรีเดียวจบ เพราะ sidebar กับ submenu ถามซ้ำกันหลายรอบต่อหน้า
     *
     * @return array<string,int>
     */
    public function unreadMap(?AppUser $user): array
    {
        if (! $user) {
            return [];
        }

        if ($this->unreadMap !== null) {
            return $this->unreadMap;
        }

        try {
            $rows = Notification::where('employee_code', $user->employee_code)
                ->whereNull('read_at')
                ->selectRaw('module_id, function_key, COUNT(*) AS n')
                ->groupBy('module_id', 'function_key')
                ->get();
        } catch (Throwable) {
            return $this->unreadMap = [];
        }

        $map = [];

        foreach ($rows as $row) {
            $n = (int) $row->n;
            $map[$row->module_id] = ($map[$row->module_id] ?? 0) + $n;
            $map[$row->module_id.'|'.$row->function_key] = ($map[$row->module_id.'|'.$row->function_key] ?? 0) + $n;
        }

        return $this->unreadMap = $map;
    }

    /**
     * จำนวนที่ยังไม่อ่าน แยกตาม "เลขที่เอกสาร" — ไว้ทำเลขแดงในตาราง
     *
     * 🔴 เจ้าของสั่ง 2026-09-07: แถวไหนมีเรื่องค้าง ให้เห็นตั้งแต่ในตาราง
     *    ไม่ต้องเปิดกระดิ่งก่อนถึงจะรู้ว่าใบไหนมีอะไรใหม่
     *
     * @return array<string,int>
     */
    public function unreadDocs(?AppUser $user): array
    {
        if (! $user) {
            return [];
        }

        if ($this->unreadDocs !== null) {
            return $this->unreadDocs;
        }

        try {
            $rows = Notification::where('employee_code', $user->employee_code)
                ->whereNull('read_at')
                ->whereNotNull('doc_no')
                ->selectRaw('doc_no, COUNT(*) AS n')
                ->groupBy('doc_no')
                ->pluck('n', 'doc_no')
                ->all();
        } catch (Throwable) {
            return $this->unreadDocs = [];
        }

        return $this->unreadDocs = array_map('intval', $rows);
    }

    /** @return Collection<int,Notification> */
    public function recent(?AppUser $user, int $limit = 50): Collection
    {
        if (! $user) {
            return collect();
        }

        try {
            return Notification::where('employee_code', $user->employee_code)
                ->orderByDesc('id')
                ->limit($limit)
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    private function urlFor(?string $route): ?string
    {
        return $route && Route::has($route) ? route($route) : null;
    }
}
