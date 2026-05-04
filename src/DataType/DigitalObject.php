<?php declare(strict_types=1);

namespace DigitalObject\DataType;

use DigitalObject\Entity\DigitalObject as DigitalObjectEntity;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\DataType\Resource\AbstractResource;

class DigitalObject extends AbstractResource
{
    public function getName()
    {
        return 'resource:digitalobject';
    }

    public function getLabel()
    {
        return 'Digital object'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        return $view->partial('common/data-type/resource', [
            'dataType' => $this->getName(),
            'resource' => $view->resource,
        ]);
    }

    public function getValidValueResources()
    {
        return [DigitalObjectEntity::class];
    }
}
