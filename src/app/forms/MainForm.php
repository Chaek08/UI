
<?php
namespace app\forms;

use Exception;
use php\xml\DomElement;
use php\xml\DomNode;
use php\xml\XmlProcessor;
use std, gui, framework, app;


class MainForm extends AbstractForm
{
    public $TextureManager;
    public $DDSLoader;
    
    //test
    public $TalkContainer;
    public $MainContainer;
    public $InvContainer;
        
    /**
     * @event show
     */
    function InitApp(UXWindowEvent $e = null)
    {
        $this->TextureManager = new DMTextureManager();
        
        //hud
        $this->TextureManager->Load('ui_hud', 'res://.data/textures/ui_hud.png', 'res://.data/ui/ui_hud.xml'); 
        //common
        $this->TextureManager->Load('ui_common', 'res://.data/textures/ui_common.png', 'res://.data/ui/ui_common.xml'); 
        //numpad
        $this->TextureManager->Load('ui_numpad', 'res://.data/textures/ui_numpad.png', 'res://.data/ui/ui_numpad.xml'); 
        //dialogue
        $this->TextureManager->Load('ui_inventory2', 'res://.data/textures/ui_inventory2.png', 'res://.data/ui/ui_inventory2.xml'); 
        //inventory
        $this->TextureManager->Load('ui_inventory', 'res://.data/textures/ui_inventory.png', 'res://.data/ui/ui_inventory.xml'); 
        
        //$this->DDSLoader = new DMDDSLoader();
        //$image = $this->DDSLoader->Load('res://.data/textures/ui/ui_bottom_background.dds', './userdata/ui_hud_cache.bmp');
        //$view = new UXImageView($this->image);        
    
        $this->MainContainer = new UXAnchorPane();
        $this->add($this->MainContainer);
        
        $this->LoadXMLUI('motion_icon.xml', $this->MainContainer);
        $this->LoadXMLUI('zone_map.xml', $this->MainContainer);
        $this->LoadXMLUI('maingame.xml', $this->MainContainer);
        
    }

    /**
     * @event keyDown-F 
     */
    function LoadDialogXml(UXKeyEvent $e = null)
    {
        if ($this->TalkContainer != null)
        {
            return;
        }
    
        if ($this->MainContainer != null)
        {
            $this->remove($this->MainContainer);
            $this->MainContainer = null;
        }
        
        if ($this->InvContainer != null)
        {
            $this->remove($this->InvContainer);
            $this->InvContainer = null;
        }        
    
        $this->TalkContainer = new UXAnchorPane();
        $this->add($this->TalkContainer);
    
        $this->LoadXMLUI('talk.xml', $this->TalkContainer);
    }

    /**
     * @event keyDown-Esc
     */
    function Escape(UXKeyEvent $e = null)
    {
        if ($this->InvContainer != null)
        {
            $this->remove($this->InvContainer);
            $this->InvContainer = null;
        }
    
        if ($this->TalkContainer != null)
        {
            $this->remove($this->TalkContainer);
            $this->TalkContainer = null;
        }
    
        if ($this->MainContainer != null)
        {
            return;
        }
    
        $this->MainContainer = new UXAnchorPane();
        $this->add($this->MainContainer);
    
        $this->LoadXMLUI('motion_icon.xml', $this->MainContainer);
        $this->LoadXMLUI('zone_map.xml', $this->MainContainer);
        $this->LoadXMLUI('maingame.xml', $this->MainContainer);
    }

    /**
     * @event keyDown-I 
     */
    function LoadInventoryXml(UXKeyEvent $e = null)
    {   
        if ($this->InvContainer != null)
        {
            return;
        }    
    
        if ($this->MainContainer != null)
        {
            $this->remove($this->MainContainer);
            $this->MainContainer = null;
        }    
        
        $this->InvContainer = new UXAnchorPane();
        $this->add($this->InvContainer);        
     
        $this->LoadXMLUI('inventory_new.xml', $this->InvContainer);
        //$this->LoadXMLUI('inventory_item.xml', $this->InvContainer);  
    }
    
