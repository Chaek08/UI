
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
    
        $this->TalkContainer = new UXAnchorPane();
        $this->add($this->TalkContainer);
    
        $this->LoadXMLUI('talk.xml', $this->TalkContainer);
    }

    /**
     * @event keyDown-Esc 
     */
    function Escape_(UXKeyEvent $e = null)
    {
        if ($this->TalkContainer == null)
        {
            return;
        }
    
        $this->remove($this->TalkContainer);
        $this->TalkContainer = null;
    
        $this->MainContainer = new UXAnchorPane();
        $this->add($this->MainContainer);
    
        $this->LoadXMLUI('motion_icon.xml', $this->MainContainer);
        $this->LoadXMLUI('zone_map.xml', $this->MainContainer);
        $this->LoadXMLUI('maingame.xml', $this->MainContainer);
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
    
        if ($root == null || $root->getNodeName() != 'window')
        {
            Logger::warn("Invalid XML root.");
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
            return;
    
        $x = $node->hasAttribute('x') ? (int)$node->getAttribute('x') : 0;
        $y = $node->hasAttribute('y') ? (int)$node->getAttribute('y') : 0;
    
        $width = $node->hasAttribute('width') ? (int)$node->getAttribute('width') : $parentWidth;
        $height = $node->hasAttribute('height') ? (int)$node->getAttribute('height') : $parentHeight;
    
        $absoluteX = $parentX + $x;
        $absoluteY = $parentY + $y;
        

        if ($node->getNodeName() == 'button')
        {
            $textureName = '';
            $textNode = null;

            foreach ($node->getChildNodes() as $buttonNode)
            {
                if ($buttonNode->getNodeType() != 1)
                {
                    continue;
                } 

                if ($buttonNode->getNodeName() == 'texture')
                {
                    $textureName = trim($buttonNode->getTextContent());
                }

                if ($buttonNode->getNodeName() == 'text')
                {
                    $textNode = $buttonNode;
                }
            }

            if ($textureName == '')
            {
                return;
            }

            $image = $this->TextureManager->Get($textureName . '_d');
            $hoverImage = $this->TextureManager->Get($textureName . '_h');
            $clickImage = $this->TextureManager->Get($textureName . '_t');

            if ($image == null)
            {
                return;
            }

            $view = new UXImageView($image);

            $view->hoverImage = $hoverImage;
            $view->clickImage = $clickImage;

            $view->x = $absoluteX;
            $view->y = $absoluteY;
            $view->fitWidth = $width;
            $view->fitHeight = $height;
            $view->preserveRatio = false;

            $parent->add($view);

            if ($textNode != null)
            {
                $this->ProcessXMLNode($textNode, $parent, $absoluteX, $absoluteY, $width, $height);
            }

            return;
        }
  

        foreach ($node->getChildNodes() as $child)
        {
            if ($child->getNodeType() != 1)
            {
                continue;
            } 

            $name = $child->getNodeName();

            
            if ($name == 'texture')
            {
                $textureName = trim($child->getTextContent());
            
                if ($textureName == '')
                {
                    continue;
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
            
                            $beginView->x = $absoluteX;
                            $beginView->y = $absoluteY;
                            $beginView->fitWidth = $width;
                            $beginView->fitHeight = $begin->height;
                            $beginView->preserveRatio = false;
            
                            $backView->x = $absoluteX;
                            $backView->y = $absoluteY + $begin->height;
                            $backView->fitWidth = $width;
                            $backView->fitHeight = $height - $begin->height - $end->height;
                            $backView->preserveRatio = false;
            
                            $endView->x = $absoluteX;
                            $endView->y = $absoluteY + $height - $end->height;
                            $endView->fitWidth = $width;
                            $endView->fitHeight = $end->height;
                            $endView->preserveRatio = false;
            
                            $parent->add($beginView);
                            $parent->add($backView);
                            $parent->add($endView);
            
                            continue;
                        }
                    }
                }
            
                $texture = $this->TextureManager->Get($textureName);
            
                if ($texture == null)
                {
                    continue;
                }                   
            
                $view = new UXImageView($texture);
            
                $view->x = $absoluteX;
                $view->y = $absoluteY;
                $view->fitWidth = $width;
                $view->fitHeight = $height;
                $view->preserveRatio = false;
            
                $parent->add($view);
            
                continue;
            }

            if ($name == 'text')
            {
                $textX = $child->hasAttribute('x') ? (int)$child->getAttribute('x') : 0;
                $textY = $child->hasAttribute('y') ? (int)$child->getAttribute('y') : 0;

                $textWidth = $child->hasAttribute('width') ? (int)$child->getAttribute('width') : $width;
                $textHeight = $child->hasAttribute('height') ? (int)$child->getAttribute('height') : $height;

                $text = new UXLabel(trim($child->getTextContent()));

                $text->x = $absoluteX + $textX;
                $text->y = $absoluteY + $textY;
                $text->prefWidth = $textWidth;
                $text->prefHeight = $textHeight;
                $text->wrapText = true;

                $r = $child->hasAttribute('r') ? (int)$child->getAttribute('r') : 255;
                $g = $child->hasAttribute('g') ? (int)$child->getAttribute('g') : 255;
                $b = $child->hasAttribute('b') ? (int)$child->getAttribute('b') : 255;
                $a = $child->hasAttribute('a') ? (int)$child->getAttribute('a') : 255;

                $r = max(0, min(255, $r));
                $g = max(0, min(255, $g));
                $b = max(0, min(255, $b));
                $a = max(0, min(255, $a));

                $text->textColor = UXColor::of(sprintf('#%02X%02X%02X', $r, $g, $b));
                $text->opacity = $a / 255;

                $align = $child->hasAttribute('align') ? $child->getAttribute('align') : 'l';

                if ($align == 'c')
                {
                    $text->textAlignment = 'CENTER';
                }
                elseif ($align == 'r')
                {
                    $text->textAlignment = 'RIGHT';
                }
                else 
                {
                    $text->textAlignment = 'LEFT';
                }

                $text->alignment = 'CENTER_LEFT';

                $font = $child->hasAttribute('font') ? $child->getAttribute('font') : 'Arial14';

                preg_match('/([0-9]+)$/', $font, $matches);
                $fontSize = isset($matches[1]) ? (int)$matches[1] : 14;

                $text->font = new UXFont('Arial', $fontSize);

                $parent->add($text);

                continue;
            }

            $this->ProcessXMLNode($child, $parent, $absoluteX, $absoluteY, $width, $height);
        }
    }
}
