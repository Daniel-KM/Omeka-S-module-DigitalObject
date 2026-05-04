<?php declare(strict_types=1);

namespace DigitalObject\Service\ViewHelper;

use DigitalObject\View\Helper\DigitalObjectInline;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DigitalObjectInlineFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $settings = $container->get('Omeka\Settings');
        $properties = $settings->get('digitalobject_inline_properties', []);
        if (!$properties) {
            $config = $container->get('Config');
            $properties = $config['digitalobject']['config']['digitalobject_inline_properties'] ?? [];
        }
        return new DigitalObjectInline($properties);
    }
}
