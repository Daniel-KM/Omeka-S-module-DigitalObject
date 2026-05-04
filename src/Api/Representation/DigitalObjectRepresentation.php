<?php declare(strict_types=1);

namespace DigitalObject\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class DigitalObjectRepresentation extends AbstractResourceEntityRepresentation
{
    public function getControllerName()
    {
        return 'digital-object';
    }

    public function getResourceJsonLdType()
    {
        return 'o:DigitalObject';
    }

    public function getResourceJsonLd()
    {
        return [
            'o:source' => $this->source(),
            'o:media_type' => $this->mediaType(),
            'o:storage_id' => $this->storageId(),
            'o:extension' => $this->extension(),
            'o:sha256' => $this->sha256(),
            'o:size' => $this->size(),
            'o:has_original' => $this->hasOriginal(),
            'o:has_thumbnails' => $this->hasThumbnails(),
            'o:original_url' => $this->originalUrl(),
            'o:thumbnail_urls' => $this->thumbnailUrls(),
            'o:lang' => $this->lang(),
            'o:alt_text' => $this->altText(),
            'data' => $this->mediaData(),
        ];
    }

    public function mediaData()
    {
        return $this->resource->getData();
    }

    public function source(): ?string
    {
        return $this->resource->getSource();
    }

    public function mediaType(): ?string
    {
        return $this->resource->getMediaType();
    }

    public function storageId(): ?string
    {
        return $this->resource->getStorageId();
    }

    public function extension(): ?string
    {
        return $this->resource->getExtension();
    }

    public function filename(): ?string
    {
        return $this->resource->getFilename();
    }

    public function sha256(): ?string
    {
        return $this->resource->getSha256();
    }

    public function size(): ?int
    {
        $size = $this->resource->getSize();
        return $size === null ? null : (int) $size;
    }

    public function hasOriginal(): bool
    {
        return $this->resource->hasOriginal();
    }

    public function hasThumbnails(): bool
    {
        return $this->resource->hasThumbnails();
    }

    public function lang(): ?string
    {
        return $this->resource->getLang();
    }

    public function altText(): ?string
    {
        return $this->resource->getAltText();
    }

    public function originalUrl(): ?string
    {
        if (!$this->hasOriginal()) {
            return null;
        }
        return $this->getFileUrl('original', $this->filename());
    }

    public function thumbnailUrl($type): ?string
    {
        $manager = $this->getServiceLocator()->get('Omeka\File\ThumbnailManager');
        $types = $manager->getTypes();
        if (!$this->hasThumbnails() || !in_array($type, $types, true)) {
            return null;
        }
        return $this->getFileUrl($type, $this->storageId(), 'jpg');
    }

    public function thumbnailUrls(): array
    {
        if (!$this->hasThumbnails()) {
            return [];
        }
        $manager = $this->getServiceLocator()->get('Omeka\File\ThumbnailManager');
        $urls = [];
        foreach ($manager->getTypes() as $type) {
            $urls[$type] = $this->thumbnailUrl($type);
        }
        return $urls;
    }

    public function primaryMedia()
    {
        return null;
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        $params = ['id' => $this->id()];
        if ($action !== null) {
            $params['action'] = $action;
        }
        return $url(
            'admin/digital-object/id',
            $params,
            ['force_canonical' => $canonical]
        );
    }
}
