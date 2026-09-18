<?php

namespace App\Support;

/**
 * โครงเมนูของ SBMS — แหล่งความจริงเดียว
 *
 * ทั้งเมนูซ้าย (layouts/partials/sidebar) และแผงเมนูย่อยทางขวา (layouts/partials/submenu)
 * อ่านจากที่นี่ที่เดียว จะได้ไม่หลุดจากกันเวลาแก้
 *
 * โครงสร้าง 3 ชั้น (เจ้าของกำหนด 2026-09-02)
 *   หัวข้อหลัก  →  โมดูล (ชื่อกว้างๆ ครอบคลุมงานทั้งก้อน)  →  ฟังก์ชันในโมดูล
 *
 * หัวข้อหลัก 3 กลุ่ม (เจ้าของจัดใหม่ 2026-09-03)
 *   📊 ภาพรวมระบบ    แดชบอร์ด · รายงาน
 *   🧩 โมดูลระบบงาน  งานจัดซื้อตั้งแต่ตั้งงบจนจ่ายเงิน (10 โมดูล)
 *   ⚙️ จัดการระบบ    ข้อมูลหลัก · ประวัติการใช้งาน · สิทธิ์การเข้าถึง · บัญชีภายนอก · ตั้งค่าหมายเลขเอกสาร (เห็นเฉพาะผู้ดูแลระบบ)
 *                    มีแค่ "สิทธิ์การเข้าถึง" ที่มีเมนูย่อยทางขวา ที่เหลือเป็นลิงก์ตรง
 *
 * 🔴 โมดูลที่มี `functions` จะไม่ลิงก์ไปหน้าไหน แต่เปิดแผงเมนูย่อยทางขวาแทน
 *    เพราะรายละเอียดของงานอยู่ในแผงนั้น ไม่ได้อยู่ในชื่อโมดูล
 *
 * ⚠️ เพิ่ม/แก้เมนูที่นี่ ต้องเพิ่มคีย์แปลให้ครบ 2 ภาษาใน layouts/i18n.blade.php ด้วยเสมอ
 *
 * คีย์ที่ใช้ได้
 *   key        คีย์แปล (ต้องมีทั้ง th และ en)
 *   th         ข้อความไทยที่เรนเดอร์มาก่อน JS ทำงาน (กันหน้าโล่งตอนโหลด)
 *   icon       ชื่อไอคอน (ดู layouts/partials/nav-icon)
 *   route      ชื่อ route ถ้ามีหน้าจริงแล้ว · null = ยังไม่ได้ทำ
 *   id         รหัสของโมดูล ใช้จับคู่กับแผงเมนูย่อย และกับตารางสิทธิ์ access_module_positions
 *   functions  รายการฟังก์ชันในโมดูล (ใส่ route ได้เหมือนกัน)
 *   extras     สิทธิ์ย่อยของหัวข้อนั้น เช่น "เห็นหน้าได้ทุกคน แต่แก้สถานะได้เฉพาะบางคน"
 *              เก็บในตารางเดียวกับหัวข้อย่อย (access_function_users) แค่ใช้คีย์ต่างกัน
 *   admin      true = เห็นเฉพาะผู้ดูแลระบบ (ซ่อนไปเลยถ้าไม่มีสิทธิ์)
 */
