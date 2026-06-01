<?php declare(strict_types=1);

namespace DigitalObject\Service;

use DigitalObject\Site\ResourcePageBlockLayout\ExtendedManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ResourcePageBlockLayoutManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        $manager = new ExtendedManager($services, $config['resource_page_block_layouts']);
        $manager->setResourcePageBlocksDefault($config['resource_page_blocks_default']);
        $manager->setSiteSettings($services->get('Omeka\Settings\Site'));
        $manager->setExtendedCompat($config['digitalobject']['resource_page_block_layouts_extended_compat'] ?? []);
        return $manager;
    }
}
