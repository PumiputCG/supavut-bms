<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * ตัวสร้าง QR Code เป็น SVG — เขียนเองทั้งตัว ไม่พึ่งไลบรารีและไม่พึ่งส่วนขยายของ PHP
 *
 * 🔴 ทำไมต้องเขียนเอง (ตัดสินใจ 2026-09-18 · DECISIONS 50.15)
 *
 *   ไลบรารี QR ที่นิยมออกภาพเป็น PNG ซึ่งต้องใช้ส่วนขยาย gd หรือ imagick
 *   **เครื่องนี้ปิดทั้ง 2 ตัว** และเซิร์ฟอยู่ในวงแลนจึงดึงตัวสร้าง QR จาก CDN ไม่ได้
 *   ทางที่เหลือคือวาดเป็น SVG ซึ่งเป็นข้อความล้วน — ฝังลงหน้าเว็บได้ตรงๆ
 *
 *   ผลพลอยได้ที่สำคัญ: SVG ที่ฝังในหน้า **ติดลงไฟล์ PDF เองโดยไม่ต้องโหลดอะไรเพิ่ม**
 *   (PdfPrinter ไม่ต้องไปตามฝังรูปให้ เพราะไม่มีไฟล์ภาพให้ตาม)
 *
 * 🔴 ขอบเขตที่ตั้งใจจำกัดไว้: โหมดไบต์ · ระดับแก้ความผิดพลาด M (15%) · เวอร์ชัน 1–5
 *
 *   เหตุผล: เวอร์ชัน 1–5 ของระดับ M **ทุกบล็อกมีขนาดเท่ากันหมด** (ไม่มีบล็อกสองขนาดปนกัน)
 *   การสลับเรียงโค้ดเวิร์ดจึงตรงไปตรงมาและตรวจสอบได้ง่าย ลดโอกาสวาด QR ที่เครื่องสแกนอ่านไม่ออก
 *   ความจุสูงสุด 84 ตัวอักษร ซึ่งพอสำหรับลิงก์ติดตามเอกสารของเรา (~75 ตัว)
 *   ต้องการยาวกว่านี้ให้ขึ้นเวอร์ชันพร้อมเติมตาราง SPECS + ALIGN ให้ครบก่อน
 *
 *   เลือกระดับ M ไม่ใช่ L เพราะ QR นี้ถูก **พิมพ์ลงกระดาษ** แล้วอาจถูกถ่ายเอกสารต่อ
 *   มีเนื้อที่แก้ความผิดพลาด 15% จึงทนหมึกจางกว่า
 */
final class QrCode
{
    /**
     * ตารางของแต่ละเวอร์ชันที่รองรับ — ระดับ M เท่านั้น
     * [โค้ดเวิร์ดทั้งหมด, โค้ดเวิร์ดข้อมูล, จำนวนบล็อก]
     *
     * ตรวจยันกับความจุที่ประกาศในมาตรฐาน: ตัวอักษรสูงสุด = โค้ดเวิร์ดข้อมูล − 2
     * (หัวข้อมูล 12 บิต = 1.5 ไบต์) → 14 · 26 · 42 · 62 · 84 ซึ่งตรงกับตารางความจุของ ISO
     */
    private const SPECS = [
        1 => [26, 16, 1],
        2 => [44, 28, 1],
        3 => [70, 44, 1],
        4 => [100, 64, 2],
        5 => [134, 86, 2],
    ];

    /** จุดกลางของลายจัดแนว (alignment) ของแต่ละเวอร์ชัน — เวอร์ชัน 1 ไม่มี */
    private const ALIGN = [1 => null, 2 => 18, 3 => 22, 4 => 26, 5 => 30];

    /** บิตของระดับแก้ความผิดพลาด M ในข้อมูลรูปแบบ (format information) */
    private const EC_BITS_M = 0b00;

    /** ไบต์เติมเต็มสลับกันตามมาตรฐาน เมื่อข้อมูลสั้นกว่าความจุ */
    private const PAD = [0xEC, 0x11];

    /** ตารางลอการิทึมของ GF(256) — สร้างครั้งเดียวต่อคำขอ */
    private static ?array $exp = null;

    private static ?array $log = null;

