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
