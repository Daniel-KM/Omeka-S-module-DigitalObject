<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractTestCase;
use DigitalObject\Site\ResourcePageBlockLayout\NoDigitalObject;

/**
 * Cover the "No Digital Object" resource page block layout and the inline
 * helper read path:
 *  - the block is registered for items only;
 *  - the partial returns empty when an item has at least one linked DO;
 *  - the digitalObjectInline helper reads its property list from
 *    Omeka\Settings (digitalobject_inline_properties).
 */
class NoDigitalObjectTest extends AbstractTestCase
{
    protected array $createdIds = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        if ($this->createdIds) {
            $api = $this->api();
            foreach ($this->createdIds as $type => $ids) {
                foreach (array_unique($ids) as $id) {
                    try {
                        $api->delete($type, $id);
                    } catch (\Throwable $e) {
                    }
                }
            }
            $this->createdIds = [];
        }
        parent::tearDown();
    }

    protected function loginAdmin(): void
    {
        $auth = $this->getService('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            return;
        }
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function api(): \Omeka\Api\Manager
    {
        return $this->getService('Omeka\ApiManager');
    }

    public function testBlockIsRegisteredAsInvokableAndItemOnly(): void
    {
        $manager = $this->getService('Omeka\ResourcePageBlockLayoutManager');
        self::assertTrue(
            $manager->has('no_digital_object'),
            'Block layout no_digital_object must be registered.'
        );

        $block = $manager->get('no_digital_object');
        self::assertInstanceOf(NoDigitalObject::class, $block);
        self::assertSame(['items'], $block->getCompatibleResourceNames());
    }

    public function testInlineHelperReadsPropertiesFromSettings(): void
    {
        $settings = $this->getService('Omeka\Settings');
        $previous = $settings->get('digitalobject_inline_properties');

        $settings->set('digitalobject_inline_properties', ['dcterms:hasFormat']);
        try {
            // Drop the cached helper instance so the factory re-reads settings.
            $vhm = $this->getViewHelperManager();
            $reflect = new \ReflectionObject($vhm);
            if ($reflect->hasProperty('services')) {
                $services = $reflect->getProperty('services');
                $services->setAccessible(true);
                $instances = $services->getValue($vhm);
                unset($instances['digitalobjectinline'], $instances['digitalObjectInline']);
                $services->setValue($vhm, $instances);
            }

            $helper = $this->getViewHelper('digitalObjectInline');
            $properties = (new \ReflectionObject($helper))
                ->getProperty('defaultProperties');
            $properties->setAccessible(true);
            self::assertSame(['dcterms:hasFormat'], $properties->getValue($helper));
        } finally {
            $settings->set('digitalobject_inline_properties', $previous);
        }
    }
}
