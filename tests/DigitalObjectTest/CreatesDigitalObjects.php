<?php declare(strict_types=1);

namespace DigitalObjectTest;

use DigitalObject\Api\Representation\DigitalObjectRepresentation;
use DigitalObject\Entity\DigitalObject as DigitalObjectEntity;

/**
 * Fixture helper that persists a minimal file-backed digital object directly
 * through the entity manager. The api create operation now requires an actual
 * uploaded file or a url, which is not available offline in tests, so the
 * adapter pipeline is bypassed for fixtures.
 */
trait CreatesDigitalObjects
{
    protected function persistDigitalObject(): DigitalObjectRepresentation
    {
        $entityManager = $this->getService('Omeka\EntityManager');
        $entity = new DigitalObjectEntity();
        $entity->setCreated(new \DateTime('now'));
        $entity->setStorageId(bin2hex(random_bytes(8)));
        $entity->setExtension('jpg');
        $entity->setMediaType('image/jpeg');
        $entity->setSha256(hash('sha256', uniqid('do', true)));
        $entity->setSize(1);
        $entity->setHasOriginal(true);
        $entityManager->persist($entity);
        $entityManager->flush();

        return $this->getService('Omeka\ApiManager')
            ->read('digital_objects', $entity->getId())->getContent();
    }
}
