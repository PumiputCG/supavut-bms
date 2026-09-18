{{--
  ดาวน์โหลดไฟล์แล้วเด้งหน้าต่างแจ้งผลกลางจอ (เจ้าของสั่ง 2026-09-16)

  🐛 ปัญหาเดิม: กด "ดาวน์โหลด PDF" แล้ว **ไฟล์มาจริง แต่จออนิเมชัน "กำลังโหลด" ค้างไปเลย**

     จอโหลดปิดตัวเองด้วยเหตุการณ์ pageshow ซึ่งเกิดเมื่อ "เปลี่ยนหน้าจริง" เท่านั้น
     แต่ลิงก์ที่เซิร์ฟเวอร์ตอบเป็นไฟล์ (Content-Disposition: attachment)
     เบราว์เซอร์แค่เซฟไฟล์แล้วอยู่หน้าเดิม — pageshow ไม่มีวันมา จอจึงค้างตลอดไป

  🔴 วิธีแก้ที่เลือก: ดึงไฟล์ด้วย fetch เอง จะได้ "รู้จริงว่าโหลดเสร็จเมื่อไหร่"
     แล้วเด้ง window.BMS.notify({ kind:'ok' }) ที่มีอนิเมชันขีดถูกตรงกลาง
     ตรงตามกฎโปรเจค "ทุก Action ต้องเด้งหน้าต่างแจ้งกลางจอ พร้อมอนิเมชัน"

     ทางที่ไม่เลือก: ปล่อยลิงก์ธรรมดาแล้วเด้งแจ้งทันทีที่กด
     เพราะตอนนั้นไฟล์ยังไม่มา ถ้าสร้าง PDF ล้มเหลวจะขึ้นว่าสำเร็จทั้งที่ไม่ได้ไฟล์ = โกหกผู้ใช้

  วิธีใช้:  <a href="..." download data-download>ดาวน์โหลด</a>
            🔴 คง download ไว้เสมอ เผื่อ JS ไม่ทำงาน ลิงก์จะยังเซฟไฟล์ได้ตามปกติ
               (และตัวจับลิงก์ของจอโหลดข้าม a[download] อยู่แล้ว จอจึงไม่ค้างซ้ำ)

  ต้องมี layouts.partials.dialog ในหน้าด้วย (ตัวเด้งหน้าต่าง)
--}}
@once
  <script>
    'use strict';
    (function () {
      /* ชื่อไฟล์ที่เซิร์ฟเวอร์บอกมา — ไม่มีก็ถอยไปใช้ชื่อท้าย URL */
      function nameFrom(res, url) {
        var cd = res.headers.get('Content-Disposition') || '';
        var star = /filename\*=UTF-8''([^;]+)/i.exec(cd);

        if (star) {
          try { return decodeURIComponent(star[1]); } catch (err) { /* ชื่อเพี้ยน ใช้ตัวสำรอง */ }
        }

        var plain = /filename="?([^";]+)"?/i.exec(cd);
        if (plain) { return plain[1]; }

        return (url.split('/').pop() || 'download').split('?')[0];
      }

      /*
        🔴 ค้างไว้ให้ผู้ใช้กดปิดเอง (เจ้าของสั่ง 2026-09-16) — ไม่หายเองใน 1.8 วินาที
           เพราะไฟล์ที่เซฟลงเครื่องไม่ได้เด้งขึ้นมาให้เห็น ถ้าข้อความหายไปเองด้วย
           ผู้ใช้จะไม่มีอะไรยืนยันเลยว่าโหลดสำเร็จหรือไม่
           (ฝั่งล้มเหลวก็ค้างเหมือนกัน ตามกติกาเดิมของระบบ "ข้อความผิดพลาดต้องค้างไว้ให้อ่าน")
      */
      function say(kind, key, fallback, note) {
        if (!window.BMS || !window.BMS.notify) { return; }

        window.BMS.notify({
          kind: kind,
          title: window.BMS.t(key, fallback),
          text: note || '',
          autoClose: false,
        });
      }

      function veil(on) {
        if (!window.BMS || !window.BMS.loading) { return; }
        on ? window.BMS.loading.show() : window.BMS.loading.hide();
      }

      /* เซฟก้อนข้อมูลลงเครื่อง — ต้องคืน object URL เสมอ ไม่งั้นหน่วยความจำค้างจนปิดแท็บ */
      function save(blob, name) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');

        a.href = url;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();

        window.setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
      }

      document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a[data-download]') : null;
        if (!link || e.defaultPrevented) { return; }
        // กดค้างปุ่มพิเศษ = ผู้ใช้ตั้งใจสั่งเบราว์เซอร์เอง อย่าไปขวาง
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        if (link.dataset.busy) { return; }

        e.preventDefault();
        link.dataset.busy = '1';
        veil(true);

        var url = link.href;

        // 🔴 ต้องส่ง cookie ไปด้วย ไม่งั้นเซิร์ฟเวอร์มองว่ายังไม่ล็อกอินแล้วเด้งไปหน้า login
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (res) {
            if (!res.ok) { throw new Error('http ' + res.status); }

            /*
              🔴 สร้างไฟล์ไม่สำเร็จ เซิร์ฟเวอร์จะ redirect กลับไปหน้าเดิมพร้อมข้อความ
                 fetch เดินตาม redirect ให้เอง ได้ 200 + HTML — ไม่ใช่ไฟล์
                 ถ้าไม่ดักตรงนี้ ผู้ใช้จะได้ไฟล์ .pdf ที่ข้างในเป็นหน้าเว็บ
            */
            var type = (res.headers.get('Content-Type') || '').toLowerCase();
            if (type.indexOf('text/html') === 0) { throw new Error('not a file'); }

            return res.blob().then(function (blob) { return { blob: blob, res: res }; });
          })
          .then(function (out) {
            var name = nameFrom(out.res, url);

            save(out.blob, name);
            veil(false);
            // บอกชื่อไฟล์ด้วย ผู้ใช้จะได้รู้ว่าต้องไปหาไฟล์ชื่ออะไรในโฟลเดอร์ดาวน์โหลด
            say('ok', 'common.downloaded', 'ดาวน์โหลดแล้ว', name);
          })
          .catch(function () {
            veil(false);
            say('no', 'common.downloadFailed', 'ดาวน์โหลดไม่สำเร็จ');
          })
          .then(function () { delete link.dataset.busy; });
      });
    }());
  </script>
@endonce
