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

    /**
     * Compat with MediaRepresentation: a DigitalObject has no parent item.
     */
    public function item()
    {
        return null;
    }

    /**
     * Compat with MediaRepresentation: stable ingester name for the file.
     */
    public function ingester(): string
    {
        return 'digital_object';
    }

    /**
     * Compat with MediaRepresentation: default file renderer.
     */
    public function renderer(): string
    {
        return 'file';
    }

    public function valueRepresentation()
    {
        $representation = parent::valueRepresentation();
        if (!isset($representation['thumbnail_url']) && $this->hasThumbnails()) {
            $representation['thumbnail_url'] = $this->thumbnailUrl('square');
            $representation['thumbnail_title'] = $this->displayTitle();
            $representation['thumbnail_type'] = $this->mediaType();
        }
        return $representation;
    }

    public function thumbnailDisplayUrl($type)
    {
        $thumbnail = $this->thumbnail();
        if ($thumbnail) {
            return $thumbnail->assetUrl();
        }
        return $this->thumbnailUrl($type);
    }

    public function render(array $options = [])
    {
        $mediaType = (string) $this->mediaType();
        $primary = strtok($mediaType, '/');
        $title = $this->displayTitle();
        $alt = $this->altText() ?: $title;
        $view = $this->getServiceLocator()->get('ViewRenderer');
        $escape = $view->plugin('escapeHtml');
        $url = $this->originalUrl();

        if ($primary === 'image' && $this->hasThumbnails()) {
            return sprintf(
                '<a href="%s"><img src="%s" alt="%s"></a>',
                $escape($url),
                $escape($this->thumbnailUrl('large') ?: $this->thumbnailUrl('medium')),
                $escape($alt)
            );
        }
        if ($primary === 'audio') {
            return sprintf('<audio controls src="%s"></audio>', $escape($url));
        }
        if ($primary === 'video') {
            return sprintf('<video controls src="%s"></video>', $escape($url));
        }
        return sprintf('<a href="%s">%s</a>', $escape($url), $escape($title));
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
