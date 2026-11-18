
<?php
namespace app\classes;

use std;
use framework;

class DMDDSLoader
{
    private static function Log($message)
    {
        Logger::info('[DDS] ' . $message);
    }

    private static function Byte($data, $index)
    {
        return ord($data[$index]);
    }

    private static function U16($data, $offset)
    {
        return self::Byte($data, $offset) + self::Byte($data, $offset + 1) * 256;
    }

    private static function U32($data, $offset)
    {
        return self::Byte($data, $offset)+ self::Byte($data, $offset + 1) * 256 + self::Byte($data, $offset + 2) * 65536+ self::Byte($data, $offset + 3) * 16777216;
    }

    private static function ReadComponent($value, $mask)
    {
        if ($mask == 0) return 255;

        $shift = 0;
        while ($shift < 32 && (($mask >> $shift) & 1) == 0) $shift++;

        $max = $mask >> $shift;
        if ($max == 0) return 0;

        return (int) round((($value & $mask) >> $shift) * 255 / $max);
    }

    private static function Color565($value)
    {
        return [(int) round((($value >> 11) & 31) * 255 / 31), (int) round((($value >> 5) & 63) * 255 / 63), (int) round(($value & 31) * 255 / 31), 255];
    }

    private static function MixColor($a, $b, $wa, $wb, $divisor)
    {
        return [
            (int) round(($a[0] * $wa + $b[0] * $wb) / $divisor),
            (int) round(($a[1] * $wa + $b[1] * $wb) / $divisor),
            (int) round(($a[2] * $wa + $b[2] * $wb) / $divisor),
            255
        ];
    }

    private static function DecodeColorBlock($block, $allowTransparent)
    {
        $c0 = self::U16($block, 0);
        $c1 = self::U16($block, 2);
        $color0 = self::Color565($c0);
        $color1 = self::Color565($c1);

        $palette = [$color0, $color1];

        if ($allowTransparent && $c0 <= $c1)
        {
            $palette[] = self::MixColor($color0, $color1, 1, 1, 2);
            $palette[] = [0, 0, 0, 0];
        }
        else
        {
            $palette[] = self::MixColor($color0, $color1, 2, 1, 3);
            $palette[] = self::MixColor($color0, $color1, 1, 2, 3);
        }

        $pixels = [];

        for ($i = 0; $i < 16; $i++)
        {
            $byteIndex = 4 + (int) floor($i / 4);
            $shift = ($i % 4) * 2;
            $index = (self::Byte($block, $byteIndex) >> $shift) & 3;
            $pixels[$i] = $palette[$index];
        }

        return $pixels;
    }

    private static function DecodeDXT3Alpha($alpha)
    {
        $result = [];

        for ($i = 0; $i < 16; $i++)
        {
            $byte = self::Byte($alpha, (int) floor($i / 2));
            $value = ($i % 2 == 0) ? ($byte & 15) : (($byte >> 4) & 15);
            $result[$i] = $value * 17;
        }

        return $result;
    }

    private static function DecodeDXT5Alpha($alpha)
    {
        $a0 = self::Byte($alpha, 0);
        $a1 = self::Byte($alpha, 1);
        $palette = [$a0, $a1];

        if ($a0 > $a1)
        {
            for ($i = 1; $i <= 6; $i++)
            {
                $palette[] = (int) round(($a0 * (7 - $i) + $a1 * $i) / 7);
            }
        }
        else
        {
            for ($i = 1; $i <= 4; $i++)
            {
                $palette[] = (int) round(($a0 * (5 - $i) + $a1 * $i) / 5);
            }

            $palette[] = 0;
            $palette[] = 255;
        }

        $result = [];

        for ($i = 0; $i < 16; $i++)
        {
            $bitOffset = $i * 3;
            $byteIndex = 2 + (int) floor($bitOffset / 8);
            $shift = $bitOffset % 8;
            $bits = self::Byte($alpha, $byteIndex);

            if ($byteIndex + 1 < 8)
            {
                $bits += self::Byte($alpha, $byteIndex + 1) * 256;
            }

            $result[$i] = $palette[($bits >> $shift) & 7];
        }

        return $result;
    }

    private static function DrawPixel($gc, $x, $y, $color)
    {
        $gc->fillColor = 'rgba(' . (int) $color[0] . ',' . (int) $color[1] . ',' . (int) $color[2] . ',' . ($color[3] / 255) . ')';
        $gc->fillRect($x, $y, 1, 1);
    }

