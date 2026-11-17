
<?php
namespace app\classes;

use gui;
use std;
use app;
use framework;

class DMTextureManager 
{
    private $atlases = [];
    private $compositeCache = [];

    function Load($name, $imagePath, $xmlPath)
    {
        if (isset($this->atlases[$name]))
        {
            Logger::warn("[DMTextureManager] Atlas already loaded: " . $name);
            
            return false;
        }

        $atlas = new DMTextureAtlas($imagePath, $xmlPath);

        $this->atlases[$name] = $atlas;
        $this->compositeCache = [];

        Logger::info("[DMTextureManager] Atlas loaded: " . $name);

        return true;
    }

    function Get($id)
    {
        foreach ($this->atlases as $atlas)
        {
            if ($atlas->exists($id))
            {
                return $atlas->get($id);
            }
        }

        if (isset($this->compositeCache[$id]))
        {
            return $this->compositeCache[$id];
        }

        $begin = $this->GetRaw($id . '_b');
        $back = $this->GetRaw($id . '_back');
        $end = $this->GetRaw($id . '_e');

        if ($begin != null && $back != null && $end != null)
        {
            $image = $this->Compose($begin, $back, $end);

            if ($image != null)
            {
                $this->compositeCache[$id] = $image;

                Logger::info("[DMTextureManager] Composite texture: " . $id);

                return $image;
            }
        }

        $path = 'res://.data/textures/' . str_replace('\\', '/', $id) . '.png';

        if (Stream::exists($path))
        {
            Logger::info("[DMTextureManager] Standalone texture: " . $id);
            
            return new UXImage($path);
        }

        Logger::warn("[DMTextureManager] Texture not found: " . $id);

        return null;
    }

    private function GetRaw($id)
    {
        foreach ($this->atlases as $atlas)
        {
            if ($atlas->exists($id))
            {
                return $atlas->get($id);
            }
        }

        $path = 'res://.data/textures/' . str_replace('\\', '/', $id) . '.png';

        if (Stream::exists($path))
        {
            return new UXImage($path);
        }

        return null;
    }

    private function Compose($begin, $back, $end)
    {
        $width = max($begin->width, $back->width, $end->width);

        $height = $begin->height + $back->height + $end->height;

        $container = new UXAnchorPane();
        $container->size = [$width, $height];

        $beginView = new UXImageView($begin);
        $beginView->position = [0, 0];
        $beginView->size = [$begin->width, $begin->height];

        $backView = new UXImageView($back);
        $backView->position = [0, $begin->height];
        $backView->size = [$back->width, $back->height];

        $endView = new UXImageView($end);
        $endView->position = [0, $begin->height + $back->height];
        $endView->size = [$end->width, $end->height];

        $container->add($beginView);
        $container->add($backView);
        $container->add($endView);

        return $container->snapshot();
    }

    function Exists($id)
    {
        foreach ($this->atlases as $atlas)
        {
            if ($atlas->exists($id))
            {
                return true;
            } 
        }

        return false;
    }

    function GetAtlas($name)
    {
        return isset($this->atlases[$name]) ? $this->atlases[$name] : null;
    }

    function Unload($name)
    {
        if (!isset($this->atlases[$name]))
        {
           return false; 
        } 

        unset($this->atlases[$name]);

        $this->compositeCache = [];

        Logger::info("[DMTextureManager] Atlas unloaded: " . $name);

        return true;
    }
}
