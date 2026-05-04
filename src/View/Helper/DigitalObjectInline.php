<?php declare(strict_types=1);

namespace DigitalObject\View\Helper;

use DigitalObject\Api\Representation\DigitalObjectRepresentation;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class DigitalObjectInline extends AbstractHelper
{
    /** @var string[] */
    protected $defaultProperties;

    public function __construct(array $defaultProperties)
    {
        $this->defaultProperties = $defaultProperties;
    }

    /**
     * Return DigitalObjects referenced by the given resource via the configured
     * properties (default: foaf:depiction).
     *
     * @return DigitalObjectRepresentation[]
     */
    public function __invoke(
        AbstractResourceEntityRepresentation $resource,
        ?array $properties = null
    ): array {
        $properties = $properties ?? $this->defaultProperties;
        $out = [];
        foreach ($properties as $term) {
            foreach ($resource->value($term, ['all' => true, 'default' => []]) as $value) {
                if ($value->type() !== 'resource'
                    && strpos((string) $value->type(), 'resource:') !== 0
                ) {
                    continue;
                }
                $target = $value->valueResource();
                if ($target instanceof DigitalObjectRepresentation) {
                    $out[$target->id()] = $target;
                }
            }
        }
        return array_values($out);
    }
}
