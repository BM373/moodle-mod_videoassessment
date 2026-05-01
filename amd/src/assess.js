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

/* eslint-disable
   no-empty, promise/catch-or-return, promise/always-return, camelcase */
/**
 * Video assessment
 *
 * @package
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str'], function($, str) {

    return {
        videoassessmentAssess: function() {
            // CRITICAL: This must be the FIRST line to verify function execution
            if ($("#fitem_id_advancedgradingbefore").find(".col-md-9").length > 0) {
                $("#fitem_id_advancedgradingbefore").find(".col-md-3").attr('style', "max-width:100%;");
                $("#fitem_id_advancedgradingbefore").find(".col-md-9").attr('style', "max-width:100%;");
                $("#fitem_id_advancedgradingbefore").find(".levels").find(".level").attr('style', "max-width:100%;");
                $("#fitem_id_advancedgradingbefore").find(".description").attr('style', "min-width: 120px !important;");
                $("#fitem_id_advancedgradingbefore").find(".remark").attr('style', "min-width: 150px !important;");
            }


            // Handle YouTube iframes on mobile devices.
            $('iframe').each(function() {
                const iframe = this;
                if (iframe.addEventListener) {
                    iframe.addEventListener('load', function() {
                        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry/i.test(navigator.userAgent)) {
                            const $iframe = $(iframe);
                            $iframe.attr('style', 'width:100% !important;top:0;left:0;position:static');
                            $iframe.attr('allowfullscreen', 'false');
                            $iframe.removeAttr('width');
                            $iframe.removeAttr('height');
                        }
                    });
                }
            });
            var rubrics_passed = $('input[name="rubrics_passed"]').val();

            if (typeof (rubrics_passed) != 'undefined') {
                rubrics_passed = $.parseJSON(rubrics_passed);
                for (var key in rubrics_passed) {
                    var rid = rubrics_passed[key];
                    var id = "advancedgradingbefore-criteria-" + rid;
                    var rubric = $("table#advancedgradingbefore-criteria").find('#' + id);
                    var rubric_result = $('#training-result-table-render').find('#' + id);
                    rubric_result.addClass(rubric.attr('class'));

                    rubric.after(rubric_result);
                    rubric.hide();
                }
            }

            // Add "comment" label above textarea in remark cells.
            str.get_string('comment', 'mod_videoassessment').then(function(labeltext) {
                $('.gradingform_rubric td.remark, .gradingform_rubric .criterion .remark').each(function() {
                    var $remark = $(this);
                    var $textarea = $remark.find('textarea');
                    if ($textarea.length > 0 && !$remark.find('.remark-comment-label').length) {
                        var $label = $('<span class="remark-comment-label">' + labeltext + '</span>');
                        $textarea.before($label);
                    }
                });
            });

            // Mobile: Hide video when feedback textarea is focused, show when blurred.
            // Only applies in portrait orientation to match CSS media query.
            // For desktop testing, check if width <= 768px (portrait-like).
            /**
             *
             */
            function isMobile() {
                var width = window.innerWidth;
                var height = window.innerHeight;
                // Check orientation media query, or fallback to height > width for desktop testing.
                var isPortrait = window.matchMedia && window.matchMedia('(orientation: portrait)').matches;
                if (!isPortrait && width <= 768) {
                    // For desktop testing: if width is mobile-sized, treat as portrait.
                    isPortrait = height > width || height >= width * 0.8;
                }
                var result = width <= 768 && isPortrait;
                return result;
            }

            /**
             *
             */
            function getVideoContainer() {
                var $container = $('.assess-form-videos, .path-mod-videoassessment .assess-form-videos');
                if ($container.length > 0) {
                }
                return $container;
            }

            /**
             *
             */
            function hideVideo() {
                if (isMobile()) {
                    var $videoContainer = getVideoContainer();
                    if ($videoContainer.length > 0) {
                        $videoContainer.css('display', 'none');
                    }
                } else {
                }
            }

            /**
             *
             */
            function showVideo() {
                if (isMobile()) {
                    var $videoContainer = getVideoContainer();
                    if ($videoContainer.length > 0) {
                        $videoContainer.css('display', '');
                    }
                }
            }

            // Handle rubric remark textareas.
            /**
             *
             */
            function setupFeedbackHandlers() {

                // Find all remark textareas in the rubric.
                var remarkSelectors = [
                    '.remark textarea',
                    'td.remark textarea',
                    '.criterion .remark textarea',
                    '.gradingform_rubric .remark textarea',
                    '.gradingform_rubric td.remark textarea',
                    '.gradingform_rubric .criterion .remark textarea'
                ];

                var $remarkTextareas = $(remarkSelectors.join(', '));


                // Handle focus/blur on remark textareas.
                // Mobile: Hide video when textarea is focused, show when blurred.
                $remarkTextareas.off('focus.videoassessment blur.videoassessment').on('focus.videoassessment', function() {
                    hideVideo();
                }).on('blur.videoassessment', function() {
                    // Use a small delay to check if focus moved to another textarea.
                    setTimeout(function() {
                        // Check if any remark textarea is still focused.
                        var $focused = $(remarkSelectors.join(':focus, ') + ':focus');
                        if ($focused.length === 0) {
                            showVideo();
                        }
                    }, 150);
                });

                // Handle clicks on remark containers.
                var $remarkContainers = $('.remark, td.remark, .criterion .remark');
                $remarkContainers.off('click.videoassessment').on('click.videoassessment', function(e) {
                    if (isMobile()) {
                        var $target = $(e.target);
                        // Only hide if clicking on the textarea itself, not other elements.
                        if ($target.is('textarea') || $target.closest('textarea').length > 0) {
                            hideVideo();
                        }
                    }
                });

                // Handle clicks outside remark textareas to show video.
                $(document).off('click.videoassessment-remark-outside').on('click.videoassessment-remark-outside', function(e) {
                    if (isMobile()) {
                        var $target = $(e.target);
                        // Check if clicking outside remark areas.
                        var isRemarkElement = $target.closest('.remark, td.remark, .criterion .remark').length > 0 ||
                                              $target.is('.remark, td.remark, .criterion .remark');

                        if (!isRemarkElement) {
                            // Check if any remark textarea is focused.
                            var $focused = $(remarkSelectors.join(':focus, ') + ':focus');
                            if ($focused.length === 0) {
                                showVideo();
                            }
                        }
                    }
                });
            }

            // Simple, direct event delegation for rubric remark textareas - catches all clicks/focus events.
            // This works even if elements are initialized dynamically.
            // Mobile: Hide video when textarea is clicked/focused.
            $(document).on('click.videoassessment-remark focus.videoassessment-remark', function(e) {

                if (isMobile()) {
                    var $target = $(e.target);
                    // Check if clicking on or inside any remark textarea.
                    var isRemark = $target.closest('.remark').length > 0 ||
                                   $target.is('.remark') ||
                                   $target.closest('.remark textarea').length > 0 ||
                                   $target.is('.remark textarea') ||
                                   $target.closest('td.remark, .criterion .remark').length > 0 ||
                                   $target.is('td.remark, .criterion .remark');


                    if (isRemark) {
                        hideVideo();
                    }
                } else {
                }
            });

            // Catch-all click/mousedown handler to see ALL interactions on the page
            $(document).on('click.videoassessment-debug mousedown.videoassessment-debug', function(e) {
                var $target = $(e.target);
                var isRemarkArea = $target.closest('.remark').length > 0 ||
                                   $target.is('.remark') ||
                                   $target.closest('td.remark').length > 0 ||
                                   $target.is('td.remark') ||
                                   $target.closest('.remark textarea').length > 0 ||
                                   $target.is('.remark textarea');

                if (isRemarkArea) {
                }
            });

            // Also try attaching directly to any textarea elements we can find
            /**
             *
             */
            function attachDirectHandlers() {
                var $allTextareas = $('textarea');

                $allTextareas.each(function() {
                    var $textarea = $(this);
                    var isRemarkTextarea = $textarea.closest('.remark').length > 0 ||
                                         $textarea.closest('td.remark').length > 0 ||
                                         $textarea.parent().hasClass('remark');

                    if (isRemarkTextarea) {

                        // Mobile: Hide video when textarea is clicked/focused.
                        $textarea.off('click.videoassessment-direct focus.videoassessment-direct blur.videoassessment-direct')
                                 .on('click.videoassessment-direct', function() {
                                     hideVideo();
                                 })
                                 .on('focus.videoassessment-direct', function() {
                                     hideVideo();
                                 })
                                 .on('blur.videoassessment-direct', function() {
                                     setTimeout(function() {
                                         var $focused = $('textarea:focus');
                                         if ($focused.length === 0) {
                                             showVideo();
                                         }
                                     }, 150);
                                 });
                    }
                });
            }

            // Try attaching direct handlers immediately and after delays
            setTimeout(attachDirectHandlers, 100);
            setTimeout(attachDirectHandlers, 1000);
            setTimeout(attachDirectHandlers, 3000);

            // Initial debug: Check what's on the page right now

            // Setup handlers after a short delay to ensure editors are initialized.
            setTimeout(function() {
                setupFeedbackHandlers();
            }, 500);

            setTimeout(function() {
                setupFeedbackHandlers();
            }, 1500);

            setTimeout(function() {
                setupFeedbackHandlers();
            }, 3000);

            // Use MutationObserver to catch dynamically added remark textareas.
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    var shouldSetup = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            // Check if any added nodes are remark textareas.
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                if (node.nodeType === 1) { // Element node
                                    var $node = $(node);
                                    if ($node.is('.remark, td.remark, .remark textarea') ||
                                        $node.find('.remark, td.remark, .remark textarea').length > 0) {
                                        shouldSetup = true;
                                        break;
                                    }
                                }
                            }
                        }
                    });
                    if (shouldSetup && isMobile()) {
                        setTimeout(setupFeedbackHandlers, 100);
                    }
                });

                // Observe the document body for changes.
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }

            // Re-setup handlers when window is resized or orientation changes (in case mobile detection changes).
            $(window).on('resize orientationchange', function() {
                if (isMobile()) {
                    setupFeedbackHandlers();
                }
            });
        }
    };
});

