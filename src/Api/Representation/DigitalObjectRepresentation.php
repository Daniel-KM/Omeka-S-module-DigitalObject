<?php declare(strict_types=1);

namespace DigitalObject\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;

class DigitalObjectRepresentation extends AbstractResourceEntityRepresentation
{
    protected ?ItemRepresentation $contextItem = null;

    /**
     * Transient parent item, set by callers iterating a DO inside an item.
     *
     * This method simplifies processes that use media and allows to adapt them
     * quickly without modifying them ((manifest building, sidebar inline, etc).
     * DigitalObjects are autonomous resources without a stored item, but
     * consumers that walk them from an item need a parent reference.
     */
    public function setItem(ItemRepresentation $item): self
    {
        $this->contextItem = $item;
        return $this;
    }

    public function item(): ?ItemRepresentation
    {
        return $this->contextItem;
    }

    public function ingester(): string
    {
        return 'digital_object';
    }

    public function renderer(): string
    {
        return 'file';
    }

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
            'o:lang' => $this->lang(),
            'o:alt_text' => $this->altText(),
            'o:original_url' => $this->originalUrl(),
            'o:thumbnail_urls' => $this->thumbnailUrls(),
            'data' => $this->mediaData(),
        ];
    }

    /**
     * Get the media data.
     *
     * Named mediaData() so as not to override parent::getData().
     *
     * @return mixed
     */
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

    public function altTextResolved(): ?string
    {
        $altText = $this->altText();
        if (!strlen((string) $altText)) {
            $settings = $this->getServiceLocator()->get('Omeka\Settings');
            $fallbackProperty = $settings->get('media_alt_text_property');
            if ($fallbackProperty) {
                $altText = $this->value($fallbackProperty);
            }
        }
        return $altText === null ? null : (string) $altText;
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

    /**
     * A DigitalObject is file-backed and acts as its own primary media for the
     * thumbnail pipeline. Same idiom as MediaRepresentation::primaryMedia().
     * Returning $this lets the core AbstractRepresentation::thumbnailDisplayUrl
     * and AbstractResourceEntityRepresentation::valueRepresentation work for
     * DOs without custom overrides.
     */
    public function primaryMedia()
    {
        return $this;
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

    /**
     * Thumbnail + title link honoring the site setting "attachment_link_type".
     *
     * This method allows to display do like a media attachment.
     * For a digital object: "media" (default) links to the digital object page,
     * "original" to the file, "item" to the context item when iterating inside
     * one, else falls back to the digital object page (a digital object is
     * autonomous, with no single parent item).
     */
    public function attachmentLink(string $linkType = 'media', string $thumbnailType = 'square'): string
    {
        if ($linkType === 'item' && $this->contextItem()) {
            $url = $this->contextItem()->siteUrl();
        } elseif ($linkType === 'original') {
            $url = $this->originalUrl();
        } else {
            $url = null;
        }
        if (!$url) {
            // "media" and every fallback: the digital object own page.
            return $this->linkPretty($thumbnailType);
        }
        $view = $this->getServiceLocator()->get('ViewRenderer');
        $escape = $view->plugin('escapeHtml');
        $thumbnail = $view->plugin('thumbnail');
        return sprintf(
            '<a class="resource-link" href="%s">%s<span class="resource-name">%s</span></a>',
            $escape($url),
            $thumbnail($this, $thumbnailType),
            $escape($this->displayTitle())
        );
    }

    public function siteUrl($siteSlug = null, $canonical = false)
    {
        if ($siteSlug === null) {
            $siteSlug = $this->getServiceLocator()->get('Application')
                ->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        }
        $url = $this->getViewHelper('Url');
        return $url(
            'site/digital-object',
            [
                'site-slug' => $siteSlug,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
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
