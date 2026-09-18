{{--
  โครงหน้า "พิมพ์ / ดาวน์โหลด PDF" ที่ใช้ร่วมกันทุกเอกสาร

  🔴 ยกออกมาเป็นชิ้นส่วนกลางเมื่อ 2026-09-18 ตอนเพิ่มปุ่มดาวน์โหลดให้ใบ INV
     ของเดิมกฎการพิมพ์ทั้งชุด (รวมบทเรียนเรื่อง @page margin ของ Chrome) อยู่ในหน้าใบ BGT หน้าเดียว
     ถ้าก็อปไปวางในหน้าใหม่ วันหนึ่งจะแก้ไม่ครบ แล้วเอกสารคนละใบพิมพ์ออกมาไม่เหมือนกัน

  🔴 หน้านี้ตั้งใจไม่มีเมนูซ้ายและแถบบน — เปิดในแท็บใหม่ เห็นแต่ตัวกระดาษอย่างเดียว
     จึงไม่ @extends('layouts.app') แต่ยังต้อง include ธีมกลางกับตัวแปลภาษาเอง (กฎโปรเจค)

  🔴 ใช้ 2 ทาง
       forPdf = false  แสดงบนจอ มีแถบปุ่ม พิมพ์ / ดาวน์โหลด PDF
       forPdf = true   ส่งให้เบราว์เซอร์เบื้องหลังแปลงเป็นไฟล์ — ต้องไม่มีแถบปุ่มติดไปในไฟล์

  ตัวแปรที่ต้องส่งมา
    $docNo      เลขที่เอกสาร (โชว์บนแถบปุ่มและเป็นชื่อหน้า)
    $titleText  ชื่อชนิดเอกสารต่อท้ายชื่อหน้า
    $paperView  ชื่อ view ของตัวกระดาษ — ได้ตัวแปรของหน้าแม่ไปทั้งชุด
    $pdfUrl     ลิงก์ดาวน์โหลด PDF · null = ไม่ขึ้นปุ่ม (เครื่องนี้แปลงไฟล์ไม่ได้)
    $forPdf     กำลังแปลงเป็นไฟล์อยู่ไหม
--}}
@php
  // เรียกจากที่อื่นโดยไม่ส่งมา ให้ถือว่าเป็นการแสดงบนจอตามปกติ
  $forPdf = $forPdf ?? false;
  $pdfUrl = $pdfUrl ?? null;
@endphp

<title>{{ $docNo }} · {{ $titleText }}</title>

@include('layouts.theme')

