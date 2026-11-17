<?php
namespace app\classes;

use gui;
use std;

class DMDDSLoader
{
    private function Byte($data, $offset)
    {
        return ord($data[$offset]);
    }

    private function U16($data, $offset)
    {
        return $this->Byte($data, $offset) + $this->Byte($data, $offset + 1) * 256;
    }

    private function U32($data, $offset)
    {
        return $this->Byte($data, $offset) + $this->Byte($data, $offset + 1) * 256 + $this->Byte($data, $offset + 2) * 65536 + $this->Byte($data, $offset + 3) * 16777216;
    }

    private function LE16($value)
    {
        return chr($value & 255) . chr(($value >> 8) & 255);
    }

    private function LE32($value)
    {
        return chr($value & 255) . chr(($value >> 8) & 255) . chr(($value >> 16) & 255) . chr(($value >> 24) & 255);
    }

    private function Color565($value)
    {
        $r = ($value >> 11) & 31;
        $g = ($value >> 5) & 63;
        $b = $value & 31;

        return array(($r << 3) | ($r >> 2), ($g << 2) | ($g >> 4), ($b << 3) | ($b >> 2));
    }

    private function ColorPalette($c0, $c1, $allowAlpha)
    {
        $a = $this->Color565($c0);
        $b = $this->Color565($c1);

        $palette = array( array($a[0], $a[1], $a[2], 255), array($b[0], $b[1], $b[2], 255));

        if ($c0 > $c1 || !$allowAlpha)
        {
            $palette[] = array( (2 * $a[0] + $b[0]) / 3, (2 * $a[1] + $b[1]) / 3, (2 * $a[2] + $b[2]) / 3, 255 );
            
            $palette[] = array(($a[0] + 2 * $b[0]) / 3, ($a[1] + 2 * $b[1]) / 3, ($a[2] + 2 * $b[2]) / 3, 255);
        }
        else
        {
            $palette[] = array(($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2, ($a[2] + $b[2]) / 2, 255);
            
            $palette[] = array(0, 0, 0, 0);
        }

        return $palette;
    }

    private function DecodeAlphaDXT5($data, $offset)
    {
        $a0 = $this->Byte($data, $offset);
        $a1 = $this->Byte($data, $offset + 1);

        $table = array($a0, $a1);

        if ($a0 > $a1)
        {
            for ($i = 1; $i <= 6; $i++)
            {
                $table[] = (int)(((7 - $i) * $a0 + $i * $a1) / 7);
            }
                
        }
        else
        {
            for ($i = 1; $i <= 4; $i++)
            {
                $table[] = (int)(((5 - $i) * $a0 + $i * $a1) / 5);
            }

            $table[] = 0;
            $table[] = 255;
        }

        $bits = 0;
        $factor = 1;

        for ($i = 0; $i < 6; $i++)
        {
            $bits += $this->Byte($data, $offset + 2 + $i) * $factor;
            $factor *= 256;
        }

        $result = array();

        for ($i = 0; $i < 16; $i++)
        {
            $index = ($bits >> ($i * 3)) & 7;
            $result[] = $table[$index];
        }

        return $result;
    }

    private function DecodeBlock($data, $offset, $format, &$pixels, $width, $height, $bx, $by)
    {
        $colorOffset = $offset;

        if ($format == 'DXT3' || $format == 'DXT5')
        {
            $colorOffset += 8;
        }

        $c0 = $this->U16($data, $colorOffset);
        $c1 = $this->U16($data, $colorOffset + 2);

        $palette = $this->ColorPalette($c0, $c1, $format == 'DXT1');

        $selectors = $this->U32($data, $colorOffset + 4);

        if ($format == 'DXT5')
        {
            $alpha = $this->DecodeAlphaDXT5($data, $offset);
        }
        else
        {
            $alpha = array();
        }

        for ($py = 0; $py < 4; $py++)
        {
            for ($px = 0; $px < 4; $px++)
            {
                $i = $py * 4 + $px;

                $x = $bx * 4 + $px;
                $y = $by * 4 + $py;

                if ($x >= $width || $y >= $height)
                {
                    continue;
                }

                $colorIndex = ($selectors >> ($i * 2)) & 3;
                $color = $palette[$colorIndex];

                $a = $color[3];

                if ($format == 'DXT3')
                {
                    $alphaByte = $this->Byte($data, $offset + (int)($i / 2));

                    if (($i & 1) == 0)
                    {
                        $a = ($alphaByte & 15) * 17;
                    }
                    else
                    {
                       $a = (($alphaByte >> 4) & 15) * 17; 
                    }
                }
                elseif ($format == 'DXT5')
                {
                    $a = $alpha[$i];
                }

                $argb = ((int)$a << 24) | ((int)$color[0] << 16) | ((int)$color[1] << 8) | (int)$color[2];

                $pixels[$y * $width + $x] = $argb;
            }
        }
    }

    private function CreateBMP($pixels, $width, $height)
    {
        $imageSize = $width * $height * 4;
        $offset = 70;

        $bmp = 'BM';
        $bmp .= $this->LE32($offset + $imageSize);
        $bmp .= $this->LE32(0);
        $bmp .= $this->LE32($offset);

        $bmp .= $this->LE32(56);
        $bmp .= $this->LE32($width);
        $bmp .= $this->LE32($height);
        $bmp .= $this->LE16(1);
        $bmp .= $this->LE16(32);
        $bmp .= $this->LE32(3);
        $bmp .= $this->LE32($imageSize);
        $bmp .= $this->LE32(0);
        $bmp .= $this->LE32(0);
        $bmp .= $this->LE32(0);
        $bmp .= $this->LE32(0);

        $bmp .= $this->LE32(0x00FF0000);
        $bmp .= $this->LE32(0x0000FF00);
        $bmp .= $this->LE32(0x000000FF);
        $bmp .= $this->LE32(0xFF000000);

        for ($y = $height - 1; $y >= 0; $y--)
        {
            for ($x = 0; $x < $width; $x++)
            {
                $argb = $pixels[$y * $width + $x];

                $a = ($argb >> 24) & 255;
                $r = ($argb >> 16) & 255;
                $g = ($argb >> 8) & 255;
                $b = $argb & 255;

                $bmp .= chr($b);
                $bmp .= chr($g);
                $bmp .= chr($r);
                $bmp .= chr($a);
            }
        }

        return $bmp;
    }

    public function Load($path, $cachePath)
    {
        $data = Stream::getContents($path);

        if (strlen($data) < 128)
        {
            throw new \Exception('Invalid DDS file.');
        }

        if (substr($data, 0, 4) != 'DDS ')
        {
            throw new \Exception('Invalid DDS signature.');
        }

        $height = $this->U32($data, 12);
        $width = $this->U32($data, 16);

        if ($width <= 0 || $height <= 0)
        {
            throw new \Exception('Invalid DDS dimensions.');
        }

        $format = substr($data, 84, 4);

        if ($format != 'DXT1' && $format != 'DXT3' && $format != 'DXT5')
        {
            throw new \Exception('Unsupported DDS format: ' . $format);
        }
            
        $blockSize = ($format == 'DXT1') ? 8 : 16;

        $blocksX = (int)(($width + 3) / 4);
        $blocksY = (int)(($height + 3) / 4);

        $requiredSize = 128 + $blocksX * $blocksY * $blockSize;

        if (strlen($data) < $requiredSize)
        {
            throw new \Exception('DDS pixel data is incomplete.');
        }

        $pixels = array_fill(0, $width * $height, 0);

        $offset = 128;

        for ($by = 0; $by < $blocksY; $by++)
        {
            for ($bx = 0; $bx < $blocksX; $bx++)
            {
                $this->DecodeBlock($data, $offset, $format, $pixels, $width, $height, $bx, $by);

                $offset += $blockSize;
            }
        }

        $bmp = $this->CreateBMP($pixels, $width, $height);

        if (file_put_contents($cachePath, $bmp) === false)
        {
            throw new \Exception('Cannot write DDS cache.');
        }

        $image = new UXImage($cachePath);

        if ($image->isError())
        {
            throw new \Exception('Cannot load decoded DDS image.');
        }

        return $image;
    }
}