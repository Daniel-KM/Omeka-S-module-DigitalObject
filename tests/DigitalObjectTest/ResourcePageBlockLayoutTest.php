<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractTestCase;
use DigitalObject\Site\ResourcePageBlockLayout\ExtendedManager;
use DigitalObject\View\Helper\ResourcePageBlocks;

/**
 * Cover the resource page block layout extension wired by the module:
 *  - the manager is the extended subclass;
 *  - getAllForResource('digital_objects') includes the configured core
 *    blocks (resourceClass, values, linkedResources, lightboxGallery);
 *  - the resourcePageBlocks view helper accepts a digital object resource
 *    instead of throwing "Invalid resource".
 */
class ResourcePageBlockLayoutTest extends AbstractTestCase
{
    use CreatesDigitalObjects;

    public function testManagerIsExtendedSubclass(): void
    {
        $manager = $this->getService('Omeka\ResourcePageBlockLayoutManager');
        self::assertInstanceOf(
            ExtendedManager::class,
            $manager,
            'Module must replace the core resource page block layout manager.'
        );
    }

    public function testGetAllForResourceDigitalObjectsIncludesExtendedCompat(): void
    {
        $manager = $this->getService('Omeka\ResourcePageBlockLayoutManager');
        $all = $manager->getAllForResource('digital_objects');

        // Module-native block for DO show page: declares digital_objects.
        self::assertArrayHasKey('digital_object_render', $all);
        // digital_objects_embeds / digital_objects_list target items, not DO
        // pages themselves; they must NOT show up here.
        self::assertArrayNotHasKey('digital_objects_embeds', $all);
        self::assertArrayNotHasKey('digital_objects_list', $all);

        // Core blocks injected via extendedCompat (do not declare DO).
        self::assertArrayHasKey('resourceClass', $all);
        self::assertArrayHasKey('values', $all);
        self::assertArrayHasKey('linkedResources', $all);
        self::assertArrayHasKey('lightboxGallery', $all);
    }

    public function testGetAllForResourceItemsUnaffected(): void
    {
        $manager = $this->getService('Omeka\ResourcePageBlockLayoutManager');
        $all = $manager->getAllForResource('items');

        // Items compat is unchanged: core blocks still listed.
        self::assertArrayHasKey('values', $all);
        self::assertArrayHasKey('resourceClass', $all);
        // DO-specific blocks must not leak into items.
        self::assertArrayNotHasKey('digital_object_render', $all);
    }

    public function testStandardizeBlocksRecognizesDigitalObjects(): void
    {
        $manager = $this->getService('Omeka\ResourcePageBlockLayoutManager');
        $out = $manager->standardizeResourcePageBlocks([
            'items' => ['main' => ['values']],
            'digital_objects' => ['main' => ['digital_object_render']],
        ]);
        self::assertArrayHasKey('digital_objects', $out);
        self::assertSame(['digital_object_render'], $out['digital_objects']['main']);
    }

    public function testResourcePageBlocksHelperAcceptsDigitalObject(): void
    {
        $helper = $this->getViewHelper('resourcePageBlocks');
        self::assertInstanceOf(ResourcePageBlocks::class, $helper);

        // Use a freshly created DO so the helper has a real resource.
        $api = $this->getService('Omeka\ApiManager');
        $auth = $this->getService('Omeka\AuthenticationService');
        if (!$auth->hasIdentity()) {
            $adapter = $auth->getAdapter();
            $adapter->setIdentity('admin@example.com');
            $adapter->setCredential('root');
            $auth->authenticate();
        }
        $do = $this->persistDigitalObject();
        try {
            $result = $helper($do);
            self::assertSame($helper, $result, 'Helper must accept a DigitalObject and return itself.');
        } finally {
            try {
                $api->delete('digital_objects', $do->id());
            } catch (\Throwable $e) {
            }
        }
    }
}
