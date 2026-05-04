<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class Created extends \Omeka\ColumnType\Created
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
