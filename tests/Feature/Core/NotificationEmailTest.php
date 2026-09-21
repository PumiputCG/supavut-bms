<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\Authenticate;
use App\Jobs\Core\SendNotificationEmail;
use App\Mail\Core\NotificationMail;
use App\Models\Access\FunctionUser;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Models\Core\Notification;
use App\Services\Budget\BudgetAccess;
use App\Services\Core\InsightMirror;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * อีเมลแจ้งเตือน — ของ "เพิ่ม" จากเลขแดงบนกระดิ่ง (เจ้าของสั่ง 2026-09-21)
 *
 * 🔴 กติกาที่ต้องไม่หาย
 *    ส่งเอกสาร   -> ผู้รับทราบ + ผู้อนุมัติคนแรก ได้เมล
 *    เซ็นผ่าน     -> ผู้อนุมัติคนถัดไปได้เมล ไล่ไปจนถึงคนสุดท้าย
 *    ลงทะเบียนงบ -> **ไม่ส่งเมล** (หัวข้อนี้ตั้งเป็น "ทุกคน" ได้ = เมลพันกว่าฉบับต่อเอกสารใบเดียว)
 *    ไม่มีอีเมล   -> เงียบ ไม่พัง และแจ้งเตือนในระบบต้องยังทำงานครบ
 *    1 แจ้งเตือน  -> ส่งเมลได้ครั้งเดียวตลอดกาล
 *    เนื้อเมล     -> ไทย + อังกฤษ · ไม่มีจำนวนเงิน · ลิงก์ต้องไม่ใช่ localhost
 */
class NotificationEmailTest extends TestCase
{
    use RefreshModuleDatabase;

    private DocGroup $docGroup;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // ปิด "CEO ต่อท้ายอัตโนมัติ" — รหัสจริงไม่มีบัญชีในฐานทดสอบ (เหตุผลเดียวกับ NotificationTest)
        config(['bms.final_approver' => '']);