class NavMenu
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function groups(): array
    {
        return [
            // ═══ 📊 ภาพรวมระบบ ═════════════════════════════════════════
            [
                // หน้า "หน้าหลัก" (/guide) ไม่อยู่ในเมนูนี้ — เข้าจากปุ่ม "หน้าแรก" บนแถบเครื่องมือ
                // เจ้าของสลับชื่อหัวข้อหลักกับหัวข้อย่อยเมื่อ 2026-09-03:
                //   หัวข้อหลัก = ภาพรวมระบบ · หัวข้อย่อย = แดชบอร์ด
                'key' => 'nav.overview', 'th' => 'ภาพรวมระบบ', 'icon' => 'mod-dashboard', 'open' => true,
                'id' => 'overview',
                'items' => [
                    /*
                      🔴 perm_key = "รายการนี้กำหนดสิทธิ์ได้ด้วยตัวเอง" (เจ้าของสั่ง 2026-09-10)
                         ใช้กับรายการที่เป็นลิงก์ตรง ไม่มีหัวข้อย่อยของตัวเอง
                         permissionedModules() จะปั้นหัวข้อย่อยให้ 1 อันโดยอัตโนมัติ
                         เมนูซ้ายไม่เปลี่ยน เพราะยังไม่มีคีย์ functions
                    */
                    [
                        'key' => 'nav.dashboard', 'th' => 'แดชบอร์ด', 'route' => 'dashboard',
                        'icon' => 'dashboard', 'id' => 'dashboard', 'perm_key' => 'fn.overview.dashboard',
                    ],
                ],
            ],

            // ═══ 🧩 โมดูลระบบงาน ═══════════════════════════════════════
            [
                /*
                  เรียงตามลำดับงานจริงตั้งแต่ตั้งงบจนจ่ายภาษี 🔴 ห้ามเรียงใหม่ตามตัวอักษร

                  โครงเมนูชุดนี้เจ้าของยืนยันเมื่อ 2026-09-03 ตรงกับเอกสาร `ให้วิเคราะห์/Module_BMS.txt`
                    Invest → CEO → Budget → แจ้งขอซื้อ → Manager → PR → Purchase ตรวจ
                    → RFQ → Spec/Quotation → QuoteCompare → CEO → PO → รับของ + Invoice
                    → ตรวจรับ 4 คน → Asset/QR → ตั้งเบิก/จ่าย → ภาษีหัก ณ ที่จ่าย

                  ⚠️ RFQ กับ Spec/Quotation ไม่ใช่โมดูลแยกแล้ว — ย้ายเข้าไปอยู่ใต้ PR
                     เพราะเป็นขั้นตอนของใบขอซื้อใบเดียวกัน ไม่ใช่งานคนละก้อน
                */
                'key' => 'nav.modules', 'th' => 'โมดูลระบบงาน', 'icon' => 'group', 'open' => true,
                'items' => [
                    [
                        'key' => 'nav.budget', 'th' => 'งบประมาณ', 'icon' => 'mod-budget', 'id' => 'budget',
                        'functions' => [
                            ['key' => 'fn.budget.invest', 'th' => 'ของบประมาณ', 'route' => 'budget.invest.index'],
                            /*
                              🔴 ยุบ "รับทราบ" + "อนุมัติ Invest" เป็นหัวข้อเดียว (เจ้าของสั่ง 2026-09-09)
                                 เหตุผล: ผู้ขอเลือกผู้อนุมัติเองตอนส่งเอกสารแล้ว
                                 จึงไม่ต้องตั้งรายชื่อผู้ลงนามล่วงหน้าที่หน้านี้อีก
                                 สิทธิ์ที่นี่เหลือความหมายเดียว = "เข้าหน้ารับทราบ/อนุมัติได้ไหม"
                                 ส่วนใครเซ็นใบไหนได้ ตัดสินจากการมีชื่ออยู่ในเอกสารฉบับนั้น
                            */
                            [
                                'key' => 'fn.budget.inbox', 'th' => 'สถานะการดำเนินการ',
                                'route' => 'budget.approval.index', 'icon' => 'fn-budget-inbox',
                                /*
                                  🔴 ไม่ต้องกำหนดคนที่นี่ (เจ้าของสั่ง 2026-09-09)
                                     ผู้ขอเลือกผู้อนุมัติเองได้จากพนักงานทุกคนตอนส่งเอกสาร
                                     ถ้ากั้นด้วยรายชื่อ คนที่ถูกเลือกแต่ไม่อยู่ในรายชื่อจะเปิดเอกสารของตัวเองไม่ได้
                                     จึงกั้นด้วย "ข้อมูล" แทน — เปิดหน้าได้ทุกคน แต่เห็นเฉพาะใบที่ตัวเองอยู่ในสาย
                                */
                                'note' => ['key' => 'access.fn.pickedBySender', 'th' => 'ผู้ขอเลือกเองตอนส่งเอกสาร'],
                                'no_edit' => true,
                            ],
                            /*
                              🔴 ถอดสิทธิ์ย่อย "ปรับสถานะงบประมาณ" ออกทั้งระบบ (เจ้าของสั่ง 2026-09-09)
                                 ใครเห็นหน้านี้ได้ ก็ปรับสถานะได้เลย ไม่ต้องมีสิทธิ์ซ้อนอีกชั้น
                            */
                            [
                                'key' => 'fn.budget.approved', 'th' => 'ลงทะเบียนงบประมาณ',
                                'route' => 'budget.list.index', 'icon' => 'fn-budget-approved',
                            ],
                            // 🔴 หัวข้อ "ประวัติ..." ของทุกโมดูลใช้ไอคอนตัวนี้ร่วมกัน
                            ['key' => 'fn.budget.history', 'th' => 'ประวัติงบประมาณ', 'route' => 'budget.history.index', 'icon' => 'fn-history'],
                        ],
                    ],
                    [
                        'key' => 'nav.pr', 'th' => 'ใบขอซื้อ (PR)', 'icon' => 'mod-pr', 'id' => 'pr',
                        'functions' => [
                            ['key' => 'fn.pr.request', 'th' => 'แจ้งขอซื้อ'],
                            ['key' => 'fn.pr.manager', 'th' => 'PR รอ Manager อนุมัติ'],
                            ['key' => 'fn.pr.purchase', 'th' => 'PR รอ Purchase ตรวจสอบ'],
                            ['key' => 'fn.pr.rfq', 'th' => 'RFQ / ขอรายละเอียดการซื้อ'],
                            ['key' => 'fn.pr.spec', 'th' => 'Spec / Quotation'],
                            ['key' => 'fn.pr.history', 'th' => 'ประวัติ PR', 'icon' => 'fn-history'],
                        ],
                    ],
                    [
                        'key' => 'nav.quote', 'th' => 'เปรียบเทียบราคาผู้ขาย', 'icon' => 'mod-quote', 'id' => 'quote',
                        'functions' => [
                            ['key' => 'fn.quote.compare', 'th' => 'Quote Compare'],
                            ['key' => 'fn.quote.ceo', 'th' => 'รอ CEO ลงนาม'],
                            ['key' => 'fn.quote.history', 'th' => 'ประวัติ Quote Compare', 'icon' => 'fn-history'],
                        ],
                    ],
                    [
                        'key' => 'nav.poInvoice', 'th' => 'ใบสั่งซื้อและใบแจ้งหนี้', 'icon' => 'mod-po-invoice', 'id' => 'po',
                        'functions' => [
                            ['key' => 'fn.po.doc', 'th' => 'ใบสั่งซื้อ (PO)'],
                            ['key' => 'fn.po.invoice', 'th' => 'Invoice'],
                            ['key' => 'fn.po.history', 'th' => 'ประวัติ PO / Invoice', 'icon' => 'fn-history'],
                        ],
                    ],
                    [
                        'key' => 'nav.receiving', 'th' => 'รับและตรวจรับสินค้า', 'icon' => 'mod-receiving', 'id' => 'receiving',
                        'functions' => [
                            ['key' => 'fn.grn.pending', 'th' => 'รายการรอรับสินค้า'],
                            ['key' => 'fn.grn.check', 'th' => 'ตรวจรับสินค้า / GRN'],
                            ['key' => 'fn.grn.asset', 'th' => 'Asset / Generate QR Code'],
                            ['key' => 'fn.grn.history', 'th' => 'ประวัติการรับสินค้า', 'icon' => 'fn-history'],
                        ],
                    ],
                    [
                        'key' => 'nav.payment', 'th' => 'ตั้งเบิกและจ่ายเงิน', 'icon' => 'mod-payment', 'id' => 'payment',
                        'functions' => [
                            ['key' => 'fn.pay.pending', 'th' => 'รายการรอตั้งเบิก'],
                            ['key' => 'fn.pay.due', 'th' => 'รายการรอจ่าย'],
                            ['key' => 'fn.pay.history', 'th' => 'ประวัติการจ่ายเงิน', 'icon' => 'fn-history'],
                        ],
                    ],
                    [
                        'key' => 'nav.tax', 'th' => 'ภาษีหัก ณ ที่จ่าย', 'icon' => 'mod-tax', 'id' => 'tax',
                        'functions' => [
                            ['key' => 'fn.tax.pending', 'th' => 'รายการรอดำเนินการ'],
                            ['key' => 'fn.tax.cert', 'th' => 'หนังสือรับรอง / 50 ทวิ'],
                            ['key' => 'fn.tax.history', 'th' => 'ประวัติภาษี', 'icon' => 'fn-history'],
                        ],
                    ],
                    [
                        // รายงานข้ามโมดูล — อยู่ท้ายสุดเพราะต้องรอโมดูลอื่นมีข้อมูลก่อน
                        'key' => 'nav.reports', 'th' => 'รายงาน', 'icon' => 'mod-report', 'id' => 'report',
                        'functions' => [
                            ['key' => 'nav.report.all', 'th' => 'รายงานทั้งหมด'],
                            ['key' => 'nav.report.budget', 'th' => 'รายงานงบประมาณ'],
                            ['key' => 'nav.report.purchase', 'th' => 'รายงานจัดซื้อ'],
                            ['key' => 'nav.report.receiving', 'th' => 'รายงานรับสินค้า'],
                            ['key' => 'nav.report.payment', 'th' => 'รายงานจ่ายเงิน / ภาษี'],
                        ],
                    ],
                ],
            ],

            // ═══ ⚙️ จัดการระบบ ════════════════════════════════════════
            [
                // 🔒 ทั้งกลุ่มเห็นเฉพาะผู้ดูแลระบบ — ไม่มีสิทธิ์คือซ่อนไปเลย ไม่ใช่เทาลง
                'key' => 'nav.manage', 'th' => 'จัดการระบบ', 'icon' => 'settings-main', 'open' => true, 'admin' => true,
                'items' => [
                    // 🔴 2 รายการนี้เป็นลิงก์ตรง ไม่มีเมนูย่อยทางขวา (เจ้าของสั่ง 2026-09-03)
                    //    เนื้อหาไม่เยอะพอจะแตกเป็นเมนูย่อย — รวมไว้หน้าเดียวแล้วแยกด้วยแท็บ/ลิงก์ในหน้าเอง
                    ['key' => 'nav.master', 'th' => 'ข้อมูลหลัก (Master Data)', 'route' => 'master.index', 'icon' => 'settings-sub'],
                    ['key' => 'nav.activityLog', 'th' => 'ประวัติการใช้งานระบบ', 'route' => 'activity.index', 'icon' => 'settings-sub'],
                    [
                        // เจ้าของสั่งเปลี่ยนชื่อจาก "สิทธิ์การเข้าถึงโมดูล" เป็น "สิทธิ์การเข้าถึง" (2026-09-03)
                        // แล้วแตกเป็น 2 หน้าในเมนูย่อยทางขวา
                        'key' => 'nav.access', 'th' => 'สิทธิ์การเข้าถึง', 'icon' => 'settings-sub', 'id' => 'access',
                        'functions' => [
                            // 🔴 หัวข้อย่อยของงานตั้งค่า ใช้ไอคอนเฟืองร่วมกัน
                            ['key' => 'fn.access.system', 'th' => 'สิทธิ์การเข้าถึงระบบ SBMS', 'route' => 'access.system', 'icon' => 'fn-settings'],
                            ['key' => 'fn.access.module', 'th' => 'สิทธิ์การเข้าถึงโมดูล', 'route' => 'access.modules', 'icon' => 'fn-settings'],
                        ],
                    ],
                    ['key' => 'nav.external', 'th' => 'บัญชีผู้ใช้ภายนอก (Supplier)', 'route' => 'admin.external', 'icon' => 'settings-sub'],
                    /*
                      ตั้งค่าหมายเลขเอกสาร — กลุ่มเอกสาร + โค้ดในเลขที่ INV/BGT (เจ้าของสั่ง 2026-09-17)
                      ต่อท้ายกลุ่มจัดการระบบ (DECISIONS 50.3 ข้อ 6) · ลิงก์ตรง ไม่มีเมนูย่อยทางขวา
                    */
                    ['key' => 'nav.docNumber', 'th' => 'ตั้งค่าหมายเลขเอกสาร', 'route' => 'budget.docgroup.index', 'icon' => 'settings-sub'],
                ],
            ],
        ];
    }

    /**
     * โมดูลที่กำหนดสิทธิ์รายคนได้ — ทุกกลุ่มยกเว้นกลุ่มที่ตั้งธง admin ไว้
     *
     * ใช้กับตารางสิทธิ์การเข้าถึงโมดูล และใช้ตัดสินว่าใครเห็นโมดูลไหนในเมนูซ้าย
     *
     * @return array<int,array<string,mixed>>
     */
    public static function permissionedModules(): array
    {
        $modules = [];

        foreach (self::groups() as $group) {
            if (! empty($group['admin'])) {
                continue;   // กลุ่มจัดการระบบเป็นของผู้ดูแลระบบอยู่แล้ว ไม่ต้องมากำหนดสิทธิ์รายตำแหน่งซ้ำ
            }

            foreach ($group['items'] as $item) {
                if (! empty($item['functions'])) {
                    $modules[] = $item + ['group' => $group];

                    continue;
                }

                /*
                  รายการที่เป็นลิงก์ตรงแต่ต้องกำหนดสิทธิ์ได้ (เช่น แดชบอร์ด)
                  ปั้นหัวข้อย่อยให้ 1 อันจากตัวมันเอง โค้ดที่เหลือจะได้ทำงานเหมือนโมดูลปกติ
                */
                if (! empty($item['perm_key'])) {
                    $modules[] = $item + [
                        'group' => $group,
                        'functions' => [[
                            'key' => $item['perm_key'],
                            'th' => $item['th'],
                            'icon' => $item['icon'] ?? null,
                            'route' => $item['route'] ?? null,
                        ]],
                    ];
                }
            }
        }

        return $modules;
    }

    /**
     * หน้าที่เปิดอยู่ตอนนี้สังกัด "สาย" ของเมนูไหน
     *
     * ใช้ตอบ 2 เรื่องที่เจ้าของสั่งไว้ 2026-09-03
     *   1. ปุ่ม "หน้าแรก" ต้องพากลับไปหน้าแรกของสายที่เข้ามา
     *      เช่น งบประมาณ > ของบประมาณ > เสนอรายการใหม่  ->  กดแล้วกลับไป "ของบประมาณ"
     *   2. แผงเมนูย่อยทางขวาต้องไม่ปิดเมื่อเข้าหน้าลูก (เสนอรายการใหม่ ฯลฯ)
     *
     * วิธีจับคู่ — ชื่อ route ตรงเป๊ะก่อน ถ้าไม่ตรงค่อยเทียบ "กลุ่ม" (ตัดส่วนท้ายออก 1 ชั้น)
     *   budget.invest.create  ->  กลุ่ม budget.invest  ->  ตรงกับ budget.invest.index
     *   access.modules        ->  ตรงเป๊ะกับ access.modules เอง (ไม่ไปโดน access.system)
     *
     * @return array{module:?array<string,mixed>,route:string,key:string,th:string}|null
     */
    public static function sectionFor(?string $routeName): ?array
    {
        if (! $routeName) {
            return null;
        }

        $group = str_contains($routeName, '.') ? substr($routeName, 0, strrpos($routeName, '.')) : $routeName;
        $exact = null;
        $byGroup = null;

        foreach (self::groups() as $g) {
            foreach ($g['items'] as $item) {
                // โมดูลที่มีเมนูย่อย — เทียบทีละหัวข้อย่อย
                foreach ($item['functions'] ?? [] as $fn) {
                    $hit = self::match($fn, $item, $routeName, $group);
                    $exact ??= $hit['exact'];
                    $byGroup ??= $hit['group'];
                }

                // รายการเดี่ยวที่ลิงก์ตรง (ข้อมูลหลัก · ประวัติการใช้งาน · แดชบอร์ด)
                if (empty($item['functions']) && ! empty($item['route'])) {
                    $hit = self::match($item, null, $routeName, $group);
                    $exact ??= $hit['exact'];
                    $byGroup ??= $hit['group'];
                }
            }
        }

        return $exact ?? $byGroup;
    }

    /**
     * เทียบ 1 เมนูกับ route ปัจจุบัน
     *
     * @param  array<string,mixed>  $entry  เมนูที่จะเทียบ (หัวข้อย่อย หรือรายการเดี่ยว)
     * @param  array<string,mixed>|null  $module  โมดูลแม่ ถ้ามี
     * @return array{exact:?array<string,mixed>,group:?array<string,mixed>}
     */
    private static function match(array $entry, ?array $module, string $routeName, string $group): array
    {
        $route = $entry['route'] ?? null;

        if (! $route) {
            return ['exact' => null, 'group' => null];
        }

        $found = [
            'module' => $module,
            'route' => $route,
            'key' => $entry['key'],
            'th' => $entry['th'],
        ];

        $routeGroup = str_contains($route, '.') ? substr($route, 0, strrpos($route, '.')) : $route;

        return [
            'exact' => $route === $routeName ? $found : null,
            'group' => $routeGroup === $group ? $found : null,
        ];
    }

    /**
     * id ของโมดูลที่อยู่ในกลุ่มของผู้ดูแลระบบ
     *
     * ใช้แยกว่าโมดูลไหนคุมด้วย "เป็นแอดมินไหม" ไม่ใช่ "มีสิทธิ์เห็นไหม"
     *
     * @return array<int,string>
     */
    public static function adminModuleIds(): array
    {
        $ids = [];

        foreach (self::groups() as $group) {
            if (empty($group['admin'])) {
                continue;
            }

            foreach ($group['items'] as $item) {
                if (! empty($item['id'])) {
                    $ids[] = (string) $item['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * route นี้ต้องมีสิทธิ์หัวข้อย่อยไหนถึงจะเข้าได้
     *
     * 🔴 หัวใจของการกันสิทธิ์จริง: middleware bms.fn เอาชื่อ route มาถามที่นี่
     *    จะได้ไม่ต้องไล่เขียนเงื่อนไขสิทธิ์ทีละ controller
     *
     * เทียบชื่อ route แบบ "ขึ้นต้นด้วย" เพื่อให้หน้าลูกได้สิทธิ์ตามหน้าแม่
     *   budget.invest.index / .create / .store / .edit ...  ->  fn.budget.invest
     *
     * 🔴 คืนได้ "หลายคีย์" เพราะหน้าเดียวอาจเปิดให้หลายบทบาทเข้า
     *    เช่นหน้ารับทราบ/อนุมัติ — มีสิทธิ์ข้อใดข้อหนึ่งก็เข้าได้
     *
     * @return array{module_id:string,function_keys:array<int,string>}|null
     */
    /**
     * หัวข้อย่อยนี้ "ตั้งสิทธิ์ไม่ได้" ใช่ไหม (no_edit)
     *
     * 🔴 หัวข้อพวกนี้ไม่มีหน้าจอให้กำหนดรายชื่อ (เช่น "รับทราบ" ที่ผู้เสนอเลือกคนเองตอนส่ง)
     *    รายชื่อจึงว่างตลอดไป — **ห้ามเอาไปใช้เป็นตัวกั้นสิทธิ์** ไม่งั้นจะปฏิเสธทุกคน
     *    (บทเรียนจริง 2026-09-07: ส่งเอกสารแล้วได้ 403 เพราะเอาหัวข้อแบบนี้ไปตรวจผู้รับทราบ)
     */
    public static function isUnsettable(string $functionKey): bool
    {
        foreach (self::permissionedModules() as $module) {
            foreach ($module['functions'] ?? [] as $fn) {
                if (($fn['key'] ?? null) === $functionKey) {
                    return ! empty($fn['no_edit']);
                }
            }
        }

        return false;
    }

    public static function functionKeyFor(?string $routeName): ?array
    {
        if (! $routeName) {
            return null;
        }

        $bestLen = -1;
        $bestModule = null;
        $keys = [];

        foreach (self::permissionedModules() as $module) {
            foreach ($module['functions'] ?? [] as $fn) {
                $route = $fn['route'] ?? null;

                if (! $route) {
                    continue;
                }

                // ตัด .index ท้ายออก เหลือ "สาย" ของหน้านั้น เช่น budget.invest.index -> budget.invest
                $prefix = str_ends_with($route, '.index') ? substr($route, 0, -6) : $route;

                if ($routeName !== $route && ! str_starts_with($routeName, $prefix.'.')) {
                    continue;
                }

                $len = strlen($prefix);

                // สายที่ยาวกว่าตรงกว่า — กัน budget.list ไปทับ budget.list.status
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $bestModule = (string) $module['id'];
                    $keys = [(string) $fn['key']];
                } elseif ($len === $bestLen && $bestModule === (string) $module['id']) {
                    $keys[] = (string) $fn['key'];
                }
            }
        }

        if ($bestModule === null) {
            return null;
        }

        return ['module_id' => $bestModule, 'function_keys' => array_values(array_unique($keys))];
    }

    /**
     * คีย์สิทธิ์ทั้งหมดของโมดูล — หัวข้อย่อย + สิทธิ์ย่อยของแต่ละหัวข้อ
     *
     * ใช้ตอนตรวจว่าคีย์ที่ส่งมาบันทึกมีอยู่จริงไหม กันคนยิงคีย์มั่วเข้ามา
     *
     * @param  array<string,mixed>  $module
     * @return array<int,string>
     */
    public static function permissionKeys(array $module): array
    {
        $keys = [];

        foreach ($module['functions'] ?? [] as $fn) {
            $keys[] = (string) $fn['key'];

            foreach ($fn['extras'] ?? [] as $extra) {
                $keys[] = (string) $extra['key'];
            }
        }

        return $keys;
    }

    /**
     * หัวข้อย่อยที่จะวาดในเมนูซ้าย — ยุบรายการที่ใช้ menu เดียวกันให้เหลืออันเดียว
     *
     * ใช้ตอนที่หน้าเดียวเปิดให้หลายบทบาทเข้า แต่ไม่อยากให้เมนูซ้ายมี 2 บรรทัดชี้ที่เดิม
     *
     * @param  array<string,mixed>  $module
     * @return array<int,array<string,mixed>>
     */
    public static function menuFunctions(array $module): array
    {
        $out = [];

        foreach ($module['functions'] ?? [] as $fn) {
            $group = $fn['menu'] ?? null;

            if (! $group) {
                $out[] = $fn + ['keys' => [$fn['key']]];

                continue;
            }

            $id = $group['key'];

            if (isset($out[$id])) {
                $out[$id]['keys'][] = $fn['key'];

                continue;
            }

            $out[$id] = [
                'key' => $group['key'],
                'th' => $group['th'],
                'route' => $fn['route'] ?? null,
                // ไอคอนของรายการที่ยุบรวมกัน — กำหนดในบล็อก menu ได้ ไม่กำหนดก็ใช้ของหัวข้อแรก
                'icon' => $group['icon'] ?? ($fn['icon'] ?? null),
                'keys' => [$fn['key']],
            ];
        }

        return array_values($out);
    }

    /**
     * เฉพาะโมดูลที่มีฟังก์ชันย่อย — ใช้วาดแผงเมนูทางขวา
     *
     * @return array<int,array<string,mixed>>
     */
    public static function modulesWithFunctions(): array
    {
        $modules = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                if (! empty($item['functions'])) {
                    $modules[] = $item;
                }
            }
        }

        return $modules;
    }
}
