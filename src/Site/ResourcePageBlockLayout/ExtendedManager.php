<?php declare(strict_types=1);

namespace DigitalObject\Site\ResourcePageBlockLayout;

use Omeka\Site\ResourcePageBlockLayout\Manager;

/**
 * Extend the core resource page block layout manager so digital_objects is a
 * recognized resource name, and so block layouts can be made compatible with
 * digital_objects without subclassing each one. The extension list is set via
 * setExtendedCompat() from the factory.
 *
 * Reasoning: Omeka core hardcodes ['items', 'item_sets', 'media'] in the
 * standardize methods, and each block's getCompatibleResourceNames() returns a
 * literal array. There is no event/initializer hook to extend either.
 * Concentrating the override on the manager keeps each block class pristine.
 */
class ExtendedManager extends Manager
{
    /**
     * Map of resource name → list of block layout names that should be
     * considered compatible even when their getCompatibleResourceNames() does
     * not list the resource name.
     *
     * @var array<string, string[]>
     */
    protected $extendedCompat = [];

    /**
     * Resource names recognized in addition to the core ones in standardize
     * methods.
     *
     * @var string[]
     */
    protected $extraResourceNames = [];

    public function setExtendedCompat(array $extendedCompat): self
    {
        $this->extendedCompat = $extendedCompat;
        $this->extraResourceNames = array_keys($extendedCompat);
        return $this;
    }

    public function getAllForResource($resourceName)
    {
        $all = parent::getAllForResource($resourceName);
        foreach ($this->extendedCompat[$resourceName] ?? [] as $name) {
            if (isset($all[$name]) || !$this->has($name)) {
                continue;
            }
            $all[$name] = $this->get($name);
        }
        return $all;
    }

    public function standardizeResourcePageBlocks($blocksIn)
    {
        $blocksIn = is_array($blocksIn) ? $blocksIn : [];
        $blocksOut = [];
        $resourceNames = array_merge(['items', 'item_sets', 'media'], $this->extraResourceNames);
        foreach ($resourceNames as $resourceName) {
            if (isset($blocksIn[$resourceName]) && is_array($blocksIn[$resourceName])) {
                foreach ($blocksIn[$resourceName] as $regionName => $blockLayouts) {
                    if (!is_array($blockLayouts)) {
                        $blockLayouts = [];
                    }
                    $blocksOut[$resourceName][$regionName] = array_filter(array_map('strval', $blockLayouts));
                }
            } else {
                $blocksOut[$resourceName] = ['main' => []];
            }
        }
        return $blocksOut;
    }

    public function standardizeResourcePageRegions($regionsIn)
    {
        $regionsIn = is_array($regionsIn) ? $regionsIn : [];
        $regionsOut = [];
        $resourceNames = array_merge(['items', 'item_sets', 'media'], $this->extraResourceNames);
        foreach ($resourceNames as $resourceName) {
            if (isset($regionsIn[$resourceName]) && is_array($regionsIn[$resourceName])) {
                foreach ($regionsIn[$resourceName] as $regionName => $regionLabel) {
                    $regionsOut[$resourceName][$regionName] = strval($regionLabel);
                }
            } else {
                $regionsOut[$resourceName] = [];
            }
            if (!isset($regionsOut[$resourceName]['main'])) {
                $regionsOut[$resourceName]['main'] = 'Main'; // @translate
            }
        }
        return $regionsOut;
    }
}
