<?php

namespace App\Support;

/**
 * ไอคอนของไฟล์แนบ — ตัดมาจากแผ่นไอคอนที่เจ้าของให้มา (ไอคอนเอกสาร.png)
 *
 * 🔴 กติกาที่เจ้าของสั่ง 2026-09-03: ไฟล์แนบทุกที่ในระบบใช้ชุดไอคอนนี้เสมอ
 *    ไฟล์อยู่ที่ public/img/file/*.png — อย่าไปหาไอคอนจากที่อื่นมาปน
 *
 * นามสกุลที่ไม่มีไอคอนของตัวเอง จะถอยไปใช้ txt (กระดาษเปล่า) ไม่ปล่อยรูปแตก
 */
class FileIcon
{
    /** นามสกุล -> ชื่อไฟล์ไอคอน (ไม่ใส่ .png) */
    private const MAP = [
        'pdf' => 'pdf',
        'doc' => 'docx', 'docx' => 'docx', 'rtf' => 'docx', 'odt' => 'docx',
        'xls' => 'xlsx', 'xlsx' => 'xlsx', 'xlsm' => 'xlsx', 'ods' => 'xlsx',
        'ppt' => 'ppt', 'pptx' => 'ppt', 'odp' => 'ppt',
        'csv' => 'csv',
        'zip' => 'zip', 'rar' => 'zip', '7z' => 'zip', 'tar' => 'zip', 'gz' => 'zip',
        'jpg' => 'jpg', 'jpeg' => 'jpg', 'webp' => 'jpg', 'gif' => 'jpg', 'bmp' => 'jpg', 'heic' => 'jpg',
        'png' => 'png',
        'json' => 'json', 'xml' => 'json',
        'txt' => 'txt', 'log' => 'txt', 'md' => 'txt',
    ];

    /** ชื่อไอคอนของไฟล์นี้ — ส่งชื่อไฟล์เต็มหรือแค่นามสกุลมาก็ได้ */
    public static function name(?string $filename): string
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION) ?: (string) $filename);

        return self::MAP[$ext] ?? 'txt';
    }

    /** URL ของไอคอน พร้อมใส่ใน src ได้เลย */
    public static function url(?string $filename): string
    {
        return asset('img/file/'.self::name($filename).'.png');
    }

    /**
     * ตารางนามสกุล -> URL ไอคอน สำหรับส่งให้ JS
     *
     * ฝั่งหน้าเว็บเลือกไฟล์ใหม่แล้วต้องวาดไอคอนเองทันทีโดยยังไม่ส่งขึ้นเซิร์ฟเวอร์
     * จึงต้องมีตารางนี้ไว้ในหน้า ไม่ให้ JS ไปเดา path เอง
     *
     * @return array<string,string>
     */
    public static function urlMap(): array
    {
        $map = ['_default' => asset('img/file/txt.png')];

        foreach (self::MAP as $ext => $icon) {
            $map[$ext] = asset('img/file/'.$icon.'.png');
        }

        return $map;
    }
}
