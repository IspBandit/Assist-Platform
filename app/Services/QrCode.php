<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Self-contained QR Code generator (byte mode, error-correction level M).
 *
 * A compact PHP port of Nayuki's reference QR Code algorithm. No external
 * libraries or network calls — output is a monochrome SVG suitable for
 * embedding as a `data:` URI (allowed by the site CSP) or printing.
 */
final class QrCode
{
    // Error-correction level M, indexed by version (1..40).
    private const ECC_CODEWORDS_PER_BLOCK = [
        10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26,
        26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28,
    ];
    private const NUM_ERROR_CORRECTION_BLOCKS = [
        1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16,
        17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49,
    ];

    private int $size;
    /** @var array<int,array<int,bool>> */
    private array $modules = [];
    /** @var array<int,array<int,bool>> */
    private array $isFunction = [];

    private function __construct(private int $version, array $dataCodewords)
    {
        $this->size = $version * 4 + 17;
        for ($y = 0; $y < $this->size; $y++) {
            $this->modules[$y] = array_fill(0, $this->size, false);
            $this->isFunction[$y] = array_fill(0, $this->size, false);
        }

        $this->drawFunctionPatterns();
        $allCodewords = $this->addEccAndInterleave($dataCodewords);
        $this->drawCodewords($allCodewords);

        $bestMask = $this->chooseMask();
        $this->applyMask($bestMask);
        $this->drawFormatBits($bestMask);
    }

    /** Build a QR code for the given text and return it as an SVG string. */
    public static function svg(string $text, int $border = 4, int $scale = 8): string
    {
        $qr = self::encodeText($text);
        $dim = ($qr->size + $border * 2) * $scale;

        $parts = [];
        for ($y = 0; $y < $qr->size; $y++) {
            for ($x = 0; $x < $qr->size; $x++) {
                if ($qr->modules[$y][$x]) {
                    $px = ($x + $border) * $scale;
                    $py = ($y + $border) * $scale;
                    $parts[] = "M{$px},{$py}h{$scale}v{$scale}h-{$scale}z";
                }
            }
        }
        $path = implode('', $parts);

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" '
            . 'viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<path d="' . $path . '" fill="#000000"/></svg>';
    }

