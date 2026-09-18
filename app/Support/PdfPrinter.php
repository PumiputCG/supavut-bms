<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * แปลงหน้า HTML ของระบบให้เป็นไฟล์ PDF
 *
 * 🔴 ทำไมใช้ "เบราว์เซอร์ที่ลงไว้ในเครื่อง" ไม่ใช่ไลบรารี PDF (ตัดสินใจ 2026-09-10)
 *
 *   เจ้าของสั่งว่ากดแล้วต้องได้ไฟล์เลย ไม่ใช่เด้งหน้าต่างพิมพ์ให้เลือกเอง
 *   ทางเลือกที่มีคือ
 *
 *     mPDF / DomPDF   ต้องใช้ส่วนขยาย gd ซึ่ง "ปิดอยู่" ใน php.ini ของเครื่องนี้
 *                     และต้องลง package ใหม่ ~10 MB
 *     เบราว์เซอร์      มี Chrome กับ Edge อยู่ในเครื่องแล้ว ไม่ต้องลงอะไรเพิ่ม
 *                     ไม่ต้องแตะ php.ini และผลลัพธ์ "ตรงกับที่เห็นบนจอเป๊ะ"
 *                     ตรวจแล้วฝังฟอนต์ไทยให้ครบ สระ/วรรณยุกต์ไม่เพี้ยน
 *
 *   จึงเลือกทางหลัง · ถ้าเครื่องไหนไม่มีเบราว์เซอร์ ให้ตั้ง BMS_PDF_BROWSER ใน .env
 *   หรือระบบจะถอยไปใช้หน้าต่างพิมพ์ของเบราว์เซอร์ตามเดิม
 *
 * 🔴 หน้า HTML ถูกเขียนลงไฟล์แล้วเปิดด้วย file:// — ไม่ต้องยิงกลับเข้าเว็บตัวเอง
 *    จึงไม่ต้องแบก session/สิทธิ์เข้าไปในเบราว์เซอร์ที่รันเบื้องหลัง
 */
final class PdfPrinter
{
    /** ที่อยู่ที่เบราว์เซอร์มักถูกติดตั้งไว้บน Windows */
    private const CANDIDATES = [
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
    ];

    /** เครื่องนี้แปลง PDF ได้ไหม — หน้าจอใช้ตัวนี้ตัดสินว่าจะโชว์ปุ่มดาวน์โหลดไหม */
    public static function available(): bool
    {
        return self::browser() !== null;
    }

    /** ที่อยู่ของเบราว์เซอร์ที่จะใช้ — ตั้งเองใน .env ได้ */
    public static function browser(): ?string
    {
        $set = (string) config('bms.pdf.browser');

        if ($set !== '') {
            return is_file($set) ? $set : null;
        }

        foreach (self::CANDIDATES as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * แปลง HTML เป็นไฟล์ PDF แล้วคืน path ของไฟล์ที่สร้าง
     *
     * ผู้เรียกมีหน้าที่ลบไฟล์ทิ้งเอง (ใช้ deleteFileAfterSend ของ response ได้)
     *
     * @throws RuntimeException เมื่อไม่มีเบราว์เซอร์ หรือแปลงไม่สำเร็จ
     */
    public static function fromHtml(string $html, string $name = 'document'): string
    {
        $browser = self::browser();

        if ($browser === null) {
            throw new RuntimeException('ไม่พบเบราว์เซอร์สำหรับสร้าง PDF ในเครื่องนี้ / No browser available to build the PDF');
        }

        $dir = storage_path('app/pdf');

        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException('สร้างโฟลเดอร์พักไฟล์ PDF ไม่ได้ / Cannot create the PDF working folder');
        }

        $slug = Str::slug($name) ?: 'document';
        $stamp = $slug.'-'.Str::random(8);

        $htmlFile = $dir.DIRECTORY_SEPARATOR.$stamp.'.html';
        $pdfFile = $dir.DIRECTORY_SEPARATOR.$stamp.'.pdf';

        /*
          🔴 โปรไฟล์ของเบราว์เซอร์แยกต่อหนึ่งครั้ง แล้วลบทิ้งเสมอ
             (บทเรียนเดิมของโปรเจค: เคยสร้างโฟลเดอร์โปรไฟล์ค้างไว้ 75 อัน กิน 1.4 GB)
        */
        $profile = $dir.DIRECTORY_SEPARATOR.'chrome-'.$stamp;

        file_put_contents($htmlFile, self::inlineAssets($html));

        try {
            $process = new Process([
                $browser,
                '--headless=new',
                '--disable-gpu',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--user-data-dir='.$profile,
                '--print-to-pdf='.$pdfFile,
                /*
                  ไม่เอาหัว/ท้ายกระดาษของเบราว์เซอร์ (ชื่อหน้า · URL · วันที่ · เลขหน้า)
                  🔴 ส่งทั้ง 2 ชื่อ เพราะ Chrome เปลี่ยนชื่อธงนี้ระหว่างเวอร์ชัน
                     ธงที่รุ่นนั้นไม่รู้จักจะถูกมองข้ามเฉยๆ ไม่พัง
                */
                '--print-to-pdf-no-header',
                '--no-pdf-header-footer',
                // รอให้รูปกับฟอนต์โหลดเสร็จก่อนพิมพ์ ไม่งั้นได้กระดาษเปล่า
                '--virtual-time-budget=10000',
                'file:///'.str_replace('\\', '/', $htmlFile),
            ]);

            $process->setTimeout((float) config('bms.pdf.timeout', 60));
            $process->run();

            if (! is_file($pdfFile) || filesize($pdfFile) === 0) {
                throw new RuntimeException('สร้างไฟล์ PDF ไม่สำเร็จ / Building the PDF failed: '.trim($process->getErrorOutput()));
            }

            return $pdfFile;
        } catch (ProcessTimedOutException) {
            throw new RuntimeException('สร้างไฟล์ PDF นานเกินไป / Building the PDF timed out');
        } finally {
            @unlink($htmlFile);
            self::removeTree($profile);
        }
    }

    /**
     * ฝังรูปในเครื่องลงไปในหน้า HTML เป็น data URI
     *
     * 🐛 บั๊กจริง 2026-09-10 (เจ้าของแจ้ง): ไฟล์ PDF ที่ได้ "ไม่มีรูปเลย"
     *
     *    หน้าเอกสารอ้างรูปด้วย asset() ซึ่งเป็นที่อยู่ http ของเว็บตัวเอง
     *    พอเบราว์เซอร์เบื้องหลังเปิดหน้าจากไฟล์ แล้ววิ่งกลับมาโหลดรูปจากเว็บ
     *    🔴 ถ้าเว็บรันด้วย `php artisan serve` ซึ่งรับได้ทีละคำขอ มันกำลังยุ่งกับ
     *       คำขอ /pdf นี้อยู่ คำขอรูปจึงค้างจนหมดเวลา — ได้กระดาษที่ไม่มีรูป
     *
     *    แก้โดยอ่านไฟล์รูปจากดิสก์ตรงๆ แล้วฝังลงไปในหน้าเลย
     *    เบราว์เซอร์จึงไม่ต้องยิงกลับมาที่เว็บอีก (ลายเซ็นเป็น data URI อยู่แล้ว)
     */
    private static function inlineAssets(string $html): string
    {
        $html = self::inlineLocal($html);

        return self::inlineInsightPhotos($html);
    }

    /** รูปที่อยู่ในโฟลเดอร์ public ของ SBMS เอง — อ่านจากดิสก์ตรงๆ */
    private static function inlineLocal(string $html): string
    {
        // ที่อยู่เว็บของเราเอง เช่น http://127.0.0.1:8000 หรือ http://localhost/SBMS/public
        $base = rtrim(asset('/'), '/');

        if ($base === '') {
            return $html;
        }

        return preg_replace_callback(
            '~(src|href)="'.preg_quote($base, '~').'/([^"?#]+)(?:[^"]*)"~i',
            static function (array $m): string {
                $file = public_path(urldecode($m[2]));

                // ไม่ใช่ไฟล์รูป/ไม่มีอยู่จริง ปล่อยไว้ตามเดิม (เช่นลิงก์เอกสารแนบ)
                if (! is_file($file)) {
                    return $m[0];
                }

                $type = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    default => null,
                };

                if ($type === null) {
                    return $m[0];
                }

                return $m[1].'="data:'.$type.';base64,'.base64_encode((string) file_get_contents($file)).'"';
            },
            $html,
        ) ?? $html;
    }

