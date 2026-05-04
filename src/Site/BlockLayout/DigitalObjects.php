<?php declare(strict_types=1);

namespace DigitalObject\Site\BlockLayout;

use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;

class DigitalObjects extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /** @var ApiManager */
    protected $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    public function getLabel()
    {
        return 'Digital objects'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        ?SitePageRepresentation $page = null,
        ?SitePageBlockRepresentation $block = null
    ) {
        $view->headScript()->appendFile($view->assetUrl('js/digital-objects-block.js', 'DigitalObject'));
        $view->headLink()->appendStylesheet($view->assetUrl('css/digital-object.css', 'DigitalObject'));

        $items = $block ? ($block->dataValue('digital_objects') ?: []) : [];
        $sidebarUrl = $view->url('admin/digital-object/default', ['action' => 'sidebar-select']);
        $translate = $view->plugin('translate');
        $escape = $view->plugin('escapeHtml');
        $escapeAttr = $view->plugin('escapeHtmlAttr');

        $texts = [
            'Caption' => $escapeAttr($translate('Caption')),
            'Delete' => $escapeAttr($translate('Delete')),
            'Caption_Label' => $escape($translate('Caption')),
            'Apply' => $escape($translate('Apply')),
        ];

        $rowTemplate = <<<HTML
            <div class="attachment">
                <span class="sortable-handle"></span>
                <div class="item-title">__THUMBNAIL____TITLE__</div>
                <ul class="actions">
                    <li><a href="#" class="attachment-options-icon o-icon-configure button" title="{$texts['Caption']}"></a></li>
                    <li class="delete"><a href="#" class="o-icon-delete button" title="{$texts['Delete']}"></a></li>
                </ul>
                <input type="hidden" class="do-id" name="o:block[__blockIndex__][o:data][digital_objects][__attachmentIndex__][id]" value="__ID__">
                <input type="hidden" class="do-caption" name="o:block[__blockIndex__][o:data][digital_objects][__attachmentIndex__][caption]" value="__CAPTION__">
                <div class="caption-form" style="display:none;">
                    <label>
                        {$texts['Caption_Label']}
                        <textarea class="caption-textarea" rows="2"></textarea>
                    </label>
                    <button type="button" class="apply-caption button">{$texts['Apply']}</button>
                </div>
            </div>
            HTML;

        $layout = new Element\Select('o:block[__blockIndex__][o:data][layout]');
        $layout->setOptions([
            'label' => 'Layout', // @translate
            'empty_option' => 'Vertical', // @translate
            'value_options' => ['horizontal' => 'Horizontal'], // @translate
        ]);
        $layout->setValue($block ? $block->dataValue('layout') : '');

        $display = new Element\Select('o:block[__blockIndex__][o:data][media_display]');
        $display->setOptions([
            'label' => 'Display', // @translate
            'empty_option' => 'Embed', // @translate
            'value_options' => ['thumbnail' => 'Thumbnail only'], // @translate
        ]);
        $display->setValue($block ? $block->dataValue('media_display') : '');

        $html = '<div class="attachments-form digital-objects-block-form" data-sidebar-content-url="' . $escapeAttr($sidebarUrl) . '">';
        $html .= '<a href="#" class="collapse"><h4>' . $escape($translate('Digital objects')) . '</h4></a>';
        $html .= '<div class="attachments collapsible" data-template="' . $escapeAttr($rowTemplate) . '">';
        foreach ($items as $entry) {
            $id = $entry['id'] ?? null;
            $caption = $entry['caption'] ?? '';
            if (!$id) {
                continue;
            }
            try {
                $do = $this->api->read('digital_objects', $id)->getContent();
            } catch (\Exception $e) {
                continue;
            }
            $thumb = $view->thumbnail($do, 'square');
            $row = $rowTemplate;
            $row = strtr($row, [
                '__THUMBNAIL__' => $thumb,
                '__TITLE__' => $escape($do->displayTitle()),
                'value="__ID__"' => 'value="' . $escapeAttr($id) . '"',
                'value="__CAPTION__"' => 'value="' . $escapeAttr($caption) . '"',
            ]);
            $html .= $row;
        }
        $html .= '</div>';
        $html .= '<button type="button" class="button digital-objects-add">'
            . $escape($translate('Add digital object')) . '</button>';
        $html .= '</div>';

        $html .= '<a href="#" class="collapse"><h4>' . $escape($translate('Options')) . '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= $view->formRow($layout);
        $html .= $view->formRow($display);
        $html .= $view->blockThumbnailTypeSelect($block);
        $html .= $view->blockShowTitleSelect($block);
        $html .= '</div>';
        $html .= $view->partial('common/resource-select-sidebar');
        return $html;
    }

    public function render(
        PhpRenderer $view,
        SitePageBlockRepresentation $block,
        $templateViewScript = 'common/block-layout/digital-objects'
    ) {
        $entries = $block->dataValue('digital_objects') ?: [];
        if (!$entries) {
            return '';
        }

        $digitalObjects = [];
        $captions = [];
        foreach ($entries as $entry) {
            $id = $entry['id'] ?? null;
            if (!$id) {
                continue;
            }
            try {
                $do = $this->api->read('digital_objects', $id)->getContent();
                $digitalObjects[] = $do;
                $captions[$do->id()] = $entry['caption'] ?? '';
            } catch (\Exception $e) {
                continue;
            }
        }
        if (!$digitalObjects) {
            return '';
        }

        $layout = $block->dataValue('layout');
        $mediaDisplay = $block->dataValue('media_display');
        $thumbnailType = $block->dataValue('thumbnail_type', 'square');
        $showTitleOption = $block->dataValue('show_title_option', 'item_title');

        $classes = ['digital-objects', 'media-embed'];
        $classes[] = $layout === 'horizontal' ? 'layout-horizontal' : 'layout-vertical';
        $classes[] = $mediaDisplay === 'thumbnail' ? 'media-display-thumbnail' : 'media-display-embed';
        $classes[] = count($digitalObjects) > 3
            ? 'multiple-attachments'
            : 'attachment-count-' . count($digitalObjects);

        return $view->partial($templateViewScript, [
            'block' => $block,
            'digitalObjects' => $digitalObjects,
            'captions' => $captions,
            'thumbnailType' => $thumbnailType,
            'showTitleOption' => $showTitleOption,
            'classes' => $classes,
            'mediaDisplay' => $mediaDisplay,
        ]);
    }
}
