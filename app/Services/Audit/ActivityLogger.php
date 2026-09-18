<?php

namespace App\Services\Audit;

use App\Models\Audit\ActivityLog;
use App\Models\Core\AppUser;
use Illuminate\Http\Request;
use Throwable;

/**
 * ตัวบันทึกประวัติการใช้งาน — ทุกจุดที่อยากบันทึกต้องเรียกผ่านที่นี่
 *
 * 🔴 การบันทึกประวัติต้องไม่ทำให้งานหลักพัง
 *    ถ้าเขียนไม่ได้ (ตารางยังไม่ migrate / ฐานล่ม) ให้เงียบไป ไม่โยน exception ออกมา
 *    ไม่งั้นแค่ log พังจะทำให้ล็อกอินไม่ได้ทั้งระบบ
 *
 * 🔴 ห้ามส่งรหัสผ่าน เลขบัตรประชาชน หรือค่าจ้าง เข้ามาที่นี่
 */
class ActivityLogger
{
    /**
     * บันทึก 1 บรรทัด
     *
     * @param  string  $event  รหัสเหตุการณ์ (ดู ActivityLog::EVENTS)
     * @param  array{th:string,en:string}  $detail  คำอธิบาย 2 ภาษา
     * @param  string|null  $subject  สิ่งที่ถูกกระทำ เช่น รหัสพนักงาน หรือ id โมดูล
     * @param  AppUser|null  $actor  คนทำ · ไม่ส่งมาจะใช้คนที่ล็อกอินอยู่
     */
    public function record(string $event, array $detail, ?string $subject = null, ?AppUser $actor = null): void
    {
        try {
            $actor ??= $this->currentUser();
            $request = request();

            ActivityLog::create([
                'employee_code' => $actor?->employee_code,
                'actor_name' => $actor?->displayName('th'),
                'event' => $event,
                'subject' => $subject,
                'detail_th' => $detail['th'] ?? null,
                'detail_en' => $detail['en'] ?? null,
                'ip' => $request?->ip(),
                'method' => $request?->method(),
                'url' => $this->shortUrl($request),
                'agent' => $this->shortAgent($request),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // เงียบไว้โดยตั้งใจ — ดูคำอธิบายในหัวคลาส
        }
    }

    /** บันทึกการล็อกอินไม่สำเร็จ — ยังไม่มีบัญชี จึงบันทึกแค่รหัสที่กรอกมา */
    public function loginFailed(string $code, string $reasonTh, string $reasonEn): void
    {
        $this->record('login_failed', [
            'th' => 'รหัสพนักงาน '.$code.' — '.$reasonTh,
            'en' => 'Employee ID '.$code.' — '.$reasonEn,
        ], $code, null);
    }

    private function currentUser(): ?AppUser
    {
        return app()->bound('current_user') ? app('current_user') : null;
    }

    /** เก็บแค่ path + query จะได้ไม่ยาวเกินจำเป็น */
    private function shortUrl(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        $path = '/'.ltrim($request->path(), '/');
        $query = $request->getQueryString();

        return mb_substr($query ? $path.'?'.$query : $path, 0, 255);
    }

    /**
     * ย่อ user agent ให้เหลือแค่ชื่อเบราว์เซอร์กับระบบปฏิบัติการ
     *
     * เก็บสตริงเต็มไม่มีประโยชน์ในการตรวจสอบ แถวยาวจนอ่านไม่ไหว
     * 🔴 ใช้คำภาษาอังกฤษล้วน เพราะค่านี้ถูกเก็บลงฐานข้อมูล สลับภาษาทีหลังไม่ได้
     */
    private function shortAgent(?Request $request): ?string
    {
        $agent = (string) $request?->userAgent();
        if ($agent === '') {
            return null;
        }

        $browser = 'Unknown';
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $needle => $name) {
            if (str_contains($agent, $needle)) {
                $browser = $name;

                break;
            }
        }

        $os = 'Unknown';
        foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iOS', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $needle => $name) {
            if (str_contains($agent, $needle)) {
                $os = $name;

                break;
            }
        }

        return $browser.' · '.$os;
    }
}
