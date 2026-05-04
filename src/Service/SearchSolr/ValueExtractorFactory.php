<?php declare(strict_types=1);

namespace DigitalObject\Service\SearchSolr;

use DigitalObject\SearchSolr\ValueExtractor\DigitalObjectValueExtractor;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ValueExtractorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        $baseFilepath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new DigitalObjectValueExtractor(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Logger'),
            $baseFilepath
        );
    }
}
