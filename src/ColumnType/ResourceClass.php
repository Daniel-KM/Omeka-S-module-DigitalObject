<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class ResourceClass extends \Omeka\ColumnType\ResourceClass
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
