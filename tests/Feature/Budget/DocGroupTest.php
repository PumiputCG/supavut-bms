<?php

namespace Tests\Feature\Budget;

use App\Http\Middleware\Authenticate;
use App\Models\Audit\ActivityLog;
use App\Models\Budget\Budget;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * ตั้งค่าหมายเลขเอกสาร (เจ้าของสั่ง 2026-09-17 · DECISIONS 50.10)
 *
 * คุมกติกา
 *   - หน้านี้เป็นของผู้ดูแลระบบเท่านั้น
 *   - โค้ด A–Z/0–9 2–4 ตัว แปลงเป็นตัวใหญ่ให้ · ห้ามซ้ำ · ชื่อบังคับครบ 2 ภาษา
 *   - มีเอกสารออกเลขแล้ว = ล็อกโค้ด แก้ได้แค่ชื่อ
 *   - มีเอกสาร (รวมร่าง) = ลบไม่ได้ ต้องปิดใช้งานแทน
 */
class DocGroupTest extends TestCase
{
    use RefreshModuleDatabase;

    private int $seq = 0;

    /** ตัวนับเลขที่ของเอกสารทดสอบ — แยกจากตัวนับผู้ใช้ ไม่งั้นเลขซ้ำชน unique */
    private int $docSeq = 0;

