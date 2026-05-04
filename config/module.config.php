<?php declare(strict_types=1);

namespace DigitalObject;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            Entity\DigitalObject::class => Entity\DigitalObject::class,
        ],
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'digital_objects' => Api\Adapter\DigitalObjectAdapter::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'DigitalObject\Controller\Admin\DigitalObject' => Service\Controller\Admin\DigitalObjectControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'digital-object' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/digital-object[/:action]',
                            'defaults' => [
                                '__NAMESPACE__' => 'DigitalObject\Controller\Admin',
                                'controller' => 'DigitalObject',
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminResource' => [
            [
                'label' => 'Digital objects', // @translate
                'route' => 'admin/digital-object',
                'resource' => 'DigitalObject\Controller\Admin\DigitalObject',
                'pages' => [
                    [
                        'route' => 'admin/digital-object/id',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'digitalobject' => [
        'config' => [
            'digitalobject_inline_properties' => ['foaf:depiction'],
            'digitalobject_storage_prefix' => 'do_',
        ],
    ],
];
