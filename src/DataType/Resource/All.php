<?php declare(strict_types=1);

namespace DigitalObject\DataType\Resource;

use DigitalObject\Entity\DigitalObject;

class All extends \Omeka\DataType\Resource\All
{
    public function getValidValueResources()
    {
        return array_merge(parent::getValidValueResources(), [DigitalObject::class]);
    }
}
