<?php declare(strict_types=1);

namespace DigitalObject\Entity;

use Omeka\Entity\Resource;

/**
 * @Entity
 */
class DigitalObject extends Resource
{
    /**
     * @Id
     * @Column(type="integer")
     */
    protected $id;

    /**
     * @Column(type="json_array", nullable=true)
     */
    protected $data;

    /**
     * @Column(type="text", nullable=true)
     */
    protected $source;

    /**
     * @Column(nullable=true, length=190)
     */
    protected $mediaType;

    /**
     * @Column(nullable=true, unique=true, length=190)
     */
    protected $storageId;

    /**
     * @Column(nullable=true, length=190)
     */
    protected $extension;

    /**
     * @Column(type="string", length=64, nullable=true, options={"fixed"=true})
     */
    protected $sha256;

    /**
     * @Column(type="bigint", nullable=true)
     */
    protected $size;

    /**
     * @Column(type="boolean")
     */
    protected $hasOriginal = false;

    /**
     * @Column(type="boolean")
     */
    protected $hasThumbnails = false;

    /**
     * @Column(nullable=true, length=190)
     */
    protected $lang;

    /**
     * @Column(type="text", nullable=true)
     */
    protected $altText;

    public function getResourceName()
    {
        return 'digital_objects';
    }

    public function setData($data): void
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setSource($source): void
    {
        $this->source = $source;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function setMediaType($mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    public function getMediaType()
    {
        return $this->mediaType;
    }

    public function setStorageId($storageId): void
    {
        $this->storageId = $storageId;
    }

    public function getStorageId()
    {
        return $this->storageId;
    }

    public function getFilename(): ?string
    {
        if ($this->storageId === null) {
            return null;
        }
        return $this->extension === null
            ? $this->storageId
            : $this->storageId . '.' . $this->extension;
    }

    public function setExtension($extension): void
    {
        $this->extension = $extension;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function setSha256($sha256): void
    {
        $this->sha256 = $sha256;
    }

    public function getSha256()
    {
        return $this->sha256;
    }

    public function setSize($size): void
    {
        $this->size = $size;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setHasOriginal($hasOriginal): void
    {
        $this->hasOriginal = (bool) $hasOriginal;
    }

    public function hasOriginal(): bool
    {
        return $this->hasOriginal;
    }

    public function setHasThumbnails($hasThumbnails): void
    {
        $this->hasThumbnails = (bool) $hasThumbnails;
    }

    public function hasThumbnails(): bool
    {
        return $this->hasThumbnails;
    }

    public function setLang($lang): void
    {
        $this->lang = $lang;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function setAltText($altText): void
    {
        $this->altText = $altText;
    }

    public function getAltText()
    {
        return $this->altText;
    }
}
