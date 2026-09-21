<?php

namespace App\Jobs\Core;

use App\Mail\Core\NotificationMail;
use App\Models\Core\AppUser;
use App\Models\Core\Notification;
use App\Services\Core\InsightMirror;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * ส่งอีเมลของแจ้งเตือน 1 ใบ
 *
 * 🔴 เข้าคิวเสมอ ไม่ส่งสดตอนกดปุ่ม (เจ้าของเคาะ 2026-09-21)
 *    ส่งสดแปลว่าคนกดอนุมัติต้องรอ SMTP ตอบก่อนหน้าจอถึงจะขยับ — เมลเซิร์ฟเวอร์ช้าหรือล่ม
 *    ผู้ใช้จะเห็นหน้าค้างแล้วกดซ้ำ ทั้งที่เอกสารบันทึกเรียบร้อยไปตั้งแต่วินาทีแรกแล้ว
 *
 * 🔴 ส่งซ้ำไม่ได้ — เช็ค emailed_at ก่อนเสมอ ทั้งตอนเข้าคิวและตอนคิวทำงานจริง
 */
class SendNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** เว้นช่วงก่อนลองใหม่ — เมลเซิร์ฟเวอร์ล่มชั่วคราวมักกลับมาใน 1–5 นาที */
    public array $backoff = [60, 300];

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        $note = Notification::find($this->notificationId);

        if (! $note || $note->emailed_at !== null) {
            return;
        }

        $user = $this->freshUser((string) $note->employee_code);
        $email = trim((string) ($user->email ?? ''));

        /*
          ไม่มีอีเมล = ไม่ใช่ความผิดพลาด แค่คนนั้นยังไม่ได้กรอกไว้ที่ Insight
          🔴 ปล่อย emailed_at เป็น null ไว้ตามความจริง (ไม่ได้ส่งจริงๆ) แล้วจบงานแบบสำเร็จ
             ถ้าไปประทับเวลาไว้ จะอ่านย้อนหลังไม่ออกว่า "ส่งแล้ว" หรือ "ไม่มีที่จะส่ง"
             และใบนี้ยังค้างรอ bms:mail-pending มาเก็บให้ตอนเขาเพิ่มอีเมลทีหลัง
        */
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        /*
          🔴 ส่งชื่อไปทั้ง 2 ภาษา (เจ้าของแจ้ง 2026-09-21 ว่าบรรทัด Dear ขึ้นชื่อไทย)
             ใช้ `displayName()` ของ AppUser ที่มีกติกาถอยกลับอยู่แล้ว ไม่เขียนเงื่อนไขซ้ำ
             ในมิเรอร์มีชื่ออังกฤษอยู่ 99.5% ของบัญชี
        */
        Mail::to($email)->send(new NotificationMail(
            $note,
            $user->displayName('th'),
            $user->displayName('en'),
        ));

        $note->forceFill(['emailed_at' => now()])->save();
    }

    /**
     * อีเมลล่าสุดของคนนี้ — ถาม Insight สดก่อนเสมอ
     *
     * 🔴🔴 บั๊กจริง 2026-09-21: เดิมอ่านจากมิเรอร์ `app_users` อย่างเดียว
     *    มิเรอร์อัปเดตรายคนเฉพาะ "ตอนคนนั้นล็อกอิน" หรือตอนสั่งซิงค์ทั้งก้อน (ครั้งล่าสุด 15 ก.ย.)
     *    ผู้อนุมัติที่เพิ่งไปตั้งอีเมลที่ Insight จึงไม่ได้เมล ทั้งที่ Insight มีข้อมูลแล้ว
     *    (เคสจริง: ตั้งอีเมล 11:30:34 · เอกสารส่ง 11:38:05 · งานวิ่ง 11:39:03 แต่มิเรอร์เพิ่งได้ 11:40:05)
     *    → วิธีเดียวกับที่หน้าข้อมูลส่วนตัวใช้อยู่แล้วตั้งแต่ 3 ก.ย. (ปัญหาเดียวกันเป๊ะ)
     *
     * ต่อ Insight ไม่ติดก็ถอยไปใช้มิเรอร์ — เมลช้ายังดีกว่าเอกสารเดินไม่ได้
     */
    private function freshUser(string $code): ?AppUser
    {
        try {
            $fresh = app(InsightMirror::class)->pullUserByCode($code);

            if ($fresh) {
                return $fresh;
            }
        } catch (Throwable) {
            // เงียบไว้ — ถอยไปใช้ค่าที่มิเรอร์ไว้แล้วข้างล่าง
        }

        return AppUser::where('employee_code', $code)->first();
    }
}
