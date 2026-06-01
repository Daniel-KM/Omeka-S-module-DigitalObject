<?php declare(strict_types=1);

namespace DigitalObject\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;

class DigitalObjectRepresentation extends AbstractResourceEntityRepresentation
{
    protected ?ItemRepresentation $contextItem = null;

    /**
     * Memoized content type (file / iiif-image / iiif-presentation), since it
     * is read repeatedly (ingester, renderer, thumbnailUrl, thumbnailUrls).
     */
    private ?string $typeCache = null;

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

    /**
     * Nature of the digital object content: 'file', 'iiif-image' or
     * 'iiif-presentation'. A IIIF Image is detected from its self-describing
     * info.json (stored raw as data), so it carries no control key; a IIIF
     * Presentation stores no payload, only the 'type' discriminant.
     *
     * Internal classification from which ingester()/renderer() derive; not part
     * of the public representation API (consumers use renderer()).
     */
    protected function type(): string
    {
        if ($this->typeCache !== null) {
            return $this->typeCache;
        }
        $data = $this->resource->getData();
        if (!is_array($data)) {
            return $this->typeCache = 'file';
        }
        // IIIF Image: the info.json is self-describing. Tested first, so the
        // info.json own "type" (e.g. "ImageService3") is never mistaken for the
        // discriminant.
        $context = $data['@context'] ?? '';
        $context = is_array($context) ? implode(' ', $context) : (string) $context;
        if (($data['protocol'] ?? null) === 'http://iiif.io/api/image'
            || strpos($context, 'iiif.io/api/image') !== false
        ) {
            return $this->typeCache = 'iiif-image';
        }
        if (($data['type'] ?? null) === 'iiif-presentation') {
            return $this->typeCache = 'iiif-presentation';
        }
        return $this->typeCache = 'file';
    }

    public function ingester(): string
    {
        // A digital object has no pluggable ingestion: its ingester is always
        // itself. IIIF detection by consumers (IiifServer) is based on
        // renderer(), not ingester().
        return 'digital_object';
    }

    public function renderer(): string
    {
        // Mirror the core Media IIIF renderer names ('iiif', 'iiif_presentation')
        // so consumers can test renderer() uniformly for media and digital
        // objects.
        $typesToRenderers = [
            'iiif-image' => 'iiif',
            'iiif-presentation' => 'iiif_presentation',
        ];
        return $typesToRenderers[$this->type()]
            ?? 'file';
    }

    public function getControllerName()
    {
        return 'digital-object';
    }

    public function getResourceJsonLdType()
    {
        return 'o:DigitalObject';
    }

