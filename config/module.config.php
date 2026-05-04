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
            'DigitalObject\Controller\Site\DigitalObject' => Controller\Site\DigitalObjectController::class,
        ],
    ],
    'data_types' => [
        'invokables' => [
            'resource' => DataType\Resource\All::class,
            'resource:digitalobject' => DataType\DigitalObject::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'column_types' => [
        'invokables' => [
            'digital_object_id' => ColumnType\Id::class,
            'digital_object_created' => ColumnType\Created::class,
            'digital_object_modified' => ColumnType\Modified::class,
            'digital_object_is_public' => ColumnType\IsPublic::class,
            'digital_object_owner' => ColumnType\Owner::class,
            'digital_object_resource_class' => ColumnType\ResourceClass::class,
            'digital_object_resource_template' => ColumnType\ResourceTemplate::class,
            'digital_object_media_type' => ColumnType\MediaType::class,
            'digital_object_size' => ColumnType\Size::class,
        ],
        'factories' => [
            'digital_object_value' => Service\ColumnType\ValueFactory::class,
        ],
    ],
    'column_defaults' => [
        'admin' => [
            'digital_objects' => [
                ['type' => 'digital_object_resource_class'],
                ['type' => 'digital_object_media_type'],
                ['type' => 'digital_object_size'],
                ['type' => 'digital_object_owner'],
                ['type' => 'digital_object_created'],
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'digital_objects' => [
                'sort_by' => 'id',
                'sort_order' => 'desc',
            ],
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'thumbnail' => View\Helper\Thumbnail::class,
        ],
        'factories' => [
            'digitalObjectInline' => Service\ViewHelper\DigitalObjectInlineFactory::class,
        ],
    ],
    'block_layouts' => [
        'factories' => [
            'digitalObjects' => Service\Site\BlockLayout\DigitalObjectsFactory::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'digital_objects_embeds' => Site\ResourcePageBlockLayout\DigitalObjectsEmbeds::class,
            'digital_objects_list' => Site\ResourcePageBlockLayout\DigitalObjectsList::class,
        ],
    ],
    'resource_page_blocks_default' => [
        'digital_objects' => [
            // Like a media page (render + values), plus the linked resources.
            'main' => [
                'digital_object_render',
                'values',
                'linkedResources',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'digital-object' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/digital-object/:id[/:action]',
                            'constraints' => [
                                'id' => '\d+',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'DigitalObject\Controller\Site',
                                'controller' => 'DigitalObject\Controller\Site\DigitalObject',
                                'action' => 'show',
                            ],
                        ],
                    ],
                ],
            ],
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
                'class' => 'o-icon- fa-file',
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
    'searchsolr_value_extractors' => [
        'factories' => [
            'digital_objects' => Service\SearchSolr\ValueExtractorFactory::class,
        ],
    ],
    'digitalobject' => [
        'config' => [
            // RDF properties whose values (of type "resource") pointing to a
            // DigitalObject trigger inline rendering of that DigitalObject in
            // the referencing item's display. See readme for recommendations.
            'digitalobject_inline_properties' => [
                'dcterms:hasFormat',
            ],
            'digitalobject_storage_prefix' => 'do_',
        ],
    ],
];
