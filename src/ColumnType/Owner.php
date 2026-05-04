<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class Owner extends \Omeka\ColumnType\Owner
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
