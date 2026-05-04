<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractTestCase;
use DigitalObject\DataType\DigitalObject;
use DigitalObject\DataType\Resource\All;
use DigitalObject\Entity\DigitalObject as DigitalObjectEntity;

/**
 * Cover the two data types registered by the module:
 *  - resource:digitalobject (dedicated DO picker)
 *  - resource (generic, overridden to also accept DOs alongside Item/ItemSet/
 *    Media so item editors can pick a DO via the generic resource selector).
 */
class DataTypeTest extends AbstractTestCase
{
    public function testDedicatedDataTypeAcceptsOnlyDigitalObjectEntity(): void
    {
        /** @var DigitalObject $dt */
        $dt = $this->getDataTypeManager()->get('resource:digitalobject');

        self::assertSame('resource:digitalobject', $dt->getName());
        self::assertSame([DigitalObjectEntity::class], $dt->getValidValueResources());
    }

    public function testGenericResourceDataTypeIsOverriddenAndAcceptsDigitalObject(): void
    {
        /** @var All $dt */
        $dt = $this->getDataTypeManager()->get('resource');

        self::assertInstanceOf(All::class, $dt, 'Module must override the core "resource" data type.');

        $valid = $dt->getValidValueResources();
        self::assertContains(DigitalObjectEntity::class, $valid);
        self::assertContains(\Omeka\Entity\Item::class, $valid);
        self::assertContains(\Omeka\Entity\ItemSet::class, $valid);
        self::assertContains(\Omeka\Entity\Media::class, $valid);
    }

    protected function getDataTypeManager(): \Omeka\DataType\Manager
    {
        return $this->getService('Omeka\DataTypeManager');
    }
}