    private static function DecodeBlocks($stream, $gc, $width, $height, $format)
    {
        $blocksX = (int) ceil($width / 4);
        $blocksY = (int) ceil($height / 4);

        for ($by = 0; $by < $blocksY; $by++)
        {
            $blockColors = [];

            for ($bx = 0; $bx < $blocksX; $bx++)
            {
                if ($format == 'DXT1')
                {
                    $colorBlock = $stream->read(8);

                    if (strlen($colorBlock) != 8)
                    {
                        throw new \Exception('Unexpected end of DXT1 data');
                    }

                    $colors = self::DecodeColorBlock($colorBlock, true);
                }
                else
                {
                    $alphaBlock = $stream->read(8);
                    $colorBlock = $stream->read(8);

                    if (strlen($alphaBlock) != 8 || strlen($colorBlock) != 8)
                    {
                        throw new \Exception('Unexpected end of DXT data');
                    }

                    $colors = self::DecodeColorBlock($colorBlock, false);
                    $alphas = ($format == 'DXT3') ? self::DecodeDXT3Alpha($alphaBlock) : self::DecodeDXT5Alpha($alphaBlock);

                    for ($i = 0; $i < 16; $i++)
                    {
                        $colors[$i][3] = $alphas[$i];
                    }
                }

                $blockColors[$bx] = $colors;
            }

            for ($py = 0; $py < 4; $py++) 
            {
                $y = $by * 4 + $py;
                if ($y >= $height) continue;

                $runColor = null;
                $runStart = 0;
                $runLength = 0;

                for ($bx = 0; $bx < $blocksX; $bx++)
                {
                    for ($px = 0; $px < 4; $px++)
                    {
                        $x = $bx * 4 + $px;
                        if ($x >= $width) continue;

                        $color = $blockColors[$bx][$py * 4 + $px];
                        $same = $runColor !== null && $runColor[0] == $color[0] && $runColor[1] == $color[1] && $runColor[2] == $color[2] && $runColor[3] == $color[3];

                        if ($same)
                        {
                            $runLength++;
                        }
                        else
                        {
                            if ($runColor !== null)
                            {
                                $gc->fillColor = 'rgba(' . $runColor[0] . ',' . $runColor[1] . ',' . $runColor[2] . ',' . ($runColor[3] / 255) . ')';
                                $gc->fillRect($runStart, $y, $runLength, 1);
                            }

                            $runColor = $color;
                            $runStart = $x;
                            $runLength = 1;
                        }
                    }
                }

                if ($runColor !== null && $runLength > 0)
                {
                    $gc->fillColor = 'rgba(' . $runColor[0] . ',' . $runColor[1] . ',' . $runColor[2] . ',' . ($runColor[3] / 255) . ')';
                    $gc->fillRect($runStart, $y, $runLength, 1);
                }
            }
        }
    }

    private static function DecodeRGB($stream, $gc, $width, $height, $pitch, $bits, $rMask, $gMask, $bMask, $aMask)
    {
        $bytesPerPixel = (int) ($bits / 8);

        if ($bytesPerPixel < 1 || $bytesPerPixel > 4)
        {
            throw new \Exception('Unsupported RGB bit depth');
        }

        $rowBytes = $width * $bytesPerPixel;
        if ($pitch < $rowBytes) $pitch = $rowBytes;

        for ($y = 0; $y < $height; $y++)
        {
            $readBytes = 0;

            for ($x = 0; $x < $width; $x++)
            {
                $pixelData = $stream->read($bytesPerPixel);

                if (strlen($pixelData) != $bytesPerPixel)
                {
                    throw new \Exception('Unexpected end of RGB data');
                }

                $value = 0;
                for ($i = 0; $i < $bytesPerPixel; $i++)
                {
                    $value += self::Byte($pixelData, $i) * pow(256, $i);
                }

                $color = [self::ReadComponent($value, $rMask), self::ReadComponent($value, $gMask), self::ReadComponent($value, $bMask), $aMask == 0 ? 255 : self::ReadComponent($value, $aMask)];

                self::DrawPixel($gc, $x, $y, $color);
                $readBytes += $bytesPerPixel;
            }

            $padding = $pitch - $readBytes;
            if ($padding > 0) $stream->read($padding);
        }
    }

    public static function Load($path)
    {
        self::Log('Loading: ' . $path);
        $stream = \php\io\Stream::of($path, 'r');

        if ($stream == null)
        {
            throw new \Exception('Cannot open DDS file: ' . $path);
        }

        try {
            $header = $stream->read(128);

            if (strlen($header) != 128)
            {
                throw new \Exception('Invalid DDS header length');
            }

            $magic = $header[0] . $header[1] . $header[2] . $header[3];

            if ($magic != 'DDS ')
            {
                throw new \Exception('Invalid DDS signature');
            }

            $height = self::U32($header, 12);
            $width = self::U32($header, 16);
            $pitch = self::U32($header, 20);
            $pfFlags = self::U32($header, 80);
            $fourCC = $header[84] . $header[85] . $header[86] . $header[87];
            $bits = self::U32($header, 88);
            $rMask = self::U32($header, 92);
            $gMask = self::U32($header, 96);
            $bMask = self::U32($header, 100);
            $aMask = self::U32($header, 104);

            if ($width <= 0 || $height <= 0)
            {
                throw new \Exception('Invalid DDS dimensions');
            }

            self::Log('Size: ' . $width . 'x' . $height);
            self::Log('FourCC: ' . $fourCC);

            $canvas = new \php\gui\UXCanvas();
            $canvas->resize($width, $height);
            $gc = $canvas->gc();

            if ($fourCC == 'DXT1' || $fourCC == 'DXT3' || $fourCC == 'DXT5')
            {
                self::DecodeBlocks($stream, $gc, $width, $height, $fourCC);
            }
            elseif (($pfFlags & 0x40) != 0)
            {
                self::DecodeRGB($stream, $gc, $width, $height, $pitch, $bits, $rMask, $gMask, $bMask, $aMask);
            }
            else
            {
                throw new \Exception('Unsupported DDS format: ' . $fourCC);
            }

            self::Log('DDS loaded successfully');
            return $canvas;
        } finally {
            $stream->close();
        }
    }
}