    /** Return a data: URI for the QR SVG, embeddable in an <img> src. */
    public static function svgDataUri(string $text, int $border = 4, int $scale = 8): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $border, $scale));
    }

    // ---- Encoding ----------------------------------------------------------

    private static function encodeText(string $text): self
    {
        $bytes = array_values(unpack('C*', $text === '' ? "\0" : $text) ?: []);
        if ($text === '') {
            $bytes = [];
        }
        $dataLen = count($bytes);

        // Find the smallest version that fits the byte-mode segment at level M.
        for ($version = 1; $version <= 40; $version++) {
            $capacityBits = self::numDataCodewords($version) * 8;
            $charCountBits = $version <= 9 ? 8 : 16;
            $usedBits = 4 + $charCountBits + $dataLen * 8;
            if ($usedBits <= $capacityBits) {
                return self::build($version, $bytes, $charCountBits);
            }
        }
        throw new RuntimeException('Data too long for a QR code.');
    }

    /** @param array<int,int> $bytes */
    private static function build(int $version, array $bytes, int $charCountBits): self
    {
        $bits = [];
        self::appendBits($bits, 0x4, 4);                 // byte mode indicator
        self::appendBits($bits, count($bytes), $charCountBits);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }

        $dataCapacityBits = self::numDataCodewords($version) * 8;
        // Terminator and bit padding to a byte boundary.
        $terminator = min(4, $dataCapacityBits - count($bits));
        self::appendBits($bits, 0, $terminator);
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);

        // Byte padding with the standard alternating pattern.
        for ($pad = 0xEC; count($bits) < $dataCapacityBits; $pad ^= 0xEC ^ 0x11) {
            self::appendBits($bits, $pad, 8);
        }

        $codewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $codewords[] = $byte;
        }

        return new self($version, $codewords);
    }

    /** @param array<int,int> $bits */
    private static function appendBits(array &$bits, int $value, int $len): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function numDataCodewords(int $version): int
    {
        return intdiv(self::numRawDataModules($version), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$version - 1] * self::NUM_ERROR_CORRECTION_BLOCKS[$version - 1];
    }

    private static function numRawDataModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    // ---- Reed-Solomon error correction -------------------------------------

    /**
     * @param array<int,int> $data
     * @return array<int,int>
     */
    private function addEccAndInterleave(array $data): array
    {
        $version = $this->version;
        $numBlocks = self::NUM_ERROR_CORRECTION_BLOCKS[$version - 1];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$version - 1];
        $rawCodewords = intdiv(self::numRawDataModules($version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $blocks = [];
        $rsDiv = self::reedSolomonComputeDivisor($blockEccLen);
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $ecc = self::reedSolomonComputeRemainder($dat, $rsDiv);
            $block = $dat;
            if ($i < $numShortBlocks) {
                $block[] = 0; // placeholder so all block arrays share a length
            }
            foreach ($ecc as $e) {
                $block[] = $e;
            }
            $blocks[] = $block;
        }

        $result = [];
        $shortBlockDataLen = $shortBlockLen - $blockEccLen;
        $maxLen = $shortBlockLen + 1;
        for ($i = 0; $i < $maxLen; $i++) {
            for ($j = 0; $j < $numBlocks; $j++) {
                // Skip the placeholder data cell present only in short blocks.
                if ($i === $shortBlockDataLen && $j < $numShortBlocks) {
                    continue;
                }
                $result[] = $blocks[$j][$i];
            }
        }
        return $result;
    }

    /** @return array<int,int> */
    private static function reedSolomonComputeDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }
        return $result;
    }

    /**
     * @param array<int,int> $data
     * @param array<int,int> $divisor
     * @return array<int,int>
     */
    private static function reedSolomonComputeRemainder(array $data, array $divisor): array
    {
        $degree = count($divisor);
        $result = array_fill(0, $degree, 0);
        foreach ($data as $b) {
            $factor = $b ^ $result[0];
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::gfMultiply($divisor[$i], $factor);
            }
        }
        return $result;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    // ---- Matrix drawing ----------------------------------------------------

    private function setFunctionModule(int $x, int $y, bool $isDark): void
    {
        $this->modules[$y][$x] = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        $alignPositions = $this->alignmentPatternPositions();
        $numAlign = count($alignPositions);
        for ($i = 0; $i < $numAlign; $i++) {
            for ($j = 0; $j < $numAlign; $j++) {
                // Skip the three positions overlapping finder patterns.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $numAlign - 1) || ($i === $numAlign - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignmentPattern($alignPositions[$i], $alignPositions[$j]);
            }
        }

        $this->drawFormatBits(0);
        $this->drawVersion();
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunctionModule($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** @return array<int,int> */
    private function alignmentPatternPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $numAlign = intdiv($this->version, 7) + 2;
        $step = $this->version === 32
            ? 26
            : (int) (ceil(($this->version * 4 + 4) / ($numAlign * 2 - 2)) * 2);

        $result = array_fill(0, $numAlign, 0);
        $result[0] = 6;
        $pos = $this->size - 7;
        for ($i = $numAlign - 1; $i >= 1; $i--, $pos -= $step) {
            $result[$i] = $pos;
        }
        return $result;
    }

    private function drawFormatBits(int $mask): void
    {
        // Level M format value is 0; combine with the mask and append BCH.
        $data = (0 << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, self::getBit($bits, $i));
        }
        $this->setFunctionModule(8, 7, self::getBit($bits, 6));
        $this->setFunctionModule(8, 8, self::getBit($bits, 7));
        $this->setFunctionModule(7, 8, self::getBit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, self::getBit($bits, $i));
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, self::getBit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, self::getBit($bits, $i));
        }
        $this->setFunctionModule(8, $this->size - 8, true);
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = self::getBit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $bit);
            $this->setFunctionModule($b, $a, $bit);
        }
    }

    /** @param array<int,int> $data */
    private function drawCodewords(array $data): void
    {
        $i = 0;
        $totalBits = count($data) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $this->modules[$y][$x] = self::getBit($data[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                    default => false,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function chooseMask(): int
    {
        $bestMask = 0;
        $minPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $minPenalty = $penalty;
                $bestMask = $mask;
            }
            $this->applyMask($mask); // undo (XOR is its own inverse)
        }
        return $bestMask;
    }

    private function penaltyScore(): int
    {
        $size = $this->size;
        $result = 0;
        $penaltyN1 = 3;
        $penaltyN2 = 3;
        $penaltyN3 = 40;
        $penaltyN4 = 10;

        // Adjacent modules in rows / columns with the same colour.
        for ($y = 0; $y < $size; $y++) {
            $runColor = false;
            $runX = 0;
            $history = [0, 0, 0, 0, 0, 0, 0];
            for ($x = 0; $x < $size; $x++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runX++;
                    if ($runX === 5) {
                        $result += $penaltyN1;
                    } elseif ($runX > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runX, $history, $size);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($history) * $penaltyN3;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runX = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runX, $history, $size) * $penaltyN3;
        }
        for ($x = 0; $x < $size; $x++) {
            $runColor = false;
            $runY = 0;
            $history = [0, 0, 0, 0, 0, 0, 0];
            for ($y = 0; $y < $size; $y++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runY++;
                    if ($runY === 5) {
                        $result += $penaltyN1;
                    } elseif ($runY > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runY, $history, $size);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($history) * $penaltyN3;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runY = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runY, $history, $size) * $penaltyN3;
        }

        // 2x2 blocks of the same colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $color = $this->modules[$y][$x];
                if ($color === $this->modules[$y][$x + 1]
                    && $color === $this->modules[$y + 1][$x]
                    && $color === $this->modules[$y + 1][$x + 1]) {
                    $result += $penaltyN2;
                }
            }
        }

        // Balance of dark and light modules.
        $dark = 0;
        for ($y = 0; $y < $size; $y++) {
            foreach ($this->modules[$y] as $cell) {
                if ($cell) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $k = (int) ((abs($dark * 20 - $total * 10) + $total - 1) / $total) - 1;
        $result += $k * $penaltyN4;

        return $result;
    }

    /** @param array<int,int> $history */
    private function finderPenaltyAddHistory(int $currentRunLength, array &$history, int $size): void
    {
        if ($history[0] === 0) {
            $currentRunLength += $size; // add light border to the first run
        }
        array_pop($history);
        array_unshift($history, $currentRunLength);
    }

    /** @param array<int,int> $history */
    private function finderPenaltyCountPatterns(array $history): int
    {
        $n = $history[1];
        $core = $n > 0 && $history[2] === $n && $history[3] === $n * 3 && $history[4] === $n && $history[5] === $n;
        return ($core && $history[0] >= $n * 4 && $history[6] >= $n ? 1 : 0)
            + ($core && $history[6] >= $n * 4 && $history[0] >= $n ? 1 : 0);
    }

    /** @param array<int,int> $history */
    private function finderPenaltyTerminateAndCount(bool $currentRunColor, int $currentRunLength, array &$history, int $size): int
    {
        if ($currentRunColor) { // ends with dark
            $this->finderPenaltyAddHistory($currentRunLength, $history, $size);
            $currentRunLength = 0;
        }
        $currentRunLength += $size; // add light border to the last run
        $this->finderPenaltyAddHistory($currentRunLength, $history, $size);
        return $this->finderPenaltyCountPatterns($history);
    }

    private static function getBit(int $value, int $i): bool
    {
        return (($value >> $i) & 1) !== 0;
    }
}