<style>
  body { background: var(--bg); padding: 18px 16px 40px; }

  /*
    แถบปุ่มกว้างเท่ากระดาษพอดี (ใช้ .bar.is-paper ร่วมกับหน้าจอปกติ)
    🔴 ต้องระบุ 3 คลาสซ้อน เพราะ .bar.is-paper ตั้ง margin ย่อแบบสั้นไว้แล้ว จะทับด้วยคลาสเดียวไม่ได้
  */
  .bar.is-paper.prt-bar { margin-bottom: 14px; }
  .prt-bar .doc-no { font-weight: 700; color: var(--navy-800); }

  /*
    ── ระยะขอบกระดาษ ────────────────────────────────────────────
    🐛 บั๊กจริง 2026-09-10 (เจ้าของแจ้ง): ไฟล์ PDF ที่โหลดมา "ข้อมูลครบ"
       แต่พอเอาไปสั่งพิมพ์ ข้อมูลข้างล่างของหน้าแรกหายไป

       สาเหตุ: ขอบล่างของไฟล์แคบกว่า "ขอบที่เครื่องพิมพ์พิมพ์ไม่ได้" ของเครื่องนั้น
       บรรทัดท้ายหน้าจึงตกอยู่ในเขตที่พิมพ์ไม่ออก

    🔴 บทเรียนที่วัดมาแล้ว: Chrome ตอน --print-to-pdf **ไม่สนใจ @page margin
       ที่เล็กกว่าค่าเริ่มต้นของมันเอง (~10mm)** — ตั้ง 0 กับตั้ง 10mm ได้ผลเท่ากันเป๊ะ
       จะให้มีผลจริงต้องตั้ง "ใหญ่กว่า" ค่าเริ่มต้น (ทดสอบ: 16/15/18mm ทำให้จำนวนหน้าเปลี่ยนจริง)

       จึงเผื่อขอบล่างไว้ 18mm ให้เครื่องพิมพ์ทุกรุ่นพิมพ์ได้ครบ
  */
  @page { size: A4; margin: 16mm 15mm 18mm; }

  /*
    กฎตอนพิมพ์ — ใช้ทั้งตอนกดพิมพ์เองและตอนเซิร์ฟเวอร์แปลงเป็นไฟล์
    (เบราว์เซอร์ใช้ @media print ตอน --print-to-pdf เหมือนกัน จึงเขียนที่เดียวพอ)
  */
  @media print {
    body { background: var(--surface); padding: 0; }
    .prt-bar { display: none !important; }

    /* กระดาษเต็มหน้า ไม่มีกรอบ/เงา และไม่ต้องมี padding ของตัวเอง — ระยะขอบอยู่ที่ @page แล้ว */
    .paper {
      box-shadow: none; border: 0; margin: 0; padding: 0; max-width: none;
    }

    /* 🔴 ก้อนที่ห้ามถูกตัดครึ่งกลางหน้า — ให้ยกไปทั้งก้อนที่หน้าถัดไปแทน */
    .paper-row,
    .apv-doc-row,
    .sign-box,
    .file-row { break-inside: avoid; }

    /* 🔴 QR ห้ามถูกตัดครึ่ง ไม่งั้นสแกนไม่ออก */
    .paper-qr { break-inside: avoid; }

    /* หัวข้อคั่นห้ามค้างเดี่ยวๆ ท้ายหน้า โดยเนื้อหาไปโผล่หน้าถัดไป */
    .paper-sec { break-after: avoid; }

    /* ช่องลงชื่อแบ่งหน้าได้ แต่ขอให้เริ่มพร้อมหัวข้อของมัน */
    .sign-grid { break-inside: auto; }

    /* ข้อความยาวห้ามเหลือบรรทัดเดียวคาบเกี่ยวหน้า */
    p, dd { orphans: 3; widows: 3; }
  }

  @if ($forPdf)
    /*
      ── ตอนเซิร์ฟเวอร์แปลงเป็นไฟล์ PDF ─────────────────────────
      🔴 เขียนไว้นอก @media print ด้วย (เจ้าของแจ้ง 2026-09-10: ไฟล์ที่ได้ยังมีกรอบ)
         ไม่ฝากความหวังไว้กับ @media print อย่างเดียว — ถ้าเบราว์เซอร์ที่ใช้แปลง
         ไม่ได้ถือว่าเป็นการ "พิมพ์" กรอบกับพื้นเทาของหน้าจอจะติดลงไฟล์ไปด้วย

      พื้นต้องขาวล้วน · กระดาษต้องไม่มีกรอบ ไม่มีเงา ไม่มีมุมโค้ง
    */
    body { background: var(--surface); padding: 0; }

    .paper {
      box-shadow: none; border: 0; border-radius: 0;
      margin: 0; padding: 0; max-width: none; background: var(--surface);
    }

    .paper-row,
    .apv-doc-row,
    .sign-box,
    .file-row,
    .paper-qr { break-inside: avoid; }

    .paper-sec { break-after: avoid; }
  @endif
</style>

@unless ($forPdf)
  <div class="bar is-paper prt-bar">
    <span class="doc-no">{{ $docNo }}</span>
    <span class="spacer"></span>

    <button type="button" class="btn" id="btn-print" data-i18n="budget.print">พิมพ์</button>

    {{--
      🔴 ดาวน์โหลดเป็น "ลิงก์" ไม่ใช่ปุ่มที่เรียก window.print() (เจ้าของสั่ง 2026-09-10)
         กดแล้วเซิร์ฟเวอร์สร้างไฟล์ PDF ส่งกลับมาเลย ผู้ใช้ไม่ต้องเลือกปลายทางเอง
         เครื่องที่ไม่มีเบราว์เซอร์ให้แปลง จะไม่ขึ้นปุ่มนี้ — เหลือแค่ "พิมพ์"

      🔴 ต้องมี download คู่กับ data-download (เจ้าของแจ้ง 2026-09-16 ว่า "เหมือนมันจะค้างไปเลย")
         ลิงก์นี้ไม่ได้พาออกจากหน้า — เบราว์เซอร์แค่เซฟไฟล์แล้วอยู่หน้าเดิม
         จอ "กำลังโหลด" จึงขึ้นแล้วไม่มีอะไรมาปิด เพราะมันรอเหตุการณ์ pageshow
    --}}
    @if ($pdfUrl)
      <a class="btn btn-primary" download data-download href="{{ $pdfUrl }}"
         data-i18n="budget.downloadPdf">ดาวน์โหลด PDF</a>
    @endif
  </div>
@endunless

@include($paperView)

@include('budget.partials.assets')
@include('budget.partials.paper')

@unless ($forPdf)
  @include('layouts.partials.image-zoom')
  @include('layouts.partials.dialog')
  {{-- หน้านี้ไม่มีจอ "กำลังโหลด" (ไม่ได้ @extends layouts.app) แต่ยังต้องเด้งขีดถูกตอนโหลดเสร็จ --}}
  @include('layouts.partials.download')
  @include('layouts.i18n')

  <script>
    'use strict';

    // ปุ่ม "พิมพ์" = หน้าต่างพิมพ์ของเบราว์เซอร์ตามปกติ (ส่งเข้าเครื่องพิมพ์จริง)
    document.getElementById('btn-print').addEventListener('click', function () {
      window.print();
    });
  </script>
@endunless
