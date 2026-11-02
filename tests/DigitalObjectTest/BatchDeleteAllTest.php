<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractHttpControllerTestCase;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Entity\Job;
use Omeka\Job\BatchDelete;

class BatchDeleteAllTest extends AbstractHttpControllerTestCase
{
    use CreatesDigitalObjects;

    protected array $digitalObjectIds = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function tearDown(): void
    {
        foreach ($this->digitalObjectIds as $id) {
            try {
                $this->api()->delete('digital_objects', $id);
            } catch (\Throwable $e) {
            }
        }
        parent::tearDown();
    }

    public function testActionRequiresValidConfirmForm(): void
    {
        $digitalObject = $this->persistDigitalObject();
        $this->digitalObjectIds[] = $digitalObject->id();

        $this->dispatch('/admin/digital-object/batch-delete-all', 'POST', [
            'query' => json_encode(['id' => $digitalObject->id()]),
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertSame(
            $digitalObject->id(),
            $this->api()->read('digital_objects', $digitalObject->id())->getContent()->id()
        );
    }

    public function testActionRedirectsOnGet(): void
    {
        $this->dispatch('/admin/digital-object/batch-delete-all');
        $this->assertResponseStatusCode(302);
    }

    public function testJobDeletesOnlyDigitalObjectsMatchingQuery(): void
    {
        $deleted = $this->persistDigitalObject();
        $kept = $this->persistDigitalObject();
        $this->digitalObjectIds = [$deleted->id(), $kept->id()];

        $job = new Job();
        $job->setClass(BatchDelete::class);
        $job->setStatus(Job::STATUS_IN_PROGRESS);
        $job->setArgs([
            'resource' => 'digital_objects',
            'query' => ['id' => $deleted->id()],
        ]);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($job);
        $entityManager->flush();
        (new BatchDelete($job, $this->getApplicationServiceLocator()))->perform();
        $entityManager->remove($job);
        $entityManager->flush();

        $this->assertSame($kept->id(), $this->api()->read('digital_objects', $kept->id())->getContent()->id());
        $this->expectException(NotFoundException::class);
        $this->api()->read('digital_objects', $deleted->id());
    }

    protected function getService(string $name)
    {
        return $this->getApplicationServiceLocator()->get($name);
    }
}
