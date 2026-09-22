# SBMS

## SBMS คืออะไร / About

ระบบงานจัดซื้อที่ครอบคลุมตั้งแต่ต้นจนจบ เริ่มจากขออนุมัติงบประมาณ เปิด PR เทียบราคา Supplier ออก PO ลงทะเบียนทรัพย์สิน ไปจนถึงออกใบ 50 ทวิ ทำงานคู่กับ ERP ของบริษัทเพื่อเติมส่วนที่ ERP ยังทำได้ไม่ครบ

An end-to-end purchasing system covering budget approval, purchase requests, supplier comparison, purchase orders, asset registration and withholding tax certificates. It runs alongside the company ERP and fills the gaps the ERP leaves.

## ทำอะไรได้บ้าง / Features

- ขออนุมัติงบประมาณ และแยกงบลงทุนออกจากงบดำเนินงาน
- เปิด PR ออก PO และดูได้ว่าเอกสารแต่ละใบอยู่ขั้นไหน
- แดชบอร์ดงบประมาณที่แยกงบที่ตั้งไว้ ยอดสั่งซื้อ และเงินที่ใช้จริง
- ลงทะเบียนทรัพย์สิน และออกใบ 50 ทวิ
- ส่งอีเมลแจ้งเตือนเมื่อมีเอกสารรออนุมัติ
- เก็บประวัติทุกการกระทำไว้ตรวจสอบย้อนหลัง

* Budget requests, with capital spending tracked apart from operating budgets
* Purchase requests and purchase orders, with the status of every document visible
* A budget dashboard that separates budgeted, ordered and actually spent amounts
* Asset registration and withholding tax certificates (50 Tawi)
* Email alerts when a document is waiting for approval
* A full activity log for auditing

## Tech Stack

**Backend:** PHP 8, Laravel 12

**Frontend:** Blade, Tailwind CSS, Vite, Axios

**Database:** MySQL

## ติดตั้ง / Installation

ต้องมี PHP 8.2 ขึ้นไป, Composer, Node.js และ MySQL ไฟล์ migration ของระบบนี้แยกเก็บตามโมดูล จึงต้องระบุ path ให้ครบตอนรัน ก่อนรันให้แก้ค่า `DB_*` ใน `.env` ให้ตรงกับฐานข้อมูลในเครื่อง

Requires PHP 8.2+, Composer, Node.js and MySQL. Migrations are split into folders by module, so every path has to be listed. Set the `DB_*` values in `.env` before migrating.

```bash
git clone https://github.com/PumiputCG/sbms.git
cd sbms
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate \
  --path=database/migrations \
  --path=database/migrations/core \
  --path=database/migrations/access \
  --path=database/migrations/audit \
  --path=database/migrations/budget \
  --path=database/migrations/erp
npm run build
php artisan serve
```

ถ้าต้องการให้ระบบส่งอีเมลแจ้งเตือน ให้รัน `php artisan queue:work` ค้างไว้อีกหน้าต่างหนึ่ง

To send email notifications, keep `php artisan queue:work` running in a separate terminal.