    function LoadXMLUI($file, $target = null)
    {
        $path = 'res://.data/ui/' . $file;
    
        if (!Stream::exists($path))
        {
            Logger::warn("XML not found: " . $path);
            
            return;
        }
    
        $content = Stream::getContents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = preg_replace('/<\?xml.*?\?>/s', '', $content);
    
        $processor = new XmlProcessor();
        $document = $processor->parse($content);
        $root = $document->getDocumentElement();
    
        if ($root == null)
        {
            Logger::warn("Invalid XML root.");
            return;
        }
        
        $rootName = $root->getNodeName();
        
        if ($rootName != 'window' && $rootName != 'w')
        {
            Logger::warn("Invalid XML root: " . $rootName);
            return;
        }
    
        if ($target == null)
        {
            $target = $this;
        }
    
        foreach ($root->getChildNodes() as $node)
        {
            if ($node->getNodeType() != 1)
            {
                continue;
            } 
    
            $this->ProcessXMLNode($node, $target, 0, 0, 0, 0);
        }
    }

    function ProcessXMLNode($node, $parent, $parentX, $parentY, $parentWidth, $parentHeight)
    {
        if ($node->getNodeType() != 1)
        {
            return;
        }
    
        $x = $node->hasAttribute('x') ? (int)$node->getAttribute('x') : 0;
        $y = $node->hasAttribute('y') ? (int)$node->getAttribute('y') : 0;
    
        $width = $node->hasAttribute('width') ? (int)$node->getAttribute('width') : $parentWidth;
        $height = $node->hasAttribute('height') ? (int)$node->getAttribute('height') : $parentHeight;
    
        $absoluteX = $parentX + $x;
        $absoluteY = $parentY + $y;
    
        $name = $node->getNodeName();
    
        if ($name == 'button' || substr($name, -7) == '_button')
        {
            $this->CreateXMLButton($node, $parent, $absoluteX, $absoluteY, $width, $height);
            return;
        }
    
        foreach ($node->getChildNodes() as $child)
        {
            if ($child->getNodeType() != 1)
            {
                continue;
            }
    
            $childName = $child->getNodeName();
    
            if ($childName == 'texture')
            {
                $this->CreateXMLTexture(trim($child->getTextContent()), $parent, $absoluteX, $absoluteY, $width, $height);
    
                continue;
            }
    
            if ($childName == 'text')
            {
                $this->CreateXMLText($child, $parent, $absoluteX, $absoluteY, $width, $height);
    
                continue;
            }
    
            $this->ProcessXMLNode($child, $parent, $absoluteX, $absoluteY, $width, $height);
        }
    }
    
    function CreateXMLButton($node, $parent, $x, $y, $width, $height)
    {
        $textureName = '';
        $textNode = null;
    
        foreach ($node->getChildNodes() as $child)
        {
            if ($child->getNodeType() != 1) continue;
    
            $childName = $child->getNodeName();
    
            if ($childName == 'texture')
            {
                $textureName = trim($child->getTextContent());
            }
    
            if ($childName == 'text')
            {
                $textNode = $child;
            }
        }
    
        if ($textureName == '') return;
    
        $texture = $this->TextureManager->Get($textureName . '_d');
        $hoverImage = $this->TextureManager->Get($textureName . '_h');
        $clickImage = $this->TextureManager->Get($textureName . '_t');
    
        if ($texture == null) return;
    
        $view = new UXImageView($texture);
    
        $view->hoverImage = $hoverImage;
        $view->clickImage = $clickImage;
    
        $view->x = $x;
        $view->y = $y;
        $view->fitWidth = $width;
        $view->fitHeight = $height;
        $view->preserveRatio = false;
    
        $parent->add($view);
    
        if ($textNode != null)
        {
            $text = new UXLabel(trim($textNode->getTextContent()));
    
            $text->x = $x;
            $text->y = $y;
            $text->prefWidth = $width;
            $text->prefHeight = $height;
    
            $text->alignment = 'CENTER';
            $text->mouseTransparent = true;
            $text->wrapText = false;
    
            $font = $textNode->hasAttribute('font') ? $textNode->getAttribute('font') : 'Arial14';
    
            preg_match('/([0-9]+)$/', $font, $matches);
            $fontSize = isset($matches[1]) ? (int)$matches[1] : 14;
    
            $text->font = $text->font->withSize($fontSize);
    
            $r = $textNode->hasAttribute('r') ? (int)$textNode->getAttribute('r') : 255;
            $g = $textNode->hasAttribute('g') ? (int)$textNode->getAttribute('g') : 255;
            $b = $textNode->hasAttribute('b') ? (int)$textNode->getAttribute('b') : 255;
    
            $text->textColor = UXColor::of(sprintf('#%02X%02X%02X', $r, $g, $b));
    
            $parent->add($text);
        }
    
        return $view;
    }
    
