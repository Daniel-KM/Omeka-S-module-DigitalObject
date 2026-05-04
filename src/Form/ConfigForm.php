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
    }
}
