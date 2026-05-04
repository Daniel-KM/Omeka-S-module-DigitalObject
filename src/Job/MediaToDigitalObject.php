<?php declare(strict_types=1);

namespace DigitalObject\Job;

use Doctrine\DBAL\Connection;
use Omeka\Job\AbstractJob;

/**
 * Convert media to digital objects.
 *
 * For each media matching the filters:
 * - INSERT row in digital_object copying the file columns.
 * - UPDATE resource.resource_type to DigitalObject\Entity\DigitalObject.
 * - INSERT a value on the parent item, of type resource:digitalobject,
 *   property = configured (default dcterms:hasFormat), value_resource_id = id,
 *   positioned right after existing values of the same property (preserving the
 *   original media position order).
 * - DELETE row from media. Files on disk are untouched (storage_id stays the
 * same).
 */
class MediaToDigitalObject extends AbstractJob
{
    /** @var Connection */
    protected $connection;

    /** @var \Omeka\Stdlib\Logger */
    protected $logger;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->connection = $services->get('Omeka\Connection');
        $this->logger = $services->get('Omeka\Logger');

        $args = $this->job->getArgs();
        $dryRun = !empty($args['dry_run']);
        $onlyWithFile = $args['only_with_file'] ?? true;
        $ingesters = $this->normalizeList($args['ingester'] ?? '');
        $mediaTypes = $this->normalizeList($args['media_types'] ?? '');
        $propertyTerm = trim($args['property'] ?? 'dcterms:hasFormat');

        // Resolve item query (Omeka\Form\Element\Query stores a URL-encoded
        // query string). Empty = all items.
        $itemIds = [];
        $queryString = $args['query'] ?? '';
        if (is_string($queryString) && $queryString !== '') {
            $query = [];
            parse_str(ltrim($queryString, '?'), $query);
            if ($query) {
                $query['limit'] = 0;
                $itemIds = $services->get('Omeka\ApiManager')
                    ->search('items', $query, ['returnScalar' => 'id'])
                    ->getContent();
                $itemIds = array_values(array_map('intval', $itemIds));
                if (!$itemIds) {
                    $this->logger->info('Query returned no item, nothing to convert.'); // @translate
                    return;
                }
            }
        }

