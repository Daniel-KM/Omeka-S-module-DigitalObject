<?php declare(strict_types=1);

namespace DigitalObject\Service\ColumnType;

use DigitalObject\ColumnType\Value;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ValueFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new Value($services->get('FormElementManager'), $services->get('Omeka\ApiManager'));
    }
}
