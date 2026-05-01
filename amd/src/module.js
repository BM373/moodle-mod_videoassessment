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

/* eslint-disable no-alert, no-restricted-properties, no-empty-function */
/**
 * Video assessment
 *
 * @package
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define the module using AMD.
define(['jquery', 'core/log', 'jqueryui'], function($, log) {

    const videoassessment = {

        mainInit(cmid) {
            this.cmid = cmid;
            this.initVideoPreview();
        },

        videosInit(users, assocs) {
            this.users = users;
            this.assocs = assocs;
            this.initVideoPreview();

            $('#assocpanel').dialog({
                autoOpen: false,
                width: 450,
                height: 420
            }).draggable({handle: ".ui-dialog-titlebar"});

            $('.videodel').on('click', (e) => {
                if (!confirm(M.str.videoassessment.reallydeletevideo)) {
                    e.preventDefault();
                }
            });
        },

        peersInit() {
        },

        peersConfirmRandom() {
            return confirm(M.str.videoassessment.reallyresetallpeers);
        },

        assessInit() {
        },

        initVideoLinks() {
            $('.videolink').on('click', (e) => {
                e.preventDefault();
                const videoId = $(e.target).data('videoid') || $(e.target).parent().data('videoid');
                if (videoId) {
                    this.initVideoPreview(videoId);
                } else {
                    log.debug('Video ID not found');
                }
            });
        },

        initVideoPreview() {
            $('#videopreview').dialog({
                autoOpen: false,
                modal: true,
                width: 480,
                height: 420,
                close: () => $('#videopreview').html('')
            });

            $('#videopreviewnotice').dialog({
                autoOpen: false,
                modal: true,
                width: 450,
                height: 150
            });
        },

        videosUpdateAssocCell($div) {
            let html = '<select size="4"></select><select>';
            for (const user of this.users) {
                html += `<option>${user.fullname}</option>`;
            }
            html += '</select>';
            $div.html(html);
        },

        videosShowVideoPreview(videoId) {
            const src = `videopreview.php?id=${this.cmid}&videoid=${videoId}&width=400&height=300`;
            const html = `<iframe class="videopreview" width="420" height="370" src="${src}"></iframe>`;
            $('#videopreview').html(html).dialog('open').dialog("moveToTop").focus();
        },

        initVideoTrainingPreview() {
            $('.show-training-video').on('click', (e) => {
                e.preventDefault();
                const videoId = $(e.currentTarget).data('videoid');
                this.videosShowVideoPreview(videoId);
            });
        },

        videosShowVideoPreviewByUser(userId, timing) {
            const src = `videopreview.php?id=${this.cmid}&userid=${userId}&timing=${timing}&width=400&height=300`;
            const html = `<iframe class="videopreview" width="420" height="370" src="${src}"></iframe>`;
            $('#videopreview').html(html).dialog('open').dialog("moveToTop").focus();
        },

        videosVideoPreviewNotice() {
            let html = `
                <p class="popup-video-preview-heading">${M.str.mod_videoassessment.donotclickhere}</p>
                <div class="popup-video-preview">
                    ${M.str.mod_videoassessment.clickonthe}
                    <span class="button-firstassess-popup">${M.str.mod_videoassessment.firstassess}</span>
                    ${M.str.mod_videoassessment.or}
                    <span class="button-assessagain-popup">${M.str.mod_videoassessment.assessagain}</span>
                </div>
            `;
            $('#videopreviewnotice').html(html).dialog('open').dialog("moveToTop").focus();
        },

        videosShowAssocPanel(videoId) {
            this.videoid = videoId;
            this.videosAssocPanelRefresh();
            $('#id_videoid').val(this.videoid);
            $('#assocpanel').dialog('open').dialog("moveToTop").focus();
        },

        videosAssocPanelRefresh() {
            let filter = $('#studentfilter').val() || 'unassociated';
            let html = `
                <div>${M.str.videoassessment.liststudents}
                    <select id="studentfilter">
                        <option value="unassociated">${M.str.videoassessment.unassociated}</option>
                        <option value="associated">${M.str.videoassessment.associated}</option>
                        <option value="all">${M.str.moodle.all}</option>
                    </select>
                </div>
                <div>
                    ${M.str.videoassessment.beforeafter}:
                    <input type="radio" name="timing" id="timingbefore" checked="checked">
                    <label for="timingbefore">${M.str.videoassessment.before}</label>
                    <input type="radio" name="timing" id="timingafter">
                    <label for="timingafter">${M.str.videoassessment.after}</label>
                </div>
            `;

            for (const [i, user] of Object.entries(this.users)) {
                if (this.shouldShowUser(filter, user)) {
                    const checked = user.assocvideos.includes(this.videoid) ? 'checked' : '';
                    html += `<div class="videoassoc-studentlist">
                        <label><input type="checkbox" class="assocuser" value="${i}" ${checked}/> ${user.fullname}</label>
                    </div>`;
                }
            }

            html += `<button id="saveAssocBtn">${M.str.videoassessment.saveassociations}</button>`;
            $('#assocpanel').html(html);

            $('#studentfilter').val(filter).on('change', () => this.videosAssocPanelRefresh());
            $('#timingbefore').on('click', () => this.setTiming('before'));
            $('#timingafter').on('click', () => this.setTiming('after'));
            $('#saveAssocBtn').on('click', () => this.videosSaveAssociations());
        },

        shouldShowUser(filter, user) {
            return filter === 'all' ||
                (filter === 'unassociated' && !user.assocvideos.length) ||
                (filter === 'associated' && user.assocvideos.length);
        },

        videosSaveAssociations() {
            const assocdata = [];
            $('.assocuser').each(function() {
                assocdata.push([$(this).val(), $(this).is(':checked')]);
            });
            $('#id_assocdata').val(JSON.stringify(assocdata));
            $('#mform1').submit();
        },

        setTiming(value) {
            $('#id_timing').val(value);
            log.debug(`timing set ${value}`);
        },

        reportCombineRubrics() {
            $('.report-rubrics').each((index, node) => {
                const $node = $(node); // Convert to jQuery object

                ['before', 'after'].forEach((timing) => {
                    const $teacherRubric = $node.find(`#rubrics-${timing}teacher`);
                    const $selfRubric = $node.find(`#rubrics-${timing}self`);
                    const $peerRubric = $node.find(`#rubrics-${timing}peer`);
                    const $classRubric = $node.find(`#rubrics-${timing}class`);

                    if (!$teacherRubric.length) {
 return;
}

                    $teacherRubric.find('.remark').addClass('rubrictext-teacher');
                    $node.find(`#heading-${timing}teacher`).hide();

                    let countClass = $classRubric.find('.criterion').length
                        ? $node.find('.finalgrade .rubrictext-class').length
                        : 0;
                    let countPeer = $peerRubric.find('.criterion').length
                        ? $node.find('.finalgrade .rubrictext-peer').length
                        : 0;
                    let countTeacher = $teacherRubric.find('.criterion').length
                        ? $node.find('.finalgrade .rubrictext-teacher').length
                        : 0;

                    if ($selfRubric.find('.criterion').length) {
                        this.manageReportRubric(
                            $node,
                            'self',
                            ['peer', 'teacher', 'class'],
                            timing,
                            countPeer,
                            countClass,
                            countTeacher
                        );
                    }

                    if (!$selfRubric.find('.remark .rubrictext-peer').length && $peerRubric.find('.criterion').length) {
                        if ($peerRubric.find('.criteria').length > 1) {
                            this.manageReportSameRubric($node, 'peer', timing, countPeer, countClass, countTeacher);
                        }
                        this.manageReportRubric($node, 'peer', ['teacher', 'class'], timing, countPeer, countClass, countTeacher);
                    }

                    if (!$selfRubric.find('.remark .rubrictext-class').length &&
                        !$peerRubric.find('.remark .rubrictext-class').length &&
                        !$teacherRubric.find('.remark .rubrictext-class').length &&
                        $classRubric.find('.criterion').length) {
                        if ($classRubric.find('.criteria').length > 1) {
                            this.manageReportSameRubric($node, 'class', timing, countPeer, countClass, countTeacher);
                        }
                    }

                    ['self', 'peer', 'teacher', 'class'].forEach((gradertype) => {
                        $node.find(`#rubrics-${timing}${gradertype} .criterion`).each((_, crit) => {
                            $(crit).find('.level').each((_, level) => {
                                const $level = $(level);
                                if ($level.hasClass('checked')) {
                                    this.setStyleGradeCell($level, $(crit), gradertype, countPeer, countClass, countTeacher);
                                }
                            });
                        });
                    });
                });
            });
        },

        initPrint() {
            window.print();
        },

        initPublishVideos() {
            this.initCheckAll("#all-video-check", ".video-check");
        },

        initDeleteVideos() {
            this.initCheckAll("#all-video-check", ".video-check");

            $('#id_submitbutton').on('click', (e) => {
                const count = $(".video-check:checked").length;

                if (count === 0) {
                    alert(M.str.mod_videoassessment.errorcheckvideostodelete);
                    e.preventDefault();
                    return;
                }

                const deleteMessage = M.str.videoassessment.confirmdeletevideos.replace('{$a}', count);
                if (!confirm(deleteMessage)) {
                    e.preventDefault();
                }
            });
        },

        initCheckAll(allSelector, checkBoxesSelector) {
            $(allSelector).on('click', function() {
                $(checkBoxesSelector).prop('checked', $(this).is(':checked'));
            });
        },


        manageGradesInit() {
            $('.deletegrade').on('click', (e) => {
                if (!confirm(M.str.mod_videoassessment.confirmdeletegrade)) {
                    e.preventDefault();
                }
            });
        },

        manageReportRubric($node, targetGrade, graderTypes, timing, countPeer, countClass, countTeacher) {
            graderTypes.forEach((graderType) => {
                $node.find(`#rubrics-${timing}${graderType} .criterion`).each((_, crit) => {
                    const $crit = $(crit);
                    const critName = $crit.find('.description').html();
                    const rowIndex = $crit.parent().children().index($crit);
                    const levelName = $crit.find('.checked .definition').html();
                    const colIndex = $crit.find('.checked').parent().children().index($crit.find('.checked'));
                    const remark = $crit.find('.remark').html();

                    let critFound = false;
                    $node.find(`#rubrics-${timing}${targetGrade} .criterion`).each((index, tcrit) => {
                        const $tcrit = $(tcrit);
                        if (critFound) {
 return;
}

                        if ($tcrit.find('.description').html() === critName && index === rowIndex) {
                            critFound = true;
                            let levelFound = false;
                            $tcrit.find('.level').each((index, level) => {
                                const $level = $(level);
                                if (levelFound) {
 return;
}

                                if ($level.find('.definition').html() === levelName && index === colIndex) {
                                    levelFound = true;
                                    this.fillGradeCell($level, graderType, countPeer, countClass, countTeacher);
                                }
                                $level.css('width', '40px');
                            });

                            $tcrit.find('.remark').addClass(`rubrictext-${targetGrade}`);
                            $tcrit.find('.remark').append(`<div class="rubrictext-${graderType}">${remark}</div>`);
                            $crit.hide().addClass('hidden-information');
                            const height = $tcrit.find('.remark').outerHeight();
                            $tcrit.find('.checked').outerHeight(height);
                        }
                    });
                });

                const criterionCount = $node.find(`#rubrics-${timing}${graderType} .criterion`).length;
                const hiddenCriterionCount = $node.find(`#rubrics-${timing}${graderType} .hidden-information`).length;
                const $comment = $node.find(`#rubrics-${timing}${graderType} .comment`);

                if (`rubrics-beforeclass` !== `rubrics-${timing}${graderType}`) {
                    if (!$comment.length && criterionCount <= hiddenCriterionCount) {
                        $node.find(`#rubrics-${timing}${graderType}`).hide();
                    }

                    if ($comment.length && criterionCount <= hiddenCriterionCount) {
                        if ($node.find(`#rubrics-${timing}${graderType} .pagebreak`).length) {
                            $node.find(`#rubrics-${timing}${graderType} .pagebreak`).remove();
                        }
                    }
                } else {
                    if (criterionCount <= hiddenCriterionCount) {
                        $node.find('#rubrics-beforeclass .pagebreak').remove();
                        $node.find('#rubrics-beforeclass').prev().prev().find('.pagebreak').remove();
                    }
                }
                $node.find('#rubrics-beforetraining .pagebreak').remove();
                $node.find(`#heading-${timing}${graderType}`).hide();
            });
        },

        manageReportSameRubric($node, graderType, timing, countPeer, countClass, countTeacher) {
            const $criterias = $node.find(`#rubrics-${timing}${graderType} .criteria`).slice(1);

            $criterias.each((_, criteria) => {
                const $criteria = $(criteria);
                $criteria.find('.criterion').each((_, crit) => {
                    const $crit = $(crit);
                    const critName = $crit.find('.description').html();
                    const rowIndex = $crit.parent().children().index($crit);
                    const levelName = $crit.find('.checked .definition').html();
                    const colIndex = $crit.find('.checked').parent().children().index($crit.find('.checked'));
                    const remark = $crit.find('.remark').html();

                    let critFound = false;
                    $node.find(`#rubrics-${timing}${graderType} .criteria:first .criterion`).each((index, tcrit) => {
                        const $tcrit = $(tcrit);
                        if (critFound) {
 return;
}

                        if ($tcrit.find('.description').html() === critName && index === rowIndex) {
                            critFound = true;
                            let levelFound = false;
                            $tcrit.find('.level').each((index, level) => {
                                const $level = $(level);
                                if (levelFound) {
 return;
}

                                if ($level.find('.definition').html() === levelName && index === colIndex) {
                                    levelFound = true;
                                    this.fillGradeCell($level, graderType, countPeer, countClass, countTeacher);
                                }
                                $level.css('width', '40px');
                            });

                            $tcrit.find('.remark').addClass(`rubrictext-${graderType}`);
                            $tcrit.find('.remark').append(`<div class="rubrictext-${graderType}">${remark}</div>`);
                            $crit.hide().addClass('hidden-information');
                            const height = $tcrit.find('.remark').outerHeight();
                            $tcrit.find('.checked').outerHeight(height);
                        }
                    });
                });

                const criterionCount = $node.find(`#rubrics-${timing}${graderType} .criterion`).length;
                const hiddenCriterionCount = $node.find(`#rubrics-${timing}${graderType} .hidden-information`).length;
                const $comment = $node.find(`#rubrics-${timing}${graderType} .comment`);

                if (`rubrics-beforeclass` !== `rubrics-${timing}${graderType}`) {
                    if (!$comment.length && criterionCount <= hiddenCriterionCount) {
                        $node.find(`#rubrics-${timing}${graderType}`).hide();
                    }

                    if ($comment.length && criterionCount <= hiddenCriterionCount) {
                        if ($node.find(`#rubrics-${timing}${graderType} .pagebreak`).length) {
                            $node.find(`#rubrics-${timing}${graderType} .pagebreak`).remove();
                        }
                    }
                } else {
                    if (criterionCount <= hiddenCriterionCount) {
                        $node.find('#rubrics-beforeclass .pagebreak').remove();
                        $node.find('#rubrics-beforeclass').prev().prev().find('.pagebreak').remove();
                    }
                }
                $node.find('#rubrics-beforetraining .pagebreak').remove();
                $node.find(`#heading-${timing}${graderType}`).hide();
                $criteria.hide();
            });
        },

        setStyleGradeCell($level, $tcrit, graderType, countPeer, countClass, countTeacher) {
            $level.css({'border': 'none', 'border-left': '1px solid #ddd', 'border-right': '1px solid #ddd'});
            $tcrit.find('.checked').css('background', 'none');
            this.fillGradeCell($level, graderType, countPeer, countClass, countTeacher);
        },

        fillGradeCell($level, graderType, countPeer, countClass, countTeacher) {
            if (graderType === 'self') {
                $level.find('.level-wrapper').append(
                    `<span class="inferiorlevelmarker rubrictext-${graderType}">` +
                    `${M.str.videoassessment[graderType]}</span><br>`
                );
            } else if (graderType === 'teacher' && !$level.find('.level-wrapper .rubrictext-teacher').length) {
                $level.find('.level-wrapper').append(
                    `<span class="inferiorlevelmarker rubrictext-${graderType}">` +
                    `${M.str.videoassessment[graderType]} (${countTeacher})</span><br>`
                );
            } else if (graderType === 'peer' && !$level.find('.level-wrapper .rubrictext-peer').length) {
                $level.find('.level-wrapper').append(
                    `<span class="inferiorlevelmarker rubrictext-${graderType}">` +
                    `${M.str.videoassessment[graderType]} (${countPeer})</span><br>`
                );
            } else if (graderType === 'class' && !$level.find('.level-wrapper .rubrictext-class').length) {
                $level.find('.level-wrapper').append(
                    `<span class="inferiorlevelmarker rubrictext-${graderType}">` +
                    `${M.str.videoassessment[graderType]} (${countClass})</span><br>`
                );
            }
        }
    };

    return videoassessment;

});