    function CreateXMLTexture($textureName, $parent, $x, $y, $width, $height)
    {
        if ($textureName == '')
        {
            return;
        }
    
        if (substr($textureName, -2) == '_b')
        {
            $baseName = substr($textureName, 0, -2);
    
            $back = $this->TextureManager->Get($baseName . '_back');
            $end = $this->TextureManager->Get($baseName . '_e');
    
            if ($back != null && $end != null)
            {
                $begin = $this->TextureManager->Get($textureName);
    
                if ($begin != null)
                {
                    $beginView = new UXImageView($begin);
                    $backView = new UXImageView($back);
                    $endView = new UXImageView($end);
    
                    $beginView->x = $x;
                    $beginView->y = $y;
                    $beginView->fitWidth = $width;
                    $beginView->fitHeight = $begin->height;
                    $beginView->preserveRatio = false;
    
                    $backView->x = $x;
                    $backView->y = $y + $begin->height;
                    $backView->fitWidth = $width;
                    $backView->fitHeight = $height - $begin->height - $end->height;
                    $backView->preserveRatio = false;
    
                    $endView->x = $x;
                    $endView->y = $y + $height - $end->height;
                    $endView->fitWidth = $width;
                    $endView->fitHeight = $end->height;
                    $endView->preserveRatio = false;
    
                    $parent->add($beginView);
                    $parent->add($backView);
                    $parent->add($endView);
    
                    return;
                }
            }
        }
    
        $texture = $this->TextureManager->Get($textureName);
    
        if ($texture == null)
        {
            return;
        }
    
        return $this->CreateXMLImage($texture, $parent, $x, $y, $width, $height);
    }  
    
    function CreateXMLImage($image, $parent, $x, $y, $width, $height)
    {
        $view = new UXImageView($image);
    
        $view->x = $x;
        $view->y = $y;
        $view->fitWidth = $width;
        $view->fitHeight = $height;
        $view->preserveRatio = false;
    
        $parent->add($view);
    
        return $view;
    }      
        
    function CreateXMLText($node, $parent, $x, $y, $width, $height)
    {
        $value = trim($node->getTextContent());
    
        $text = new UXLabel($value);
    
        $textX = $node->hasAttribute('x') ? (int)$node->getAttribute('x') : 0;
        $textY = $node->hasAttribute('y') ? (int)$node->getAttribute('y') : 0;
    
        $textWidth = $node->hasAttribute('width') ? (int)$node->getAttribute('width') : $width;
        $textHeight = $node->hasAttribute('height') ? (int)$node->getAttribute('height') : $height;
    
        $text->x = $x + $textX;
        $text->y = $y + $textY;
    
        $text->prefWidth = $textWidth;
        $text->prefHeight = $textHeight;
    
        $text->visible = true;
        $text->opacity = 1;
        $text->wrapText = true;
    
        $r = $node->hasAttribute('r') ? (int)$node->getAttribute('r') : 255;
        $g = $node->hasAttribute('g') ? (int)$node->getAttribute('g') : 255;
        $b = $node->hasAttribute('b') ? (int)$node->getAttribute('b') : 255;
        $a = $node->hasAttribute('a') ? (int)$node->getAttribute('a') : 255;
    
        $text->textColor = UXColor::of(sprintf('#%02X%02X%02X', $r, $g, $b));
        $text->opacity = $a / 255;
    
        $font = $node->hasAttribute('font') ? $node->getAttribute('font') : 'System';
    
        preg_match('/([0-9]+)$/', $font, $matches);
        $fontSize = isset($matches[1]) ? (int)$matches[1] : 14;
    
        $text->font = $text->font->withName('System')->withSize($fontSize);
    
        $parent->add($text);
    
        return $text;
    }
}
