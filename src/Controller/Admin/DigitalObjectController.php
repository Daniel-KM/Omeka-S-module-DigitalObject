<?php declare(strict_types=1);

namespace DigitalObject\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceForm;

class DigitalObjectController extends AbstractActionController
{
    public function browseAction()
    {
        $this->browse()->setDefaults('digital_objects');
        $response = $this->api()->search('digital_objects', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        $returnQuery = $this->params()->fromQuery();
        unset($returnQuery['page']);

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], ['query' => $returnQuery], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $view = new ViewModel;
        $resources = $response->getContent();
        $view->setVariable('digitalObjects', $resources);
        $view->setVariable('resources', $resources);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('returnQuery', $returnQuery);
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('digital_objects', $this->params('id'));
        $resource = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('digitalObject', $resource);
        $view->setVariable('resource', $resource);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('digital_objects', $this->params('id'));
        $resource = $response->getContent();
        $values = $resource->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $resource);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('id', 'add-digital-object');
        $form->setAttribute('enctype', 'multipart/form-data');

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $fileData = $this->params()->fromFiles();
            $form->setData($data);
            if ($form->isValid()) {
                $payload = $data;
                if (!empty($fileData['file']['tmp_name'])) {
                    $payload['file'] = $fileData['file'];
                }
                $response = $this->api($form)->create('digital_objects', $payload, $fileData);
                if ($response) {
                    $this->messenger()->addSuccess('Digital object successfully created.'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function editAction()
    {
        $response = $this->api()->read('digital_objects', $this->params('id'));
        $resource = $response->getContent();

        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('id', 'edit-digital-object');
        $form->setAttribute('enctype', 'multipart/form-data');

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $fileData = $this->params()->fromFiles();
            $form->setData($data);
            if ($form->isValid()) {
                $payload = $data;
                if (!empty($fileData['file']['tmp_name'])) {
                    $payload['file'] = $fileData['file'];
                }
                $response = $this->api($form)->update('digital_objects', $resource->id(), $payload, $fileData);
                if ($response) {
                    $this->messenger()->addSuccess('Digital object successfully updated.'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('digitalObject', $resource);
        $view->setVariable('resource', $resource);
        $view->setVariable('form', $form);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('digital_objects', $this->params('id'));
        $resource = $response->getContent();
        $values = $resource->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $resource);
        $view->setVariable('resourceLabel', 'digital object'); // @translate
        $view->setVariable('partialPath', 'digital-object/admin/digital-object/show-details');
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('digital_objects', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Digital object successfully deleted.'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/digital-object');
    }
}
