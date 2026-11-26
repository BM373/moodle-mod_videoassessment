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
 * Reloads the scores page while an uploaded video is being converted, so that it appears by itself.
 *
 * @module     mod_videoassessment/conversion_refresh
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    const INTERVAL = 15000;

    /**
     * Whether a reload now would lose what the user is doing.
     *
     * @return {boolean}
     */
    function isBusy() {
        const active = document.activeElement;
        return document.hidden
            || document.querySelector('.modal.show, .moodle-dialogue-base:not(.moodle-dialogue-hidden)') !== null
            || (active !== null && (active.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)));
    }

    let started = false;

    return {
        /**
         * Start checking. One table can ask more than once, for several learners.
         */
        init: function() {
            if (started) {
                return;
            }
            started = true;
            setInterval(function() {
                if (!isBusy()) {
                    window.location.reload();
                }
            }, INTERVAL);
        }
    };
});
