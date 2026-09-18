{{-- ไม่พบหน้านี้ — ทางตันแบบเดียวกับ 403 จึงใช้หน้าเดียวกัน --}}
@include('errors.partials.page', [
  'code' => 404,
  'titleTh' => 'ไม่พบหน้านี้',
  'titleEn' => 'Page not found',
  'textTh' => 'ลิงก์อาจเก่าไปแล้ว พิมพ์ผิด หรือเอกสารถูกลบไป',
  'textEn' => 'The link may be out of date, mistyped, or the document has been removed.',
])
