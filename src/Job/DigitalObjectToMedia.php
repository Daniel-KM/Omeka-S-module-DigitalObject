<?php declare(strict_types=1);

namespace DigitalObject\Job;

use Doctrine\DBAL\Connection;
use Omeka\Job\AbstractJob;

/**
 * Reverse of MediaToDigitalObject: convert digital objects back to media,
 * attached to a single parent item.
 *
 * For each DigitalObject matching the filters:
 * - Resolve the parent item: a DO must be referenced by exactly one item via
 *   a value (any property). Skip DOs referenced by multiple items (would
 *   require duplication) or no item (would orphan the file).
 * - INSERT row in media copying the file columns, with item_id = parent and
 *   ingester = 'upload' (closest to what the original media was).
 * - UPDATE resource.resource_type back to Omeka\Entity\Media.
 * - DELETE the referencing value (the media now appears in the item's media
 *   list directly; the property value is redundant).
 * - DELETE row from digital_object. Files on disk untouched.
 *
 * Multi-referenced DOs are reported but not converted.
 */
class DigitalObjectToMedia extends AbstractJob
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
        $deleteValues = !isset($args['delete_values']) || $args['delete_values'];

        $doIds = $this->findDigitalObjects();
        $total = count($doIds);

        $shared = $this->findSharedDigitalObjects();
        if ($shared) {
            $this->logger->warn(
                '{count} digital objects are referenced by more than one item and will be skipped: {ids}', // @translate
                [
                    'count' => count($shared),
                    'ids' => implode(', ', array_slice($shared, 0, 20)) . (count($shared) > 20 ? '…' : ''),
                ]
            );
        }
        $sharedSet = array_flip($shared);
        $orphans = [];

        $this->logger->info(
            '{mode}: {count} digital objects will be converted back to media.', // @translate
            [
                'mode' => $dryRun ? 'Dry run' : 'Job',
                'count' => $total - count($shared),
            ]
        );

        if ($dryRun || $total === 0) {
            return;
        }

        $count = 0;
        foreach ($doIds as $doId) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Job stopped after converting {count} digital objects.', // @translate
                    ['count' => $count]
                );
                break;
            }
            if (isset($sharedSet[$doId])) {
                continue;
            }
            try {
                $converted = $this->convertOne($doId, $deleteValues);
                if ($converted === null) {
                    $orphans[] = $doId;
                } else {
                    $count++;
                }
            } catch (\Throwable $e) {
                $this->logger->err(
                    'Conversion failed for digital object {id}: {message}', // @translate
                    ['id' => $doId, 'message' => $e->getMessage()]
                );
            }
            if ($count > 0 && $count % 100 === 0) {
                $this->logger->info(
                    '{count} / {total} digital objects converted.', // @translate
                    ['count' => $count, 'total' => $total]
                );
            }
        }

        if ($orphans) {
            $this->logger->warn(
                '{count} digital objects are not referenced by any item and were skipped: {ids}', // @translate
                [
                    'count' => count($orphans),
                    'ids' => implode(', ', array_slice($orphans, 0, 20)) . (count($orphans) > 20 ? '…' : ''),
                ]
            );
        }

        $this->logger->info(
            'Conversion complete: {count} / {total} digital objects converted to media.', // @translate
            ['count' => $count, 'total' => $total]
        );
    }

    /**
     * @return int[]
     */
    protected function findDigitalObjects(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id FROM digital_object ORDER BY id ASC'
        );
        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * Find DOs referenced by more than one distinct item.
     *
     * @return int[]
     */
    protected function findSharedDigitalObjects(): array
    {
        $sql = <<<'SQL'
            SELECT v.value_resource_id
            FROM `value` v
            INNER JOIN digital_object d ON d.id = v.value_resource_id
            GROUP BY v.value_resource_id
            HAVING COUNT(DISTINCT v.resource_id) > 1
            SQL;
        $rows = $this->connection->fetchAllAssociative($sql);
        return array_map('intval', array_column($rows, 'value_resource_id'));
    }

    /**
     * Convert a single DO to a media. Returns null if no parent item was found
     * (orphan), the new media id otherwise.
     */
    protected function convertOne(int $doId, bool $deleteValues): ?int
    {
        $this->connection->beginTransaction();
        try {
            $row = $this->connection->createQueryBuilder()
                ->select('d.id, d.data, d.source, d.media_type, d.storage_id, d.extension, d.sha256, d.size, d.has_original, d.has_thumbnails, d.lang, d.alt_text')
                ->from('digital_object', 'd')
                ->where('d.id = :id')
                ->setParameter('id', $doId)
                ->execute()
                ->fetchAssociative();
            if (!$row) {
                throw new \RuntimeException('Digital object not found');
            }

            // Find the parent item: any value pointing to this DO.
            $itemId = $this->connection->fetchOne(
                'SELECT resource_id FROM `value` WHERE value_resource_id = :id LIMIT 1',
                ['id' => $doId]
            );
            if (!$itemId) {
                $this->connection->rollBack();
                return null;
            }
            $itemId = (int) $itemId;

            // Determine next position for the media on the item.
            $position = (int) $this->connection->fetchOne(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM media WHERE item_id = :i',
                ['i' => $itemId]
            );

            // Restore the media row. Use ingester=upload (the only safe
            // assumption for a file-backed DO) and renderer=file.
            $this->connection->insert('media', [
                'item_id' => $itemId,
                'id' => $row['id'],
                'ingester' => 'upload',
                'renderer' => 'file',
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
                'position' => $position,
            ]);

            // Drop the value(s) on the parent item that pointed to the DO: they
            // become redundant now that the file is a real media of the item.
            if ($deleteValues) {
                $this->connection->executeStatement(
                    'DELETE FROM `value` WHERE value_resource_id = :id',
                    ['id' => $doId]
                );
            }

            // Remove the digital_object row.
            $this->connection->delete('digital_object', ['id' => $doId]);

            // Re-type the resource.
            $this->connection->executeStatement(
                'UPDATE resource SET resource_type = :rt WHERE id = :id',
                ['rt' => \Omeka\Entity\Media::class, 'id' => $doId]
            );

            $this->connection->commit();
            return $doId;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
