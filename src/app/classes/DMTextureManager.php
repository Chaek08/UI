
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
        return $this->GetRaw($id);
    }
    
    function GetRaw($id)
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
    
        Logger::warn("[DMTextureManager] Texture not found: " . $id);
    
        return null;
    }
    
    function Has($id)
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
    
    private function Compose($begin, $back, $end)
    {
        $height = max($begin->height, $back->height, $end->height);
    
        $width = $begin->width + $back->width + $end->width;
    
        $container = new UXAnchorPane();
        $container->size = [$width, $height];
    
        $beginView = new UXImageView($begin);
        $beginView->position = [0, 0];
        $beginView->size = [$begin->width, $begin->height];
    
        $backView = new UXImageView($back);
        $backView->position = [$begin->width, 0];
        $backView->size = [$back->width, $back->height];
    
        $endView = new UXImageView($end);
        $endView->position = [$begin->width + $back->width, 0];
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
