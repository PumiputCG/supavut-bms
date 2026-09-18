<?php

namespace Tests\Unit;

use App\Support\QrCode;
use PHPUnit\Framework\TestCase;

/**
 * เทสต์ของตัววาด QR (App\Support\QrCode)
 *
 * 🔴 เทสต์ที่เช็คแค่ว่า "มี <svg> ในหน้า" ไม่ได้คุมอะไรเลย — QR ที่วาดผิดก็ยังมี <svg>
 *    ไฟล์นี้จึงตรวจ 4 ทางที่ "เป็นอิสระจากตัวเขียน" เท่าที่ทำได้ในเครื่องที่ไม่มีโปรแกรมอ่าน QR
 *
 *      ① ข้อมูลรูปแบบ (format info) ต้องตรงกับค่าคงที่ที่มาตรฐานประกาศไว้
 *      ② โค้ดเวิร์ดทุกบล็อกต้องหารด้วยพหุนามตัวสร้างลงตัว (คุณสมบัติของ Reed–Solomon)
 *      ③ อ่านตารางช่องกลับเป็นข้อความเดิมได้ ด้วยตัวอ่านที่เขียนแยกในไฟล์นี้
 *      ④ ลายประจำตำแหน่งอยู่ครบและถูกที่
 */
class QrCodeTest extends TestCase
{
    /**
     * ① ข้อมูลรูปแบบของระดับ M ทั้ง 8 หน้ากาก
     *
     * ค่าพวกนี้เป็นค่าคงที่ที่มาตรฐาน ISO/IEC 18004 ประกาศไว้เป็นตาราง
     * ถ้าคำนวณ BCH ของเราตรงกับตารางนี้ = ทั้ง 2 ทางยืนยันกันเอง (คนละที่มา)
     */
    public function test_format_information_matches_the_published_table(): void
    {
        $expected = [0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0];

        foreach ($expected as $mask => $want) {
            $this->assertSame($want, QrCode::formatBits($mask), 'หน้ากากที่ '.$mask);
        }
    }

    /**
     * ② คุณสมบัติของ Reed–Solomon: (ข้อมูล + โค้ดเวิร์ดแก้ความผิดพลาด) ต้องหารด้วยตัวสร้างลงตัว
     *
     * เป็นการตรวจแบบคณิตศาสตร์ ไม่ได้ลอกค่าจากตัวเขียนมาเทียบกับตัวเอง
     */
    public function test_error_correction_codewords_divide_cleanly(): void
    {
        $matrix = QrCode::matrix('http://192.168.7.12:8080/SBMS/public/track/'.str_repeat('a', 32));
        $codewords = $this->readCodewords($matrix);

        // เวอร์ชัน 5 (ขนาด 37) ระดับ M = 2 บล็อก · ข้อมูล 43 + แก้ความผิดพลาด 24 ต่อบล็อก
        $this->assertSame(37, count($matrix), 'ความยาว 75 ตัว ต้องได้เวอร์ชัน 5');
        $this->assertSame(134, count($codewords));

        foreach ($this->deinterleave($codewords, 134, 86, 2) as $i => $block) {
            $this->assertSame(
                array_fill(0, 24, 0),
                $this->remainder($block, 24),
                'บล็อกที่ '.$i.' หารด้วยพหุนามตัวสร้างไม่ลงตัว'
            );
        }
    }

    /** ③ อ่านตารางช่องกลับเป็นข้อความเดิมได้ — ครอบทุกเวอร์ชันที่รองรับ */
    public function test_the_matrix_reads_back_as_the_original_text(): void
    {
        $cases = [
            'SBMS/track/abc' => [1, 21],
            'http://192.168.7.12/t/ab' => [2, 25],
            'http://192.168.7.12:8080/t/9f2c8d71' => [3, 29],
            'http://192.168.7.12:8080/SBMS/public/track/9f2c' => [4, 33],
            'http://192.168.7.12:8080/SBMS/public/track/'.str_repeat('7', 32) => [5, 37],
        ];

        foreach ($cases as $text => [$version, $size]) {
            $matrix = QrCode::matrix($text);
            $this->assertSame($size, count($matrix), 'เวอร์ชัน '.$version.' ต้องกว้าง '.$size.' ช่อง');
            $this->assertSame($text, $this->decode($matrix), 'อ่านกลับไม่ตรงข้อความเดิม');
        }
    }

