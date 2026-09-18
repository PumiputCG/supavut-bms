# BMS — Business Management System

**TH:** ระบบอนุมัติเอกสารภายใน + แดชบอร์ดงบประมาณที่ตรวจสอบย้อนกลับได้ทุกบาท
**EN:** Internal document approvals plus a budget dashboard where every number can be traced back to its evidence.

`PHP 8.2` · `Laravel 12` · `MySQL` · `Tailwind CSS 4` · `Vite 7` · `ERP integration`

---

## 🇹🇭 ภาษาไทย

### คำถามที่ระบบนี้ตอบ

"แผนกฉันมีงบเท่าไหร่ ใช้ไปแล้วเท่าไหร่ กันไว้เท่าไหร่ แล้วเหลือใช้ได้จริงอีกเท่าไหร่?"

ฟังดูเป็นคำถามง่ายๆ แต่ตอบยากมาก เพราะข้อมูลอยู่ใน ERP กระจายหลายตาราง และที่สำคัญ — **ใบสั่งซื้อ (PO) ไม่เท่ากับเงินที่ใช้ไปจริง** ระบบเก่าชอบเอามารวมกันแล้วตัวเลขก็เพี้ยน

BMS แยกสามอย่างนี้ออกจากกันให้ชัด: **งบที่ตั้งไว้** / **ที่สั่งซื้อไปแล้ว** / **ที่ใช้จริง** แล้วทุกตัวเลขบนแดชบอร์ดกดเข้าไปดูเอกสารต้นทางได้

### ฟีเจอร์หลัก

- **แดชบอร์ดงบประมาณ** — แยกตามบริษัท / แผนก / ช่วงเวลา สลับไปมาโดยไม่หลุด context
- **เอกสารอนุมัติ** — สร้าง ส่ง ติดตามสถานะ ตามลำดับอนุมัติจริงขององค์กร
- **งบลงทุน (Invest)** — แยกจากงบดำเนินงาน
- **Track & History** — ย้อนดูได้ว่าเอกสารไหนผ่านมือใคร เมื่อไหร่
- **Activity Log** — บันทึกการกระทำทุกอย่างเพื่อตรวจสอบย้อนหลัง
- **จัดการสิทธิ์** — คุมการเข้าถึงระดับโมดูลและระดับระบบแยกกัน
- **Master Data** — จัดการข้อมูลตั้งต้นทั้งหมดในที่เดียว

### หลักที่ยึดตอนออกแบบ

1. **ยอดรวมต้องกระทบยอดกับรายละเอียดได้** ถ้าไม่ตรงต้องบอกให้ชัดว่าต่างตรงไหน ไม่ใช่ซ่อนไว้
2. **อย่าเอา PO มานับเป็นเงินที่ใช้แล้ว** — นี่คือบั๊กคลาสสิกที่ทำให้ผู้บริหารตัดสินใจผิด
3. **ข้อมูลที่จัดหมวดไม่ได้ ห้ามทิ้ง** ต้องแสดงไว้และบอกว่ามันคืออะไร
4. ใช้ภาษาไทย/อังกฤษคู่กัน คุมด้วยคีย์บอร์ดได้ contrast ผ่าน WCAG AA

### โครงสร้างโค้ด

```
app/Http/Controllers/
├── Budget/    → BudgetController, ApprovalController, InvestController,
│                TrackController, HistoryController, DocGroupController
├── Core/      → DashboardController, NotificationController, Auth
├── Access/    → ModuleAccessController, SystemAccessController
├── Audit/     → ActivityLogController
└── Master/    → MasterDataController
```

### ติดตั้ง

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate && npm run build && php artisan serve
```

---

## 🇬🇧 English

### The question this answers

"How much budget does my department have, how much is spent, how much is committed, and how much can I actually still use?"

Simple question, hard answer. The data is spread across ERP tables, and — this is the part that trips most systems up — **a purchase order is not the same thing as money spent**. Conflate the two and the numbers quietly go wrong.

BMS keeps three things separate and visible: **budgeted**, **purchased**, and **consumed**. Every figure on the dashboard opens the underlying document.

### Features

- **Budget dashboard** by company, department, and period — context survives navigation
- **Approval documents** following the organization's real signature chain
- **Investment budgets** tracked apart from operating budgets
- **Track & History** — who touched which document, and when
- **Activity log** for after-the-fact auditing
- **Access control** split between module-level and system-level permissions
- **Master data** management in one place

### Design rules

1. **Summaries must reconcile with their details** — and where they don't, say so out loud
2. **Never count purchase orders as consumption** — the classic bug that sends managers the wrong signal
3. **Never silently drop unclassified data** — show it, and label what it is
4. Thai and English throughout, keyboard navigable, WCAG AA contrast

### Note

Code only. ERP schema dumps, real budget data, and analysis files are excluded from this repository.
