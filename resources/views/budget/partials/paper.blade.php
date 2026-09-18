{{--
  กระดาษเอกสาร — หน้าตาแบบเอกสารจริงของบริษัท (เจ้าของสั่ง 2026-09-03)

  ใช้ทุกที่ที่เป็น "ตัวเอกสาร": สร้าง Invest · ดูเอกสาร · เซ็นอนุมัติ
  หัวกระดาษมีโลโก้บริษัทเสมอ · ท้ายกระดาษเป็นช่องลงชื่อ

  วิธีใช้
    @include('budget.partials.paper-open', ['title' => '...', 'docNo' => '...'])
      ...เนื้อเอกสาร...
    @include('budget.partials.paper-close')

  🔴 สีและระยะทั้งหมดอ่านจาก token ที่ layouts/theme.blade.php ห้ามเขียนค่าดิบ
--}}
<style>
  /* ── แผ่นกระดาษ ─────────────────────────────────────────── */
  .paper {
    max-width: var(--paper-max); margin: 0 auto; padding: 38px 46px 32px;
    border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface); box-shadow: var(--shadow);
  }

  /* ── หัวกระดาษ ──────────────────────────────────────────── */
  /*
    🔴 เส้นกั้นขยับขึ้นมาชิดเนื้อหา (เจ้าของสั่ง 2026-09-18)
       พอย้ายเลขที่เอกสารไปฝั่งซ้ายและคำกำกับ QR เหลือบรรทัดเดียว หัวกระดาษเตี้ยลง
       จึงลดระยะก่อนเส้นจาก 14px เหลือ 8px ให้เส้นไม่ลอยห่างจากของที่มันคั่น
  */
  .paper-head {
    display: flex; align-items: flex-start; gap: 16px;
    padding-bottom: 8px; border-bottom: 2px solid var(--navy-800);
  }

  .paper-logo { width: 58px; height: 58px; flex: 0 0 auto; object-fit: contain; }

  .paper-org { flex: 1; min-width: 0; display: grid; gap: 2px; }
  .paper-org b { color: var(--navy-900); font-size: var(--fs-md); font-weight: 700; }
  /*
    🔴 ต้องเจาะจงที่ชื่อบริษัทอังกฤษ ห้ามใช้กฎเหมา `.paper-org span`
       บทเรียนเดิมของโปรเจค: `.paper-org span` (0,1,1) ชนะ `.st-approved` (0,1,0)
       พอย้ายป้ายสถานะเข้ามาอยู่ใต้ชื่อบริษัท สีเขียว/แดงของป้ายจึงถูกทับเป็นสีเทา
       (เจ้าของแจ้ง 2026-09-18 ว่า "อนุมัติแล้ว" ไม่มีสี) · แก้ด้วยการเปลี่ยน selector ไม่ใช่เติม !important
  */
  .paper-org-en { color: var(--muted); font-size: var(--fs-sm); }

  /* เลขที่เอกสาร + ป้ายสถานะ อยู่บรรทัดเดียวกันใต้ชื่อบริษัท (เจ้าของสั่งย้ายมา 2026-09-18) */
  .paper-ids {
    display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;
    margin-top: 6px;
  }
  .paper-ids .doc-no { font-size: var(--fs-md); }

  /* เลขที่เอกสาร + สถานะ ชิดขวาบน เหมือนเอกสารจริง */
  .paper-meta { flex: 0 0 auto; text-align: right; display: grid; gap: 3px; }
  .paper-meta .doc-no { font-size: var(--fs-md); }

  /*
    QR ติดตามเอกสาร (มุมขวาบน · เจ้าของกำหนด 2026-09-18)
    🔴 กำหนดขนาดเป็น "มิลลิเมตร" ไม่ใช่พิกเซล เพราะ QR นี้ต้องสแกนติดจากกระดาษจริง
       22 มม. เป็นขนาดที่กล้องโทรศัพท์จับได้สบายในระยะถือปกติ และยังไม่กินที่หัวกระดาษ
    🔴 ห้ามใส่พื้นหลัง/กรอบสีทับ — ตัว SVG วาดพื้นขาวมาให้แล้ว และ QR ต้องมีที่ว่างรอบตัว
  */
  .paper-qr { justify-self: end; display: grid; justify-items: center; gap: 2px; }
  .paper-qr svg { width: 22mm; height: 22mm; display: block; }
  /* 🔴 คำกำกับต้องอยู่บรรทัดเดียว (เจ้าของสั่ง) จึงใช้คำสั้นและห้ามตัดบรรทัด */
  .paper-qr span { color: var(--muted); font-size: var(--fs-xs); white-space: nowrap; }

  /* ── ชื่อเรื่อง ──────────────────────────────────────────── */
  .paper-title {
    margin: 20px 0 18px; text-align: center;
    color: var(--navy-900); font-size: var(--fs-lg); font-weight: 700;
  }

  /* ── ตารางข้อมูลในเอกสาร ────────────────────────────────── */
  .paper-rows { display: grid; gap: 0; }

  .paper-row {
    display: grid; grid-template-columns: 190px 1fr; gap: 14px;
    padding: 9px 0; border-bottom: 1px dashed var(--line-soft);
  }

  .paper-row:last-child { border-bottom: 0; }
  .paper-row > dt { color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; }
  .paper-row > dd { margin: 0; color: var(--ink); }
  .paper-row > dd.strong { color: var(--navy-900); font-weight: 700; }
  .paper-row > dd.pre { white-space: pre-line; }

  /* ยอดเงินหลักของเอกสาร — ตัวหนา สีเขียว (เจ้าของสั่ง 2026-09-03) */
  .paper-amount {
    display: flex; align-items: baseline; gap: 8px;
    color: var(--ok); font-size: 21px; font-weight: 700;
    font-variant-numeric: tabular-nums;
  }
  .paper-amount small { color: var(--muted); font-size: var(--fs-sm); font-weight: 400; }

  /* ── หัวข้อคั่นในกระดาษ ─────────────────────────────────── */
  .paper-sec {
    margin: 22px 0 10px; padding-bottom: 5px;
    border-bottom: 1px solid var(--line);
    color: var(--navy-800); font-size: var(--fs-sm); font-weight: 700;
    letter-spacing: .02em;
  }

  /*
    ── สำเนาเรียน (ผู้อนุมัติตามลำดับ) ─────────────────────────
    🔴 ลำดับคือสายอนุมัติจริง จึงต้องมีเลขกำกับให้เห็นชัดว่าใครเซ็นก่อนหลัง
  */
  .apv-doc { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }

  .apv-doc-row {
    display: grid; grid-template-columns: 24px 30px 1fr;
    /* 🔴 ชิดบน ไม่ใช่กึ่งกลาง — แต่ละคนสูงไม่เท่ากันเพราะบางคนมีหมายเหตุ */
    align-items: start; gap: 10px;
    padding: 8px 2px;
    border-bottom: 1px dashed var(--line-soft);
  }

  .apv-doc-row:last-child { border-bottom: 0; }

  /*
    ── ข้อมูลของคนหนึ่งคน จัดเป็น 3 บรรทัดตายตัว (เจ้าของสั่งจัดใหม่ 2026-09-10) ──
       ชื่อ / สถานะ · วันที่ / หมายเหตุ — ไล่สายตาลงมาได้ ไม่กองรวมเป็นบรรทัดเดียว
  */
  .chip-body { display: grid; gap: 3px; min-width: 0; }

  .chip-name { color: var(--ink); font-size: var(--fs-sm); line-height: 1.45; }

  /* ตำแหน่ง · แผนก ต่อท้ายชื่อ — จางกว่าชื่อ เพื่อไม่แย่งสายตา */
  .chip-meta { color: var(--muted); font-size: var(--fs-xs); font-weight: 400; }

  /*
    🔴 สถานะกับหมายเหตุอยู่บรรทัดเดียวกัน และ "ตรงคอลัมน์กันทุกแถว" (เจ้าของสั่ง 2026-09-10)
       ใช้ grid ล็อกความกว้างคอลัมน์แรกไว้ ไม่ใช่ flex ที่ความกว้างเดินตามความยาวคำ
       (ไม่งั้นแถวที่ขึ้น "อนุมัติแล้ว" กับ "รออนุมัติ" หมายเหตุจะเริ่มไม่ตรงกัน)
  */
  .chip-state {
    display: grid; grid-template-columns: 82px minmax(0, 1fr);
    align-items: baseline; gap: 10px;
    font-size: var(--fs-xs);
  }

  /*
    หมายเหตุ — ป้ายจาง ตัวข้อความสีปกติ
    🔴 ไม่ใส่กรอบ/พื้นสี เพราะสีบอกผลอยู่ที่คำว่าสถานะแล้ว (เจ้าของสั่ง 2026-09-10)
  */
  .chip-note {
    min-width: 0;
    font-size: var(--fs-xs); line-height: 1.5;
    overflow-wrap: anywhere;
  }

  .chip-note-key { color: var(--muted); }
  .chip-note-val { color: var(--ink-soft); }

  .apv-doc-no {
    display: grid; place-items: center; width: 24px; height: 24px; border-radius: 50%;
    background: var(--navy-50); color: var(--navy-800);
    font-size: var(--fs-xs); font-weight: 700;
  }

  /* ── ช่องลงชื่อ ─────────────────────────────────────────── */
  /*
    🔴 แถวละไม่เกิน 5 ช่อง · แถวสุดท้ายที่ไม่เต็มต้องจัดกึ่งกลาง (เจ้าของสั่ง 2026-09-09)
       ใช้ flex ไม่ใช่ grid เพราะ grid จะดันช่องที่เหลือไปชิดซ้ายเสมอ จัดกึ่งกลางไม่ได้
       --sign-cols ตั้งจากหน้าที่เรียก = จำนวนคน แต่ไม่เกิน 5
  */
  .sign-grid {
    display: flex; flex-wrap: wrap; justify-content: center;
    gap: var(--sign-gap, 18px); margin-top: 8px;
  }

  .sign-grid > .sign-box {
    flex: 0 0 calc((100% - (var(--sign-cols, 5) - 1) * var(--sign-gap, 18px)) / var(--sign-cols, 5));
  }

  /* คนเยอะ = ย่อลง จะได้ไม่ดันกระดาษให้ยาว */
  .sign-grid.is-tight { --sign-gap: 12px; }
  .sign-grid.is-tight .sign-area { height: 54px; }
  .sign-grid.is-tight .sign-area img { max-height: 48px; }
  .sign-grid.is-tight .sign-name { font-size: var(--fs-xs); }

  .sign-box { display: grid; gap: 6px; justify-items: center; text-align: center; }

  /* กล่องลายเซ็น — ว่างไว้ให้เห็นว่ายังไม่เซ็น */
  .sign-area {
    width: 100%; height: 74px; display: grid; place-items: center;
    border-bottom: 1px solid var(--navy-800);
  }

  .sign-area img { max-height: 66px; max-width: 100%; object-fit: contain; }

  /* ยังไม่เซ็น — เว้นว่างไว้ ไม่ใส่ข้อความให้รก */
  .sign-area .sign-wait { color: var(--muted); font-size: var(--fs-xs); }

  .sign-name { color: var(--navy-900); font-size: var(--fs-sm); font-weight: 700; }
  .sign-role { color: var(--muted); font-size: var(--fs-xs); }
  .sign-date { color: var(--muted); font-size: var(--fs-xs); font-variant-numeric: tabular-nums; }

  /*
    คนที่ไม่อนุมัติ — ทั้งคำและวันเวลาเป็นสีแดง (เจ้าของสั่ง 2026-09-10)
    🔴 นิยามไว้ที่นี่ ไม่ยืม .state-off ของชิ้นส่วนเลือกคน
       เพราะหน้างบกับหน้าพิมพ์ไม่ได้ include ชิ้นส่วนนั้น สีจะหายเงียบๆ
  */
  .sign-date.is-no { color: var(--danger); font-weight: 700; }

  /*
    🔴 เส้นลงชื่อของคนที่ไม่อนุมัติเป็นสีแดง (เจ้าของถาม 2026-09-16)
       ตั้งแต่ reject ประทับลายเซ็นด้วย ช่องนี้จะมีลายเซ็นเหมือนคนที่อนุมัติ
       บนกระดาษที่พิมพ์ออกไป "ลายเซ็น" อ่านเป็นเห็นชอบโดยอัตโนมัติ
       จึงต้องมีตัวบอกที่ตัวช่องเอง ไม่ใช่รอให้อ่านบรรทัดวันที่ข้างล่าง
  */
  .sign-area.is-no { border-bottom-color: var(--danger); }

  /* ── รายชื่อผู้รับทราบ — ไม่มีช่องเซ็น เพราะเขาไม่ต้องเซ็น ── */
  /*
    คนหนึ่งคนในกระดาษ — รูปอยู่บรรทัดเดียวกับชื่อเสมอ (เจ้าของสั่ง 2026-09-04)
    🔴 ต้องนิยามไว้ที่นี่ ไม่ใช่ไปยืมคลาส .route-* ของเส้นทางเอกสาร
       เพราะหน้ากระดาษไม่ได้โหลดสไตล์ชุดนั้นมาด้วย รูปกับชื่อจะหล่นคนละบรรทัด
  */
  .paper-person { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
  .paper-person-name { color: var(--ink); }
  .paper-person-note { color: var(--muted); font-size: var(--fs-xs); }

  /* ── เอกสารแนบ ──────────────────────────────────────────── */
  .file-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }

  .file-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; border-radius: var(--radius-sm); background: var(--surface-2);
  }

  .file-ico { width: 20px; height: 20px; flex: 0 0 auto; object-fit: contain; }

  .file-name {
    flex: 1 1 auto; min-width: 0; color: var(--navy-900); font-weight: 600;
    text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  .file-name:hover { text-decoration: underline; }
  .file-size { flex: 0 0 auto; color: var(--muted); font-size: var(--fs-sm); }

  /* ปุ่มกากบาทเอาไฟล์ออก — ไม่ใส่กรอบตามกฎ */
  .file-x {
    flex: 0 0 auto; width: 26px; height: 26px; display: grid; place-items: center;
    border: 0; border-radius: var(--radius-sm); background: transparent;
    color: var(--muted); font-size: 13px; cursor: pointer;
    transition: background .14s var(--ease), color .14s var(--ease);
  }

  .file-x:hover { background: var(--danger-soft); color: var(--danger); }

  /* ── จอแคบ ──────────────────────────────────────────────── */
  @media (max-width: 720px) {
    .paper { padding: 24px 20px; }
    .paper-row { grid-template-columns: 1fr; gap: 2px; }
  }
</style>
