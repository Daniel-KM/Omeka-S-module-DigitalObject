<?php declare(strict_types=1);

namespace DigitalObject\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\View\Helper\ResourcePageBlocks as CoreResourcePageBlocks;

/**
 * Allow digital_objects (plus any extra resource name the manager recognises)
 * to be a valid input of the resource page block view helper.
 *
 * The only core constraint is the hardcoded
 * `in_array(['items', 'media', 'item_sets'])` guard. We override only that
 * guard via the extended list of resource names.
 */
class ResourcePageBlocks extends CoreResourcePageBlocks
{
    /**
     * @var string[]
     */
    protected $extraResourceNames = [];

    public function setExtraResourceNames(array $names): self
    {
        $this->extraResourceNames = array_values(array_unique($names));
        return $this;
    }

    public function __invoke(AbstractResourceEntityRepresentation $resource, $regionName = 'main')
    {
        $this->resource = $resource;
        $this->regionName = $regionName;
        $this->resourceName = $resource->resourceName();

        $allowed = array_merge(['items', 'media', 'item_sets'], $this->extraResourceNames);
        if (!in_array($this->resourceName, $allowed, true)) {
            throw new \Exception('Invalid resource');
        }

        return $this;
    }
}
