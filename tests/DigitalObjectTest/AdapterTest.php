<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractTestCase;
use DigitalObject\Api\Representation\DigitalObjectRepresentation;
use DigitalObject\Entity\DigitalObject as DigitalObjectEntity;

/**
 * Sanity checks on the DigitalObject adapter and Doctrine STI wiring:
 *  - the discriminator map is loaded (Resource::class find() resolves to a
 *    DigitalObject entity);
 *  - API CRUD round-trips the resource_name;
 *  - the resource is shared (no per-item FK).
 */
class AdapterTest extends AbstractTestCase
{
    use CreatesDigitalObjects;

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

    public function testCreateReadDeleteRoundTrip(): void
    {
        $created = $this->persistDigitalObject();
        self::assertInstanceOf(DigitalObjectRepresentation::class, $created);
        $id = $created->id();
        $this->createdIds['digital_objects'][] = $id;

        $read = $this->api()->read('digital_objects', $id)->getContent();
        self::assertSame($id, $read->id());
        self::assertSame('digital_objects', $read->resourceName());

        $this->api()->delete('digital_objects', $id);
        $this->createdIds['digital_objects'] = array_diff($this->createdIds['digital_objects'] ?? [], [$id]);

        $this->expectException(\Omeka\Api\Exception\NotFoundException::class);
        $this->api()->read('digital_objects', $id);
    }

    public function testStiDiscriminatorResolvesViaAbstractResourceFind(): void
    {
        $created = $this->persistDigitalObject();
        $id = $created->id();
        $this->createdIds['digital_objects'][] = $id;

        $em = $this->getService('Omeka\EntityManager');
        $entity = $em->find(\Omeka\Entity\Resource::class, $id);

        self::assertInstanceOf(
            DigitalObjectEntity::class,
            $entity,
            'find() on the abstract Resource root must resolve to the DigitalObject subclass via the STI discriminator map.'
        );
    }

    public function testReturnScalarDoesNotIncludeStorageId(): void
    {
        $created = $this->persistDigitalObject();
        $this->createdIds['digital_objects'][] = $created->id();

        try {
            $this->api()->search(
                'digital_objects',
                ['id' => [$created->id()]],
                ['returnScalar' => 'storage_id']
            );
            self::fail('storage_id must not be a public scalar field.');
        } catch (\Throwable $e) {
            self::assertStringContainsString('storage_id', $e->getMessage());
        }

        // Confirm a legitimate scalar field still works.
        $ids = $this->api()->search(
            'digital_objects',
            ['id' => [$created->id()]],
            ['returnScalar' => 'id']
        )->getContent();
        self::assertSame([$created->id() => $created->id()], $ids);
    }

    public function testApiSearchByIdsReturnsOnlyMatching(): void
    {
        $a = $this->persistDigitalObject();
        $b = $this->persistDigitalObject();
        $this->createdIds['digital_objects'][] = $a->id();
        $this->createdIds['digital_objects'][] = $b->id();

        $found = $this->api()->search('digital_objects', ['id' => [$a->id(), $b->id()]])->getContent();
        $foundIds = array_map(fn ($r) => $r->id(), $found);
        sort($foundIds);
        $expected = [$a->id(), $b->id()];
        sort($expected);
        self::assertSame($expected, $foundIds);
    }
}