    private function user(string $code, string $role = 'user'): AppUser
    {
        $this->seq++;

        Employee::create([
            'insight_id' => 5000 + $this->seq,
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

        return AppUser::create([
            'insight_id' => 6000 + $this->seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => $role,
            'full_name_th' => 'ทดสอบ '.$code,
            'dept_code' => 'D01',
        ]);
    }

    private function signIn(AppUser $user): self
    {
        $this->flushSession();
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $this;
    }

    private function admin(): AppUser
    {
        return $this->user('DG_ADMIN_'.($this->seq + 1), 'admin');
    }

    /** เอกสารในกลุ่ม — $numbered = false คือร่างที่ยังไม่ออกเลข */
    private function invest(DocGroup $group, bool $numbered = true): Invest
    {
        $this->docSeq++;

        return Invest::create([
            'doc_no' => $numbered ? 'INV-'.$group->code.'-2569-'.str_pad((string) $this->docSeq, 6, '0', STR_PAD_LEFT) : null,
            'group_id' => $group->id,
            'fiscal_year' => 2569,
            'dept_code' => 'D01',
            'title' => 'เอกสารของกลุ่ม '.$group->code,
            'amount' => 1000,
            'approval_status' => $numbered ? Invest::PENDING : Invest::DRAFT,
            'created_by' => 'X',
            'created_by_name' => 'X',
        ]);
    }

    public function test_only_admins_can_open_the_settings(): void
    {
        $this->get('/budget/doc-groups')->assertRedirect();

        $this->signIn($this->user('DG_USER'))->get('/budget/doc-groups')->assertForbidden();
        $this->signIn($this->user('DG_USER2'))->post('/budget/doc-groups', [
            'code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model',
        ])->assertForbidden();
        $this->assertSame(0, DocGroup::count());

        $this->signIn($this->admin())->get('/budget/doc-groups')->assertOk()
            ->assertSee('data-i18n="nav.docNumber"', false)
            ->assertSee('data-i18n="dg.none"', false);
    }

    /** เมนูอยู่ท้ายกลุ่มจัดการระบบ และเห็นเฉพาะผู้ดูแลระบบ */
    public function test_the_menu_is_admin_only(): void
    {
        $this->signIn($this->admin())->get('/guide')->assertOk()
            ->assertSee('data-i18n="nav.docNumber"', false)
            ->assertSee('href="'.route('budget.docgroup.index').'"', false);

        $this->signIn($this->user('DG_MENU'))->get('/guide')->assertOk()
            ->assertDontSee('data-i18n="nav.docNumber"', false);
    }

    public function test_an_admin_can_add_a_group_and_see_the_numbers(): void
    {
        $admin = $this->admin();

        // 🔴 โค้ดพิมพ์ตัวเล็ก/มีช่องว่าง ต้องถูกแปลงเป็นตัวใหญ่ให้
        $this->signIn($admin)->post('/budget/doc-groups', [
            'code' => ' n m ',
            'name_th' => '  โมเดลใหม่  ',
            'name_en' => 'New Model',
        ])->assertRedirect(route('budget.docgroup.index'))->assertSessionHas('flash_success');

        $group = DocGroup::firstOrFail();
        $this->assertSame('NM', $group->code);
        $this->assertSame('โมเดลใหม่', $group->name_th);
        $this->assertTrue($group->active);
        $this->assertSame(1, $group->sort);
        $this->assertTrue(ActivityLog::where('event', 'doc_group_created')->where('subject', 'NM')->exists());

        /*
          ตัวอย่างเลขที่ทั้ง 2 ชนิดเป็น "รูปแบบ" — ปีและเลขวิ่งเป็น xx/xxx (เจ้าของสั่ง 2026-09-17)
          🔴 ปีจริงรันตามปีงบ และเลขวิ่งออกตอนกดส่ง เขียนเลขจริงจะอ่านเหมือนเป็นเลขของใบถัดไป
        */
        $era = substr((string) ((int) now()->year + 543), 0, 2);
        $this->signIn($admin)->get('/budget/doc-groups')->assertOk()
            ->assertSee('INV-NM-'.$era.'<i class="dg-run">xx</i>-000<i class="dg-run">xxx</i>', false)
            ->assertSee('BGT-NM-'.$era.'<i class="dg-run">xx</i>-000<i class="dg-run">xxx</i>', false);
    }

    public function test_bad_input_is_refused_with_a_clear_message(): void
    {
        $admin = $this->admin();
        DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);

        $cases = [
            'สั้นเกิน' => ['code' => 'N', 'name_th' => 'ก', 'name_en' => 'A'],
            'ยาวเกิน' => ['code' => 'ABCDE', 'name_th' => 'ก', 'name_en' => 'A'],
            'อักขระพิเศษ' => ['code' => 'N-M', 'name_th' => 'ก', 'name_en' => 'A'],
            'ภาษาไทย' => ['code' => 'กข', 'name_th' => 'ก', 'name_en' => 'A'],
            'ซ้ำ' => ['code' => 'nm', 'name_th' => 'ก', 'name_en' => 'A'],
            'ไม่มีชื่อไทย' => ['code' => 'AB', 'name_th' => '  ', 'name_en' => 'A'],
            'ไม่มีชื่ออังกฤษ' => ['code' => 'AB', 'name_th' => 'ก', 'name_en' => ''],
        ];

        foreach ($cases as $why => $input) {
            $this->signIn($admin)->post('/budget/doc-groups', $input)
                ->assertRedirect(route('budget.docgroup.index'))
                ->assertSessionHas('flash_error')
                // หน้าต่างเดิมต้องเปิดกลับมาพร้อมค่าที่กรอก
                ->assertSessionHas('dg_reopen', 'new');

            $this->assertSame(1, DocGroup::count(), 'ต้องไม่บันทึก: '.$why);
        }

        // ข้อความของโค้ดซ้ำต้องบอกตรงๆ ว่าซ้ำ ไม่ใช่ข้อความรูปแบบผิด
        $this->signIn($admin)->post('/budget/doc-groups', ['code' => 'NM', 'name_th' => 'ก', 'name_en' => 'A'])
            ->assertSessionHas('flash_error', [
                'th' => 'โค้ด NM มีหมวดอื่นใช้อยู่แล้ว',
                'en' => 'Code NM is already used by another category',
            ]);
    }

    public function test_a_group_without_numbered_documents_can_change_its_code(): void
    {
        $admin = $this->admin();
        $group = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);

        // ร่างที่ยังไม่ออกเลขไม่ล็อกโค้ด — เลขยังไม่ได้ออกไปไหน
        $this->invest($group, numbered: false);
        // อ่านจาก DB — ตัว model ที่เพิ่ง create() ยังไม่มีค่า default ของคอลัมน์ติดมาด้วย
        $sortBefore = $group->fresh()->sort;

        $this->signIn($admin)->put('/budget/doc-groups/'.$group->id, [
            'code' => 'NW', 'name_th' => 'โมเดลใหม่ล่าสุด', 'name_en' => 'Newest Model',
        ])->assertRedirect()->assertSessionHas('flash_success');

        $group->refresh();
        $this->assertSame('NW', $group->code);
        $this->assertSame('โมเดลใหม่ล่าสุด', $group->name_th);
        // 🔴 ไม่มีช่อง "ลำดับการแสดง" ให้กรอกแล้ว (เจ้าของสั่งเอาออก 2026-09-17)
        //    การแก้ไขจึงต้องไม่ไปขยับลำดับของหมวดเลย
        $this->assertSame($sortBefore, $group->sort);
    }

