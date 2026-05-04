<?php declare(strict_types=1);

namespace DigitalObject\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'digitalobject_inline_properties',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Properties to render digital objects as media', // @translate
                    'info' => 'The RDF properties whose values pointing to a digital object will trigger its inline rendering in the referencing item. See readme for recommendations.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DigitalObject',
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'digitalobject-inline-properties',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'digitalobject_storage_prefix',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Storage ID prefix', // @translate
                    'info' => 'A string to prepend to the storage id of digital object files to avoid collisions with media files in database and file system.', // @translate
                ],
                'attributes' => [
                    'id' => 'digitalobject-storage-prefix',
                    'placeholder' => 'do_',
                ],
            ])
        ;

        // Fieldset for conversion to do.

        $this
            ->add([
                'type' => Element\Fieldset::class,
                'name' => 'convert_media',
                'options' => [
                    'label' => 'Convert media to digital objects', // @translate
                    'info' => 'Run a background job that converts existing media into digital objects. Media ids are preserved. The item gets values referencing new digital objects via the configured property below.', // @translate
                ],
            ])

            ->get('convert_media')

            ->add([
                'name' => 'process',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Process conversion when saving the configuration', // @translate
                ],
                'attributes' => [
                    'id' => 'convert-media-process',
                ],
            ])
            ->add([
                'name' => 'ingester',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Filter by ingester', // @translate
                    'info' => 'Comma-separated list. Examples of file ingesters: upload, sideload. Non-file ingesters to exclude: youtube, oembed, iiif, url, html.', // @translate
                ],
                'attributes' => [
                    'id' => 'convert-media-ingester',
                    'placeholder' => 'upload, sideload',
                    'value' => 'upload, sideload',
                ],
            ])
            ->add([
                'name' => 'media_types',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Filter by media type', // @translate
                    'info' => 'Comma-separated list of media types or prefixes ending in /. Examples: image/, application/pdf, video/mp4.', // @translate
                ],
                'attributes' => [
                    'id' => 'convert-media-types',
                    'placeholder' => 'image/, application/pdf',
                ],
            ])
            ->add([
                'name' => 'only_with_file',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Only media with an original file', // @translate
                    'info' => 'Skip media without original file. It is recommended to exclude non-file media: external urls, oEmbed, YouTube, IIIF, etc.', // @translate
                ],
                'attributes' => [
                    'id' => 'convert-only-with-file',
                    'value' => '1',
                ],
            ])
            ->add([
                'name' => 'query',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'label' => 'Limit to items matching this query', // @translate
                    'info' => 'Only media belonging to items matching this query will be converted.', // @translate
                    'query_resource_type' => 'items',
                    'query_partial_excludelist' => [
                        'common/advanced-search/site',
                    ],
                ],
                'attributes' => [
                    'id' => 'convert-query',
                ],
            ])
            ->add([
                'name' => 'property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Reference property', // @translate
                    'info' => 'RDF property used on the item to reference the new digital object.', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'convert-property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                    'value' => 'dcterms:hasFormat',
                ],
            ])
            ->add([
                'name' => 'dry_run',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Dry run', // @translate
                    'info' => 'Log what would be converted without modifying the database.', // @translate
                ],
                'attributes' => [
                    'id' => 'convert-dry-run',
                ],
            ])
        ;
    }
}
