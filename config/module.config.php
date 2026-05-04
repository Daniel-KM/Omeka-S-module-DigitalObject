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
        'invokables' => [
            'DigitalObject\Controller\Admin\DigitalObject' => Controller\Admin\DigitalObjectController::class,
        ],
    ],
    'data_types' => [
        'invokables' => [
            'resource' => DataType\Resource\All::class,
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'digitalObjectInline' => Service\ViewHelper\DigitalObjectInlineFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'digital-object' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/digital-object',
                            'defaults' => [
                                '__NAMESPACE__' => 'DigitalObject\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => 'DigitalObject\Controller\Admin\DigitalObject',
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
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
                        'route' => 'admin/digital-object/default',
                        'visible' => false,
                    ],
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
            // RDF properties whose values (of type "resource") pointing to a
            // DigitalObject trigger inline rendering of that DigitalObject in
            // the referencing item's display.
            // - dcterms:hasFormat (default)
            // - dcterms:relation (very generic)
            // - crm:P138i_is_represented_by (CIDOC-CRM)
            // - rico:hasInstantiation / rico:hasOrHadDigitalInstantiation (RiC-O)
            // - edm:isShownBy / edm:hasView (Europeana Data Model)
            // - pcdm:hasFile (Portland Common Data Model)
            // - bibo:transcriptOf / premis:hasOriginal (derivative roles)
            // - foaf:depiction (image only, person-leaning vocabulary)
            // - schema:image / schema:associatedMedia / schema:encoding
            'digitalobject_inline_properties' => [
                'dcterms:hasFormat',
            ],
            'digitalobject_storage_prefix' => 'do_',
        ],
    ],
];
