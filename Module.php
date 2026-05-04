<?php declare(strict_types=1);

namespace DigitalObject;

if (!class_exists('Common\TraitModule', false)) {
    require_once file_exists(dirname(__DIR__) . '/Common/src/TraitModule.php')
        ? dirname(__DIR__) . '/Common/src/TraitModule.php'
        : dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $entity = Entity\DigitalObject::class;
        $adapter = Api\Adapter\DigitalObjectAdapter::class;

        $acl
            ->allow(
                null,
                [$entity, $adapter],
                ['read', 'search']
            )
            // Public access to the site controller (like core site
            // controllers).
            ->allow(
                null,
                ['DigitalObject\Controller\Site\DigitalObject']
            )
            ->allow(
                [
                    \Omeka\Permissions\Acl::ROLE_AUTHOR,
                    \Omeka\Permissions\Acl::ROLE_REVIEWER,
                    \Omeka\Permissions\Acl::ROLE_EDITOR,
                    \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
                    \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
                ],
                [$entity, $adapter],
                ['create', 'update', 'delete']
            );
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.85')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.85'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            Api\Adapter\DigitalObjectAdapter::class,
            'api.delete.pre',
            [$this, 'deleteFiles']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.sidebar',
            [$this, 'displayInline']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.details',
            [$this, 'displayInline']
        );
    }

    public function displayInline($event): void
    {
        $view = $event->getTarget();
        $item = $view->vars()->resource ?? $view->vars()->item ?? null;
        if (!$item) {
            return;
        }

        $dos = $view->digitalObjectInline($item);
        if (empty($dos)) {
            return;
        }

        echo $view->partial('common/digital-object-inline', [
            'digitalObjects' => $dos,
        ]);
    }

    public function deleteFiles($event): void
    {
        $request = $event->getParam('request');
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        try {
            $do = $api->read('digital_objects', $request->getId())->getContent();
        } catch (\Exception $e) {
            return;
        }

        $storageId = $do->storageId();
        $extension = $do->extension();
        if (!$storageId) {
            return;
        }

        $store = $services->get('Omeka\File\Store');
        $thumbnailManager = $services->get('Omeka\File\ThumbnailManager');

        $filename = $extension === null ? $storageId : $storageId . '.' . $extension;
        if ($do->hasOriginal()) {
            $store->delete('original/' . $filename);
        }

        if ($do->hasThumbnails()) {
            foreach ($thumbnailManager->getTypes() as $type) {
                $store->delete($type . '/' . $storageId . '.jpg');
            }
        }
    }
}
