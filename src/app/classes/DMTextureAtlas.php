
<?php
namespace app\classes;

use app;
use php\xml\XmlProcessor;
use gui;
use framework;
use std;

use app\classes\DMTextureCapture;

class DMTextureAtlas
{
    private $image;
    private $textures = [];
    private $cache = [];

    function __construct($imagePath, $xmlPath)
    {
        if (!Stream::exists($imagePath) || !Stream::exists($xmlPath))
        {
            Logger::warn("[DMTextureAtlas] Files not found.");
            return;
        }

        $this->image = new UXImage($imagePath);

        $content = Stream::getContents($xmlPath);

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = preg_replace('/<\?xml.*?\?>/s', '', $content);

        $processor = new XmlProcessor();
        $document = $processor->parse($content);
        $root = $document->getDocumentElement();

        if ($root == null)
        {
            return;
        }

        foreach ($root->getChildNodes() as $node)
        {
            if ($node->getNodeType() != 1 || $node->getNodeName() != 'texture')
            {
                continue;
            }

            $id = $node->getAttribute('id');

            if ($id == '')
            {
                continue;
            }

            $this->textures[$id] = [
                'x' => (int) $node->getAttribute('x'),
                'y' => (int) $node->getAttribute('y'),
                'width' => (int) $node->getAttribute('width'),
                'height' => (int) $node->getAttribute('height')
            ];
        }

        Logger::info("[DMTextureAtlas] Loaded: " . count($this->textures) . " textures");
    }

    function exists($id)
    {
        return isset($this->textures[$id]);
    }

    function get($id)
    {
        if (!$this->exists($id)) return null;
        if (isset($this->cache[$id])) return $this->cache[$id];

        $texture = $this->textures[$id];

        $image = DMTextureCapture::Crop($this->image, $texture['x'], $texture['y'], $texture['width'], $texture['height'], $id);

        $this->cache[$id] = $image;

        return $image;
    }

    function getRegion($id)
    {
        return isset($this->textures[$id]) ? $this->textures[$id] : null;
    }

    function getCount()
    {
        return count($this->textures);
    }
}
