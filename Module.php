<?php declare(strict_types=1);

namespace DigitalObject;

if (!trait_exists(\Common\TraitModule::class, false)) {
    if (file_exists(OMEKA_PATH . '/modules/Common/src/TraitModule.php')) {
        require_once OMEKA_PATH . '/modules/Common/src/TraitModule.php';
    } elseif (file_exists(OMEKA_PATH . '/composer-addons/modules/Common/src/TraitModule.php')) {
        require_once OMEKA_PATH . '/composer-addons/modules/Common/src/TraitModule.php';
    } elseif (file_exists(dirname(__DIR__) . '/Common/src/TraitModule.php')) {
        require_once dirname(__DIR__) . '/Common/src/TraitModule.php';
    }
}

use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $this->addAclRules();
    }

    /**
     * Same rules than core items and media (see Omeka\Service\AclFactory).
     *
     * Site admin and global admin are allowed everything by the core.
     */
    protected function addAclRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $entity = Entity\DigitalObject::class;
        $adapter = Api\Adapter\DigitalObjectAdapter::class;
        $adminController = 'DigitalObject\Controller\Admin\DigitalObject';
        $siteController = 'DigitalObject\Controller\Site\DigitalObject';

        $contributors = [
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
        ];
        $viewers = array_merge([\Omeka\Permissions\Acl::ROLE_RESEARCHER], $contributors);

        $acl
            // Visibility of private resources is managed by the core filter.
            ->allow(null, $adapter, ['search', 'read'])
            ->allow(null, $entity, 'read')
            ->allow(null, $siteController)

            ->allow(
                $viewers,
                $adminController,
                ['index', 'browse', 'show', 'show-details', 'search', 'sidebar-select']
            )

            ->allow(
                $contributors,
                $adminController,
                ['add', 'edit', 'delete', 'delete-confirm', 'batch-delete']
            )
            ->allow(
                \Omeka\Permissions\Acl::ROLE_EDITOR,
                $adminController,
                ['batch-delete-all']
            )
            ->allow($contributors, $adapter, ['create', 'update', 'delete', 'batch_update', 'batch_delete'])
            ->allow(
                [\Omeka\Permissions\Acl::ROLE_REVIEWER, \Omeka\Permissions\Acl::ROLE_EDITOR],
                $adapter,
                'batch_update_all'
            )
            ->allow(\Omeka\Permissions\Acl::ROLE_EDITOR, $adapter, 'batch_delete_all')
            ->allow($contributors, $entity, 'create')

            // Author: update and delete own digital objects only.
            ->allow(
                \Omeka\Permissions\Acl::ROLE_AUTHOR,
                $entity,
                ['update', 'delete'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion()
            )
            // Reviewer: update all, delete own only.
            ->allow(\Omeka\Permissions\Acl::ROLE_REVIEWER, $entity, 'update')
            ->allow(
                \Omeka\Permissions\Acl::ROLE_REVIEWER,
                $entity,
                'delete',
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion()
            )
            // Editor: update and delete all.
            ->allow(\Omeka\Permissions\Acl::ROLE_EDITOR, $entity, ['update', 'delete']);
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

        $escape = $view->plugin('escapeHtml');
        $escapeAttr = $view->plugin('escapeHtmlAttr');
        $t = $services->get('MvcTranslator');

        $html = '<p style="color:#c00"><strong>' . $escape($t->translate('WARNING')) . '</strong>: '; // @translate
        $html .= $escape(sprintf(
            $t->translate('%d digital objects still exist. Without conversion, files and derivatives will be left on disk.'), // @translate
            $count
        ));
        $html .= '</p>';
        $html .= '<label><input name="digitalobject-convert-to-media" type="checkbox" form="confirmform" value="1"> ';
        $html .= $escape($t->translate('Convert digital objects back to media before uninstalling (synchronous, may be long).')); // @translate
        $html .= '</label>';
        $html .= '<input type="hidden" name="digitalobject-uninstall-count" form="confirmform" value="' . $escapeAttr((string) $count) . '">';

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
        $request = $services->get('Request');
        $convert = (bool) $request->getPost('digitalobject-convert-to-media');
        if (!$convert) {
            $message = sprintf(
                $translator->translate('Cannot uninstall: %d digital objects still exist. Tick the conversion checkbox in the uninstall confirmation, or convert/delete them beforehand.'), // @translate
                $count
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $strategy = $services->get(\Omeka\Job\DispatchStrategy\Synchronous::class);
        $dispatcher->dispatch(
            \DigitalObject\Job\DigitalObjectToMedia::class,
            [],
            $strategy
        );

        $remaining = (int) $connection->fetchOne('SELECT COUNT(id) FROM digital_object');
        if ($remaining > 0) {
            $message = sprintf(
                $translator->translate('Conversion incomplete: %d digital objects could not be converted. Check job logs and retry.'), // @translate
                $remaining
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.88')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.88'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        $this->setDigitalObjectResourcePageBlocks();
    }

    /**
     * Add the default digital object resource page blocks to every site whose
     * theme already has an explicit resource page blocks configuration (which
     * otherwise takes precedence over the module default and would leave the
     * digital object page empty). Sites without such configuration already fall
     * back to the module default.
     */
    protected function setDigitalObjectResourcePageBlocks(): void
    {
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $blocks = $services->get('Config')['resource_page_blocks_default']['digital_objects']['main']
            ?? ['digital_object_render', 'values', 'linkedResources'];

        $sites = $connection->fetchAllAssociative('SELECT id, theme FROM site');
        foreach ($sites as $site) {
            $siteId = (int) $site['id'];
            $key = 'theme_settings_' . $site['theme'];
            $value = $connection->fetchOne(
                'SELECT value FROM site_setting WHERE site_id = :site AND id = :id',
                ['site' => $siteId, 'id' => $key]
            );
            if ($value === false) {
                continue;
            }
            $settings = json_decode((string) $value, true);
            // Only when the theme is configured but lacks digital objects.
            if (!is_array($settings)
                || !isset($settings['resource_page_blocks'])
                || isset($settings['resource_page_blocks']['digital_objects'])
            ) {
                continue;
            }
            $settings['resource_page_blocks']['digital_objects'] = ['main' => $blocks];
            $connection->executeStatement(
                'UPDATE site_setting SET value = :value WHERE site_id = :site AND id = :id',
                ['value' => json_encode($settings), 'site' => $siteId, 'id' => $key]
            );
        }
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $ok = $this->handleConfigFormAuto($controller);
        if (!$ok) {
            return false;
        }
        $post = $controller->getRequest()->getPost()->toArray();
        $services = $this->getServiceLocator();
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $url = $controller->url();
        $messenger = $controller->messenger();

        $convert = $post['convert_media'] ?? [];
        if (!empty($convert['process'])) {
            $job = $dispatcher->dispatch(\DigitalObject\Job\MediaToDigitalObject::class, [
                'ingester' => $convert['ingester'] ?? '',
                'media_types' => $convert['media_types'] ?? '',
                'only_with_file' => !empty($convert['only_with_file']),
                'query' => $convert['query'] ?? '',
                'property' => $convert['property'] ?? 'dcterms:hasFormat',
                'dry_run' => !empty($convert['dry_run']),
            ]);
            $messenger->addSuccess(sprintf(
                'Convert media to digital objects job launched (job #%d). %s',
                $job->getId(),
                sprintf(
                    '<a href="%s">%s</a>',
                    $url->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]),
                    'View job logs'
                )
            ));
        }

        $revert = $post['revert_digital_objects'] ?? [];
        if (!empty($revert['process'])) {
            $job = $dispatcher->dispatch(\DigitalObject\Job\DigitalObjectToMedia::class, [
                'delete_values' => !isset($revert['delete_values']) || $revert['delete_values'],
                'dry_run' => !empty($revert['dry_run']),
            ]);
            $messenger->addSuccess(sprintf(
                'Revert digital objects to media job launched (job #%d). %s',
                $job->getId(),
                sprintf(
                    '<a href="%s">%s</a>',
                    $url->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]),
                    'View job logs'
                )
            ));
        }
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

        // Fallback primary media for items without a native media but linked to
        // one or more DigitalObjects. Attached to every aggregator that emits
        // the generic rep.resource.primary_media event.
        foreach ([
            \Contribute\Api\Representation\ContributionRepresentation::class,
            \Generateur\Api\Representation\GenerationRepresentation::class,
            \Mapping\Api\Representation\MappingRepresentation::class,
            \Selection\Api\Representation\SelectionRepresentation::class,
            \Selection\Api\Representation\SelectionResourceRepresentation::class,
        ] as $identifier) {
            $sharedEventManager->attach(
                $identifier,
                'rep.resource.primary_media',
                [$this, 'fallbackPrimaryMediaForItem']
            );
        }
    }

    public function fallbackPrimaryMediaForItem($event): void
    {
        $resource = $event->getParam('resource');
        if (!$resource instanceof \Omeka\Api\Representation\ItemRepresentation) {
            return;
        }
        $services = $this->getServiceLocator();
        $view = $services->get('ViewHelperManager');
        if (!$view->has('digitalObjectInline')) {
            return;
        }
        $dos = $view->get('digitalObjectInline')->__invoke($resource);
        foreach ($dos as $do) {
            if ($do) {
                $event->setParam('primary', $do);
                return;
            }
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
