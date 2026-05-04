<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractTestCase;
use DigitalObject\Api\Representation\DigitalObjectRepresentation;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * Cover the DigitalObject representation API surface relied on by the IIIF and
 * tile pipelines: transient item context, Media compat stubs, URLs.
 */
class RepresentationTest extends AbstractTestCase
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

    public function testCompatStubsMatchMediaIngesterRendererConvention(): void
    {
        $do = $this->createMinimalDigitalObject();

        self::assertInstanceOf(DigitalObjectRepresentation::class, $do);
        self::assertSame('digital_object', $do->ingester(), 'Singular per Omeka media_ingesters convention.');
        self::assertSame('file', $do->renderer(), 'DOs default to the core file renderer.');
        self::assertSame('digital_objects', $do->resourceName());
        self::assertSame('digital-object', $do->getControllerName());
    }

    public function testItemContextDefaultsToNullAndCanBeSet(): void
    {
        $do = $this->createMinimalDigitalObject();

        self::assertNull($do->item(), 'No parent item until setItem() is called.');

        $item = $this->createMinimalItem();
        $do->setItem($item);

        self::assertSame($item->id(), $do->item()?->id());
        self::assertInstanceOf(ItemRepresentation::class, $do->item());
    }

    public function testSiteUrlAndAdminUrlUseDedicatedRoutes(): void
    {
        $do = $this->createMinimalDigitalObject();
        $id = $do->id();

        self::assertStringContainsString(
            '/admin/digital-object/' . $id,
            $do->adminUrl()
        );

        // siteUrl needs a current site context; bypass by passing slug.
        self::assertStringContainsString(
            '/s/test/digital-object/' . $id,
            $do->siteUrl('test')
        );
    }

    public function testJsonLdTypeIsOmekaDigitalObject(): void
    {
        $do = $this->createMinimalDigitalObject();
        self::assertSame('o:DigitalObject', $do->getResourceJsonLdType());
    }

    protected function createMinimalDigitalObject(): DigitalObjectRepresentation
    {
        $response = $this->api()->create('digital_objects', []);
        $do = $response->getContent();
        $this->createdIds['digital_objects'][] = $do->id();
        return $do;
    }

    protected function createMinimalItem(): ItemRepresentation
    {
        $response = $this->api()->create('items', []);
        $item = $response->getContent();
        $this->createdIds['items'][] = $item->id();
        return $item;
    }
}
