{{--
  สไตล์ของหน้าผลเปรียบเทียบ — ใช้ร่วม 2 แท็บ (เจ้าของสั่งแยกแท็บ 2026-09-17)
    core/partials/compare-view          แท็บงบประมาณตามงวดงบ
    core/partials/expense-compare-view  แท็บค่าใช้จ่ายตามวันที่
  🔴 @once — แต่ละหน้าใช้แค่ 1 ใน 2 อยู่แล้ว แต่กันไว้ไม่ให้ซ้ำถ้าวันหนึ่งถูก include ทั้งคู่
--}}
@once
<style>
  /*
    ── หัวเรื่องโหมดเปรียบเทียบ — อยู่ในการ์ดเดียวกับปุ่มถอยกลับ (เจ้าของสั่ง 2026-09-16) ──
  */
  .cv-head {
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 14px; padding: 14px 16px 16px;
  }

  /* ปุ่มย้อนกลับมุมซ้ายบน — ไม่มีกรอบ (เจ้าของสั่ง 2026-09-16) เหลือแค่ลูกศร */
  .cv-back {
    display: grid; place-items: center; width: 30px; height: 30px; flex: 0 0 auto;
    margin-top: 2px;
    margin-left: -6px; border: 0; border-radius: var(--radius-sm);
    background: transparent; color: var(--navy-800); text-decoration: none;
  }
  .cv-back:hover { background: var(--surface-2); }

  /* ชื่อรายงาน + บรรทัดสรุป อยู่ในก้อนเดียว ปุ่มเปรียบเทียบจึงยังถูกดันไปชิดขวาได้ */
  .cv-title { display: grid; gap: 8px; min-width: 0; }
  .cv-head h1 { margin: 0; min-width: 0; color: var(--navy-900); font-size: var(--fs-lg); }

  /* ชนิดการเทียบ — น้ำหนักปกติ ให้ตัวหนาอยู่ที่ชื่อรายงานอย่างเดียว */
  .cv-kind { color: var(--ink-soft); font-weight: 400; }

  /* ── การ์ดของแต่ละฝั่ง ── */
  .cv-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }

  /*
    🔴 หัวการ์ดพื้นน้ำเงิน ตัวอักษรขาว (เจ้าของสั่ง 2026-09-16)
       ชุดสีเดียวกับหัวตารางของระบบ — ไม่ได้คิดสีใหม่ ใช้ token เดิม
  */
  .cv-panel .card-head {
    background: var(--navy-800); color: #fff;
    border-bottom-color: var(--navy-900);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  }
  .cv-panel .card-head b { color: #fff; }

  /* หัวข้อย่อยในการ์ด */
  .cv-sub {
    margin: 0 0 8px; color: var(--muted);
    font-size: var(--fs-xs); font-weight: 700; letter-spacing: .03em;
  }
  .cv-sub + .cv-sub, .cv-rows + .cv-sub { margin-top: 16px; }

  /* คู่ "หัวข้อ: ค่า" — หัวข้อกว้างเท่ากันทุกบรรทัด ค่าจึงเริ่มตรงกันทั้ง 2 ฝั่ง */
  .cv-rows { display: grid; gap: 7px; margin: 0; }
  .cv-row { display: grid; grid-template-columns: 86px minmax(0, 1fr); gap: 10px; align-items: baseline; }
  .cv-row > dt { color: var(--muted); font-size: var(--fs-sm); }
  .cv-row > dd { margin: 0; color: var(--ink); font-size: var(--fs-sm); font-weight: 600; }

  /*
    สรุปการใช้งบ — ประโยคเดียวใต้ชื่อรายงาน ที่เดียวทั้งหน้า (เจ้าของสั่ง 2026-09-17)
    🔴 ตัวใหญ่ขึ้นและเว้นบรรทัดกว้าง เพราะเป็นประโยคยาวที่มีตัวเลข 3 ตัวปนอยู่
       ถ้าตัวเล็กและบรรทัดชิด ตัวเลขจะกลืนกับข้อความจนอ่านไม่ออก (เจ้าของแจ้ง)
  */
  .cv-usage {
    margin: 6px 0 0; padding: 10px 13px;
    border-radius: var(--radius-sm); background: var(--surface-2);
    color: var(--ink-soft); font-size: var(--fs-md); line-height: 2;
  }
  .cv-usage-head { margin-right: 4px; color: var(--navy-900); }
  /* คำเทียบ (น้อยกว่า / มากกว่า) — ตัวหนา เพราะเป็นใจความของประโยค (เจ้าของสั่ง) */
  .cv-usage-word { color: var(--navy-900); font-weight: 700; }

  /* ตัวเลขเน้นให้สายตาจับได้กลางประโยค — เว้นซ้ายขวากันไม่ให้ติดข้อความ */
  .cv-usage-rate {
    margin: 0 3px; color: var(--navy-900);
    font-size: var(--fs-lg); font-variant-numeric: tabular-nums;
  }
  /* ฝั่งที่ใช้งบน้อยกว่า = เขียว · มากกว่า = แดง (กฎเดียวกับ .cv-dir) */
  .cv-usage-rate.good { color: var(--ok); }
  .cv-usage-rate.bad { color: var(--danger); }

  /*
    🔴🔴 สีของอัตราการใช้งบ "กลับด้าน" กับแถวยอดเงิน — ตั้งใจ (เจ้าของสั่ง 2026-09-17)

       แถวยอดเงิน (งบประมาณ · ใช้ไป · รอตัดจ่าย · คงเหลือ) : มากกว่า = เขียว · น้อยกว่า = แดง
       แถวอัตราการใช้งบ                                    : **ใช้น้อยกว่า = เขียว · ใช้มากกว่า = แดง**

       เพราะระบบนี้คือ budget control คำถามหลักคือ "อยู่ในกรอบงบไหม"
       ใช้งบน้อยกว่า = อยู่ในกรอบ = ดี · ใช้มากกว่า = เข้าใกล้/ทะลุกรอบ = ต้องระวัง
       และตรงกับภาษาสีที่แดชบอร์ดใช้อยู่แล้ว (`ใช้ไป` แดง · `คงเหลือ` เขียว)

       🔴 ห้ามยุบ 2 กฎนี้เป็นกฎเดียว — คนละหน่วย คนละคำถาม
          ยอดเงินตอบ "เยอะขึ้นหรือน้อยลง" · อัตราตอบ "คุมงบได้ดีขึ้นหรือแย่ลง"
  */
  .cv-dir { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--ink-soft); }
  .cv-dir.good { color: var(--ok); }
  .cv-dir.bad { color: var(--danger); }

  /*
    🔴 ใช้งบเกิน 100% = ทะลุกรอบงบ ต้องแดงเสมอ **ไม่ว่าจะน้อยกว่าอีกฝั่งหรือไม่**
       เพราะ "แย่น้อยกว่าอีกฝั่ง" ไม่ได้แปลว่าไม่แย่ — ข้อเท็จจริงที่ว่าเกินงบสำคัญกว่าการเทียบ
  */
  .cv-usage-rate.is-over { color: var(--danger); }

  /* แถว "สรุป" ในตาราง — เส้นคั่นหนาบอกว่าเป็นค่าอีกชนิด (% ไม่ใช่บาท) */
  .cv-sum-row td {
    border-top: 2px solid var(--line);
    background: var(--surface-2); color: var(--navy-900); font-weight: 700;
  }

  /* อัตราของฝั่งนี้ = ตัวเลขหลักของแถว จึงใหญ่กว่ายอดเงินแถวอื่นหนึ่งขั้น */
  /* 🔴 ตัวเลขในแถวสรุป "ไม่ตัวหนา" (เจ้าของสั่ง) — ตัวหนาเหลือไว้ที่ชื่อแถวอย่างเดียว */
  .cv-sum-rate { font-size: var(--fs-md); font-weight: 600; font-variant-numeric: tabular-nums; }
  .cv-sum-rate.good { color: var(--ok); }
  .cv-sum-rate.bad { color: var(--danger); }

  .cv-count {
    margin: 12px 0 0; padding-top: 10px; border-top: 1px solid var(--line-soft);
    color: var(--muted); font-size: var(--fs-xs);
  }

  /* ── ตัวเลขในตารางของแต่ละฝั่ง ── */
  .cv-amt { font-variant-numeric: tabular-nums; }

  /*
    % เขียนต่อท้ายจำนวนเงินในช่องเดียวกัน (เจ้าของสั่ง 2026-09-16)
    🔴 ช่อง % กว้างคงที่ทุกแถว เพื่อให้ "ยอดเงิน" ของทุกแถวจบที่ตำแหน่งเดียวกัน
       (เจ้าของแจ้ง 2026-09-17) — แถวที่ไม่มี % เช่นรอตัดจ่าย ยอดจะไหลไปชิดขวาคนเดียว
       ถ้าไม่จองที่ไว้ · จองด้วย flex-basis ไม่ใช่คอลัมน์ใหม่ เพราะเจ้าของสั่งให้ % อยู่ช่องเดียวกับยอด
  */
  .cv-cell { display: flex; justify-content: flex-end; gap: 6px; }
  .cv-cell .cv-pct, .cv-cell .cv-pct-slot { flex: 0 0 8.5em; text-align: right; }
  .cv-pct { font-weight: 700; font-variant-numeric: tabular-nums; }
  .cv-pct.good { color: var(--ok); }
  .cv-pct.bad { color: var(--danger); }
  .cv-pct.flat { color: var(--muted); }
  /* แถวที่ไม่ตัดสินดี/ไม่ดี (รอตัดจ่าย) — ตัวอักษรสีดำเหมือนยอดเงินทั่วไป */
  .cv-pct.plain { color: var(--ink); }

  /* มีฝั่งที่ไม่มีข้อมูลเลย — ต้องบอกตรงๆ ไม่ใช่ปล่อยให้อ่าน −100% */
  .cv-empty {
    margin-bottom: 14px; padding: 10px 13px; border-radius: var(--radius-sm);
    background: var(--warn-soft); color: var(--ink-soft); font-size: var(--fs-sm);
  }

  /* ช่วงที่ยังไม่จบ — เตือนเรื่อง "เวลา" ไม่ใช่ "ข้อมูลหาย" จึงมีแถบซ้ายกำกับให้แยกออกจากแถบข้างบน */
  .cv-partial { border-left: 4px solid var(--warn); }

  @media (max-width: 720px) {
    .cv-pair { grid-template-columns: minmax(0, 1fr); }
  }

  /*
    มือถือ — ตารางต้องพอดีกรอบ ไม่ใช่ล้นแล้วให้เลื่อนหา
    🔴 ตัวที่ทำให้กว้างเกินคือช่อง % ที่จองไว้ตายตัว 8.5em (106px) **ทุกแถว**
       ช่องนี้มีไว้ให้ยอดเงินของทุกแถวชิดขวาตรงกัน (เจ้าของสั่งไว้เมื่อ 17 ก.ย.)
       ซึ่งบนจอกว้างคุ้ม แต่บนมือถือมันกินไปหนึ่งในสามของตาราง จนคอลัมน์สุดท้ายหลุดกรอบ
       วัดจริงที่ 390px ก่อนแก้: กรอบ 317px · ตาราง 417px
    บนมือถือจึงย้าย % ลงมาอยู่ **ใต้** จำนวนเงินแทน — ยอดยังชิดขวาตรงกันทุกแถวเหมือนเดิม
  */
  @media (max-width: 560px) {
    .cv-cell { display: grid; justify-items: end; gap: 0; }
    /* ชนะกฎ flex: 0 0 8.5em ข้างบนด้วยน้ำหนัก (0,3,0) ไม่ต้องใช้ !important */
    .cv-panel .cv-cell .cv-pct { flex: none; }
    /* ช่องเปล่าที่จองไว้เฉยๆ ไม่มีหน้าที่แล้วเมื่อ % ไม่ได้อยู่บรรทัดเดียวกับยอด */
    .cv-panel .cv-cell .cv-pct-slot { display: none; }
    .cv-panel .tbl th, .cv-panel .tbl td { padding-left: 7px; padding-right: 7px; }
  }
</style>
@endonce
