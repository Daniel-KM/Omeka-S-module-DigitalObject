<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class Size extends \Omeka\ColumnType\Size
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
