<?php declare(strict_types=1);

namespace DigitalObject\ColumnType;

class Id extends \Omeka\ColumnType\Id
{
    public function getLabel(): string
    {
        return 'Digital object id'; // @translate
    }

    public function getResourceTypes(): array
    {
        return ['digital_objects'];
    }
}
