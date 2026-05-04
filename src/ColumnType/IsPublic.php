<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class IsPublic extends \Omeka\ColumnType\IsPublic
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
