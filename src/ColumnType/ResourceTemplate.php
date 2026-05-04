<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class ResourceTemplate extends \Omeka\ColumnType\ResourceTemplate
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
