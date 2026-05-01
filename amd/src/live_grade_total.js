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
 * Live rubric total updater.
 *
 * Item #13 of the 2026-04 fix programme. Keeps a running "X / Y (Z%)"
 * indicator in sync with the rubric cells the teacher has clicked, so
 * the score is visible before the Save Changes round trip. The math
 * mirrors the PHP class \mod_videoassessment\rubric_total exactly.
 *
 * @module     mod_videoassessment/live_grade_total
 * @copyright  2026 Shinonome Labo Co., Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Compute the running totals for a set of rubric criteria.
     *
     * @param {{[id: string]: number[]}} criteria Map criterion id ->
     *     list of available level scores for that criterion.
     * @param {{[id: string]: number}} selected  Map criterion id ->
     *     score the grader has picked.
     * @returns {{total: number, max: number, percentage: number}}
     */
    function calculate(criteria, selected) {
        var total = 0;
        var max = 0;
        Object.keys(criteria).forEach(function(critId) {
            var levels = criteria[critId];
            var maxForCrit = levels.length === 0 ? 0 : Math.max.apply(null, levels);
            max += maxForCrit;
            if (Object.prototype.hasOwnProperty.call(selected, critId)) {
                total += selected[critId];
            }
        });
        var pct = max > 0 ? Math.round((total / max) * 100) : 0;
        return {total: total, max: max, percentage: pct};
    }

    /**
     * Format the running totals as "X / Y (Z%)" or "-" when no rubric
     * has been provided.
     *
     * @param {{total: number, max: number, percentage: number}} result
     * @returns {string}
     */
    function format(result) {
        if (!result.max || result.max <= 0) {
            return '-';
        }
        return result.total + ' / ' + result.max
            + ' (' + result.percentage + '%)';
    }

    /**
     * Read the current selection out of a rubric DOM subtree.
     *
     * Each criterion is expected to be a `.criterion` element with
     * `.level` children. A selected level has a `.checked` class and
     * its score is read from `data-score`.
     *
     * @param {Element} root
     * @returns {{
     *   criteria: {[id: string]: number[]},
     *   selected: {[id: string]: number}
     * }}
     */
    function readSelection(root) {
        var criteria = {};
        var selected = {};
        var critEls = root.querySelectorAll('.criterion');
        critEls.forEach(function(critEl, idx) {
            var critId = critEl.id || ('criterion-' + idx);
            var levelEls = critEl.querySelectorAll('.level');
            criteria[critId] = [];
            levelEls.forEach(function(lvl) {
                var score = parseFloat(lvl.dataset.score);
                if (!isNaN(score)) {
                    criteria[critId].push(score);
                    if (lvl.classList.contains('checked')) {
                        selected[critId] = score;
                    }
                }
            });
        });
        return {criteria: criteria, selected: selected};
    }

    /**
     * Wire the live updater to every `[data-vassmt-live-grade]`
     * display on the page.
     */
    function init() {
        var displays = document.querySelectorAll('[data-vassmt-live-grade]');
        displays.forEach(function(display) {
            var rootSelector = display.dataset.vassmtRubricRoot || '.gradingform_rubric';
            var root = document.querySelector(rootSelector);
            if (!root) {
                return;
            }

            function refresh() {
                var snapshot = readSelection(root);
                var result = calculate(snapshot.criteria, snapshot.selected);
                display.textContent = format(result);
            }

            // Recompute after every click inside the rubric. setTimeout
            // gives Moodle's own click handler time to toggle the
            // `.checked` class first.
            root.addEventListener('click', function() {
                window.setTimeout(refresh, 50);
            });
            refresh();
        });
    }

    return {
        init: init,
        // Exported for unit-test / debugging.
        calculate: calculate,
        format: format,
    };
});