        $this->docGroup = DocGroup::create([
            'code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model', 'sort' => 1,
        ]);
    }

    public function test_submitting_emails_the_first_approver_and_the_acknowledger(): void
    {
        Mail::fake();

        $acc = $this->user('E_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('E_SIGN', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'signer@supavut.com']);
        $cc = $this->user('E_CC', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'cc@supavut.com']);

        $this->submit($acc, [$signer->employee_code, $cc->employee_code], [1 => 'notice']);

        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('signer@supavut.com'));
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('cc@supavut.com'));
        Mail::assertSentCount(2);

        // ส่งแล้วต้องประทับเวลาไว้ ไม่งั้นกันส่งซ้ำไม่ได้
        $this->assertNotNull(Notification::where('employee_code', 'E_SIGN')->firstOrFail()->emailed_at);
    }

    /** เซ็นผ่านแล้วเรื่องต้องเดินต่อ "ทางอีเมล" ด้วย ไม่ใช่แค่เลขแดง */
    public function test_approving_emails_the_next_approver_only(): void
    {
        $acc = $this->user('E2_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $first = $this->user('E2_ONE', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'one@supavut.com']);
        $second = $this->user('E2_TWO', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'two@supavut.com']);

        $invest = $this->submit($acc, [$first->employee_code, $second->employee_code]);

        // 🔴 เริ่มดักเมลหลังส่งเอกสาร จะได้วัดเฉพาะผลของการ "เซ็นผ่าน"
        Mail::fake();

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();

        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('two@supavut.com'));
        Mail::assertSentCount(1);
    }

    /**
     * 🔴 ข้อที่เจ้าของสั่งชัดที่สุด: ขั้น "ลงทะเบียนงบประมาณ" ไม่ส่งอีเมล
     *
     * แจ้งเตือนในระบบยังต้องมีตามเดิม — ที่ห้ามคือเมลเท่านั้น
     */
    public function test_the_registration_step_never_sends_email(): void
    {
        $acc = $this->user('E3_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('E3_SIGN', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'sign3@supavut.com']);
        $registrar = $this->user('E3_REG', ['fn' => BudgetAccess::FN_BUDGETS, 'email' => 'reg3@supavut.com']);

        $invest = $this->submit($acc, [$signer->employee_code]);

        Mail::fake();

        // เซ็นครบ -> ระบบสร้างก้อนงบ แล้วแจ้งผู้ลงทะเบียน
        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();

        $note = Notification::where('employee_code', $registrar->employee_code)
            ->where('event', 'budget_to_register')
            ->first();

        $this->assertNotNull($note, 'ผู้ลงทะเบียนต้องยังได้แจ้งเตือนในระบบเหมือนเดิม');
        $this->assertNull($note->emailed_at);

        Mail::assertNotSent(NotificationMail::class, fn ($m) => $m->hasTo('reg3@supavut.com'));
    }

    /** คนที่ยังไม่ได้กรอกอีเมลไว้ที่ Insight — ต้องเงียบ ไม่ใช่พัง */
    public function test_a_recipient_without_an_email_still_gets_the_in_app_notification(): void
    {
        Mail::fake();

        $acc = $this->user('E4_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('E4_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->submit($acc, [$signer->employee_code]);

        $note = Notification::where('employee_code', $signer->employee_code)->first();

        $this->assertNotNull($note, 'ไม่มีอีเมลก็ต้องยังได้เลขแดงตามปกติ');
        // 🔴 ต้องเป็น null ตามความจริง — ประทับเวลาไว้จะอ่านย้อนหลังไม่ออกว่า "ส่งแล้ว" หรือ "ไม่มีที่จะส่ง"
        $this->assertNull($note->emailed_at);

        Mail::assertNothingSent();
    }

    /** 1 แจ้งเตือน = 1 เมล ต่อให้คิวทำงานซ้ำ (เช่น คิวลองใหม่หลังเซิร์ฟเวอร์สะดุด) */
    public function test_the_same_notification_is_never_emailed_twice(): void
    {
        Mail::fake();

        $acc = $this->user('E5_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('E5_SIGN', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'five@supavut.com']);

        $this->submit($acc, [$signer->employee_code]);

        $note = Notification::where('employee_code', $signer->employee_code)->firstOrFail();

        Mail::assertSentCount(1);

        // สั่งงานเดิมซ้ำตรงๆ
        (new SendNotificationEmail($note->id))->handle();

        Mail::assertSentCount(1);
    }

    public function test_the_email_is_bilingual_has_no_amount_and_no_localhost_link(): void
    {
        config(['bms.qr_base' => 'http://192.168.7.12:8080/SBMS/public']);

        $acc = $this->user('E6_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('E6_SIGN', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'six@supavut.com']);

        $invest = $this->submit($acc, [$signer->employee_code], [], 987654);

        $note = Notification::where('employee_code', $signer->employee_code)->firstOrFail();
        $html = (new NotificationMail($note, 'ทดสอบ'))->render();

        // ไทย + อังกฤษอยู่ในฉบับเดียวกัน (อีเมลรัน JS ไม่ได้ สลับภาษาตอนอ่านไม่ได้)
        $this->assertStringContainsString('รอคุณลงนามอนุมัติ', $html);
        $this->assertStringContainsString('Waiting for your signature', $html);
        $this->assertStringContainsString('Supavut Business Management System', $html);

        // 🔴 ห้ามมีจำนวนเงินหลุดออกไปนอกระบบ (เจ้าของสั่ง)
        $this->assertStringNotContainsString('987,654', $html);
        $this->assertStringNotContainsString('987654', $html);

        // 🔴 ลิงก์ต้องเป็นที่อยู่ของเซิร์ฟ ไม่ใช่ localhost ซึ่งแปลว่า "เครื่องของคนที่เปิดเมล"
        $this->assertStringContainsString('http://192.168.7.12:8080/SBMS/public/budget/doc/'.$invest->id, $html);
        $this->assertStringNotContainsString('localhost', $html);
    }

    /**
     * ป้ายกำกับของทุกบรรทัดต้องแปลได้
     *
     * 🔴 พจนานุกรมของระบบเป็น JavaScript ท้ายหน้า อีเมลใช้ไม่ได้ จึงมีสำเนาเล็กๆ ที่ NotificationMail
     *    เทสต์นี้กันลืม: เพิ่มคีย์ป้ายใหม่แล้วไม่ไปเพิ่มในสำเนา ผู้รับจะเห็นบรรทัดที่ไม่มีป้ายกำกับ
     */
    public function test_every_label_key_the_budget_flow_uses_has_a_translation(): void
    {
        $acc = $this->user('E7_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('E7_SIGN', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'seven@supavut.com']);
        $this->user('E7_REG', ['fn' => BudgetAccess::FN_BUDGETS, 'email' => 'reg7@supavut.com']);

        // เดินให้ครบทั้ง 2 ปลายทาง: อนุมัติจนจบ และตีกลับ
        $ok = $this->submit($acc, [$signer->employee_code]);
        $this->signIn($signer)->post('/budget/doc/'.$ok->id.'/approve')->assertRedirect();

        $no = $this->submit($acc, [$signer->employee_code]);
        $this->signIn($signer)->post('/budget/doc/'.$no->id.'/reject', ['reason' => 'งบไม่พอ'])->assertRedirect();

        $keys = Notification::query()
            ->get(['subject_key', 'note_key', 'note2_key'])
            ->flatMap(fn ($n) => [$n->subject_key, $n->note_key, $n->note2_key])
            ->filter()
            ->unique()
            ->values();

        $this->assertNotEmpty($keys, 'ต้องมีคีย์ป้ายให้ตรวจอย่างน้อย 1 ตัว');

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, NotificationMail::LABELS, 'ไม่มีคำแปลของป้าย "'.$key.'" ใน NotificationMail::LABELS');
        }
    }

    /**
     * 🐛 บั๊กจริงที่เจ้าของเจอ 2026-09-21: ผู้อนุมัติไม่ได้เมล ทั้งที่ตั้งอีเมลไว้ที่ Insight แล้ว
     *
     * ต้นเหตุ: ตัวส่งเมลอ่านอีเมลจาก **มิเรอร์** ซึ่งอัปเดตรายคนเฉพาะตอนคนนั้นล็อกอิน
     * เคสจริง: ตั้งอีเมลที่ Insight 11:30 · ส่งเอกสาร 11:38 · งานวิ่ง 11:39 · มิเรอร์เพิ่งได้ 11:40
     */
    public function test_the_email_is_read_live_from_insight_not_from_the_stale_mirror(): void
    {
        Mail::fake();

        $acc = $this->user('E8_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        // มิเรอร์ยังไม่มีอีเมลของคนนี้ — เหมือนสถานการณ์จริงเป๊ะ
        $signer = $this->user('E8_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        // จำลองว่า Insight มีอีเมลแล้ว และการถามสดจะคืนค่าล่าสุดมาให้
        $this->mock(InsightMirror::class, function ($mock) use ($signer) {
            $mock->shouldReceive('pullUserByCode')
                ->with($signer->employee_code)
                ->andReturnUsing(function () use ($signer) {
                    $signer->forceFill(['email' => 'live@supavut.com'])->save();

                    return $signer->fresh();
                });
            $mock->shouldReceive('emailsFor')->andReturn([]);
        });

        $this->submit($acc, [$signer->employee_code]);

        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('live@supavut.com'));
    }

    /**
     * เจ้าของสั่ง: "ถ้ามีรับทราบหลายคน ก็ให้เด้งกระจายไปด้วยสิ"
     */
    public function test_every_acknowledger_gets_their_own_email(): void
    {
        Mail::fake();

        $acc = $this->user('E9_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('E9_SIGN', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'sign9@supavut.com']);
        $cc1 = $this->user('E9_CC1', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'cc9a@supavut.com']);
        $cc2 = $this->user('E9_CC2', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'cc9b@supavut.com']);
        $cc3 = $this->user('E9_CC3', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'cc9c@supavut.com']);

        $this->submit(
            $acc,
            [$signer->employee_code, $cc1->employee_code, $cc2->employee_code, $cc3->employee_code],
            [1 => 'notice', 2 => 'notice', 3 => 'notice'],
        );

        foreach (['cc9a@supavut.com', 'cc9b@supavut.com', 'cc9c@supavut.com'] as $to) {
            Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo($to));
        }

        // ผู้อนุมัติคนแรกได้ด้วย รวมเป็น 4
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('sign9@supavut.com'));
        Mail::assertSentCount(4);
    }

    /**
     * เจ้าของสั่ง: "อย่าลืมเด้งแบบอนุมัติเป็นเส้นทางยาวด้วย"
     *
     * 🔴 ต้องเด้ง **ทีละคนตามคิว** ไม่ใช่ยิงทุกคนพร้อมกันตั้งแต่ต้น
     *    เพราะคนลำดับหลังกดเข้ามาก็ยังเซ็นไม่ได้ กลายเป็นเมลหลอก
     */
    public function test_the_approval_chain_emails_each_signer_in_turn(): void
    {
        $acc = $this->user('EA_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $one = $this->user('EA_ONE', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'a1@supavut.com']);
        $two = $this->user('EA_TWO', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'a2@supavut.com']);
        $three = $this->user('EA_THREE', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'a3@supavut.com']);

        Mail::fake();
        $invest = $this->submit($acc, [$one->employee_code, $two->employee_code, $three->employee_code]);

        // ขั้นที่ 1 เท่านั้นที่ได้เมลตอนกดส่ง
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('a1@supavut.com'));
        Mail::assertSentCount(1);

        Mail::fake();
        $this->signIn($one)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('a2@supavut.com'));
        Mail::assertSentCount(1);

        Mail::fake();
        $this->signIn($two)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('a3@supavut.com'));
        Mail::assertSentCount(1);
    }

    /**
     * เจ้าของสั่ง: "ถ้าเขากลับมาเพิ่มเมลก็ให้เด้งเหมือนเดิม"
     */
    public function test_a_pending_notification_is_emailed_once_the_person_adds_an_email(): void
    {
        Mail::fake();

        $acc = $this->user('EB_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('EB_SIGN', ['fn' => BudgetAccess::FN_INBOX]); // ยังไม่มีอีเมล

        $this->submit($acc, [$signer->employee_code]);

        $note = Notification::where('employee_code', $signer->employee_code)->firstOrFail();
        $this->assertTrue($note->wants_mail, 'ต้องจำไว้ว่าใบนี้อยากได้เมล');
        $this->assertNull($note->emailed_at);
        Mail::assertNothingSent();

        // เขาไปเพิ่มอีเมลที่ Insight ทีหลัง
        $this->mock(InsightMirror::class, function ($mock) use ($signer) {
            $mock->shouldReceive('emailsFor')->andReturn([$signer->employee_code => 'later@supavut.com']);
            $mock->shouldReceive('pullUserByCode')->andReturnUsing(function () use ($signer) {
                $signer->forceFill(['email' => 'later@supavut.com'])->save();

                return $signer->fresh();
            });
        });

        $this->artisan('bms:mail-pending')->assertSuccessful();

        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('later@supavut.com'));
        $this->assertNotNull($note->fresh()->emailed_at);
    }

    /** อ่านไปแล้ว = ลงมือไปแล้ว ไม่ต้องตามไปเตือนทางอีเมลอีก */
    public function test_the_sweep_skips_notifications_that_were_already_read(): void
    {
        Mail::fake();

        $acc = $this->user('EC_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('EC_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->submit($acc, [$signer->employee_code]);

        $note = Notification::where('employee_code', $signer->employee_code)->firstOrFail();
        $note->forceFill(['read_at' => now()])->save();

        /*
          🔴 mock ต้องคืนผู้ใช้ที่ "มีอีเมลแล้ว" จริงๆ
             ไม่งั้นเทสต์จะผ่านเพราะหาอีเมลไม่เจอ ไม่ใช่เพราะตัวกวาดข้ามใบที่อ่านแล้ว
             (พลาดแบบนี้ไปแล้วรอบหนึ่ง — เทสต์ผ่านทั้งที่ถอดเงื่อนไขออก)
        */
        $this->mock(InsightMirror::class, function ($mock) use ($signer) {
            $mock->shouldReceive('emailsFor')->andReturn([$signer->employee_code => 'read@supavut.com']);
            $mock->shouldReceive('pullUserByCode')->andReturnUsing(function () use ($signer) {
                $signer->forceFill(['email' => 'read@supavut.com'])->save();

                return $signer->fresh();
            });
        });

        $this->artisan('bms:mail-pending')->assertSuccessful();

        Mail::assertNothingSent();
    }

    /**
     * เจ้าของแจ้ง 2026-09-21: บรรทัด "Dear" ขึ้นชื่อไทยซ้ำกับบรรทัดบน
     * ต้องดึงชื่ออังกฤษจากฐานข้อมูลมาใช้
     */
    public function test_the_greeting_uses_the_english_name_on_the_english_line(): void
    {
        $acc = $this->user('ED_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('ED_SIGN', [
            'fn' => BudgetAccess::FN_INBOX,
            'email' => 'dear@supavut.com',
            'name_en' => 'Mister Thannapat Pimaiklang',
        ]);

        $this->submit($acc, [$signer->employee_code]);

        $note = Notification::where('employee_code', $signer->employee_code)->firstOrFail();
        $html = (new NotificationMail($note, $signer->displayName('th'), $signer->displayName('en')))->render();

        $this->assertTrue(
            str_contains($html, 'เรียน ทดสอบ ED_SIGN'),
            'บรรทัดภาษาไทยต้องใช้ชื่อไทย',
        );
        $this->assertTrue(
            str_contains($html, 'Dear Mister Thannapat Pimaiklang'),
            'บรรทัด Dear ต้องใช้ชื่ออังกฤษจากฐานข้อมูล',
        );
        // 🔴 ต้องไม่เหลือชื่อไทยบนบรรทัด Dear อีก
        $this->assertFalse(str_contains($html, 'Dear ทดสอบ'), 'บรรทัด Dear ยังเป็นชื่อไทยอยู่');
    }

    /** 🔴 ไม่มีชื่ออังกฤษ = ถอยไปใช้ชื่อไทย ห้ามปล่อยว่าง (กฎ 2 ภาษาของโปรเจค) */
    public function test_the_greeting_falls_back_to_thai_when_there_is_no_english_name(): void
    {
        $acc = $this->user('EE_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('EE_SIGN', ['fn' => BudgetAccess::FN_INBOX, 'email' => 'nofb@supavut.com']);

        $this->submit($acc, [$signer->employee_code]);

        $note = Notification::where('employee_code', $signer->employee_code)->firstOrFail();
        $html = (new NotificationMail($note, $signer->displayName('th'), $signer->displayName('en')))->render();

        $this->assertTrue(str_contains($html, 'Dear ทดสอบ EE_SIGN'), 'ไม่มีชื่ออังกฤษต้องถอยไปใช้ชื่อไทย');
        $this->assertFalse(str_contains($html, 'Dear <'), 'บรรทัด Dear ต้องไม่ว่าง');
    }

    // ── ตัวช่วย ────────────────────────────────────────────────

    /**
     * สร้างเอกสารแล้วกดส่ง
     *
     * @param  array<int,string>  $approvers
     * @param  array<int,string>  $roles  ลำดับ (เริ่มที่ 0) -> 'notice' สำหรับผู้รับทราบ
     */
    private function submit(AppUser $acc, array $approvers, array $roles = [], int $amount = 12000): Invest
    {
        $payload = [
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            'proposer_code' => $acc->employee_code,
            'dept_code' => 'D01',
            'title' => 'งบทดสอบอีเมล',
            'amount' => $amount,
            'approvers' => $approvers,
        ];

        // ชื่อช่องจริงของฟอร์มคือ roles และค่าคือ approve / notice
        foreach ($approvers as $index => $ignored) {
            $payload['roles'][$index] = $roles[$index] ?? 'approve';
        }

        $this->signIn($acc)->post('/budget/invest', $payload);

        $invest = Invest::orderByDesc('id')->firstOrFail();

        /*
          🔴 ฟอร์มจริงส่งรายชื่อ + บทบาทไปกับตอน "กดส่ง" ด้วย
             ไม่ส่งไป controller จะถอยไปใช้รายชื่อของร่างแล้ว **รีเซ็ตบทบาทเป็นอนุมัติทุกคน**
             เทสต์ที่ไม่ส่งจึงไม่มีทางมีผู้รับทราบเลย
        */
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => $payload['approvers'],
            'roles' => $payload['roles'],
        ])->assertRedirect();

        return $invest->refresh();
    }

    /** @param  array{fn?:string|array<int,string>,email?:string,name_en?:string}  $attributes */
    private function user(string $code, array $attributes = []): AppUser
    {
        $this->seq++;

        Employee::create([
            'insight_id' => 8000 + $this->seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'name_th' => 'ทดสอบ',
            'surname_th' => $code,
            'dept_code' => 'D01',
            'dept_th' => 'แผนกทดสอบ',
            'job_code' => 'S1',
            'job_th' => 'เจ้าหน้าที่',
            'emp_status' => '1',
        ]);

        $user = AppUser::create([
            'insight_id' => 8000 + $this->seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => 'user',
            'full_name_th' => 'ทดสอบ '.$code,
            // ไม่ส่ง name_en มา = บัญชีที่ยังไม่มีชื่ออังกฤษ (มีจริง 0.5% ในมิเรอร์)
            'full_name_en' => $attributes['name_en'] ?? null,
            'dept_code' => 'D01',
            'email' => $attributes['email'] ?? null,
            'signature' => 'data:image/png;base64,TEST',
        ]);

        foreach ((array) ($attributes['fn'] ?? []) as $key) {
            FunctionUser::create([
                'module_id' => BudgetAccess::MODULE,
                'function_key' => $key,
                'employee_code' => $code,
            ]);
        }

        return $user;
    }

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $this;
    }
}
