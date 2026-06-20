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
 * Inject a "Finish making rubric" action box into the core advanced-
 * grading management page, alongside the Edit / Delete boxes.
 *
 * @module     mod_videoassessment/finish_rubric_manage_button
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var BTN_ID = 'vassmt-finish-rubric-manage-btn';

    // Dark navy matching the requested three-star icon.
    var STAR_COLOUR = '#22335a';

    /**
     * Build the three-star icon used in place of the core pix icon.
     *
     * Deliberately does NOT use the core `.icon` class: that class
     * constrains the wrapper to a 16px box, which would force the three
     * (larger) stars to wrap onto separate lines and overlap the label.
     * Instead the wrapper is a full-width block that holds the stars in
     * a single centred, non-wrapping row sitting above the label, the
     * same way the core pix icons sit above their text.
     *
     * @returns {HTMLElement}
     */
    function buildStars() {
        var wrap = document.createElement('span');
        wrap.className = 'vassmt-finish-rubric-stars';
        wrap.style.display = 'block';
        wrap.style.width = '100%';
        wrap.style.textAlign = 'center';
        wrap.style.whiteSpace = 'nowrap';
        wrap.style.lineHeight = '1';
        wrap.style.marginBottom = '0.6rem';
        for (var i = 0; i < 3; i++) {
            var star = document.createElement('i');
            star.className = 'fa fa-star';
            star.setAttribute('aria-hidden', 'true');
            star.style.display = 'inline-block';
            star.style.color = STAR_COLOUR;
            star.style.fontSize = '2rem';
            // Small gap between the stars.
            star.style.margin = '0 0.1em';
            wrap.appendChild(star);
        }
        return wrap;
    }

    /**
     * Build the action box and place it first in the actions row, so it
     * sits to the left of the core Edit / Delete boxes.
     *
     * @param {string} viewUrl Destination URL (the activity view).
     * @param {string} label   Localised box label.
     */
    function inject(viewUrl, label) {
        if (document.getElementById(BTN_ID)) {
            return;
        }
        var container = document.querySelector('#region-main .actions')
            || document.querySelector('.actions');
        if (!container) {
            return;
        }

        var anchor = document.createElement('a');
        anchor.id = BTN_ID;
        // Same classes as the core action boxes so the card styling,
        // border and sizing match exactly.
        anchor.className = 'action btn btn-lg vassmt-finish-rubric-action';
        anchor.href = viewUrl;

        anchor.appendChild(buildStars());

        var text = document.createElement('div');
        text.className = 'action-text';
        text.textContent = label;
        anchor.appendChild(text);

        container.insertBefore(anchor, container.firstChild);
    }

    return {
        init: function(viewUrl, label) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    inject(viewUrl, label);
                });
            } else {
                inject(viewUrl, label);
            }
        },
        // Exposed for testing only.
        _inject: inject,
    };
});
