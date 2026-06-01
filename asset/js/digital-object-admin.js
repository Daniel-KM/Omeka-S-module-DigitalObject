(function () {
    'use strict';

    function init() {
        var form = document.getElementById('add-digital-object');
        var file = document.getElementById('file');
        var url = document.getElementById('ingest_url');
        if (!form || !file || !url) {
            return;
        }
        // Optional: present only when the FileSideload module is installed.
        var sideload = document.getElementById('ingest_filename');

        var clear = function () { url.setCustomValidity(''); };
        url.addEventListener('input', clear);
        file.addEventListener('change', clear);
        if (sideload) {
            sideload.addEventListener('change', clear);
        }

        // Capture phase + stopImmediatePropagation so the check runs before
        // other submit handlers (resource-form.js, AdvancedResourceTemplate…)
        // that disable and serialize the value fields; otherwise the form would
        // stay on the page with all its fields disabled.
        form.addEventListener('submit', function (e) {
            var hasFile = file.files && file.files.length > 0;
            var hasUrl = url.value.trim() !== '';
            var hasSideload = sideload && sideload.value !== '';
            if (hasFile || hasUrl || hasSideload) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.Omeka && Omeka.switchActiveSection && window.jQuery) {
                Omeka.switchActiveSection(window.jQuery('#digital-object-file'));
            }
            url.setCustomValidity(Omeka.jsTranslate('A file or a URL is required.'));
            url.reportValidity();
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
