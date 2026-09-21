{{--
  หมดเวลาเชื่อมต่อ — เด้งหน้าต่างบอกผู้ใช้ แทนที่จะเงียบแล้วเด้งไปหน้าล็อกอินเฉยๆ

  🔴 ที่มา (เจ้าของแจ้ง 2026-09-18): "มันจะล็อคเอ้าออกเองแล้วไม่ได้บอก"
     `SESSION_LIFETIME=120` คือ **120 นาทีนับจากคำขอครั้งล่าสุด** ไม่ใช่นับตั้งแต่ล็อกอิน
     เปิดหน้าค้างไว้เกิน 2 ชั่วโมงแล้วกดอะไรสักอย่าง = เด้งไปหน้าล็อกอินโดยไม่มีคำอธิบาย

  🔴 นับเวลาด้วย "เวลาจริง" (Date.now) ไม่ใช่ setTimeout ยาวๆ ตัวเดียว
     เพราะมือถือหยุดเดินเวลาให้แท็บที่อยู่เบื้องหลัง และคอมที่ sleep ก็หยุดเหมือนกัน
     ตัวจับเวลาจะเพี้ยนเสมอ · เทียบเวลาจริงตอนกลับมาดูหน้าจึงตรงกว่า

  🔴 ทุกคำขอที่วิ่งออกจากหน้านี้ = เซิร์ฟเวอร์ต่ออายุ session ให้ใหม่
     จึงต้องรีเซ็ตเวลานับถอยหลังด้วย ไม่งั้นจะเด้งทั้งที่ยังใช้งานได้อยู่
--}}
@php
  // แปลงเป็นหน่วยที่คนอ่านรู้เรื่อง — 120 นาที ควรอ่านว่า "2 ชั่วโมง" ไม่ใช่ "120 นาที"
  $ttlMinutes = max(1, (int) config('session.lifetime'));
  $useHours = $ttlMinutes >= 60 && $ttlMinutes % 60 === 0;
@endphp

<script>
  'use strict';

  (function () {
    var LIFETIME = {{ $ttlMinutes }} * 60 * 1000;
    var AMOUNT = {{ $useHours ? intdiv($ttlMinutes, 60) : $ttlMinutes }};
    var UNIT_KEY = '{{ $useHours ? 'common.hours' : 'common.minutes' }}';
    var LOGIN_URL = @json(route('login'));

    // เผื่อเวลาให้ฝั่งเซิร์ฟเวอร์หมดอายุก่อนจริงๆ — เด้งก่อนเวลาแล้วผู้ใช้ยังใช้งานได้อยู่ = แจ้งผิด
    var GRACE = 10000;
    var EVERY = 15000;

    var deadline = Date.now() + LIFETIME + GRACE;
    var shown = false;

    // มีคำขอวิ่งออกไป = เซิร์ฟเวอร์ต่ออายุให้แล้ว เริ่มนับใหม่
    var bump = function () { deadline = Date.now() + LIFETIME + GRACE; };

    var show = function () {
      if (shown) { return; }
      shown = true;

      var span = AMOUNT + ' ' + window.BMS.t(UNIT_KEY);

      window.BMS.confirm({
        kind: 'warn',
        title: window.BMS.t('session.expiredTitle'),
        text: window.BMS.t('session.expiredText').replace(':t', span),
        ok: window.BMS.t('session.signInAgain'),
        cancel: window.BMS.t('common.close'),
        onOk: function () { window.location.href = LOGIN_URL; },
      });
    };

    var check = function () { if (Date.now() >= deadline) { show(); } };

    window.setInterval(check, EVERY);

    // กลับมาดูแท็บนี้อีกครั้ง (หรือเครื่องตื่นจาก sleep) — เช็คทันที ไม่ต้องรอรอบถัดไป
    document.addEventListener('visibilitychange', function () {
      if (! document.hidden) { check(); }
    });
    window.addEventListener('focus', check);

    /*
      ต่ออายุตามคำขอจริงที่หน้านี้ยิงออกไป (บันทึกสิทธิ์ · ตัวเลือกตัวกรอง · ดาวน์โหลด · อ่านแจ้งเตือน)
      🔴 ครอบทั้ง fetch และ XMLHttpRequest — ในระบบใช้ทั้ง 2 แบบ
    */
    var realFetch = window.fetch;
    if (realFetch) {
      window.fetch = function () {
        return realFetch.apply(this, arguments).then(function (res) { bump(); return res; },
          function (err) { bump(); throw err; });
      };
    }

    var realSend = window.XMLHttpRequest && window.XMLHttpRequest.prototype.send;
    if (realSend) {
      window.XMLHttpRequest.prototype.send = function () {
        this.addEventListener('loadend', bump);

        return realSend.apply(this, arguments);
      };
    }
  })();
</script>
