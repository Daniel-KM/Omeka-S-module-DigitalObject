<?php declare(strict_types=1);

namespace DigitalObject;

use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $connection = $services->get('Omeka\Connection');
        $sql = file_get_contents(__DIR__ . '/data/install/schema.sql');
        $connection->executeStatement($sql);
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $connection = $services->get('Omeka\Connection');
        $sql = file_get_contents(__DIR__ . '/data/install/uninstall.sql');
        $connection->executeStatement($sql);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            Api\Adapter\DigitalObjectAdapter::class,
            'api.delete.pre',
            [$this, 'deleteFiles']
        );
        foreach ([
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Site\Item',
        ] as $controller) {
            $sharedEventManager->attach($controller, 'view.show.sidebar', [$this, 'displayInline']);
            $sharedEventManager->attach($controller, 'view.details', [$this, 'displayInline']);
        }
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
        echo $view->partial('common/digital-object-inline', ['digitalObjects' => $dos]);
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

    protected function addAclRules(): void
    {
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
}
