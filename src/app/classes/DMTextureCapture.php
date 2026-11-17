<?php
namespace app\classes;

use framework;
use gui;

class DMTextureCapture
{
    public static function Crop($texture, int $x, int $y, int $width, int $height, string $name = 'default'): UXImage
    {
        if (is_string($texture))
        {
            $texture = new UXImage($texture);
        }

        Logger::info("[DMTextureCapture] Added: {$name} (x={$x}, y={$y}, size={$width}x{$height})");

        $container = new UXAnchorPane();
        $container->size = [$width, $height];

        $clip = new UXRectangle();
        $clip->width = $width;
        $clip->height = $height;
        $container->clip = $clip;

        $view = new UXImageView($texture);
        $view->position = [-$x, -$y];
        $view->size = [$texture->width, $texture->height];
        $container->add($view);

        return $container->snapshot();
    }
}