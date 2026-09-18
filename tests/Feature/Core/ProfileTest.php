<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\Authenticate;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Core\InsightMirror;
use Mockery;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshModuleDatabase;

    private function user(): AppUser
    {
        // หน้านี้ดึงข้อมูลสดจาก Insight ทุกครั้ง — เทสต์ต้องไม่ไปต่อฐานจริง
        $mirror = Mockery::mock(InsightMirror::class);
        $mirror->shouldReceive('pullUserByCode')->andReturnNull();
        $this->instance(InsightMirror::class, $mirror);

        Employee::create(['insight_id' => 9801, 'company' => 'TEST', 'employee_code' => 'TEST09', 'name_th' => 'ทดสอบ',
            'surname_th' => 'Profile', 'dept_code' => 'D01', 'job_code' => 'S1', 'emp_status' => '1']);
        $user = AppUser::create(['insight_id' => 9801, 'company' => 'TEST', 'employee_code' => 'TEST09',
            'password' => 'secret', 'role' => 'user', 'full_name_th' => 'ทดสอบ Profile', 'dept_code' => 'D01']);
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $user;
    }

    /** เฉพาะกฎในจอมือถือ — ตัดออกมาเป็นก้อนเดียวจะได้ไม่เผลอไปเจอกฎของเดสก์ท็อป */
    private function mobileCss(string $html): string
    {
        preg_match('~@media \(max-width: 620px\)(.*?)@media \(prefers-reduced-motion~s', $html, $m);

        return $m[1] ?? '';
    }

    /**
     * 🐛 บั๊กจริง 2026-09-18 (เจ้าของแจ้งว่าเข้าผ่านโทรศัพท์แล้วหน้านี้ไม่โอเค)
     *
     *    บัตรถูกล็อกสูง 340px (`min-height`) แต่ `.card-face` เป็น `position: absolute`
     *    + `overflow: hidden` ส่วนเนื้อในสูงถึง 452px — **แถว "อีเมล" กับ "ลายเซ็น"
     *    ถูกตัดหายไปเลย** ทั้งที่ยังอยู่ในหน้า (วัดจริงที่ 360 · 390 · 414px)
     *
     *    บนมือถือบัตรต้องสูงตามเนื้อหา ไม่ใช่ตามสัดส่วน 16:9
     */
    public function test_the_card_grows_with_its_content_on_a_phone(): void
    {
        $this->user();

        $html = $this->get('/profile')->assertOk()->getContent();
        $mobile = $this->mobileCss($html);

        $this->assertNotSame('', $mobile, 'ไม่พบบล็อกกฎของจอมือถือ');

        // สูงตามเนื้อหา — เลิกล็อกสัดส่วน และเลิกลอยทับจนเนื้อในถูกตัด
        $this->assertMatchesRegularExpression('~\.namecard \{[^}]*aspect-ratio: auto~s', $mobile);
        $this->assertMatchesRegularExpression('~\.card-face \{ position: relative~', $mobile);

        // 🔴 ห้ามกลับไปล็อกความสูงของบัตรอีก ไม่งั้นเนื้อในโดนตัดเหมือนเดิม
        $this->assertDoesNotMatchRegularExpression('~\.namecard \{[^}]*min-height~s', $mobile);

        // มือถือมีที่เหลือทางตั้ง ไม่ใช่ทางนอน — ค่ายาวต้องขึ้นบรรทัดใหม่ ไม่ใช่ตัดด้วย ...
        $this->assertMatchesRegularExpression('~dd \{ white-space: normal~', $mobile);
    }

    /** เดสก์ท็อปต้องเป็นนามบัตร 16:9 เหมือนเดิม — แก้มือถือแล้วห้ามกระทบของเดิม */
    public function test_the_desktop_card_is_still_a_16_by_9_name_card(): void
    {
        $this->user();

        $html = $this->get('/profile')->assertOk()->getContent();
        $desktop = str_replace($this->mobileCss($html), '', $html);

        $this->assertMatchesRegularExpression('~\.namecard \{[^}]*aspect-ratio: 16 / 9~s', $desktop);
        $this->assertMatchesRegularExpression('~\.card-face \{\s*position: absolute; inset: 0; overflow: hidden~s', $desktop);
        $this->assertMatchesRegularExpression('~\.card-body \{[^}]*grid-template-columns: auto 1fr~s', $desktop);
    }
}
