<?php declare(strict_types=1);

namespace DigitalObject\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class DigitalObjectRender implements ResourcePageBlockLayoutInterface
{
    public function getLabel(): string
    {
        return 'Digital object render'; // @translate
    }

    public function getCompatibleResourceNames(): array
    {
        return ['digital_objects'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string
    {
        return $view->partial(
            'common/resource-page-block-layout/digital-object-render',
            ['resource' => $resource]
        );
    }
}