    /**
     * The json ld is adapted from MediaRepresentation.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::getResourceJsonLd()
     */
    public function getResourceJsonLd()
    {
        return [
            'o:ingester' => $this->ingester(),
            'o:renderer' => $this->renderer(),
            'o:source' => $this->source(),
            'o:media_type' => $this->mediaType(),
            'o:sha256' => $this->sha256(),
            'o:size' => $this->size(),
            'o:filename' => $this->filename(),
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
        if ($this->hasOriginal()) {
            return $this->getFileUrl('original', $this->filename());
        }
        // A digital object exported to a remote repository keeps no local
        // original but stores the remote file url as source.
        $source = (string) $this->source();
        return strpos($source, 'http://') === 0 || strpos($source, 'https://') === 0
            ? $source
            : null;
    }

    public function thumbnailUrl($type): ?string
    {
        $manager = $this->getServiceLocator()->get('Omeka\File\ThumbnailManager');
        $types = $manager->getTypes();
        if (!in_array($type, $types, true)) {
            return null;
        }
        if ($this->hasThumbnails()) {
            return $this->getFileUrl($type, $this->storageId(), 'jpg');
        }
        // A IIIF Presentation manifest stores its own thumbnail url.
        if ($this->renderer() === 'iiif_presentation') {
            $data = $this->resource->getData();
            return is_array($data) && !empty($data['thumbnail'])
                ? (string) $data['thumbnail']
                : null;
        }
        // No local derivatives: resolve the thumbnail from the remote IIIF
        // image service.
        return $this->iiifThumbnailUrl($type);
    }

    /**
     * Build a thumbnail url from the remote IIIF image service stored as data.
     */
    protected function iiifThumbnailUrl(string $type): ?string
    {
        if ($this->type() !== 'iiif-image') {
            return null;
        }
        $serviceId = $this->iiifImageServiceId();
        if (!$serviceId) {
            return null;
        }
        $sizes = ['large' => 800, 'medium' => 200, 'square' => 200];
        $max = $sizes[$type] ?? 200;
        // Square uses the square region; other types keep the full region.
        $region = $type === 'square' ? 'square' : 'full';
        return sprintf('%s/%s/!%d,%d/0/default.jpg', $serviceId, $region, $max, $max);
    }

    /**
     * Base id of the remote IIIF image service (info.json @id or id).
     */
    protected function iiifImageServiceId(): ?string
    {
        $data = $this->resource->getData();
        if (!is_array($data)) {
            return null;
        }
        $id = $data['id'] ?? $data['@id'] ?? null;
        return is_string($id) && $id !== '' ? rtrim($id, '/') : null;
    }

    /**
     * Remote IIIF Image info.json, stored raw as data (no control key added).
     */
    protected function iiifTileSources(): array
    {
        $data = $this->resource->getData();
        if (!is_array($data)) {
            return [];
        }
        return $data;
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
        $renderer = $this->renderer();
        if ($renderer === 'iiif') {
            return $this->renderIiif($options);
        }
        if ($renderer === 'iiif_presentation') {
            return $this->renderIiifPresentation($options);
        }

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

    /**
     * Render a remote IIIF Presentation manifest in the core IIIF viewer, using
     * the same idiom as the core Omeka\Media\Renderer\IiifPresentation
     * renderer.
     */
    protected function renderIiifPresentation(array $options = []): string
    {
        $url = (string) $this->source();
        if ($url === '') {
            return '';
        }
        $view = $this->getServiceLocator()->get('ViewRenderer');
        return $view->iiifViewer(['url' => $url], $options);
    }

    /**
     * Render a remote IIIF image service through OpenSeadragon, using the same
     * tile sources idiom as the core Omeka\Media\Renderer\IIIF renderer.
     */
    protected function renderIiif(array $options = []): string
    {
        $view = $this->getServiceLocator()->get('ViewRenderer');
        $escape = $view->plugin('escapeHtml');
        $tileSources = $this->iiifTileSources();
        if (!$tileSources) {
            // No info.json available: fall back to a plain link.
            return sprintf('<a href="%s">%s</a>',
                $escape((string) $this->originalUrl()),
                $escape($this->displayTitle()));
        }

        $view->headScript()->appendFile(
            $view->assetUrl('vendor/openseadragon/openseadragon.min.js', 'Omeka')
        );
        $id = sprintf('iiif-do-%s', $this->id());
        $prefixUrl = $view->assetUrl('vendor/openseadragon/images/', 'Omeka', false, false);
        $height = isset($options['height'])
            ? sprintf('%spx', (int) $options['height'])
            : '400px';

        $html = <<<'HTML'
        <div class="openseadragon" id="%1$s" data-prefix-url="%2$s" data-tile-sources="%3$s" style="height: %4$s;"></div>
        <script>
        {
            const openSeadragonDiv = document.getElementById('%1$s');
            const viewer = OpenSeadragon({
                id: '%1$s',
                prefixUrl: openSeadragonDiv.dataset.prefixUrl,
                tileSources: [JSON.parse(openSeadragonDiv.dataset.tileSources)]
            });
        }
        </script>
        HTML;
        return sprintf(
            $html,
            $id,
            $escape($prefixUrl),
            $escape(json_encode($tileSources)),
            $height
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
