<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class Value extends \Omeka\ColumnType\Value
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
