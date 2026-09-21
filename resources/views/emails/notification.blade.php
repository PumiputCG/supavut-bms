{{--
  อีเมลแจ้งเตือนของระบบ — ใช้ร่วมทุกโมดูล

  🔴 ข้อยกเว้นเดียวของกฎ "ห้ามเขียนค่าสีดิบในหน้า": ที่นี่เขียนเป็นเลขฐานสิบหกตรงๆ ได้
     เพราะโปรแกรมอ่านอีเมลไม่รองรับ CSS variables และหลายตัวตัดบล็อก <style> ทิ้งทั้งก้อน
     ค่าที่ใช้ **คัดลอกมาจาก token ของ theme.blade.php ให้ตรงกัน ห้ามคิดสีใหม่เอง**
       #12306b = --navy-800   #0d2350 = --navy-900   #16233d = --ink
       #6b7a94 = --muted      #dde4ef = --line       #f7f9fc = --surface-2

  🔴 ทุกอย่างเป็น inline style + ตาราง ไม่ใช่ flex/grid
     Outlook บนเดสก์ท็อปวาดด้วยเครื่องมือของ Word ซึ่งไม่รู้จักผังสมัยใหม่เลย

  🔴 ต้องอ่านรู้เรื่องโดยไม่ต้องกดลิงก์ เพราะลิงก์เปิดได้เฉพาะในวงแลนบริษัท
--}}
<div style="margin:0;padding:24px 12px;background:#f7f9fc;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#16233d;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"
         style="width:100%;max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dde4ef;border-radius:10px;">

    {{-- หัวเมล: ชื่อย่อตัวใหญ่ + ชื่อเต็ม 2 ภาษา --}}
    <tr>
      <td style="padding:18px 22px;background:#12306b;border-radius:10px 10px 0 0;">
        <div style="font-size:19px;font-weight:700;color:#ffffff;letter-spacing:.5px;">SBMS</div>
        <div style="font-size:12px;color:#c7d6f0;padding-top:3px;line-height:1.5;">
          ระบบบริหารจัดการธุรกิจ สุภาวุฒิ<br>
          Supavut Business Management System
        </div>
      </td>
    </tr>

    <tr>
      <td style="padding:22px;">
        <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
          เรียน {{ $toName }}<br>
          <span style="color:#6b7a94;">Dear {{ $toNameEn }}</span>
        </p>

        {{-- บรรทัดข้อมูล — ป้ายซ้าย ค่าขวา เรียงแบบเดียวกับแผงกระดิ่ง --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;font-size:14px;">
          @foreach ($rows as $row)
            <tr>
              <td valign="top"
                  style="padding:9px 12px 9px 0;width:38%;color:#6b7a94;font-size:12px;line-height:1.5;border-bottom:1px solid #dde4ef;">
                @if ($row['label_th'] !== '')
                  {{ $row['label_th'] }}<br>{{ $row['label_en'] }}
                @endif
              </td>
              <td valign="top"
                  style="padding:9px 0;line-height:1.5;border-bottom:1px solid #dde4ef;">
                @if ($row['th'] === $row['en'])
                  {{ $row['th'] }}
                @else
                  {{ $row['th'] }}<br><span style="color:#6b7a94;">{{ $row['en'] }}</span>
                @endif
              </td>
            </tr>
          @endforeach
        </table>

        @if ($link)
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 0;">
            <tr>
              <td style="background:#12306b;border-radius:8px;">
                <a href="{{ $link }}"
                   style="display:inline-block;padding:12px 22px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;">
                  เปิดเอกสารในระบบ / Open in SBMS
                </a>
              </td>
            </tr>
          </table>

          {{-- เผื่อโปรแกรมอ่านเมลตัดปุ่มทิ้ง หรือผู้รับอยากคัดลอกที่อยู่ไปเปิดเอง --}}
          <p style="margin:10px 0 0;font-size:11px;line-height:1.5;color:#6b7a94;word-break:break-all;">
            {{ $link }}
          </p>

          <p style="margin:14px 0 0;font-size:12px;line-height:1.6;color:#97600f;">
            ลิงก์นี้เปิดได้เฉพาะเมื่ออยู่ในเครือข่ายของบริษัทเท่านั้น<br>
            This link only works while you are on the company network.
          </p>
        @endif
      </td>
    </tr>

    <tr>
      <td style="padding:14px 22px 18px;border-top:1px solid #dde4ef;font-size:11px;line-height:1.6;color:#6b7a94;">
        อีเมลฉบับนี้ส่งจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ<br>
        This is an automated message — please do not reply.
      </td>
    </tr>
  </table>
</div>
