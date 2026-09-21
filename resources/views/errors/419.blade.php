{{--
  หมดเวลาเชื่อมต่อ — เกิดตอน "กดส่งฟอร์มหลังจาก session หมดอายุไปแล้ว"
  🔴 ของเดิมเป็นหน้า "Page Expired" ดิบของ Laravel ซึ่งไม่มีทางออกเลย (ปัญหาเดียวกับ 403/404)
     และคำว่า Page Expired ไม่ได้บอกผู้ใช้ว่าต้องทำอะไรต่อ
--}}
@include('errors.partials.page', [
  'code' => 419,
  'titleTh' => 'หมดเวลาเชื่อมต่อ',
  'titleEn' => 'Session expired',
  'textTh' => 'ระบบออกจากระบบให้อัตโนมัติเพราะไม่ได้ใช้งานนานเกินกำหนด สิ่งที่เพิ่งกดส่งจึงยังไม่ถูกบันทึก — กรุณาเข้าสู่ระบบอีกครั้งแล้วทำรายการใหม่',
  'textEn' => 'You were signed out after a long period without activity, so what you just submitted was not saved — please sign in again and redo it.',
])
