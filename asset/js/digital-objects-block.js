// DigitalObject block layout — sidebar picker + caption editor.
(function ($) {
    'use strict';

    // Open the resource sidebar to add a new DO.
    $(document).on('click', '.digital-objects-add', function (e) {
        e.preventDefault();
        var $form = $(this).closest('.attachments-form');
        var $list = $form.find('.attachments');
        var url = $form.data('sidebar-content-url');
        $('.selecting-digital-objects').removeClass('selecting-digital-objects');
        $list.addClass('selecting-digital-objects');
        var $sidebar = $('#select-resource');
        Omeka.populateSidebarContent($sidebar, url);
        Omeka.openSidebar($sidebar);
    });

    // Remove a row.
    $(document).on('click', '.digital-objects-block-form .attachment .delete a', function (e) {
        e.preventDefault();
        $(this).closest('.attachment').remove();
    });

    // Toggle caption form.
    $(document).on('click', '.digital-objects-block-form .attachment-options-icon', function (e) {
        e.preventDefault();
        var $row = $(this).closest('.attachment');
        var $form = $row.find('.caption-form');
        if ($form.is(':visible')) {
            $form.hide();
            return;
        }
        $form.find('.caption-textarea').val($row.find('.do-caption').val());
        $form.show();
    });

    // Apply caption.
    $(document).on('click', '.digital-objects-block-form .apply-caption', function (e) {
        e.preventDefault();
        var $row = $(this).closest('.attachment');
        var $form = $row.find('.caption-form');
        $row.find('.do-caption').val($form.find('.caption-textarea').val());
        $form.hide();
    });

    // On resource selected from the sidebar.
    $(document).on('click', '#select-item a', function (e) {
        var $list = $('.attachments.selecting-digital-objects');
        if (!$list.length) {
            return;
        }
        var values = $('.resource-details').data('resource-values');
        if (!values || !values.value_resource_id) {
            return;
        }
        e.preventDefault();

        var rowTemplate = $list.data('template');
        var thumbSrc = values.thumbnail_asset_url || values.thumbnail_url || '';
        var thumbHtml = thumbSrc
            ? '<img loading="lazy" src="' + thumbSrc + '" alt="">'
            : '<span class="o-icon-thumbnail"></span>';

        var $block = $list.closest('.block');
        var match = $block.find('[name^="o:block["]:first').attr('name').match(/o:block\[([^\]]+)\]/);
        var blockIndex = match ? match[1] : '__blockIndex__';
        var attachIndex = $list.find('.attachment').length;
        var titleEscaped = $('<div>').text(values.display_title || '').html();

        var row = rowTemplate
            .replace(/__blockIndex__/g, blockIndex)
            .replace(/__attachmentIndex__/g, attachIndex)
            .replace('__THUMBNAIL__', thumbHtml)
            .replace('__TITLE__', titleEscaped)
            .replace('value="__ID__"', 'value="' + values.value_resource_id + '"')
            .replace('value="__CAPTION__"', 'value=""');

        $list.append(row);
        Omeka.closeSidebar($('#select-resource'));
        Omeka.closeSidebar($('#resource-details'));
        $list.removeClass('selecting-digital-objects');
    });
})(jQuery);
