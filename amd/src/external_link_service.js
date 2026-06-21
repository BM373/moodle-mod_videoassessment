// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Guide the user to the right external-video link by showing, per the
 * chosen service, an example of the video URL to paste.
 *
 * The backend resolver auto-detects the service from the pasted URL, so
 * this selector is purely a hint: it updates the URL field's
 * placeholder and a small example line whenever the service changes.
 * It exists because users pasted platform home pages
 * (e.g. https://opencast.org/) instead of an actual video link.
 *
 * @module     mod_videoassessment/external_link_service
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {

    /**
     * Apply the example for the currently selected service.
     *
     * @param {HTMLSelectElement} select The service <select>.
     * @param {HTMLElement} hint The example-line container.
     * @param {string} hintPrefix Localised "Paste a link such as:" text.
     */
    function apply(select, hint, hintPrefix) {
        var option = select.options[select.selectedIndex];
        var example = option ? (option.getAttribute('data-example') || '') : '';

        // The url field is #id_url on desktop and #id_mobileurl on mobile.
        var input = document.getElementById('id_url') || document.getElementById('id_mobileurl');
        if (input) {
            input.setAttribute('placeholder', example);
        }
        if (example) {
            hint.textContent = hintPrefix + ' ' + example;
            hint.style.display = '';
        } else {
            // "Other": no fixed format. Clear the hint.
            hint.textContent = '';
            hint.style.display = 'none';
        }
    }

    return {
        init: function() {
            var select = document.getElementById('id_videoservice');
            var hint = document.getElementById('videoservice-hint');
            if (!select || !hint) {
                return;
            }
            Str.get_string('videoservicehint', 'mod_videoassessment').then(function(hintPrefix) {
                apply(select, hint, hintPrefix);
                select.addEventListener('change', function() {
                    apply(select, hint, hintPrefix);
                });
                return null;
            }).catch(function() {
                // Fall back to a bare prefix so the example still shows.
                apply(select, hint, 'e.g.');
                select.addEventListener('change', function() {
                    apply(select, hint, 'e.g.');
                });
            });
        },
    };
});
