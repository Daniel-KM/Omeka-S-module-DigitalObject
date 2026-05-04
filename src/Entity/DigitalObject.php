<?php declare(strict_types=1);

namespace DigitalObject\Entity;

use Omeka\Entity\Resource;

/**
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(
 *             name="idx_do_sha256",
 *             columns={"sha256"}
 *         ),
 *         @Index(
 *             name="idx_do_media_type",
 *             columns={"media_type"}
 *         )
 *     }
 * )
 */
class DigitalObject extends Resource
{
    /**
     * @Id
     * @Column(
     *     type="integer"
     * )
     */
    protected $id;

    /**
     * @Column(
     *     type="json_array",
     *     nullable=true
     * )
     */
    protected $data;

    /**
     * @Column(
     *     type="text",
     *     nullable=true
     * )
     */
    protected $source;

    /**
     * @Column(
     *     nullable=true,
     *     length=190
     * )
     */
    protected $mediaType;

    /**
     * @Column(
     *     nullable=true,
     *     unique=true,
     *     length=190
     * )
     */
    protected $storageId;

    /**
     * @Column(
     *     nullable=true,
     *     length=190
     * )
     */
    protected $extension;

    /**
     * @Column(
     *     type="string",
     *     length=64,
     *     nullable=true,
     *     options={
     *         "fixed"=true
     *     }
     * )
     */
    protected $sha256;

    /**
     * @Column(
     *     type="bigint",
     *     nullable=true
     * )
     */
    protected $size;

    /**
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default": 0
     *     }
     * )
     */
    protected $hasOriginal = false;

    /**
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default": 0
     *     }
     * )
     */
    protected $hasThumbnails = false;

    /**
     * @Column(
     *     nullable=true,
     *     length=190
     * )
     */
    protected $lang;

    /**
     * @Column(
     *     type="text",
     *     nullable=true
     * )
     */
    protected $altText;

    public function getResourceName()
    {
        return 'digital_objects';
    }

    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setSource($source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function setMediaType($mediaType): self
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    public function getMediaType()
    {
        return $this->mediaType;
    }

    public function setStorageId($storageId): self
    {
        $this->storageId = $storageId;
        return $this;
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

    public function setExtension($extension): self
    {
        $this->extension = $extension;
        return $this;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function setSha256($sha256): self
    {
        $this->sha256 = $sha256;
        return $this;
    }

    public function getSha256()
    {
        return $this->sha256;
    }

    public function setSize($size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setHasOriginal($hasOriginal): self
    {
        $this->hasOriginal = (bool) $hasOriginal;
        return $this;
    }

    public function hasOriginal(): bool
    {
        return $this->hasOriginal;
    }

    public function setHasThumbnails($hasThumbnails): self
    {
        $this->hasThumbnails = (bool) $hasThumbnails;
        return $this;
    }

    public function hasThumbnails(): bool
    {
        return $this->hasThumbnails;
    }

    public function setLang($lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function setAltText($altText): self
    {
        $this->altText = $altText;
        return $this;
    }

    public function getAltText()
    {
        return $this->altText;
    }
}
