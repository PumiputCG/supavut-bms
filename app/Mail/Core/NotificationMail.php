<?php

namespace App\Mail\Core;

use App\Models\Core\Notification;
use App\Support\OutboundLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * อีเมลของแจ้งเตือน 1 ใบ — ใช้ร่วมทุกโมดูล
 *
 * 🔴 ไทย + อังกฤษอยู่ในฉบับเดียวกันเสมอ (กฎโปรเจค)
 *    อีเมลรัน JS ไม่ได้ จึงไม่มีตัวสลับภาษาให้กด และเราไม่รู้ว่าผู้รับตั้งภาษาอะไรไว้
 *
 * 🔴 ไม่ใส่จำนวนเงิน (เจ้าของสั่ง 2026-09-21)
 *    เมลออกไปนอกระบบ และอีเมลที่พนักงานกรอกไว้ส่วนใหญ่เป็นกล่องจดหมายส่วนตัว
 *
 * 🔴 ประกอบทุกบรรทัดให้เสร็จที่นี่ แล้วหน้าเมลแค่วาด (กฎ "ตรรกะไม่อยู่ใน Blade")
 */
class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * ป้ายกำกับ 2 ภาษาของบรรทัดในแจ้งเตือน
     *
     * 🔴 พจนานุกรมของระบบเป็น JavaScript ท้ายหน้า ซึ่งอีเมลเอาไปใช้ไม่ได้
     *    นี่จึงเป็นที่เดียวที่จำเป็นต้องมีคำแปลฝั่ง PHP — **ต้องตรงกับ i18n.blade.php เสมอ**
     * 🔴 เพิ่มคีย์ป้ายใหม่ให้แจ้งเตือนเมื่อไหร่ ต้องมาเพิ่มที่นี่ด้วย
     *    (มีเทสต์คุมว่าคีย์ที่โมดูลใช้จริงต้องมีครบในนี้)
     *
     * @var array<string,array{th:string,en:string}>
     */
    public const LABELS = [
        'budget.dept' => ['th' => 'แผนก', 'en' => 'Department'],
        'budget.investNo' => ['th' => 'เลขที่ Invest', 'en' => 'Invest no.'],
        'budget.budgetNo' => ['th' => 'เลขที่ Budget', 'en' => 'Budget no.'],
        'budget.reasonShort' => ['th' => 'เหตุผล', 'en' => 'Reason'],
    ];

    public function __construct(
        public Notification $note,
        public string $toName,
        public string $toNameEn = '',
    ) {
        /*
          🔴 ไม่มีชื่ออังกฤษ = ถอยไปใช้ชื่อไทย ห้ามปล่อยว่าง (กฎ 2 ภาษาของโปรเจค)
             ทำให้จบที่นี่ที่เดียว หน้าเมลจะได้ไม่ต้องเขียนเงื่อนไขซ้ำ
        */
        $this->toNameEn = trim($toNameEn) !== '' ? trim($toNameEn) : $toName;
    }

    public function envelope(): Envelope
    {
        /*
          หัวเรื่องต้องอ่านออกตั้งแต่ในรายการกล่องจดหมาย โดยไม่ต้องเปิดเข้าไปอ่าน
          รูปแบบ: [SBMS] <เลขที่เอกสาร> · <สิ่งที่ต้องทำ ไทย / อังกฤษ>
        */
        $parts = array_values(array_filter([
            trim((string) $this->note->doc_no),
            trim(trim((string) $this->note->body_th).' / '.trim((string) $this->note->body_en), ' /'),
        ], fn ($p) => $p !== ''));

        return new Envelope(
            subject: Str::limit('[SBMS] '.implode(' · ', $parts), 180, ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'rows' => $this->rows(),
                // null ได้ — หน้าเมลจะไม่วาดปุ่ม ดีกว่าพาผู้รับไปที่อยู่ที่เปิดไม่ได้
                'link' => OutboundLink::route($this->note->route_name, $this->note->route_param),
            ],
        );
    }

    /**
     * บรรทัดข้อมูลในเมล — เรียงแบบเดียวกับแผงกระดิ่ง จะได้อ่านแล้วนึกออกว่าคือใบไหน
     *
     * @return array<int,array{label_th:string,label_en:string,th:string,en:string}>
     */
    private function rows(): array
    {
        $n = $this->note;

        $rows = [];
        $this->push($rows, ['th' => 'เลขที่เอกสาร', 'en' => 'Document no.'], $n->doc_no, $n->doc_no);
        $this->push($rows, self::LABELS[$n->subject_key] ?? null, $n->title_th, $n->title_en);
        $this->push($rows, ['th' => 'แจ้งเตือน', 'en' => 'Notification'], $n->body_th, $n->body_en);
        $this->push($rows, self::LABELS[$n->note_key] ?? null, $n->note_th, $n->note_en);
        $this->push($rows, self::LABELS[$n->note2_key] ?? null, $n->note2_th, $n->note2_en);

        return $rows;
    }

    /**
     * เติม 1 บรรทัด — ไม่มีค่าก็ไม่ต้องวาดแถวเปล่า
     *
     * 🔴 ไม่รู้จักคีย์ป้าย = ปล่อยป้ายว่าง ห้ามโชว์คีย์ดิบให้ผู้รับเห็น
     *
     * @param  array<int,array{label_th:string,label_en:string,th:string,en:string}>  $rows
     * @param  array{th:string,en:string}|null  $label
     */
    private function push(array &$rows, ?array $label, ?string $th, ?string $en): void
    {
        $th = trim((string) $th);
        $en = trim((string) $en);

        if ($th === '' && $en === '') {
            return;
        }

        $rows[] = [
            'label_th' => $label['th'] ?? '',
            'label_en' => $label['en'] ?? '',
            'th' => $th !== '' ? $th : $en,
            'en' => $en !== '' ? $en : $th,
        ];
    }
}