    /** 🔴 มีเอกสารออกเลขแล้ว = ล็อกโค้ด แก้ได้แค่ชื่อ (เจ้าของเคาะ) */
    public function test_the_code_locks_once_a_document_is_numbered(): void
    {
        $admin = $this->admin();
        $group = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);
        $this->invest($group);

        $this->signIn($admin)->put('/budget/doc-groups/'.$group->id, [
            'code' => 'NW', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model',
        ])->assertRedirect()->assertSessionHas('flash_error')->assertSessionHas('dg_reopen', $group->id);

        $this->assertSame('NM', $group->fresh()->code);

        // แก้ชื่ออย่างเดียว (โค้ดเดิม) ผ่าน
        $this->signIn($admin)->put('/budget/doc-groups/'.$group->id, [
            'code' => 'nm', 'name_th' => 'ชื่อใหม่', 'name_en' => 'New name',
        ])->assertSessionHas('flash_success');

        $this->assertSame('ชื่อใหม่', $group->fresh()->name_th);

        // หน้าตั้งค่าบอกด้วยแม่กุญแจ + ช่องโค้ดในหน้าต่างรู้ว่าล็อก
        $this->signIn($admin)->get('/budget/doc-groups')->assertOk()
            ->assertSee('data-locked="1"', false)
            ->assertSee('data-i18n="dg.locked"', false);
    }

    /** งบที่อนุมัติแล้วก็นับว่า "ออกเลขแล้ว" */
    public function test_a_budget_number_also_locks_the_code(): void
    {
        $admin = $this->admin();
        $group = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);
        $invest = $this->invest($group);
        $invest->update(['doc_no' => null]);

        Budget::create([
            'doc_no' => 'BGT-NM-2569-000001',
            'group_id' => $group->id,
            'invest_id' => $invest->id,
            'fiscal_year' => 2569,
            'dept_code' => 'D01',
            'title' => 'งบ',
            'approved_amount' => 1000,
            'approval_status' => Invest::APPROVED,
            'budget_status' => Budget::PENDING_REGISTER,
        ]);

        $this->assertTrue($group->codeLocked());

        $this->signIn($admin)->put('/budget/doc-groups/'.$group->id, [
            'code' => 'NW', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model',
        ])->assertSessionHas('flash_error');
        $this->assertSame('NM', $group->fresh()->code);
    }

    /** 🔴 มีเอกสาร (รวมร่าง) = ลบไม่ได้ · ว่างเปล่า = ลบได้ */
    public function test_only_an_empty_group_can_be_deleted(): void
    {
        $admin = $this->admin();
        $used = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);
        $empty = DocGroup::create(['code' => 'MS', 'name_th' => 'เบ็ดเตล็ด', 'name_en' => 'Misc']);

        // ร่างอย่างเดียวก็ห้ามลบ ไม่งั้นร่างกำพร้า ออกเลขตอนส่งไม่ได้
        $this->invest($used, numbered: false);

        $this->signIn($admin)->delete('/budget/doc-groups/'.$used->id)->assertSessionHas('flash_error');
        $this->assertNotNull($used->fresh());

        /*
          🔴 ช่อง "ลบ" (เจ้าของสั่ง 2026-09-17) — ตรวจ "ก่อนลบหมวดว่าง" เพราะต้องมีทั้ง 2 แบบอยู่พร้อมกัน
             ลบได้ = ปุ่มถังขยะ · ลบไม่ได้ = ช่องพื้นเทาล้วน + หมายเหตุสีแดงบอกเหตุผล
        */
        $this->flushSession();
        $html = $this->signIn($admin)->get('/budget/doc-groups')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-dg-delete="'.$used->id.'"', $html);
        $this->assertStringContainsString('data-dg-delete="'.$empty->id.'"', $html, 'หมวดที่ลบได้ต้องมีปุ่มถังขยะ');
        $this->assertStringContainsString('class="st st-none"', $html, 'หมวดที่ลบไม่ได้ต้องเป็นช่องพื้นเทา');
        $this->assertStringContainsString('data-i18n="dg.hasDocs"', $html, 'ต้องบอกเหตุผลที่ลบไม่ได้');

        $this->flushSession();
        $this->signIn($admin)->delete('/budget/doc-groups/'.$empty->id)->assertSessionHas('flash_success');
        $this->assertNull($empty->fresh());
        $this->assertTrue(ActivityLog::where('event', 'doc_group_deleted')->where('subject', 'MS')->exists());
    }

    /** ปิดใช้งาน = ไม่โผล่ให้เลือก แต่เอกสารเดิมยังอยู่ครบ · เปิดกลับได้ */
    public function test_a_group_can_be_disabled_and_enabled_again(): void
    {
        $admin = $this->admin();
        $group = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);
        $invest = $this->invest($group);

        // สถานะระบายพื้นเต็มช่อง — ใช้งาน = เขียว (.st-active)
        $this->signIn($admin)->get('/budget/doc-groups')->assertOk()
            ->assertSee('<span class="st st-active"', false);

        $this->flushSession();
        $this->signIn($admin)->post('/budget/doc-groups/'.$group->id.'/toggle')->assertSessionHas('flash_success');
        $this->assertFalse($group->fresh()->active);

        // ปิดใช้งาน = เทา (.st-closed)
        $this->flushSession();
        $this->signIn($admin)->get('/budget/doc-groups')->assertOk()
            ->assertSee('<span class="st st-closed"', false);
        $this->assertNotNull($invest->fresh());
        $this->assertTrue(ActivityLog::where('event', 'doc_group_disabled')->exists());

        // ไม่อยู่ในหน้าคั่นเลือกหมวดแล้ว
        $this->signIn($admin)->get('/budget/invest')->assertOk()
            ->assertDontSee('href="'.route('budget.invest.index', ['group' => $group->id]).'"', false);

        // แต่ยังมีแท็บให้กรองดูเอกสารเก่าได้ในหน้าอื่น (ขีดฆ่าชื่อ)
        $this->signIn($admin)->get('/budget/approval')->assertOk()
            ->assertSee('dg-tab is-off', false);

        $this->signIn($admin)->post('/budget/doc-groups/'.$group->id.'/toggle');
        $this->assertTrue($group->fresh()->active);
        $this->assertTrue(ActivityLog::where('event', 'doc_group_enabled')->exists());
    }

    /** กลุ่มที่ปิดใช้งานและไม่มีเอกสาร — ไม่ต้องมีแท็บให้รก */
    public function test_a_disabled_empty_group_has_no_tab(): void
    {
        $admin = $this->admin();
        DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);
        DocGroup::create(['code' => 'OF', 'name_th' => 'เลิกใช้', 'name_en' => 'Retired', 'active' => false]);

        $html = $this->signIn($admin)->get('/budget/history')->assertOk()->getContent();

        $this->assertStringContainsString('<span class="dg-code">NM</span>', $html);
        $this->assertStringNotContainsString('<span class="dg-code">OF</span>', $html);
    }

    /** เลขที่ตัวอย่างไม่ใช่เลขที่จะได้จริง — เป็นรูปแบบ xxx เสมอแม้หมวดจะมีเอกสารแล้ว */
    public function test_the_sample_number_is_never_the_real_next_number(): void
    {
        $admin = $this->admin();
        $group = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model']);
        $this->invest($group);
        $this->invest($group);

        $year = (int) now()->year + 543;

        $this->signIn($admin)->get('/budget/doc-groups')->assertOk()
            ->assertSee('000<i class="dg-run">xxx</i>', false)
            ->assertDontSee('INV-NM-'.$year.'-000003')
            ->assertDontSee('INV-NM-'.$year.'-000001');
    }
}
