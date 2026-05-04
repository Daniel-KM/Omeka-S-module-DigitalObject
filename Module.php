<?php declare(strict_types=1);

namespace DigitalObject;

if (!class_exists('Common\TraitModule', false)) {
    require_once file_exists(dirname(__DIR__) . '/Common/src/TraitModule.php')
        ? dirname(__DIR__) . '/Common/src/TraitModule.php'
        : dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
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

    public function warnUninstall($event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() !== __NAMESPACE__) {
            return;
        }

        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $count = (int) $connection->fetchOne('SELECT COUNT(id) FROM digital_object');
        if (!$count) {
            return;
        }

        $t = $services->get('MvcTranslator');
        $html = '<p style="color:#c00"><strong>' . $t->translate('WARNING') . '</strong>: '; // @translate
        $html .= sprintf(
            $t->translate('%d digital objects still exist. Uninstallation is blocked. Convert them back to media via the module configuration page, or delete them individually through the admin interface before uninstalling. Removing them directly from the database would leave orphan files and derivatives on disk.'), // @translate
            $count
        );
        $html .= '</p>';

        echo $html;
    }

    protected function preUninstall(): void
    {
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $count = (int) $connection->fetchOne('SELECT COUNT(id) FROM digital_object');
        if ($count === 0) {
            return;
        }

        $translator = $services->get('MvcTranslator');
        $message = sprintf(
            $translator->translate('Cannot uninstall: %d digital objects still exist. Convert them back to media via the module configuration page, or delete them individually through the admin interface.'), // @translate
            $count
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
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

    public function handleConfigForm(AbstractController $controller)
    {
        $ok = $this->handleConfigFormAuto($controller);
        if (!$ok) {
            return false;
        }
        $post = $controller->getRequest()->getPost()->toArray();
        $convert = $post['convert_media'] ?? [];
        if (empty($convert['process'])) {
            return true;
        }
        $services = $this->getServiceLocator();
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\DigitalObject\Job\MediaToDigitalObject::class, [
            'ingester' => $convert['ingester'] ?? '',
            'media_types' => $convert['media_types'] ?? '',
            'only_with_file' => !empty($convert['only_with_file']),
            'query' => $convert['query'] ?? '',
            'property' => $convert['property'] ?? 'dcterms:hasFormat',
            'dry_run' => !empty($convert['dry_run']),
        ]);
        $url = $controller->url();
        $messenger = $controller->messenger();
        $messenger->addSuccess(sprintf(
            'Convert media to digital objects job launched (job #%d). %s',
            $job->getId(),
            sprintf(
                '<a href="%s">%s</a>',
                $url->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]),
                'View job logs'
            )
        ));
        return true;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            Api\Adapter\DigitalObjectAdapter::class,
            'api.delete.pre',
            [$this, 'deleteFiles']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
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