    /**
     * วาด QR เป็น SVG พร้อมใช้
     *
     * @param  string  $text  ข้อมูลที่จะเก็บใน QR (ปกติคือลิงก์)
     * @param  float  $mm  ความกว้าง/สูงของภาพเป็นมิลลิเมตร
     *                     🔴 คิดเป็นมิลลิเมตรไม่ใช่พิกเซล เพราะ QR นี้ต้องสแกนติดจาก "กระดาษจริง"
     *                     ขนาดบนจอไม่ได้บอกอะไรเลยว่าพิมพ์ออกมาแล้วจะอ่านได้ไหม
     * @param  string  $label  ข้อความอ่านด้วยเครื่อง (alt/aria) — ผู้ใช้ที่ใช้โปรแกรมอ่านหน้าจอต้องรู้ว่านี่คืออะไร
     */
    public static function svg(string $text, float $mm = 22.0, string $label = ''): string
    {
        $m = self::matrix($text);
        $n = count($m);

        /*
          เขตเงียบ (quiet zone) 4 ช่องรอบด้าน — มาตรฐานบังคับ
          🔴 ห้ามตัดออกเพื่อประหยัดที่ เพราะ QR ที่ติดขอบกระดาษ/ติดเส้นตาราง เครื่องสแกนหาขอบไม่เจอ
        */
        $q = 4;
        $side = $n + $q * 2;

        // รวมช่องทึบทั้งหมดเป็น path เดียว — ไฟล์เล็กกว่าการวาด <rect> ทีละช่องหลายร้อยชิ้น
        $d = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $d .= 'M'.($x + $q).' '.($y + $q).'h1v1h-1z';
                }
            }
        }

        $size = rtrim(rtrim(number_format($mm, 2, '.', ''), '0'), '.');
        $alt = $label !== '' ? '<title>'.e($label).'</title>' : '';

        /*
          shape-rendering: crispEdges — กันขอบช่องเบลอตอนย่อ/ขยาย ซึ่งทำให้สแกนพลาด
          พื้นขาวต้องวาดเอง ไม่พึ่งพื้นของกระดาษ เพราะถ้าวันหนึ่งพื้นเปลี่ยนสี QR จะอ่านไม่ออกทันที
        */
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$side.' '.$side.'"'
            .' width="'.$size.'mm" height="'.$size.'mm" role="img" shape-rendering="crispEdges">'
            .$alt
            .'<rect width="'.$side.'" height="'.$side.'" fill="#fff"/>'
            .'<path d="'.$d.'" fill="#000"/>'
            .'</svg>';
    }

    /**
     * ตารางช่องทึบ/ช่องโปร่งของ QR (ยังไม่มีเขตเงียบ)
     *
     * @return array<int,array<int,bool>> [$y][$x] = true คือช่องทึบ
     */
    public static function matrix(string $text): array
    {
        $version = self::versionFor($text);
        [$total, $dataLen, $blocks] = self::SPECS[$version];

        $codewords = self::interleave(self::dataCodewords($text, $version), $total, $dataLen, $blocks);

        // วางลายประจำตำแหน่งก่อน แล้วค่อยหยอดข้อมูลลงช่องที่เหลือ
        $size = 21 + ($version - 1) * 4;
        [$m, $fixed] = self::skeleton($size, $version);
        self::placeData($m, $fixed, $codewords, $size);

        // ลองทั้ง 8 หน้ากาก แล้วเลือกตัวที่ "อ่านง่ายที่สุด" ตามเกณฑ์ปรับโทษของมาตรฐาน
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $try = self::applyMask($m, $fixed, $size, $mask);
            self::placeFormat($try, $mask, $size);
            $score = self::penalty($try, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $try;
            }
        }

        return $best;
    }

    /** เวอร์ชันที่เล็กที่สุดที่ใส่ข้อความนี้ได้ */
    private static function versionFor(string $text): int
    {
        $len = strlen($text);

        foreach (self::SPECS as $v => [$total, $dataLen, $blocks]) {
            // หัวข้อมูล 12 บิต (โหมด 4 + ความยาว 8) จึงเหลือใช้จริง dataLen − 2 ไบต์
            if ($len <= $dataLen - 2) {
                return $v;
            }
        }

        throw new InvalidArgumentException(
            'ข้อความยาว '.$len.' ตัว เกินความจุ 84 ตัวของ QR ที่ระบบนี้รองรับ'
            .' / Payload too long for the supported QR versions (max 84 bytes)'
        );
    }

    /** แปลงข้อความเป็นโค้ดเวิร์ดข้อมูล (รวมหัวข้อมูลและไบต์เติมเต็มแล้ว) */
    private static function dataCodewords(string $text, int $version): array
    {
        [, $dataLen] = self::SPECS[$version];

        // 0100 = โหมดไบต์ · ความยาว 8 บิต (ใช้ได้ถึงเวอร์ชัน 9)
        $bits = '0100'.sprintf('%08b', strlen($text));
        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $bits .= sprintf('%08b', ord($text[$i]));
        }

        // ตัวปิดท้าย 4 บิต แล้วเติมศูนย์ให้ครบไบต์
        $room = $dataLen * 8;
        $bits .= str_repeat('0', min(4, $room - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);

        $out = [];
        foreach (str_split($bits, 8) as $byte) {
            $out[] = bindec($byte);
        }

        // เติมไบต์มาตรฐานสลับกันจนเต็มความจุ
        for ($i = 0; count($out) < $dataLen; $i++) {
            $out[] = self::PAD[$i % 2];
        }

        return $out;
    }

    /**
     * สลับเรียงโค้ดเวิร์ดข้อมูลกับโค้ดเวิร์ดแก้ความผิดพลาดตามมาตรฐาน
     *
     * 🔴 มาตรฐานบังคับให้ "สลับข้ามบล็อก" ไม่ใช่ต่อกันเป็นก้อนๆ
     *    เพื่อให้รอยเปื้อนที่กินติดกันหลายช่อง กระจายความเสียหายไปหลายบล็อก แทนที่จะพังยับบล็อกเดียว
     */
    private static function interleave(array $data, int $total, int $dataLen, int $blocks): array
    {
        $per = intdiv($dataLen, $blocks);
        $ecLen = intdiv($total - $dataLen, $blocks);

        $dataBlocks = [];
        $ecBlocks = [];
        for ($b = 0; $b < $blocks; $b++) {
            $chunk = array_slice($data, $b * $per, $per);
            $dataBlocks[] = $chunk;
            $ecBlocks[] = self::ecc($chunk, $ecLen);
        }

        $out = [];
        for ($i = 0; $i < $per; $i++) {
            foreach ($dataBlocks as $block) {
                $out[] = $block[$i];
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    /** โค้ดเวิร์ดแก้ความผิดพลาด (Reed–Solomon) ของบล็อกหนึ่ง */
    private static function ecc(array $block, int $ecLen): array
    {
        self::gf();
        $gen = self::generator($ecLen);
        $rest = array_merge($block, array_fill(0, $ecLen, 0));

        // หารยาวในสนามจำกัด GF(256) — เศษที่ได้คือโค้ดเวิร์ดแก้ความผิดพลาด
        for ($i = 0, $n = count($block); $i < $n; $i++) {
            $lead = $rest[$i];
            if ($lead === 0) {
                continue;
            }
            $factor = self::$log[$lead];
            foreach ($gen as $j => $g) {
                $rest[$i + $j] ^= self::$exp[($g + $factor) % 255];
            }
        }

        return array_slice($rest, count($block));
    }

    /** พหุนามตัวสร้างของ Reed–Solomon ในรูป "เลขชี้กำลัง" ของแต่ละสัมประสิทธิ์ */
    private static function generator(int $ecLen): array
    {
        $poly = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $c) {
                $next[$j] ^= $c;
                $next[$j + 1] ^= self::mul($c, self::$exp[$i]);
            }
            $poly = $next;
        }

        return array_map(fn ($c) => self::$log[$c], $poly);
    }

    /** คูณในสนามจำกัด GF(256) */
    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /** สร้างตารางยกกำลัง/ลอการิทึมของ GF(256) — พหุนามลดทอน 0x11D ตามมาตรฐาน QR */
    private static function gf(): void
    {
        if (self::$exp !== null) {
            return;
        }

        self::$exp = array_fill(0, 256, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
    }

    /**
     * วางลายประจำตำแหน่งทั้งหมด (ลายค้นหา · เส้นจังหวะ · ลายจัดแนว · ช่องทึบบังคับ)
     *
     * @return array{0: array<int,array<int,bool>>, 1: array<int,array<int,bool>>}
     *                                                                             [ตารางช่อง, ตารางบอกว่าช่องไหน "ห้ามแตะ" (ไม่ใช่ช่องข้อมูล)]
     */
    private static function skeleton(int $size, int $version): array
    {
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $fixed = $m;

        $put = function (int $x, int $y, bool $dark) use (&$m, &$fixed, $size) {
            if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
                return;
            }
            $m[$y][$x] = $dark;
            $fixed[$y][$x] = true;
        };

        // ลายค้นหา 3 มุม + แถบคั่นสีขาวรอบตัว
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$ox, $oy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $inside = $x >= 0 && $x <= 6 && $y >= 0 && $y <= 6;
                    $ring = $x === 0 || $x === 6 || $y === 0 || $y === 6;
                    $core = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                    $put($ox + $x, $oy + $y, $inside && ($ring || $core));
                }
            }
        }

        // เส้นจังหวะ — ทึบสลับโปร่งตลอดแถวที่ 6 และคอลัมน์ที่ 6
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;
            $put($i, 6, $dark);
            $put(6, $i, $dark);
        }

        // ลายจัดแนว (เวอร์ชัน 2 ขึ้นไป) — เวอร์ชัน 2–6 มีจุดเดียวที่มุมขวาล่าง
        if (self::ALIGN[$version] !== null) {
            $c = self::ALIGN[$version];
            for ($y = -2; $y <= 2; $y++) {
                for ($x = -2; $x <= 2; $x++) {
                    $edge = abs($x) === 2 || abs($y) === 2;
                    $put($c + $x, $c + $y, $edge || ($x === 0 && $y === 0));
                }
            }
        }

        /*
          จองที่ของข้อมูลรูปแบบไว้ก่อน (ค่าจริงเติมทีหลังเมื่อรู้หน้ากากที่เลือก)

          🔴 ต้องข้ามช่อง (6,8) และ (8,6) เพราะ 2 ช่องนั้นเป็น "เส้นจังหวะ" ไม่ใช่ที่ของข้อมูลรูปแบบ
             พลาดข้อนี้แล้วเส้นจังหวะขาดไป 1 ช่อง ซึ่งเครื่องสแกนใช้เส้นนี้นับตำแหน่งช่อง
             (เจอตอนเทสต์รอบแรก: m[6][8] กลายเป็นโปร่งทั้งที่ต้องทึบ)
        */
        for ($i = 0; $i <= 8; $i++) {
            if ($i === 6) {
                continue;
            }
            $put($i, 8, false);
            $put(8, $i, false);
        }
        for ($i = 0; $i < 8; $i++) {
            $put($size - 1 - $i, 8, false);
        }
        for ($i = 0; $i < 7; $i++) {
            $put(8, $size - 1 - $i, false);
        }

        // ช่องทึบบังคับ (dark module) — มาตรฐานกำหนดตำแหน่งนี้ไว้ตายตัว
        $put(8, $size - 8, true);

        return [$m, $fixed];
    }

    /**
     * หยอดโค้ดเวิร์ดลงช่องข้อมูล
     *
     * เดินเป็นคู่คอลัมน์จากขวาไปซ้าย สลับขึ้น-ลง และ **ข้ามคอลัมน์ที่ 6** ซึ่งเป็นเส้นจังหวะ
     */
    private static function placeData(array &$m, array $fixed, array $codewords, int $size): void
    {
        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= sprintf('%08b', $cw);
        }

        $i = 0;
        $up = true;
        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5;   // คอลัมน์ 6 เป็นเส้นจังหวะ ขยับคู่คอลัมน์ไปทางซ้ายหนึ่งช่อง
            }
            for ($step = 0; $step < $size; $step++) {
                $y = $up ? $size - 1 - $step : $step;
                foreach ([$right, $right - 1] as $x) {
                    if ($fixed[$y][$x]) {
                        continue;
                    }
                    $m[$y][$x] = ($bits[$i] ?? '0') === '1';
                    $i++;
                }
            }
            $up = ! $up;
        }
    }

    /** ใส่หน้ากากให้ช่องข้อมูล (ลายประจำตำแหน่งไม่ถูกแตะ) */
    private static function applyMask(array $m, array $fixed, int $size, int $mask): array
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($fixed[$y][$x] || ! self::maskBit($mask, $x, $y)) {
                    continue;
                }
                $m[$y][$x] = ! $m[$y][$x];
            }
        }

        return $m;
    }

    /** สูตรหน้ากากทั้ง 8 แบบตามมาตรฐาน */
    private static function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
            7 => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
        };
    }

    /** เขียนข้อมูลรูปแบบ (ระดับแก้ความผิดพลาด + หน้ากาก) ลงตำแหน่งที่จองไว้ */
    private static function placeFormat(array &$m, int $mask, int $size): void
    {
        $bits = sprintf('%015b', self::formatBits($mask));

        for ($i = 0; $i < 15; $i++) {
            $dark = $bits[14 - $i] === '1';   // บิตที่ 0 คือบิตขวาสุด

            // สำเนาชุดที่ 1 — รอบลายค้นหามุมซ้ายบน
            if ($i < 6) {
                $m[8][$i] = $dark;
            } elseif ($i === 6) {
                $m[8][7] = $dark;
            } elseif ($i === 7) {
                $m[8][8] = $dark;
            } elseif ($i === 8) {
                $m[7][8] = $dark;
            } else {
                $m[14 - $i][8] = $dark;
            }

            // สำเนาชุดที่ 2 — กระจายไว้อีกฝั่ง เพื่อให้เสียหายด้านเดียวยังอ่านรูปแบบได้
            if ($i < 8) {
                $m[8][$size - 1 - $i] = $dark;
            } else {
                $m[$size - 15 + $i][8] = $dark;
            }
        }
    }

    /**
     * ข้อมูลรูปแบบ 15 บิต = 5 บิตข้อมูล + 10 บิต BCH แล้ว XOR ด้วยค่าคงที่ 0x5412
     *
     * 🔴 ค่า XOR นี้มาตรฐานกำหนดไว้ เพื่อกันกรณี "ข้อมูลรูปแบบเป็นศูนย์ทั้งหมด"
     *    ซึ่งจะทำให้มุมนั้นว่างเปล่าจนเครื่องสแกนแยกไม่ออกว่าเป็น QR
     */
    public static function formatBits(int $mask): int
    {
        $data = (self::EC_BITS_M << 3) | $mask;
        $bch = $data << 10;

        for ($i = 4; $i >= 0; $i--) {
            if ($bch & (1 << ($i + 10))) {
                $bch ^= 0x537 << $i;   // พหุนามตัวสร้าง BCH(15,5)
            }
        }

        return (($data << 10) | $bch) ^ 0x5412;
    }

    /** คะแนนปรับโทษ 4 ข้อของมาตรฐาน — ยิ่งน้อยยิ่งอ่านง่าย */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;

        // ข้อ 1: ช่องสีเดียวกันติดกัน 5 ช่องขึ้นไป
        for ($i = 0; $i < $size; $i++) {
            foreach ([true, false] as $isRow) {
                $run = 1;
                for ($j = 1; $j < $size; $j++) {
                    $a = $isRow ? $m[$i][$j - 1] : $m[$j - 1][$i];
                    $b = $isRow ? $m[$i][$j] : $m[$j][$i];
                    if ($a === $b) {
                        $run++;

                        continue;
                    }
                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }
                    $run = 1;
                }
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
            }
        }

        // ข้อ 2: บล็อกสีเดียวกันขนาด 2×2
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $v = $m[$y][$x];
                if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // ข้อ 3: ลายที่ดูเหมือนลายค้นหา (1:1:3:1:1 ตามด้วยที่ว่าง 4 ช่อง)
        $target = [true, false, true, true, true, false, true];
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j <= $size - 7; $j++) {
                foreach ([true, false] as $isRow) {
                    $seq = [];
                    for ($k = 0; $k < 7; $k++) {
                        $seq[] = $isRow ? $m[$i][$j + $k] : $m[$j + $k][$i];
                    }
                    if ($seq !== $target) {
                        continue;
                    }
                    $before = true;
                    $after = true;
                    for ($k = 1; $k <= 4; $k++) {
                        $p = $j - $k;
                        $q = $j + 6 + $k;
                        if ($p >= 0 && ($isRow ? $m[$i][$p] : $m[$p][$i])) {
                            $before = false;
                        }
                        if ($q < $size && ($isRow ? $m[$i][$q] : $m[$q][$i])) {
                            $after = false;
                        }
                    }
                    if ($before || $after) {
                        $score += 40;
                    }
                }
            }
        }

        // ข้อ 4: สัดส่วนช่องทึบห่างจาก 50% มากเกินไป
        $dark = 0;
        foreach ($m as $row) {
            $dark += count(array_filter($row));
        }
        $ratio = $dark * 100 / ($size * $size);
        $score += intdiv((int) abs($ratio - 50), 5) * 10;

        return $score;
    }
}
