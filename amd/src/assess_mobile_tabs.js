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
 * Desktop and landscape phones are unaffected: the styles are scoped
 * via a matchMedia check before mounting and removed on orientation
 * change so the existing side-by-side layout stays untouched.
 *
 * Every layout-critical style is applied via element.style here
 * (rather than via assess.css) because earlier iterations hit a wall
 * of theme / Bootstrap specificity overrides AND iOS Safari's
 * `position: fixed`-vs-transformed-ancestor containing-block rule;
 * inline styles bypass both.
 *
 * @module     mod_videoassessment/assess_mobile_tabs
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {

    const STORAGE_KEY = 'vam-assess-tab';
    const MEDIA_QUERY = '(max-width: 768px) and (orientation: portrait)';

    /**
     * Apply the fixed-top-bar styles directly via element.style.
     * Bypasses external CSS so theme / Bootstrap overrides cannot
     * eat the bar and so a stale cache cannot leave a half-styled
     * element on screen.
     *
     * @param {HTMLElement} el The tab bar container.
     */
    function styleTabBar(el) {
        el.style.cssText = [
            'position: fixed',
            'top: 0',
            'left: 0',
            'right: 0',
            'width: 100vw',
            'max-width: 100vw',
            'height: 50px',
            'display: flex',
            'flex-direction: row',
            'flex-wrap: nowrap',
            'margin: 0',
            'padding: 0',
            'z-index: 10000',
            'background: #fff',
            'border-bottom: 1px solid #ddd',
            'box-shadow: 0 1px 4px rgba(0,0,0,0.08)',
            'box-sizing: border-box',
            'font-family: inherit'
        ].join(';');
    }

    /**
     * Apply the per-tab button styles + active highlight.
     *
     * @param {HTMLButtonElement} btn The tab button.
     * @param {boolean} active Whether this tab is the active one.
     */
    function styleTab(btn, active) {
        btn.style.cssText = [
            'flex: 1 1 50%',
            'width: 50%',
            'max-width: 50%',
            'height: 50px',
            'display: block',
            'padding: 0',
            'margin: 0',
            'border: none',
            'border-bottom: 3px solid ' + (active ? '#0f6cbf' : 'transparent'),
            'border-radius: 0',
            'background: ' + (active ? '#fff' : '#f5f5f5'),
            'color: ' + (active ? '#0f6cbf' : '#555'),
            'font-size: 16px',
            'font-weight: 600',
            'line-height: 50px',
            'text-align: center',
            'cursor: pointer',
            'appearance: none',
            '-webkit-appearance: none',
            'box-shadow: none'
        ].join(';');
    }

    /**
     * Build the tab bar element and mount it directly under <body>.
     *
     * @param {string} labelVideo Localised "Video" label.
     * @param {string} labelGrading Localised "Grading" label.
     * @returns {object} { tabBar, btnVideo, btnGrading } references.
     */
    function buildTabBar(labelVideo, labelGrading) {
        const tabBar = document.createElement('div');
        tabBar.id = 'vam-assess-tabs';
        tabBar.className = 'vam-assess-tabs';
        tabBar.setAttribute('role', 'tablist');
        styleTabBar(tabBar);

        const btnVideo = document.createElement('button');
        btnVideo.type = 'button';
        btnVideo.className = 'vam-assess-tab vam-assess-tab-video';
        btnVideo.setAttribute('role', 'tab');
        btnVideo.textContent = labelVideo;
        styleTab(btnVideo, true);

        const btnGrading = document.createElement('button');
        btnGrading.type = 'button';
        btnGrading.className = 'vam-assess-tab vam-assess-tab-grading';
        btnGrading.setAttribute('role', 'tab');
        btnGrading.textContent = labelGrading;
        styleTab(btnGrading, false);

        tabBar.appendChild(btnVideo);
        tabBar.appendChild(btnGrading);
        // Mount the tab bar as a direct child of <body>, NOT next to
        // .assess-form-videos. iOS Safari follows the CSS spec where
        // `position: fixed` is contained by any ancestor with a
        // transform / filter / will-change / perspective property —
        // and the boost theme's column / wrapper around the assess
        // form has transforms, so fixing relative to the viewport
        // only works when the tab bar lives under <body>.
        document.body.insertBefore(tabBar, document.body.firstChild);

        return {tabBar, btnVideo, btnGrading};
    }

    /**
     * Apply a hide / show to an element WITHOUT pausing any media
     * playing inside it.
     *
     * The customers' core workflow is "listen to the recording while
     * filling in the rubric": audio MUST keep playing after switching
     * to the grading tab (Don / Brendon / Matt, 2026-06 feedback
     * round). display:none is the obvious way to hide a pane but it
     * carries playback risk — some mobile browsers throttle or stall
     * media inside display:none subtrees, and an earlier build paused
     * playback explicitly. Instead the pane is kept rendered but
     * parked off-screen at 1×1px and fully transparent: every player
     * type on the assess page (HTML5 video, YouTube iframe, Vimeo
     * iframe) keeps playing under this treatment because the element
     * never leaves the DOM and never becomes display:none.
     *
     * @param {Element} el Target element.
     * @param {boolean} hide True to hide, false to restore.
     */
    function setHidden(el, hide) {
        if (!el) {
            return;
        }
        if (hide) {
            el.style.setProperty('position', 'fixed', 'important');
            el.style.setProperty('top', '0', 'important');
            el.style.setProperty('left', '-10000px', 'important');
            el.style.setProperty('width', '1px', 'important');
            el.style.setProperty('height', '1px', 'important');
            el.style.setProperty('max-height', '1px', 'important');
            el.style.setProperty('overflow', 'hidden', 'important');
            el.style.setProperty('opacity', '0', 'important');
            el.style.setProperty('pointer-events', 'none', 'important');
            el.setAttribute('aria-hidden', 'true');
        } else {
            el.style.removeProperty('position');
            el.style.removeProperty('top');
            el.style.removeProperty('left');
            el.style.removeProperty('width');
            el.style.removeProperty('height');
            el.style.removeProperty('max-height');
            el.style.removeProperty('overflow');
            el.style.removeProperty('opacity');
            el.style.removeProperty('pointer-events');
            // Legacy properties from the previous display:none-based
            // implementation; harmless to clear unconditionally.
            el.style.removeProperty('display');
            el.style.removeProperty('visibility');
            el.removeAttribute('aria-hidden');
        }
    }

    /**
     * Collect every plausible video-area container on the assess
     * page. view_assess() emits `.assess-form-videos` but inline
     * embeds (iframes, mediaplugin wrappers, vimeo / YouTube
     * containers) can also appear in `.video-wrap` siblings, so hide
     * all of them when the grading tab is active.
     *
     * @returns {Element[]}
     */
    function collectVideoTargets() {
        const targets = [];
        document.querySelectorAll(
            '.assess-form-videos, .video-wrap, .path-mod-videoassessment .mediaplugin'
        ).forEach(function(el) {
            targets.push(el);
        });
        return targets;
    }

    /**
     * Apply the chosen tab — park the inactive section off-screen via
     * setHidden (deliberately NOT pausing playback: see setHidden's
     * doc — students keep listening while they score), restyle the
     * buttons, persist the choice.
     *
     * @param {string} tab Either 'video' or 'grading'.
     * @param {object} refs Tab references from buildTabBar.
     * @param {Element} gradingContainer The rubric / grading form element.
     */
    function setActive(tab, refs, gradingContainer) {
        const isVideo = (tab === 'video');
        // Keep the body classes for any future CSS hook (and for the
        // PHPUnit contract test).
        document.body.classList.toggle('vam-assess-tab-video-active', isVideo);
        document.body.classList.toggle('vam-assess-tab-grading-active', !isVideo);
        refs.btnVideo.setAttribute('aria-selected', isVideo ? 'true' : 'false');
        refs.btnGrading.setAttribute('aria-selected', isVideo ? 'false' : 'true');
        styleTab(refs.btnVideo, isVideo);
        styleTab(refs.btnGrading, !isVideo);
        // Park every plausible video wrapper, not just the primary
        // one — some pages have multiple.
        collectVideoTargets().forEach(function(el) {
            setHidden(el, !isVideo);
        });
        setHidden(gradingContainer, isVideo);
        // Body padding-top so the breadcrumb / Moodle nav under the
        // fixed bar is not eaten by it.
        document.body.style.paddingTop = '50px';
        try {
            window.sessionStorage.setItem(STORAGE_KEY, tab);
        } catch (e) {
            // Private mode / quota — best effort.
            return;
        }
    }

    /**
     * Remove the tab bar and restore the page to its pre-tabs layout.
     * Used when the viewport leaves mobile-portrait (e.g. user rotated
     * to landscape) so the existing side-by-side layout is not
     * disturbed.
     */
    function uninstall() {
        const existing = document.getElementById('vam-assess-tabs');
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }
        document.body.classList.remove('vam-assess-tab-video-active');
        document.body.classList.remove('vam-assess-tab-grading-active');
        document.body.style.paddingTop = '';
        // Restore the inline overrides we layered on every video
        // wrapper + the rubric container so the side-by-side
        // desktop / landscape layout returns to its normal state.
        collectVideoTargets().forEach(function(el) {
            setHidden(el, false);
        });
        const gradingContainer = document.querySelector('form.gradingform')
            || document.querySelector('.gradingform_rubric');
        setHidden(gradingContainer, false);
    }

    /**
     * Look up the matched tab bar by id (since the JS-only mount path
     * does not keep a long-lived reference). Used by the resize / MQ
     * listener to decide whether the bar is already installed.
     *
     * @returns {boolean}
     */
    function isInstalled() {
        return !!document.getElementById('vam-assess-tabs');
    }

    /**
     * Install the tab UI: build the bar, wire click handlers, restore
     * the previously-picked tab from sessionStorage.
     *
     * @param {string} labelVideo
     * @param {string} labelGrading
     */
    function install(labelVideo, labelGrading) {
        const videoContainer = document.querySelector('.assess-form-videos');
        const gradingContainer = document.querySelector('form.gradingform')
            || document.querySelector('.gradingform_rubric');
        if (!videoContainer || !gradingContainer) {
            return;
        }
        if (isInstalled()) {
            return;
        }
        const refs = buildTabBar(labelVideo, labelGrading);
        refs.btnVideo.addEventListener('click', function() {
            setActive('video', refs, gradingContainer);
        });
        refs.btnGrading.addEventListener('click', function() {
            setActive('grading', refs, gradingContainer);
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
        setActive(initial, refs, gradingContainer);
    }

    /**
     * Entry point. Resolves the localised tab labels, then installs
     * the tab UI if the device is currently mobile-portrait. Listens
     * for orientation / size changes and installs / uninstalls so
     * desktop and landscape modes keep the existing layout.
     */
    function init() {
        Str.get_strings([
            {key: 'tabvideo', component: 'mod_videoassessment'},
            {key: 'tabgrading', component: 'mod_videoassessment'}
        ]).then(function(strings) {
            const labelVideo = strings[0] || 'Video';
            const labelGrading = strings[1] || 'Grading';

            const mq = window.matchMedia(MEDIA_QUERY);
            const apply = function() {
                if (mq.matches) {
                    install(labelVideo, labelGrading);
                } else {
                    uninstall();
                }
            };
            apply();
            // Re-evaluate on orientation / window-resize changes.
            if (typeof mq.addEventListener === 'function') {
                mq.addEventListener('change', apply);
            } else if (typeof mq.addListener === 'function') {
                // Safari < 14 compat.
                mq.addListener(apply);
            }
            return null;
        }).catch(function() {
            // Falling back to bare English if the string fetch fails
            // is better than leaving the learner with no tab UI at all.
            const mq = window.matchMedia(MEDIA_QUERY);
            if (mq.matches) {
                install('Video', 'Grading');
            }
        });
    }

    return {init: init};
});