    /** ④ ลายประจำตำแหน่งอยู่ครบและถูกที่ — ไม่มีลายพวกนี้เครื่องสแกนหา QR ไม่เจอเลย */
    public function test_function_patterns_are_in_place(): void
    {
        $m = QrCode::matrix('http://192.168.7.12:8080/t/9f2c8d71');   // 35 ตัว = เวอร์ชัน 3
        $size = count($m);
        $this->assertSame(29, $size);

        // ลายค้นหา 3 มุม (ซ้ายบน · ขวาบน · ซ้ายล่าง) — มุมขวาล่างต้องไม่มี
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$ox, $oy]) {
            $this->assertTrue($m[$oy][$ox], 'ขอบนอกของลายค้นหาต้องทึบ');
            $this->assertFalse($m[$oy + 1][$ox + 1], 'วงในของลายค้นหาต้องโปร่ง');
            $this->assertTrue($m[$oy + 3][$ox + 3], 'ใจกลางของลายค้นหาต้องทึบ');
        }

        // เส้นจังหวะ — ทึบสลับโปร่ง
        for ($i = 8; $i < $size - 8; $i++) {
            $this->assertSame($i % 2 === 0, $m[6][$i], 'เส้นจังหวะแนวนอนที่ '.$i);
            $this->assertSame($i % 2 === 0, $m[$i][6], 'เส้นจังหวะแนวตั้งที่ '.$i);
        }

        // ช่องทึบบังคับ
        $this->assertTrue($m[$size - 8][8], 'ช่องทึบบังคับต้องอยู่ที่ (8, size-8)');

        // ลายจัดแนวของเวอร์ชัน 3 อยู่ที่ (22,22)
        $this->assertTrue($m[22][22], 'ใจกลางลายจัดแนวต้องทึบ');
        $this->assertFalse($m[21][22], 'วงในลายจัดแนวต้องโปร่ง');
    }

    /** SVG ที่ออกมาต้องมีเขตเงียบ 4 ช่องและกำหนดขนาดเป็นมิลลิเมตร */
    public function test_svg_has_a_quiet_zone_and_a_physical_size(): void
    {
        $svg = QrCode::svg('http://192.168.7.12:8080/t/9f2c8d71', 22.0, 'ติดตามเอกสาร');   // 35 ตัว = เวอร์ชัน 3
        $side = 29 + 8;   // เวอร์ชัน 3 = 29 ช่อง + เขตเงียบ 4 ช่องต่อด้าน

        $this->assertStringContainsString('viewBox="0 0 '.$side.' '.$side.'"', $svg);
        $this->assertStringContainsString('width="22mm" height="22mm"', $svg, 'ขนาดต้องเป็นมิลลิเมตร ไม่ใช่พิกเซล');
        $this->assertStringContainsString('<rect width="'.$side.'" height="'.$side.'" fill="#fff"/>', $svg, 'ต้องวาดพื้นขาวเอง');
        $this->assertStringContainsString('<title>ติดตามเอกสาร</title>', $svg, 'ต้องมีข้อความให้โปรแกรมอ่านหน้าจอ');
        $this->assertStringNotContainsString('<image', $svg, 'ห้ามมีไฟล์ภาพให้ต้องโหลด (ต้องติดลง PDF ได้เอง)');
    }

    /** ยาวเกินความจุต้องฟ้อง ไม่ใช่วาด QR ที่อ่านไม่ออกออกมาเงียบๆ */
    public function test_it_refuses_a_payload_that_does_not_fit(): void
    {
        $this->expectExceptionMessage('เกินความจุ');
        QrCode::svg(str_repeat('x', 85));
    }

    /*
    |---------------------------------------------------------------
    | ตัวอ่าน QR ที่เขียนแยกไว้ในเทสต์ — ตั้งใจไม่เรียกโค้ดของตัวเขียน
    |---------------------------------------------------------------
    */

    /** อ่านตารางช่องกลับเป็นข้อความ */
    private function decode(array $m): string
    {
        $codewords = $this->readCodewords($m);
        $size = count($m);
        $version = ($size - 21) / 4 + 1;
        [$total, $dataLen, $blocks] = [
            1 => [26, 16, 1], 2 => [44, 28, 1], 3 => [70, 44, 1], 4 => [100, 64, 2], 5 => [134, 86, 2],
        ][$version];

        // ต่อโค้ดเวิร์ดข้อมูลของทุกบล็อกกลับเป็นสายเดียว
        $data = [];
        foreach ($this->deinterleave($codewords, $total, $dataLen, $blocks) as $block) {
            $data = array_merge($data, array_slice($block, 0, intdiv($dataLen, $blocks)));
        }

        $bits = '';
        foreach ($data as $cw) {
            $bits .= sprintf('%08b', $cw);
        }

        $this->assertSame('0100', substr($bits, 0, 4), 'ต้องเป็นโหมดไบต์');
        $len = bindec(substr($bits, 4, 8));

        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= chr(bindec(substr($bits, 12 + $i * 8, 8)));
        }

        return $out;
    }

    /** อ่านโค้ดเวิร์ดทั้งหมดจากตาราง (ถอดหน้ากากด้วยข้อมูลรูปแบบที่อ่านจากตัว QR เอง) */
    private function readCodewords(array $m): array
    {
        $size = count($m);
        $fixed = $this->functionModules($size);
        $mask = $this->maskFromFormat($m, $size);

        $bits = '';
        $up = true;
        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($step = 0; $step < $size; $step++) {
                $y = $up ? $size - 1 - $step : $step;
                foreach ([$right, $right - 1] as $x) {
                    if ($fixed[$y][$x]) {
                        continue;
                    }
                    $v = $m[$y][$x];
                    if ($this->maskBit($mask, $x, $y)) {
                        $v = ! $v;
                    }
                    $bits .= $v ? '1' : '0';
                }
            }
            $up = ! $up;
        }

        $out = [];
        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $byte) {
            $out[] = bindec($byte);
        }

        return $out;
    }

    /** อ่านหน้ากากจากข้อมูลรูปแบบ — และเช็คว่าสำเนาทั้ง 2 ชุดตรงกัน */
    private function maskFromFormat(array $m, int $size): int
    {
        $first = '';
        for ($i = 14; $i >= 0; $i--) {
            $first .= $this->formatModule($m, $size, $i, 1) ? '1' : '0';
        }
        $second = '';
        for ($i = 14; $i >= 0; $i--) {
            $second .= $this->formatModule($m, $size, $i, 2) ? '1' : '0';
        }
        $this->assertSame($first, $second, 'ข้อมูลรูปแบบ 2 สำเนาต้องตรงกัน');

        $value = bindec($first) ^ 0x5412;
        $this->assertSame(0b00, $value >> 13 & 0b11, 'ระดับแก้ความผิดพลาดต้องเป็น M');

        return $value >> 10 & 0b111;
    }

    /** ตำแหน่งของบิตที่ $i ในข้อมูลรูปแบบ สำเนาชุดที่ 1 หรือ 2 */
    private function formatModule(array $m, int $size, int $i, int $copy): bool
    {
        if ($copy === 1) {
            if ($i < 6) {
                return $m[8][$i];
            }
            if ($i === 6) {
                return $m[8][7];
            }
            if ($i === 7) {
                return $m[8][8];
            }
            if ($i === 8) {
                return $m[7][8];
            }

            return $m[14 - $i][8];
        }

        return $i < 8 ? $m[8][$size - 1 - $i] : $m[$size - 15 + $i][8];
    }

    /** ช่องที่เป็นลายประจำตำแหน่ง (ห้ามนับเป็นข้อมูล) — คิดจากรูปทรงตรงๆ */
    private function functionModules(int $size): array
    {
        $version = ($size - 21) / 4 + 1;
        $fixed = array_fill(0, $size, array_fill(0, $size, false));
        $mark = function (int $x, int $y) use (&$fixed, $size) {
            if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
                $fixed[$y][$x] = true;
            }
        };

        // ลายค้นหา + แถบคั่น (8×8 ที่แต่ละมุม)
        foreach ([[0, 0], [$size - 8, 0], [0, $size - 8]] as [$ox, $oy]) {
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $mark($ox + $x, $oy + $y);
                }
            }
        }

        // เส้นจังหวะ
        for ($i = 0; $i < $size; $i++) {
            $mark($i, 6);
            $mark(6, $i);
        }

        // ที่ของข้อมูลรูปแบบ + ช่องทึบบังคับ
        for ($i = 0; $i <= 8; $i++) {
            $mark($i, 8);
            $mark(8, $i);
        }
        for ($i = 0; $i < 8; $i++) {
            $mark($size - 1 - $i, 8);
            $mark(8, $size - 1 - $i);
        }

        // ลายจัดแนว (เวอร์ชัน 2–5 มีจุดเดียว)
        $align = [1 => null, 2 => 18, 3 => 22, 4 => 26, 5 => 30][$version];
        if ($align !== null) {
            for ($y = -2; $y <= 2; $y++) {
                for ($x = -2; $x <= 2; $x++) {
                    $mark($align + $x, $align + $y);
                }
            }
        }

        return $fixed;
    }

    private function maskBit(int $mask, int $x, int $y): bool
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

    /** แยกโค้ดเวิร์ดที่สลับเรียงไว้กลับเป็นบล็อก (ข้อมูล + แก้ความผิดพลาด ต่อกันในแต่ละบล็อก) */
    private function deinterleave(array $codewords, int $total, int $dataLen, int $blocks): array
    {
        $per = intdiv($dataLen, $blocks);
        $ecLen = intdiv($total - $dataLen, $blocks);
        $out = array_fill(0, $blocks, []);

        $at = 0;
        for ($i = 0; $i < $per; $i++) {
            for ($b = 0; $b < $blocks; $b++) {
                $out[$b][$i] = $codewords[$at++];
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            for ($b = 0; $b < $blocks; $b++) {
                $out[$b][$per + $i] = $codewords[$at++];
            }
        }

        return $out;
    }

    /** เศษจากการหารด้วยพหุนามตัวสร้าง — ต้องเป็นศูนย์ทั้งหมดถ้าโค้ดเวิร์ดถูกต้อง */
    private function remainder(array $block, int $ecLen): array
    {
        [$exp, $log] = $this->gf();

        // พหุนามตัวสร้าง (x−α⁰)(x−α¹)…
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $c) {
                $next[$j] ^= $c;
                $next[$j + 1] ^= $c === 0 ? 0 : $exp[($log[$c] + $i) % 255];
            }
            $gen = $next;
        }

        $rest = $block;
        for ($i = 0, $n = count($block) - $ecLen; $i < $n; $i++) {
            $lead = $rest[$i];
            if ($lead === 0) {
                continue;
            }
            foreach ($gen as $j => $g) {
                if ($g !== 0) {
                    $rest[$i + $j] ^= $exp[($log[$g] + $log[$lead]) % 255];
                }
            }
        }

        return array_values(array_slice($rest, count($block) - $ecLen));
    }

    private function gf(): array
    {
        $exp = array_fill(0, 256, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        return [$exp, $log];
    }
}
