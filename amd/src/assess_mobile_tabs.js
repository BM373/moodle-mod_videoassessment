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

/* eslint-disable no-restricted-properties */
/**
 * Mobile-portrait tab switcher for the assess screen.
 *
 * On phones the assess view stacks a tall video on top of the rubric,
 * which for portrait recordings pushes the criteria almost entirely
 * off-screen (see Brendon's report on the Task List spreadsheet).
 * This module injects a sticky "動画 / 採点" tab bar at the top of
 * the screen so the learner / teacher can devote the full viewport
 * to one section at a time. Tab state is remembered in sessionStorage
 * so coming back to the page lands on whichever tab the user picked
 * last.
 *
 * Desktop and landscape phones are unaffected: the CSS rules that
 * hide the inactive section are scoped to
 * `@media (max-width: 768px) and (orientation: portrait)`.
 *
 * @module     mod_videoassessment/assess_mobile_tabs
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    const STORAGE_KEY = 'vam-assess-tab';

    /**
     * Build the tab bar element and insert it above the video band.
     *
     * @param {Element} videoContainer The .assess-form-videos element.
     * @returns {object} { tabBar, btnVideo, btnGrading } references.
     */
    function buildTabBar(videoContainer) {
        const tabBar = document.createElement('div');
        tabBar.className = 'vam-assess-tabs';
        tabBar.setAttribute('role', 'tablist');

        const labelVideo = (window.M && M.str && M.str.videoassessment
            && M.str.videoassessment.tabvideo) || 'Video';
        const labelGrading = (window.M && M.str && M.str.videoassessment
            && M.str.videoassessment.tabgrading) || 'Grading';

        const btnVideo = document.createElement('button');
        btnVideo.type = 'button';
        btnVideo.className = 'vam-assess-tab vam-assess-tab-video';
        btnVideo.setAttribute('role', 'tab');
        btnVideo.textContent = labelVideo;

        const btnGrading = document.createElement('button');
        btnGrading.type = 'button';
        btnGrading.className = 'vam-assess-tab vam-assess-tab-grading';
        btnGrading.setAttribute('role', 'tab');
        btnGrading.textContent = labelGrading;

        tabBar.appendChild(btnVideo);
        tabBar.appendChild(btnGrading);
        // Mount the tab bar as a direct child of <body>, NOT next to
        // .assess-form-videos. iOS Safari follows the CSS spec where
        // `position: fixed` is contained by any ancestor with a
        // transform / filter / will-change / perspective property —
        // and the boost theme's column / wrapper around the assess
        // form has transforms, so fixing relative to the viewport
        // only works when the tab bar lives under <body>. Without
        // this, the bar appears in document flow at the insertion
        // point with the wrapping column's width (i.e. small and
        // inline-looking), which is exactly the regression Brendon
        // reported.
        document.body.insertBefore(tabBar, document.body.firstChild);

        return {tabBar, btnVideo, btnGrading};
    }

    /**
     * Pause every <video> element inside the assess video band. Used
     * when the learner switches to the grading tab so audio does not
     * keep playing while the video element is display:none.
     *
     * @param {Element} videoContainer
     */
    function pauseAllVideos(videoContainer) {
        const players = videoContainer.querySelectorAll('video');
        players.forEach(function(v) {
            if (typeof v.pause === 'function' && !v.paused) {
                try {
                    v.pause();
                } catch (e) {
                    // Pausing can throw if the element is mid-load on
                    // some Safari builds; the next gesture will retry.
                    return;
                }
            }
        });
    }

    /**
     * Apply the chosen tab to the body class list and update the tab
     * buttons' aria-selected states.
     *
     * @param {string} tab Either 'video' or 'grading'.
     * @param {object} refs Tab references from buildTabBar.
     * @param {Element} videoContainer
     */
    function setActive(tab, refs, videoContainer) {
        const isVideo = (tab === 'video');
        document.body.classList.toggle('vam-assess-tab-video-active', isVideo);
        document.body.classList.toggle('vam-assess-tab-grading-active', !isVideo);
        refs.btnVideo.setAttribute('aria-selected', isVideo ? 'true' : 'false');
        refs.btnGrading.setAttribute('aria-selected', isVideo ? 'false' : 'true');
        refs.btnVideo.classList.toggle('vam-assess-tab-active', isVideo);
        refs.btnGrading.classList.toggle('vam-assess-tab-active', !isVideo);
        if (!isVideo) {
            pauseAllVideos(videoContainer);
        }
        try {
            window.sessionStorage.setItem(STORAGE_KEY, tab);
        } catch (e) {
            // Private mode / quota — best effort.
            return;
        }
    }

    /**
     * Initialise the tab switcher. Looks up the standard assess
     * containers and silently exits if either is missing (e.g. on a
     * page where the user has no submission yet).
     */
    function init() {
        const videoContainer = document.querySelector('.assess-form-videos');
        const gradingContainer = document.querySelector('form.gradingform')
            || document.querySelector('.gradingform_rubric');
        if (!videoContainer || !gradingContainer) {
            return;
        }
        const refs = buildTabBar(videoContainer);
        refs.btnVideo.addEventListener('click', function() {
            setActive('video', refs, videoContainer);
        });
        refs.btnGrading.addEventListener('click', function() {
            setActive('grading', refs, videoContainer);
        });

        // Restore the last-picked tab so reloads (e.g. after saving a
        // grade) land back on whatever the user was working on.
        let initial = 'video';
        try {
            const remembered = window.sessionStorage.getItem(STORAGE_KEY);
            if (remembered === 'grading' || remembered === 'video') {
                initial = remembered;
            }
        } catch (e) {
            initial = 'video';
        }
        setActive(initial, refs, videoContainer);
    }

    return {init: init};
});
