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
 * Inject a "Finish making rubric" button into the core rubric edit page.
 *
 * @module     mod_videoassessment/finish_rubric_button
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Build the button DOM and place it at the top of the form region.
     *
     * @param {string} assessUrl Destination URL.
     * @param {string} label     Localised button label.
     */
    function inject(assessUrl, label) {
        if (document.getElementById('vassmt-finish-rubric-btn')) {
            return;
        }
        var anchor = document.createElement('a');
        anchor.id = 'vassmt-finish-rubric-btn';
        anchor.className = 'btn btn-primary mb-3 vassmt-finish-rubric';
        anchor.href = assessUrl;
        anchor.textContent = label;

        // Prefer the form region; fall back to #region-main / body.
        var target = document.querySelector('#gradingform_rubric-criteria-addcriterion')
            || document.querySelector('.gradingform_rubric')
            || document.querySelector('#region-main')
            || document.body;
        if (target.parentNode && target.parentNode.insertBefore) {
            target.parentNode.insertBefore(anchor, target);
        } else {
            target.appendChild(anchor);
        }
    }

    return {
        init: function(assessUrl, label) {
            // Defer until the rubric form has rendered.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    inject(assessUrl, label);
                });
            } else {
                inject(assessUrl, label);
            }
        },
        // Exposed for testing only.
        _inject: inject,
    };
});
