<?php declare(strict_types=1);

namespace DigitalObject\Service\Site\BlockLayout;

use DigitalObject\Site\BlockLayout\DigitalObjects;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DigitalObjectsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new DigitalObjects($container->get('Omeka\ApiManager'));
    }
}
