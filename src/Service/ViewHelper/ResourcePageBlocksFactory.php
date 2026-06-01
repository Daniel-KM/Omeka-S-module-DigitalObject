<?php declare(strict_types=1);

namespace DigitalObject\Service\ViewHelper;

use DigitalObject\View\Helper\ResourcePageBlocks;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ResourcePageBlocksFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $blockLayoutManager = $services->get('Omeka\ResourcePageBlockLayoutManager');
        $currentTheme = $services->get('Omeka\Site\ThemeManager')->getCurrentTheme();
        $resourcePageBlocks = $currentTheme
            ? $blockLayoutManager->getResourcePageBlocks($currentTheme)
            : [];

        $helper = new ResourcePageBlocks($blockLayoutManager, $resourcePageBlocks);
        $extras = $services->get('Config')['digitalobject']['resource_page_block_layouts_extended_resource_names'] ?? [];
        $helper->setExtraResourceNames($extras);
        return $helper;
    }
}
