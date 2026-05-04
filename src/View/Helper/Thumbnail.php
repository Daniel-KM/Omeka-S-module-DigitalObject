<?php declare(strict_types=1);

namespace DigitalObject\View\Helper;

use DigitalObject\Api\Representation\DigitalObjectRepresentation;
use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Api\Representation\ItemRepresentation;

class Thumbnail extends \Omeka\View\Helper\Thumbnail
{
    public function __invoke(AbstractRepresentation $representation, $type, array $attribs = [])
    {
        $result = parent::__invoke($representation, $type, $attribs);
        if ($result !== '') {
            return $result;
        }
        if (!$representation instanceof ItemRepresentation) {
            return $result;
        }
        $view = $this->getView();
        $dos = $view->digitalObjectInline($representation);
        foreach ($dos as $do) {
            if (!$do instanceof DigitalObjectRepresentation) {
                continue;
            }
            $thumb = parent::__invoke($do, $type, $attribs);
            if ($thumb !== '') {
                return $thumb;
            }
        }
        return $result;
    }
}