    /**
     * รูปโปรไฟล์พนักงาน — อยู่ที่ Insight ไม่ใช่ในเครื่องเรา
     *
     * 🔴 รูปโปรไฟล์ถูกเสิร์ฟจาก Insight (`bms.insight_asset_url`) ไม่ใช่โฟลเดอร์ public ของ SBMS
     *    จึงอ่านจากดิสก์ไม่ได้ ต้องดึงผ่าน HTTP มาฝังเอง (เจ้าของแจ้ง 2026-09-10)
     *
     * 🔴 ดึงเฉพาะที่อยู่ของ Insight ที่ตั้งไว้ใน config เท่านั้น ไม่ไล่ดึงทุก URL ที่เจอ
     *    และตั้งเวลารอสั้นๆ — ต่อ Insight ไม่ติดก็แค่ไม่มีรูป เอกสารที่เหลือต้องออกมาปกติ
     */
    private static function inlineInsightPhotos(string $html): string
    {
        $base = rtrim((string) config('bms.insight_asset_url'), '/');

        if ($base === '') {
            return $html;
        }

        // รูปคนเดิมโผล่หลายที่ในเอกสารเดียว ดึงครั้งเดียวพอ
        $cache = [];

        return preg_replace_callback(
            '~(src|data-zoom-src)="('.preg_quote($base, '~').'/[^"]+)"~i',
            static function (array $m) use (&$cache): string {
                $url = $m[2];

                if (! array_key_exists($url, $cache)) {
                    $cache[$url] = self::fetchImage($url);
                }

                return $cache[$url] === null ? $m[0] : $m[1].'="'.$cache[$url].'"';
            },
            $html,
        ) ?? $html;
    }

    /** ดึงรูปมาเป็น data URI — คืน null เมื่อดึงไม่ได้หรือไม่ใช่รูป */
    private static function fetchImage(string $url): ?string
    {
        try {
            $res = Http::timeout(5)->get($url);

            if (! $res->successful()) {
                return null;
            }

            $type = strtolower((string) $res->header('Content-Type'));

            if (! str_starts_with($type, 'image/')) {
                return null;
            }

            return 'data:'.explode(';', $type)[0].';base64,'.base64_encode($res->body());
        } catch (\Throwable) {
            // ต่อ Insight ไม่ติด = ไม่มีรูป แต่เอกสารต้องออกมาได้ตามปกติ
            return null;
        }
    }

    /** ลบโฟลเดอร์โปรไฟล์ของเบราว์เซอร์ทิ้งทั้งต้น */
    private static function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
