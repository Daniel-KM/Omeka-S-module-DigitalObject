<?php declare(strict_types=1);

namespace DigitalObject\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class DigitalObjectController extends AbstractActionController
{
    public function showAction()
    {
        $site = $this->currentSite();
        $digitalObject = $this->api()
            ->read('digital_objects', $this->params('id'))
            ->getContent();

        $view = new ViewModel();
        $view->setTemplate('omeka/site/digital-object/show');
        $view->setVariable('site', $site);
        $view->setVariable('digitalObject', $digitalObject);
        $view->setVariable('resource', $digitalObject);
        return $view;
    }
}
