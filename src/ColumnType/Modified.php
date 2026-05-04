<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class Modified extends \Omeka\ColumnType\Modified
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
