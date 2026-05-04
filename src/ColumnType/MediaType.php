<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class MediaType extends \Omeka\ColumnType\MediaType
{
    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
