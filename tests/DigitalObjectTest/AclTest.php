<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractTestCase;
use DigitalObject\Api\Adapter\DigitalObjectAdapter;
use DigitalObject\Entity\DigitalObject;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Entity\Media;
use Omeka\Entity\User;
use Omeka\Permissions\Acl;

/**
 * Digital objects must follow the same acl rules than core media.
 */
class AclTest extends AbstractTestCase
{
    protected const ROLES = [
        Acl::ROLE_RESEARCHER,
        Acl::ROLE_AUTHOR,
        Acl::ROLE_REVIEWER,
        Acl::ROLE_EDITOR,
        Acl::ROLE_SITE_ADMIN,
        Acl::ROLE_GLOBAL_ADMIN,
    ];

    public function roleProvider(): array
    {
        return array_combine(self::ROLES, array_map(fn ($role) => [$role], self::ROLES));
    }

    /**
     * @dataProvider roleProvider
     */
    public function testAdapterRulesMatchMedia(string $role): void
    {
        $acl = $this->getAcl();
        foreach (['search', 'read', 'create', 'update', 'delete', 'batch_create', 'batch_update', 'batch_update_all', 'batch_delete', 'batch_delete_all'] as $privilege) {
            self::assertSame(
                $acl->isAllowed($role, MediaAdapter::class, $privilege),
                $acl->isAllowed($role, DigitalObjectAdapter::class, $privilege),
                "Adapter privilege \"$privilege\" for role \"$role\"."
            );
        }
    }

    /**
     * @dataProvider roleProvider
     */
    public function testEntityRulesMatchMedia(string $role): void
    {
        $acl = $this->getAcl();
        $user = $this->createUser(1, $role);
        $other = $this->createUser(2, $role);
        foreach ([$user, $other] as $owner) {
            $media = new Media();
            $media->setOwner($owner);
            $digitalObject = new DigitalObject();
            $digitalObject->setOwner($owner);
            foreach (['read', 'create', 'update', 'delete'] as $privilege) {
                self::assertSame(
                    $acl->isAllowed($user, $media, $privilege),
                    $acl->isAllowed($user, $digitalObject, $privilege),
                    sprintf('Entity privilege "%s" for role "%s" (%s).', $privilege, $role, $owner === $user ? 'own' : 'other')
                );
            }
        }
    }

    public function testAnonymousCanReadAndUseSiteController(): void
    {
        $acl = $this->getAcl();
        self::assertTrue($acl->isAllowed(null, DigitalObjectAdapter::class, 'read'));
        self::assertTrue($acl->isAllowed(null, DigitalObjectAdapter::class, 'search'));
        self::assertFalse($acl->isAllowed(null, DigitalObjectAdapter::class, 'create'));
        self::assertTrue($acl->isAllowed(null, 'DigitalObject\Controller\Site\DigitalObject', 'show'));
        self::assertFalse($acl->isAllowed(null, 'DigitalObject\Controller\Admin\DigitalObject', 'browse'));
    }

    /**
     * @dataProvider roleProvider
     */
    public function testAdminControllerRules(string $role): void
    {
        $acl = $this->getAcl();
        $controller = 'DigitalObject\Controller\Admin\DigitalObject';
        foreach (['index', 'browse', 'show', 'show-details', 'search', 'sidebar-select'] as $action) {
            self::assertTrue($acl->isAllowed($role, $controller, $action), "Action \"$action\" for role \"$role\".");
        }
        $canEdit = $role !== Acl::ROLE_RESEARCHER;
        foreach (['add', 'edit', 'delete', 'delete-confirm', 'batch-delete'] as $action) {
            self::assertSame($canEdit, $acl->isAllowed($role, $controller, $action), "Action \"$action\" for role \"$role\".");
        }
        self::assertSame(
            $acl->isAllowed($role, 'Omeka\Controller\Admin\Media', 'batch-delete-all'),
            $acl->isAllowed($role, $controller, 'batch-delete-all'),
            "Action \"batch-delete-all\" for role \"$role\"."
        );
    }

    protected function getAcl(): Acl
    {
        return $this->getService('Omeka\Acl');
    }

    protected function createUser(int $id, string $role): User
    {
        $user = new User();
        $user->setRole($role);
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setAccessible(true);
        $property->setValue($user, $id);
        return $user;
    }
}
