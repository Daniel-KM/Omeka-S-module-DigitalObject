<?php declare(strict_types=1);

namespace DigitalObject\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\File\TempFile;
use Omeka\Stdlib\ErrorStore;

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

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        if (Request::CREATE === $request->getOperation()) {
            if (isset($data['data'])) {
                $entity->setData($data['data']);
            }
            if (isset($data['o:source'])) {
                $entity->setSource($data['o:source']);
            }
        }

        parent::hydrate($request, $entity, $errorStore);

        if ($this->shouldHydrate($request, 'o:lang')) {
            $entity->setLang($request->getValue('o:lang', null));
        }
        if ($this->shouldHydrate($request, 'o:alt_text')) {
            $entity->setAltText($request->getValue('o:alt_text'));
        }

        $this->hydrateFile($request, $entity, $errorStore);
    }

    protected function hydrateFile(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        $fileData = $request->getFileData();
        $content = $request->getContent();

        $services = $this->getServiceLocator();
        $uploader = $services->get('Omeka\File\Uploader');

        $tempFile = null;
        if (isset($fileData['file'])) {
            $upload = is_array($fileData['file']) && isset($fileData['file']['tmp_name'])
                ? $fileData['file']
                : (isset($fileData['file'][0]) ? $fileData['file'][0] : null);
            if ($upload && !empty($upload['tmp_name'])) {
                $tempFile = $uploader->upload($upload, $errorStore);
                if ($tempFile) {
                    $tempFile->setSourceName($upload['name']);
                    if (empty($content['o:source'])) {
                        $entity->setSource($upload['name']);
                    }
                }
            }
        } elseif (!empty($content['ingest_filename'])) {
            $tempFile = $this->sideloadTempFile((string) $content['ingest_filename'], $errorStore);
            if ($tempFile && empty($content['o:source'])) {
                $entity->setSource((string) $content['ingest_filename']);
            }
        } elseif (!empty($content['ingest_url'])) {
            $this->hydrateUrl((string) $content['ingest_url'], $entity, $errorStore);
            return;
        } elseif (Request::CREATE === $request->getOperation()) {
            $errorStore->addError('file', 'A file or a URL is required.'); // @translate
            return;
        }

        if (!$tempFile) {
            return;
        }

        $this->applyTempFile($tempFile, $entity);
    }

    /**
     * Build a temp file from a sideloaded file. Requires the FileSideload
     * module, which confines the path to its configured directory; the file is
     * copied to a real temp path so the original is never deleted by
     * applyTempFile().
     */
    protected function sideloadTempFile(string $filename, ErrorStore $errorStore): ?TempFile
    {
        $services = $this->getServiceLocator();

        // Sideload requires the FileSideload module: it confines the file to
        // the configured directory (verifyFileOrDir). Without it, accepting an
        // arbitrary path would allow reading any server-readable file (LFI), so
        // it is refused.
        if (!class_exists(\FileSideload\Module::class)
            || !$services->has('FileSideload\FileSystem')
        ) {
            $errorStore->addError('ingest_filename', 'Sideload requires the module FileSideload.'); // @translate
            return null;
        }

        $directory = $services->get('Omeka\Settings')->get('file_sideload_directory');
        $directory = $directory ? realpath($directory) : '';
        $isAbsoluteInside = $directory && strpos($filename, $directory) === 0;
        $filepath = $isAbsoluteInside
            ? $filename
            : $directory . DIRECTORY_SEPARATOR . $filename;
        $realPath = $services->get('FileSideload\FileSystem')
            ->verifyFileOrDir(new \SplFileInfo($filepath));

        if (!$realPath) {
            $errorStore->addError('ingest_filename', sprintf(
                'Cannot sideload file "%s". File does not exist or does not have sufficient permissions.', // @translate
                $filename
            ));
            return null;
        }

        $tempFile = $services->get('Omeka\File\TempFileFactory')->build();
        if (!copy($realPath, $tempFile->getTempPath())) {
            $errorStore->addError('ingest_filename', sprintf('Cannot copy sideloaded file "%s".', $filename)); // @translate
            return null;
        }
        $tempFile->setSourceName(basename($filename));
        return $tempFile;
    }

    /**
     * Ingest from a URL: either a IIIF Image info.json (stored as data and
     * served from the remote image service) or a plain file to download.
     */
    protected function hydrateUrl(string $url, EntityInterface $entity, ErrorStore $errorStore): void
    {
        $services = $this->getServiceLocator();
        $info = $this->fetchIiifImageInfo($url);
        if ($info !== null) {
            $entity->setSource($url);
            $entity->setData($info);
            $entity->setStorageId(null);
            $entity->setExtension(null);
            $entity->setHasOriginal(false);
            $entity->setHasThumbnails(false);
            return;
        }

        $downloader = $services->get('Omeka\File\Downloader');
        $tempFile = $downloader->download($url, $errorStore);
        if (!$tempFile) {
            return;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $tempFile->setSourceName($path !== '' ? basename($path) : $url);
        $entity->setSource($url);
        $this->applyTempFile($tempFile, $entity);
    }

    /**
     * Fetch a remote IIIF Image info.json, or null when the URL is not one.
     */
    protected function fetchIiifImageInfo(string $url): ?array
    {
        $services = $this->getServiceLocator();
        try {
            $client = $services->get('Omeka\HttpClient');
            $client->reset();
            $client->setUri($url);
            $client->setMethod('HEAD');
            $head = $client->send();
            $header = $head->isSuccess() ? $head->getHeaders()->get('Content-Type') : false;
            $contentType = $header ? strtolower((string) $header->getFieldValue()) : '';
            $looksJson = strpos($contentType, 'json') !== false
                || substr($url, -strlen('info.json')) === 'info.json';
            if (!$looksJson) {
                return null;
            }
            $client->reset();
            $client->setUri($url);
            $client->setMethod('GET');
            $client->setHeaders(['Accept' => 'application/json']);
            $response = $client->send();
            if (!$response->isSuccess()) {
                return null;
            }
            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                return null;
            }
            $context = $data['@context'] ?? '';
            $context = is_array($context) ? implode(' ', $context) : (string) $context;
            return (($data['protocol'] ?? null) === 'http://iiif.io/api/image'
                    || strpos($context, 'iiif.io/api/image') !== false)
                ? $data
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function applyTempFile(TempFile $tempFile, EntityInterface $entity): void
    {
        $prefix = $this->getServiceLocator()
            ->get('Config')['digitalobject']['config']['digitalobject_storage_prefix'] ?? '';
        $storageId = $prefix . $tempFile->getStorageId();
        $tempFile->setStorageId($storageId);

        $entity->setStorageId($tempFile->getStorageId());
        $entity->setExtension($tempFile->getExtension());
        $entity->setMediaType($tempFile->getMediaType());
        $entity->setSha256($tempFile->getSha256());
        $entity->setSize($tempFile->getSize());

        $tempFile->storeOriginal();
        $entity->setHasOriginal(true);

        $hasThumbnails = $tempFile->storeThumbnails();
        $entity->setHasThumbnails((bool) $hasThumbnails);

        $tempFile->delete();
    }
}