        // Hard prerequisite: storage_id is UNIQUE inside each table but not
        // cross-table. The migration INSERTs into digital_object with the
        // media's storage_id; any preexisting digital_object row sharing a
        // storage_id with a media would cause a duplicate key error. Abort
        // early if any collision exists.
        $collisions = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM media m INNER JOIN digital_object d ON m.storage_id = d.storage_id AND m.storage_id IS NOT NULL'
        );
        if ($collisions > 0) {
            $this->logger->err(
                '{count} storage_id collisions between table "media" and table "digital_object". Migration aborted. Resolve collisions (delete duplicate digital objects or rename storage_ids) and retry.', // @translate
                ['count' => $collisions]
            );
            return;
        }

        $propertyId = $this->propertyId($propertyTerm);
        if (!$propertyId) {
            $this->logger->err(
                'Property "{term}" not found, abort.', // @translate
                ['term' => $propertyTerm]
            );
            return;
        }

        $mediaIds = $this->findMedia($onlyWithFile, $ingesters, $mediaTypes, $itemIds);
        $total = count($mediaIds);
        $this->logger->info(
            '{mode}: {total} media will be converted to digital objects (property {term}).', // @translate
            [
                'mode' => $dryRun ? 'Dry run' : 'Job',
                'total' => $total,
                'term' => $propertyTerm,
            ]
        );

        if ($dryRun || $total === 0) {
            return;
        }

        $resourceType = \DigitalObject\Entity\DigitalObject::class;
        $count = 0;
        foreach ($mediaIds as $mediaId) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Job stopped after converting {count} media.', // @translate
                    ['count' => $count]
                );
                break;
            }
            try {
                $this->convertOne($mediaId, $propertyId, $resourceType);
                $count++;
            } catch (\Throwable $e) {
                $this->logger->err(
                    'Conversion failed for media {id}: {message}', // @translate
                    ['id' => $mediaId, 'message' => $e->getMessage()]
                );
            }
            if ($count % 100 === 0) {
                $this->logger->info(
                    '{count} / {total} media converted.', // @translate
                    ['count' => $count, 'total' => $total]
                );
            }
        }
        $this->logger->info(
            'Conversion complete: {count} / {total} media converted.', // @translate
            ['count' => $count, 'total' => $total]
        );
    }

    /**
     * @return int[]
     */
    protected function findMedia(bool $onlyWithFile, array $ingesters, array $mediaTypes, array $itemIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('m.id')
            ->from('media', 'm')
            ->orderBy('m.item_id', 'ASC')
            ->addOrderBy('m.position', 'ASC')
            ->addOrderBy('m.id', 'ASC');

        if ($onlyWithFile) {
            $qb->andWhere('m.has_original = 1');
        }
        if ($ingesters) {
            $qb->andWhere('m.ingester IN (:ingesters)')
                ->setParameter('ingesters', $ingesters, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
        }
        if ($mediaTypes) {
            $orx = $qb->expr()->orX();
            foreach ($mediaTypes as $i => $mt) {
                if (substr($mt, -1) === '/') {
                    $orx->add($qb->expr()->like('m.media_type', ':mt' . $i));
                    $qb->setParameter('mt' . $i, addcslashes($mt, '%_\\') . '%');
                } else {
                    $orx->add($qb->expr()->eq('m.media_type', ':mt' . $i));
                    $qb->setParameter('mt' . $i, $mt);
                }
            }
            $qb->andWhere($orx);
        }
        if ($itemIds) {
            $qb->andWhere('m.item_id IN (:item_ids)')
                ->setParameter('item_ids', $itemIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
        }

        $rows = $qb->execute()->fetchAllAssociative();
        return array_map('intval', array_column($rows, 'id'));
    }

    protected function convertOne(int $mediaId, int $propertyId, string $resourceType): void
    {
        $this->connection->beginTransaction();
        try {
            $row = $this->connection->createQueryBuilder()
                ->select('m.id, m.item_id, m.data, m.source, m.media_type, m.storage_id, m.extension, m.sha256, m.size, m.has_original, m.has_thumbnails, m.lang, m.alt_text, m.position')
                ->from('media', 'm')
                ->where('m.id = :id')
                ->setParameter('id', $mediaId)
                ->execute()
                ->fetchAssociative();
            if (!$row) {
                throw new \RuntimeException('Media not found');
            }
            $itemId = (int) $row['item_id'];

            // Move file metadata into digital_object.
            $this->connection->insert('digital_object', [
                'id' => $row['id'],
                'data' => $row['data'],
                'source' => $row['source'],
                'media_type' => $row['media_type'],
                'storage_id' => $row['storage_id'],
                'extension' => $row['extension'],
                'sha256' => $row['sha256'],
                'size' => $row['size'],
                'has_original' => $row['has_original'],
                'has_thumbnails' => $row['has_thumbnails'],
                'lang' => $row['lang'],
                'alt_text' => $row['alt_text'],
            ]);

            // Add a value on the item referencing the new DO. Position matches
            // the original media position so the order of digital objects on
            // the item mirrors the original media order.
            // "value" is a reserved keyword in MariaDB; DBAL Connection::insert
            // does not quote table identifiers, so use a raw statement.
            $this->connection->executeStatement(
                'INSERT INTO `value` (resource_id, property_id, value_resource_id, type, is_public) VALUES (:r, :p, :vr, :t, 1)',
                [
                    'r' => $itemId,
                    'p' => $propertyId,
                    'vr' => $row['id'],
                    't' => 'resource:digitalobject',
                ]
            );

            // Remove the media row (resource row will be re-typed).
            $this->connection->delete('media', ['id' => $mediaId]);

            // Re-type the resource.
            $this->connection->executeStatement(
                'UPDATE resource SET resource_type = :rt WHERE id = :id',
                ['rt' => $resourceType, 'id' => $mediaId]
            );

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    protected function propertyId(string $term): ?int
    {
        [$prefix, $local] = array_pad(explode(':', $term, 2), 2, null);
        if (!$prefix || !$local) {
            return null;
        }
        $sql = <<<'SQL'
            SELECT p.id
            FROM property p
            JOIN vocabulary v ON v.id = p.vocabulary_id
            WHERE v.prefix = :prefix AND p.local_name = :local
            LIMIT 1
            SQL;
        $id = $this->connection->fetchOne($sql, ['prefix' => $prefix, 'local' => $local]);
        return $id ? (int) $id : null;
    }

    protected function normalizeList(string $list): array
    {
        $list = preg_split('/[\s,]+/', trim($list));
        return array_values(array_filter(array_map('trim', $list ?: [])));
    }
}
