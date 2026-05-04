<?php declare(strict_types=1);

namespace DigitalObject\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;

class DigitalObjectAdapter extends AbstractResourceEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'is_public' => 'isPublic',
        'created' => 'created',
        'modified' => 'modified',
        'title' => 'title',
        'media_type' => 'mediaType',
        'size' => 'size',
        'extension' => 'extension',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'is_public' => 'isPublic',
        'created' => 'created',
        'modified' => 'modified',
        'title' => 'title',
        'source' => 'source',
        'media_type' => 'mediaType',
        'storage_id' => 'storageId',
        'extension' => 'extension',
        'sha256' => 'sha256',
        'size' => 'size',
        'has_original' => 'hasOriginal',
        'has_thumbnails' => 'hasThumbnails',
        'lang' => 'lang',
        'alt_text' => 'altText',
    ];

    public function getResourceName()
    {
        return 'digital_objects';
    }

    public function getRepresentationClass()
    {
        return \DigitalObject\Api\Representation\DigitalObjectRepresentation::class;
    }

    public function getEntityClass()
    {
        return \DigitalObject\Entity\DigitalObject::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        parent::buildQuery($qb, $query);
        $expr = $qb->expr();

        if (!empty($query['media_type'])) {
            $qb->andWhere($expr->eq('omeka_root.mediaType', $this->createNamedParameter($qb, $query['media_type'])));
        }
        if (!empty($query['sha256'])) {
            $qb->andWhere($expr->eq('omeka_root.sha256', $this->createNamedParameter($qb, $query['sha256'])));
        }
        if (!empty($query['has_original'])) {
            $qb->andWhere($expr->eq('omeka_root.hasOriginal', $this->createNamedParameter($qb, (bool) $query['has_original'])));
        }
    }
}
