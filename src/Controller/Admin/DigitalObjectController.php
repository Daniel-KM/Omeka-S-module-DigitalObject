<?php declare(strict_types=1);

namespace DigitalObject\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceForm;

class DigitalObjectController extends AbstractActionController
{
    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

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

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], ['query' => $returnQuery], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $resources = $response->getContent();
        $view->setVariable('digitalObjects', $resources);
        $view->setVariable('resources', $resources);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        $view->setVariable('returnQuery', $returnQuery);
        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('id', 'add-digital-object');
        $form->setAttribute('enctype', 'multipart/form-data');

        $postedValues = null;
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $postedValuesJson = $data['values_json'] ?? null;
            $fileData = $this->params()->fromFiles();
            $data = $this->mergeValuesJson($data);
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->create('digital_objects', $data, $fileData);
                if ($response) {
                    $this->messenger()->addSuccess('Digital object successfully created'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
            $postedValues = $this->postedValuesToJson($postedValuesJson);
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('postedValues', $postedValues);
        return $view;
    }

    /**
     * Convert the raw POSTed values_json into the shape expected by
     * resource-form.js on re-render after a failed submission.
     */
    protected function postedValuesToJson(?string $postedValuesJson): ?array
    {
        if (!$postedValuesJson) {
            return null;
        }
        $decoded = json_decode($postedValuesJson, true);
        if (!is_array($decoded)) {
            return null;
        }
        $out = [];
        foreach ($decoded as $term => $values) {
            if (is_array($values)) {
                $out[$term] = ['values' => $values];
            }
        }
        return $out;
    }

    public function editAction()
    {
        $digitalObject = $this->api()->read('digital_objects', $this->params('id'))->getContent();

        $form = $this->getForm(ResourceForm::class, ['resource' => $digitalObject]);
        $form->setAttribute('id', 'edit-digital-object');

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $data = $this->mergeValuesJson($data);
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->update('digital_objects', $this->params('id'), $data);
                if ($response) {
                    $this->messenger()->addSuccess('Digital object successfully updated'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('digitalObject', $digitalObject);
        $view->setVariable('resource', $digitalObject);
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('digital_objects', $this->params('id'));

        $view = new ViewModel;
        $digitalObject = $response->getContent();
        $view->setVariable('digitalObject', $digitalObject);
        $view->setVariable('resource', $digitalObject);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('digital_objects', $this->params('id'));
        $digitalObject = $response->getContent();
        $values = $digitalObject->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $digitalObject);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('digital_objects', $this->params('id'));
        $digitalObject = $response->getContent();
        $values = $digitalObject->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $digitalObject);
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
                    $this->messenger()->addSuccess('Digital object successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/digital-object');
    }

    public function sidebarSelectAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('digital_objects', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        $view = new ViewModel;
        $view->setVariable('digitalObjects', $response->getContent());
        $view->setVariable('searchValue', $this->params()->fromQuery('search'));
        $view->setTerminal(true);
        return $view;
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $returnQuery = $this->params()->fromQuery();
        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one digital object to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], ['query' => $returnQuery], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('digital_objects', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Digital objects successfully deleted'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], ['query' => $returnQuery], true);
    }
}
