<?php
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
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkleman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['Reembedthelink'] = 'Re-embed the link';
$string['accepteddifference'] = 'Accepted difference in scores';
$string['accepteddifference_help'] = 'Accepted difference in scores. Default 20%. Here the acceptable range, or \'difference\' can be set for students\' scores, compared to
pre-entered teacher scores. If the student score lies outside the acceptable difference for any criterion on the rubric, they fail the Training Pre-test and must take it again.';
$string['additionalassessmentbyteacher'] = 'Additional assessment by teacher';
$string['addmember'] = 'Add a member';
$string['addpeer'] = 'Add peer...';
$string['addpeergroup'] = 'Add a peer group';
$string['addprefixtolabel'] = 'Add Prefix to Label Name';
$string['addsuffixtolabel'] = 'Add Suffix to Label Name';
$string['admin_settings_executable_locked'] = 'This executable path is locked by the site administrator ($CFG->preventexecpath = true) and cannot be changed from this form.';
$string['advancedgradingmethodsgroup'] = 'Create or Select rubric';
$string['advancedgradingmethodsgroup_help'] = 'For video assessment, do not change the default settings here for "Grading Method". All settings use rubric, because that is the best method of performance assessment. If you change the settings, the video assessment system may not work.';
$string['advancedoptions'] = 'Advanced options';
$string['after'] = 'After';
$string['afterduedateevery'] = 'after due date, every';
$string['aftergrade'] = 'After grade';
$string['afterlabel'] = 'After';
$string['aftermarks'] = 'After scores';
$string['afterpeer'] = 'After - peer';
$string['afterself'] = 'After - self';
$string['afterteacher'] = 'After - teacher';
$string['aftervideo'] = 'After video';
$string['allowexternallinks'] = 'Allow external video links (e.g. YouTube)';
$string['allowexternallinks_help'] = 'When enabled site-wide, activity admins may allow students and teachers to paste links to externally-hosted videos (YouTube, Vimeo, esup-portail/Pod or any other public URL).';
$string['allowstudentpeerselection'] = 'Allow students to select peers';
$string['allowstudentpeerselection_help'] = 'If enabled, students can select peer partners by themselves.';
$string['allowstudentupload'] = 'Students can upload videos';
$string['allowstudentupload_help'] = 'If enabled, students can upload videos one by one. Bulk upload is available only for teachers in the "Advanced options" section.';
$string['allowvideorecord'] = 'Allow video recording';
$string['allowvideorecord_help'] = 'If enabled, students and teachers can record videos directly using their device camera.';
$string['allowvideorecording'] = 'Allow video recording';
$string['allowvideorecording_help'] = 'When enabled site-wide, activity admins may allow students and teachers to record videos in the browser.';
$string['allowvideoupload'] = 'Allow video uploads';
$string['allowvideoupload_help'] = 'If enabled, students and teachers can upload video files directly to the system.';
$string['allowvideouploads'] = 'Allow video uploads';
$string['allowvideouploads_help'] = 'When enabled site-wide, activity admins may allow students and teachers to upload video files directly to the activity.';
$string['allowyoutube'] = 'Allow external video links (e.g. YouTube)';
$string['allowyoutube_help'] = 'If enabled, students and teachers can paste a link to an externally-hosted video (YouTube, Vimeo, esup-portail/Pod or any other public URL) for assessment.';
$string['allparticipants'] = 'All participants';
$string['allscores'] = '</span><span class="red">Self,</span> <span class="blue">Peer,</span> <span class="green">Teacher,</span> <span class="orange"> and Class</span> Scores';
$string['assess'] = 'Assess';
$string['assess_help'] = 'In the assess stage, students do self-assessment, peer assessment with the rubrics set up in Advanced Grading.  It is also possible to give a student the right to design a rubric by giving one of them \'teacher-rights\' or by designing a rubric on paper for the teacher to input. By default, students cannot see the teacher\'s scoring until after completing self assessment. Then students can view the teacher\'s assessment.';
$string['assessagain'] = 'Assess again';
$string['assessedby'] = 'Assessed by';
$string['assessorsandweightings'] = 'Assessors and weightings';
$string['assignclass'] = 'Assign class';
$string['assignclass_help'] = 'This feature allows a teacher to turn on or turn off the "Class" mode of assessment. The "Class" mode is for all the students watching a live, real-time performance to assess the speaker without a recording. This is more difficult to assess accurately, due to the time pressure, but it gives students practice in using and understanding the rubric, and keeps them learning actively instead of half-listening to a presentation. Students must log into the class website and find the appropriate Video Assessment activity on their course. They search for the student doing the performance and begin choosing the scores on each scale. The scores of all students are averaged for one single "Class" score for the whole class, thus mitigating too high or too low scores.';
$string['assignmentisdue'] = 'videoassessment is due';
$string['assignpeerassessors'] = 'Assign Peer Assessors';
$string['assignpeerassessorsrandomly'] = 'Assign peer assessors randomly';
$string['assignpeers'] = 'Assign peers';
$string['assignpeers_help'] = 'Enter the number of peer assessors.  Two peers is the default. There are three methods to assign peer assessors: 1) randomly from enrollees with only \'student\' role in the course, 2) randomly from members in a course group, and 3) manually. Both ways of random assignments can be adjusted manually after being auto-assigned.';
$string['assignpeersaftersave'] = 'Save this activity first, then you can assign peers.';
$string['assignpeersrandomly'] = 'Assign peers randomly';
$string['associate'] = 'Associate';
$string['associate_help'] = 'After uploading files, each video file must be associated to the correct performing student. The file is matched by selecting one student by their moodle login name (user) in the course. This is called the "associate" phase in the process (after "upload" and before "assess"). In this screen, a drop-down menu lists all the students (users) in the course (or in the section of the course).';
$string['associated'] = 'Associated';
$string['associations'] = 'Associations';
$string['autodeletefiles'] = 'Automatic file deletion at course end date';
$string['autodeletefiles_help'] = 'If enabled, all video files associated with this activity will be automatically deleted when the course end date is reached. This helps manage storage space after courses are completed.';
$string['availabledate'] = 'Available from';
$string['average'] = 'Average';
$string['backupdefaults'] = 'Backup defaults';
$string['backupusers'] = 'Include user data';
$string['backupusersdesc'] = 'Sets the default for whether to include user data (videos and grades) in backups.';
$string['before'] = 'Before';
$string['beforeafter'] = 'Before/after';
$string['beforeclass'] = 'Class';
$string['beforeduedate'] = 'before due date';
$string['beforegrade'] = 'Before grade';
$string['beforelabel'] = 'Before';
$string['beforemarks'] = 'Before scores';
$string['beforepeer'] = 'Peer';
$string['beforeself'] = 'Self';
$string['beforeteacher'] = 'Teacher';
$string['beforetraining'] = 'Training Pre-test';
$string['beforevideo'] = 'Before video';
$string['bonuspercentage'] = 'Bonus Percentage';
$string['bonuspercentage_help'] = 'The percentage of the final grade that will be allocated as a fairness bonus. This bonus is added on top of the total score. For example, if set to 10%, students can earn up to 10% additional points based on how fairly they score compared to the teacher\'s assessment.';
$string['bulkvideoupload'] = 'Bulk video upload';
$string['calendardue'] = '{$a} is due';
$string['calendargradingdue'] = '{$a} is due to be graded';
$string['changetraingingwarning'] = 'changetraingingwarning';
$string['changeuploadtype'] = 'changeuploadtype';
$string['class'] = 'Class';
$string['classassessments'] = 'Class Assessments';
$string['classgrading'] = 'Whole Class Grading';
$string['classgrading_help'] = 'If you want the whole class of students to watch a live performance and assess it in real time, use this feature. To turn on whole class grading, click \'Open Class Grading\'.  The default is "Close class grading".  All the student\'s grades will be totaled into one average grade.';
$string['clickonthe'] = 'Click on the';
$string['close'] = 'Close Class Grading';
$string['command_validator_disallowed_character'] = 'The command contains a character that is not allowed. Only letters, digits, spaces and the characters _ - . / = { } \' are permitted.';
$string['command_validator_empty'] = 'The command must not be empty.';
$string['command_validator_forbidden_substring'] = 'The command contains a forbidden sequence: {$a}. Shell metacharacters are not permitted.';
$string['command_validator_input_placeholder'] = 'The command must contain the {INPUT} placeholder exactly once.';
$string['command_validator_must_invoke_ffmpeg'] = 'The command must invoke the ffmpeg binary as its first token.';
$string['command_validator_must_invoke_mp4box'] = 'The command must invoke the MP4Box binary as its first token.';
$string['command_validator_output_placeholder'] = 'The command must contain the {OUTPUT} placeholder exactly once.';
$string['comment'] = 'Comment';
$string['confirmdeletegrade'] = 'Are you sure you want to delete this grade?';
$string['confirmdeletevideos'] = 'Are you sure you want to delete {$a} videos?';
$string['course'] = 'course';
$string['coursemisconf'] = 'Course setting is incorrect.';
$string['courseshortnameexist'] = 'Short name is already used for another course';
$string['currentgrade'] = 'Current grade in gradebook';
$string['daysbefore'] = 'days before';
$string['defaultcriterionbodylanguage'] = 'Good body language, facial expression, eye contact';
$string['defaultcriterioncontent'] = 'Interesting, engaging content';
$string['defaultcriterionlanguage'] = 'Easy-to-understand language';
$string['defaultcriterionstructure'] = 'Strong introduction, transitions, conclusions';
$string['defaultcriterionvoice'] = 'Clear voice with stress/intonation';
$string['defaultrubrictemplate'] = 'Quick-start rubric for general performances (modifiable)';
$string['defaultrubrictemplatedesc'] = 'This is a quick-start rubric template for general performances. You can customize it to fit your needs.';
$string['delayedteachergrade'] = 'Delayed Teacher Grade';
$string['delayedteachergrade_help'] = 'If enabled \'yes\', the teacher assessment ratings will not be shown to the student, until he/she has completed his/her self assessment. This will alleviate any bias in student scoring by not seeing a teacher score before they start assessing.';
$string['deleteselectedvideos'] = 'Delete selected videos';
$string['deletevideo'] = 'Delete video';
$string['deletevideos'] = 'Bulk Video Deletion';
$string['deletevideos_help'] = 'A teacher can delete multiple files this way.';
$string['deletevideos_videos'] = 'Videos';
$string['deletevideos_videos_help'] = 'All selected videos are deleted from the activity. Video data on the server will be cleaned up by Moodle cron.';
$string['description'] = 'Description';
$string['difference'] = 'difference';
$string['disassociate'] = 'Disassociate';
$string['diskspacetmpl'] = 'Server disk space: {$a->free} free / {$a->total} total';
$string['donotclickhere'] = 'Do not click here.';
$string['downloadexcel'] = 'Download results in Excel';
$string['dropvideofileshere'] = 'Drop video files here';
$string['duedate'] = 'Due date';
$string['duplicateerrors'] = 'Duplicate Errors';
$string['duplicatefor'] = 'Duplicate for';
$string['duplicaterubric'] = 'Duplicate Rubric';
$string['duplicaterubric_help'] = 'This feature will repeat the rubric that has been created for the teacher, and duplicate the rubric into the assessments of self, peer, and class modes.';
$string['duplicatesuccess'] = 'Duplicate Success';
$string['educatornote_landscape'] = 'Note to educators: for best results advise learners to always record videos with their smartphone in landscape position (horizontal).';
$string['errorcapturingmedia'] = 'Error capturing media:';
$string['errorcheckvideostodelete'] = 'Check videos to delete.';
$string['errorinvalidtiming'] = 'Invalid timing value';
$string['errornovideorecord'] = 'Please add a video record first';
$string['errorovermaximumpossiblegrade'] = 'The grade to pass can not be greater than the maximum possible grade 100';
$string['erroruploadvideo'] = 'Please upload a video';
$string['event_grade_assigned'] = 'Grade assigned';
$string['event_peer_review_submitted'] = 'Peer review submitted';
$string['event_report_viewed'] = 'Report viewed';
$string['event_video_uploaded'] = 'Video uploaded';
$string['existingcourse'] = 'Publish to an existing course';
$string['existingcourse_help'] = 'If set to other than (New), videos will be published to the selected course. You need to be able to add resources to the course.';
$string['existingcourseornewcourse'] = 'Publish to an Existing Course<br /> or a New Course';
$string['failed'] = 'Fail';
$string['failednotice'] = 'Sorry. Some of your scores were {$a->accepteddifference}% different from standard scores. You must have all "O", no "X".<br />{$a->button}';
$string['fairnessbonus'] = 'Fairness Bonus';
$string['feedback'] = 'Feedback';
$string['feedbackfrom'] = 'Feedback from {$a}';
$string['ffmpegcommand'] = 'FFmpeg command';
$string['ffmpegcommanddesc'] = 'FFmpeg command line with placeholders: {INPUT} {OUTPUT}';
$string['ffmpegthumbnailcommand'] = 'FFmpeg thumbnail command';
$string['ffmpegthumbnailcommanddesc'] = 'FFmpeg command line with placeholders: {INPUT} {OUTPUT}, with options to output an image';
$string['filedeleted'] = 'File is deleted.';
$string['fileuploadlinks'] = 'File uploads / Links';
$string['fileuploads'] = 'File uploads';
$string['finalscore'] = 'Final    Score';
$string['finalscorefortable'] = 'Final score';
$string['finishmakingrubric'] = 'Finish making rubric → Go to assess';
$string['firstassess'] = 'First assess';
$string['firstassessmentbystudent'] = 'First assessment by student';
$string['firstassessmentbyteacher'] = 'First assessment by teacher';
$string['fullnamecourse'] = 'Course Full Name';
$string['gdprcookiesuppression'] = 'Suppress tracking cookies (GDPR)';
$string['gdprcookiesuppression_help'] = 'When enabled, external videos are embedded through the privacy-enhanced host (youtube-nocookie.com for YouTube, the Do-Not-Track parameter for Vimeo) so the video service does not set tracking cookies until the learner starts playback. Recommended for GDPR compliance.';
$string['generalcomments'] = 'General Comments';
$string['grade'] = 'Grade';
$string['grade_grading_name'] = 'Grading';
$string['grade_help'] = 'This section is for settings that combine the self/peer/class/teacher grades. One combined grade will be uploaded to the Gradebook in this class. Scoring details can be analyzed and downloaded in Excel format by going to the main assessment page and finding the download link. In addition, the Advanced options section has optional settings for pre-calibration and fairness bonus to improve self assessment.';
$string['grade_rating_name'] = 'Rating';
$string['gradecategory'] = 'Grade Category';
$string['graded'] = 'Graded';
$string['gradeitem:beforeclass'] = 'Class';
$string['gradeitem:beforepeer'] = 'Peer';
$string['gradeitem:beforeself'] = 'Self';
$string['gradeitem:beforeteacher'] = 'Teacher';
$string['gradeitem:beforetraining'] = 'Training Pre-test';
$string['gradingareadefined'] = 'Can not duplicated because rubric is already exist';
$string['gradingmethod_help'] = 'Choose the advanced grading method that should be used for calculating grades in the given context.

To disable advanced grading and switch back to the default grading mechanism, choose \'Simple direct grading\'.';
$string['group'] = 'group';
$string['groupname'] = 'Group name';
$string['inputnewcoursename'] = 'Input a new course name';
$string['inputnewcourseshortname'] = 'Input a new course short name';
$string['insertintosection'] = 'Insert into Section';
$string['installerrorffmpegdoesnotexist'] = 'The default installation path of ffmpeg does not exist!';
$string['installerrorffmpegversionnotfound'] = 'The ffmpeg version number was not found. Please check if ffmpeg is installed and working correctly on the system.';
$string['installsuccessffmpeg'] = 'ffmpeg detected successfully: {$a}';
$string['invalidid'] = 'Invalid ID';
$string['invalidtoken'] = 'Invalid token.';
$string['invaliduploadedfile'] = 'Uploaded file is invalid.';
$string['latesubmissionsaccepted'] = 'Allowed until {$a}';
$string['level'] = 'Level';
$string['liststudents'] = 'List students';
$string['loading'] = 'Loading...';
$string['managegrades'] = 'Manage grades';
$string['manageuploadedvideos'] = 'Manage uploaded videos';
$string['managevideo'] = 'Manage videos';
$string['managevideos'] = 'Manage videos';
$string['managevideos_help'] = 'The "Manage Videos" admin page has nine functions to click. You do not have to touch any function unless you wish to change the default settings.
<br />a. Upload a Video
<br />b. Bulk Upload Videos
<br />c. Bulk Video Deletion
<br />d. Associate
<br />e.  Assess
<br />f.  Assign Peers
<br />g. Publish Videos
<br />h. Assign Class
<br />i.  Duplicate Rubric';
$string['markasreadonnotificationno'] = 'Notifications are not marked as read automatically.';
$string['markasreadonnotificationyes'] = 'Notifications are marked as read automatically.';
$string['maximumpoints'] = 'maximum points';
$string['mobilequickmail'] = 'Mobile Quickmail';
$string['modgrade'] = 'Grade Type';
$string['modgrade_help'] = 'For video assessment, do not change the default settings here for "Grade Type". The Grade Type is "Point" and the maximum grade is "100".  If you change the settings, the video assessment system may not work.';
$string['modulename'] = 'Video Assessment';
$string['modulenameplural'] = 'Video Assessments';
$string['mp4boxcommand'] = 'MP4Box command';
$string['mp4boxcommanddesc'] = 'MP4Box command which enables progressive playback of MP4 videos';
$string['myvideos'] = 'My video';
$string['namesort'] = 'First name / Surname';
$string['no'] = 'No';
$string['nomoresubmissionsaccepted'] = 'Only allowed for participants who have been granted an extension';
$string['nopeergroup'] = 'No peer groups yet';
$string['nostudentsingroup'] = 'No students found in this course.';
$string['notattempted'] = 'Not attempted';
$string['notext'] = 'No text';
$string['notgradedyet'] = 'Not graded yet.';
$string['notificationcarriergroup'] = 'Notification carrier';
$string['notificationcarriergroup_help'] = 'There are two choices for notifications: by the registered Moodle email address in the site, or by Mobile Quickmail (an optional block for using mobile phone email addresses). One or both can be selected.';
$string['notificationcontenttypegroup'] = 'Notification content';
$string['notificationmessagesent'] = 'Notification Message sent';
$string['notifications'] = 'Notifications';
$string['notifications_help'] = 'Notifications send the assessment information to the students via their email inboxes or their mobile quickmail addresses. There are four kinds of notifications:
<br />a. Teacher Comment notification
<br />b. Peer Comment notification
<br />c. Reminder notification
<br />d. Video upload/reupload notification';
$string['notificationssendtype'] = 'Notification carrier';
$string['notsupportedbrowser'] = 'This browser is not supported';
$string['novideo'] = 'No video';
$string['numberofpeerassessors'] = 'Number of Peer Assessors';
$string['numberofpeers'] = 'Number of peers';
$string['offairnessbonus'] = 'of Fairness bonus';
$string['ofteacherscore'] = 'of teacher score = ';
$string['onduedate'] = 'on due date';
$string['onpeerassessment'] = 'on peer assessment';
$string['onselfassessment'] = 'on self assessment';
$string['onselfassessmentwithcomments'] = 'on self assessment with 20 words of comments';
$string['ontopoftotal'] = 'On top of total';
$string['onvideouploaded'] = 'on video uploaded';
$string['open'] = 'Open Class Grading';
$string['operations'] = 'Operations';
$string['or'] = 'or';
$string['order'] = 'Order';
$string['orderasc'] = 'Ascending';
$string['orderdesc'] = 'Descending';
$string['originalname'] = 'Original name';
$string['passed'] = 'Pass';
$string['passednotice'] = 'Congratulations! All of your scores were near standard scores!<br />Go to {$a} assessment.';
$string['path'] = 'Path';
$string['pause'] = 'Pause';
$string['peer'] = 'Peer';
$string['peerassessments'] = 'Peer assessments';
$string['peerassignmentrequired'] = 'You must assign peer assessors when Peer % is greater than 0 or Number of Peer Assessments is greater than 0. Either assign peers or set both values to 0.';
$string['peercomentnotificationlabel'] = 'Peer Comment notification';
$string['peerfairnessbonus'] = 'Peer Fairness Bonus';
$string['peerfairnessbonus_help'] = 'The Peer Fairness bonus rewards students who score \'fairly\', that is, their scores are not all \'100s" or "0s" and fairly close to what the teacher is scoring. The options for setting up this tool include deciding how much of a bonus (% of final score) you will assign, and how much of that bonus students will receive based on proximity to the teachers score.';
$string['peerfairnessbonusfortable'] = '+PeerFairness<br>bonus';
$string['peergroup'] = 'Peer group';
$string['peerratings'] = 'Peer ratings';
$string['peers'] = 'Peers';
$string['peertnotificationtemplate'] = 'Dear [[student name]],
Good work! One of your classmates just checked your presentation
video and made some scores and comments. Here they are:
[[insert assignment name]] [[insert current date]]
Here is a link to this report: [[insert link to student page to view assessment]]
**your classmates will get a bonus if they score you fairly**
Send an email to me if you have a question [[teacher email address]]
Best regards,
[[teacher name]]';
$string['pleasechoosegradingareas'] = 'Please choose grading areas';
$string['pleasechoosevideos'] = 'Please choose videos';
$string['pleasedefinerubricforteacher'] = 'Please define rubric for teacher';
$string['pluginadministration'] = 'Video Assessment administration';
$string['pluginname'] = 'Video Assessment';
$string['preventlate'] = 'Prevent late submissions';
$string['preventvideouploads'] = 'Prevent video uploads';
$string['preventvideouploads_help'] = 'If enabled, students will not be able to upload videos for this activity.';
$string['previewvideo'] = 'Preview video';
$string['printreport'] = 'Print report';
$string['printrubrics'] = 'Print all rubric report';
$string['printview'] = 'Open print view';
$string['privacy:metadata:videoassessment'] = 'Information about the videoassessment files.';
$string['privacy:metadata:videoassessment:class'] = 'Number of class.';
$string['privacy:metadata:videoassessment:course'] = 'Course ID number.';
$string['privacy:metadata:videoassessment:intro'] = 'Details about the file.';
$string['privacy:metadata:videoassessment:name'] = 'Name of the course.';
$string['privacy:metadata:videoassessment:ratingpeer'] = 'Rating by peer.';
$string['privacy:metadata:videoassessment:ratingself'] = 'Rating by self.';
$string['privacy:metadata:videoassessment:ratingteacher'] = 'Rating by teacher.';
$string['privacy:metadata:videoassessment:timemodified'] = 'Last modification time.';
$string['privacy:metadata:videoassessment:trainingdesc'] = 'Description about the training.';
$string['privacy:metadata:videoassessment_aggregation'] = 'Information about the videoassessment aggregations.';
$string['privacy:metadata:videoassessment_aggregation:timemodified'] = 'Modification time.';
$string['privacy:metadata:videoassessment_aggregation:timing'] = 'videoassessment aggregation time.';
$string['privacy:metadata:videoassessment_aggregation:userid'] = 'The user for whom this videoassessment aggregation.';
$string['privacy:metadata:videoassessment_aggregation:videoassessment'] = 'videoassessment ID.';
$string['privacy:metadata:videoassessment_grade_items'] = 'List of grade Items.';
$string['privacy:metadata:videoassessment_grade_items:gradeduser'] = 'User who grading.';
$string['privacy:metadata:videoassessment_grade_items:type'] = 'Name or type of grade.';
$string['privacy:metadata:videoassessment_grade_items:videoassessment'] = 'videoassessment ID.';
$string['privacy:metadata:videoassessment_grades'] = 'Grading records about video.';
$string['privacy:metadata:videoassessment_grades:grade'] = 'Grade number.';
$string['privacy:metadata:videoassessment_grades:gradeitem'] = 'Grading ID.';
$string['privacy:metadata:videoassessment_grades:submissioncomment'] = 'Comment about grade.';
$string['privacy:metadata:videoassessment_grades:timemarked'] = 'Grading entry time.';
$string['privacy:metadata:videoassessment_grades:videoassessment'] = 'videoassessment ID.';
$string['privacy:metadata:videoassessment_peers'] = 'Peer partner information.';
$string['privacy:metadata:videoassessment_peers:peerid'] = 'Peer ID.';
$string['privacy:metadata:videoassessment_peers:userid'] = 'Peer partner user ID.';
$string['privacy:metadata:videoassessment_peers:videoassessment'] = 'videoassessment ID.';
$string['privacy:metadata:videoassessment_sort_items'] = 'List of sort items.';
$string['privacy:metadata:videoassessment_sort_items:itemid'] = 'Sort item ID.';
$string['privacy:metadata:videoassessment_sort_items:type'] = 'Type of sort items.';
$string['privacy:metadata:videoassessment_sort_order'] = 'Sorting order of sort items.';
$string['privacy:metadata:videoassessment_sort_order:sortitemid'] = 'Id of sort item.';
$string['privacy:metadata:videoassessment_sort_order:userid'] = 'For whom this sortable item is.';
$string['privacy:metadata:videoassessment_video_assocs'] = 'Video assignments.';
$string['privacy:metadata:videoassessment_video_assocs:associationid'] = 'User who associate with this video.';
$string['privacy:metadata:videoassessment_video_assocs:timemodified'] = 'Last modification time.';
$string['privacy:metadata:videoassessment_video_assocs:videoassessment'] = 'videoassessment ID.';
$string['privacy:metadata:videoassessment_video_assocs:videoid'] = 'Video storage ID.';
$string['privacy:metadata:videoassessment_videos'] = 'Information about uploaded video.';
$string['privacy:metadata:videoassessment_videos:filename'] = 'Server name of the video file.';
$string['privacy:metadata:videoassessment_videos:filepath'] = 'Path of the video file.';
$string['privacy:metadata:videoassessment_videos:originalname'] = 'Uploaded name of the video file.';
$string['privacy:metadata:videoassessment_videos:timecreated'] = 'Uploaded time of the file.';
$string['privacy:metadata:videoassessment_videos:timemodified'] = 'Last modification time of the file.';
$string['privacy:metadata:videoassessment_videos:videoassessment'] = 'videoassessment ID.';
$string['publishvideos'] = 'Publish Videos';
$string['publishvideos_help'] = 'In this stage, after all performances are assessed, a teacher can select videos for long term preservation. These videos will be published to a
separate, newly created course in your site.';
$string['publishvideos_videos'] = 'Videos';
$string['publishvideos_videos_help'] = 'Selected videos will be published to an existing course or a new course.';
$string['publishvideostocourse'] = 'Publish videos to a course';
$string['quickSetup'] = 'Quick Setup';
$string['quickSetup_help'] = 'Quick Setup';
$string['ratingclass'] = 'ratingclass';
$string['ratingclass_help'] = 'This rating is not used with video recordings, but for live performances, with all classmates making scores and giving comments. Usually at 0%, even if turned \'on\', the purpose of the whole class rating is to keep the audience busy and learning the rubrics. It must be turned on in the Grading menu. In reports, the class average score is usually shown in the color \'yellow\'.';
$string['ratingpeer'] = 'Peer weighting';
$string['ratingpeer_help'] = 'Set the weighting of the peer grading within a student\'s total grade. More than one peer can grade the student (default is 2), and the average score of the peers will be displayed. In reports, the peer\'s scores are usually shown in the color \'blue\'.';
$string['ratings'] = 'Ratings';
$string['ratings_help'] = 'When combining self/peer/class/teacher assessments, a teacher can set the weighting of each type of assessment within 100%. Typical weightings might be Teacher-80%, Self-10%, Peer-10%, Class-0%. The total of the percentages must be 100%, or a warning will appear. If you wish for the teacher to be the only assessor, then set the percentages like this: Teacher-100%, Self-0%, Peer-0%, Class-0%.';
$string['ratingself'] = 'Self weighting';
$string['ratingself_help'] = 'Set the weighting of the self grading within a student\'s total grade. Self assessment is often influenced by others" scores, so the teacher\'s score is not shown until a student has completed her/his self assessment. The self scores are usually shown in the color \'red\'.';
$string['ratingteacher'] = 'Teacher weighting';
$string['ratingteacher_help'] = 'Set the weighting of the teacher\'s grading of a student\'s total grade. More than one teacher can grade the student, and the average score of the teachers will be displayed. If the teacher is the only rater, then this setting should be 100%, and all others 0%. The teacher\'s scores are usually shown in the color \'green\'.';
$string['readyforuse'] = 'Ready for use';
$string['reallydeletevideo'] = 'Are you sure you want to delete this video?';
$string['reallyresetallpeers'] = 'This will reset peer assignments and re-assign randomly. Continue?';
$string['recordnewvideo'] = 'Record New Video (max. length 2 minutes)';
$string['recordnewvideo_help'] = 'Select "Insert External Video Link" to paste a URL to a video hosted on YouTube, Vimeo, esup-portail/Pod or any other public service (YouTube "Shorts" are supported). Select "Upload Video File" to upload a video file from your device. Select "Record New Video" to record directly with your device camera.';
$string['recordradios'] = 'Record New Video';
$string['recordradios_help'] = 'Record New Video is for directly recording a video for assessment.
This function accesses the camera on your computer or mobile phone and starts a video recording.
In contrast, the "Upload Video File" selection goes to the photo/video library of files to allow you to select a previously recorded video to upload.
<br/>*Click the stop recording button, and then upload it automatically*';
$string['registeredemail'] = 'registered email';
$string['remark'] = 'Remark';
$string['reminder_notifition_mail_cron'] = 'reminder notifition mail cron';
$string['remindernotification'] = 'Reminder Notification';
$string['remindernotificationtemplate'] = 'Dear [[student name]],
Have you watched and checked your presentation?
Its due date is/was on June x. Here is a link:
[[insert link to self-assessment page]]
Be sure to write at least 3 comments as well as scores.
Send an email to your me if you have a question [[teacher email
address]]. Thanks!
Best regards,
[[teacher name]]';
$string['report'] = 'Report';
$string['results'] = 'Results';
$string['resumerecording'] = 'Resume Recording';
$string['retakevideo'] = 'Retake a video';
$string['reuploadvideo'] = 'Replace the linked / uploaded video';
$string['rubricnotcompleted'] = 'Please complete all rubric criteria before submitting.';
$string['save'] = 'Save';
$string['saveandcreaterubric'] = 'Save and create rubric';
$string['saveassociations'] = 'Save associations';
$string['score'] = 'Score';
$string['scores'] = 'Scores';
$string['seereport'] = 'See report';
$string['self'] = 'Self';
$string['selfassessments'] = 'Self assessments';
$string['selffairnessbonus'] = 'Self Fairness Bonus';
$string['selffairnessbonus_help'] = 'The Self Fairness bonus rewards students who score \'fairly\', that is, their scores are not all \'100s" or "0s" and fairly close to what the teacher is scoring. The options for setting up this tool include deciding how much of a bonus (% of final score) you will assign, and how much of that bonus students will receive based on proximity to the teachers score.';
$string['selffairnessbonusfortable'] = '+SelfFairness<br>bonus';
$string['selfpeer'] = 'Self / Peer';
$string['selfratings'] = 'Self ratings';
$string['settotalratingtoahundredpercent'] = 'Four ratings (Teacher + Self + Peer + Class) must equal 100%.';
$string['shortnamecourse'] = 'Course Short Name';
$string['simpledirectgroup'] = 'Grading - simple direct';
$string['singlevideoupload'] = 'Single video upload';
$string['sortby'] = 'Sort by';
$string['sortid'] = 'Sort by ID';
$string['sortmanually'] = 'Sort manually';
$string['sortname'] = 'Sort by name';
$string['startrecoding'] = 'Start Recording';
$string['stoprecording'] = 'Stop Recording';
$string['studentrubric'] = 'Student rubric';
$string['submission'] = 'Submission';
$string['submissionby'] = 'Submission by {$a}';
$string['takevideo'] = 'Take a video';
$string['task_automatic_file_deletion'] = 'Automatic file deletion at course end date';
$string['teacher'] = 'Teacher';
$string['teacherassesstraining'] = 'Assess training pre-test';
$string['teachercomentnotificationlabel'] = 'Teacher Comment notification';
$string['teachercommentnotification'] = 'Notification content';
$string['teachercommentnotification_help'] = 'a. Teacher Comment notification is to send an email to the student whenever a teacher makes comments and saves the assessment.
<br />b. Peer Comment notification: is to send an email notification to the student whenever a peer makes comments and saves the assessment.
<br />c. Reminder notification is given when a student has forgotten an assignment or is late.
<br />d. Video upload/reupload notification gives a notification to the teacher whenever a video is uploaded or re-uploaded into the video assessment module.
The email message format for each type of notification must be set up by the teacher.';
$string['teachernotificationtemplate'] = 'Dear [[student name]],
Good work! I just checked your presentation video and made some
scores and comments. Here they are:
[[insert assignment name]] [[insert current date]]
Here is a link to this report: [[insert link to student page to view assessment]]
You can redo your presentation on June 7th and get a better grade.
Send an email to me if you have a question [[teacher email address]]
Best regards,
[[teacher name]]';
$string['teacherratings'] = 'Teacher ratings';
$string['teacherrubric'] = 'Teacher rubric';
$string['teacherselfpeer'] = 'Teacher/self/peer/class';
$string['templatetextfornotification'] = 'Template text for notification';
$string['timemarked'] = 'Time marked';
$string['timing'] = 'Timing';
$string['timinggrade'] = '{$a} grade';
$string['timinglabel'] = 'Your word for before/after';
$string['timinglabel_help'] = 'By inputting a word here, you can customize the labels for "before" and "after." If this is left blank, standard "before" and "after" are used.';
$string['timingscores'] = '{$a} scores';
$string['title'] = 'Title:';
$string['total'] = 'Total';
$string['totalgrade'] = 'Total grade';
$string['totalscore'] = 'Total    Score';
$string['training'] = 'Training';
$string['trainingdesc'] = 'Training explanation';
$string['trainingdesc_help'] = 'Add an explanation to teach students how to score and what the accepted difference from teacher scores on each rubric criterion are. The students
need to receive all \'circles\' (within acceptable difference from teacher scores) to pass.';
$string['trainingdeschelp'] = 'Training explanation text';
$string['trainingdesctext'] = 'To pass this training, you (red score) must evaluate each scale within xx% of the teacher\'s score (green score). If you are xx% or under, you
receive a "maru" "O". If you are over xx%, you receive a "batsu" "X". All scales must be "O" before you can pass.';
$string['trainingpretest'] = 'Training Pre-test';
$string['trainingpretest_help'] = 'Similar to \'calibrating\' for scoring a test, this Training Pre-test function forces students to first pass a training test before moving on to actual scoring. Students watch an uploaded video and rubric provided by the teacher. They can only pass when they score within a decided difference (20% for example) from the pre-entered desired scores by the teacher.';
$string['trainingvideo'] = 'Training video';
$string['trainingvideo_help'] = 'Upload a video for the students to practice and complete scoring on the training Pre-test.';
$string['tryagain'] = 'Try again';
$string['typeofassessment'] = 'Types of assessment';
$string['unassociated'] = 'Unassociated';
$string['unlimited'] = 'Unlimited';
$string['upload'] = 'Upload video';
$string['uploadedat'] = 'Uploaded at';
$string['uploadedtime'] = 'Uploaded time';
$string['uploadfile'] = 'Upload Video File';
$string['uploadfile_help'] = 'There are two stages: Uploading the file, and then converting the file. The converting process compresses the file to 1/10th the size. Sometimes this takes a long time—over ten minutes. Please check if your camera is set on 4K, which is too high, and lower the resolution and lower the fps. VGA or 720hd, 30fps is better.';
$string['uploadfilename'] = 'Filename';
$string['uploadfilesize'] = 'Size';
$string['uploadingvideo'] = 'Upload / link video';
$string['uploadingvideo_help'] = 'You can share your recorded performance for assessment with three methods. In this screen, students and teachers can:
<br />1) Upload your file to YouTube or another hosting service and post a URL link to that video. Note: Youtube "Shorts" cannot be used.
<br />2) Upload a single file of your video performance from your device. Set your camera to its lowest resolution for smaller file size and faster upload times. Note: options 2 and 3 are only available to students if the default for "Students can upload videos" under "Video submissions" in the Settings menu is kept at "Yes", and the site admin has not disabled video uploads. Additionally, teachers can bulk upload video files from an SD card in "Bulk upload videos" under "Advanced options" in the Settings menu.
<br />3) Record a new video directly using your device camera and upload it here.';
$string['uploadingvideonotice'] = 'Uploading... please wait a few minutes';
$string['uploadmessage'] = 'Your video file is over 500MB. Please retake the video at a lower resolution or re-upload a smaller file';
$string['uploadmimetype'] = 'Type';
$string['uploadprogress'] = 'Progress';
$string['uploadstatus'] = 'Status';
$string['uploadvideo'] = 'Upload / link a video';
$string['uploadvideo_help'] = 'Here a teacher can click a link and upload a single video file. The file should contain the performance of one student. Record the video of each performance separately. During video upload the file is compressed to 10% of the original size.';
$string['uploadvideos'] = 'Upload videos';
$string['uploadyoutube'] = 'Insert External Video Link';
$string['uploadyoutube_help'] = 'For better performance, upload your video into your personal YouTube account or another video sharing site. Then copy the link and paste the link into the box for that link. When you link to a Youtube file, there is no thumbnail photo showing on the assessment screen. Just play the video and it will appear.';
$string['url'] = 'URL';
$string['url_error'] = 'Please enter the correct Youtube URL';
$string['url_help'] = 'This is a Youtube URL';
$string['usedpeers'] = 'Number of Peer Assessors';
$string['usedpeers_help'] = 'Enter the number of peer assessors (0 or more). In Assign Peer Assessors below, the teacher can then assign the peer automatically or manually. The default is 2 peer assessors.';
$string['usedpeerserror'] = 'Number of Peer Assessors must be a non-negative integer (0 or greater).';
$string['video'] = 'Video';
$string['videoalreadyassociated'] = '{$a} has been already associated with a video.';
$string['videoassessment:addinstance'] = 'Add a new video assessment';
$string['videoassessment:associate'] = 'Associate bulk uploaded videos with users';
$string['videoassessment:bulkupload'] = 'Bulk upload videos';
$string['videoassessment:bulkupload_help'] = 'A teacher can drag multiple video files into this window. The files will upload and be converted in order. 10-20 files may take up to an hour depending on the resolution. Avoid high resolution videos such as 4K as the size is too large for efficient handling and is unnecessary for the purpose of performance assessment. VGA quality or 720HD 30fps is preferable. In testing, all video formats were compatible.';
$string['videoassessment:exportownsubmission'] = 'Export own submission';
$string['videoassessment:fetchcourses'] = 'Access course list for video publishing';
$string['videoassessment:fetchsections'] = 'Access course sections for video publishing';
$string['videoassessment:grade'] = 'Grade video assessment';
$string['videoassessment:gradepeer'] = 'Grade peer video assessment';
$string['videoassessment:managesorting'] = 'Manage student sorting order';
$string['videoassessment:submit'] = 'Submit video assessment';
$string['videoassessment:view'] = 'View video assessment';
$string['videoassessment:viewcomments'] = 'View assessment comments';
$string['videoassessmentname'] = 'Video assessment name';
$string['videoassessmentnotfound'] = 'Video assessment not found';
$string['videoformat'] = 'Video format';
$string['videoformatdesc'] = 'Video format';
$string['videonotfound'] = 'Video not found.';
$string['videonotificationtemplate'] = 'Dear [[teacher name]],
[[student name]] has just uploaded a video file.
To view it and assess it, please go to: [[insert link to self-assessment page]]
Best regards,
https://moodle.sgu.ac.jp';
$string['videopublishing'] = 'Video Publishing';
$string['videos'] = 'Videos';
$string['videosubmissions'] = 'Video submissions';
$string['videouploadforthefirsttime'] = 'when the student uploads a video for first time';
$string['videouploadnotificationlabel'] = 'Video upload/reupload notification';
$string['viewassessmentsofmyvideo'] = 'View assessments of my video';
$string['viewassociatedvideos'] = 'View associated videos';
$string['viewresult'] = 'View result';
$string['weighting'] = 'Weighting';
$string['whatinfomationtosend'] = 'What information to send';
$string['whatinfomationtosendcontents'] = '<div class="max-with">[[student name]]<br/>[[VA assignment name]]<br/>[[current date]]<br/>[[link to view whole assessment report]]->view Report<br/>[[teacher email address]]<br/>[[teacher name]]</div>';
$string['whenevervideoupload'] = 'whenever a student re-uploads a video';
$string['whentosendnotification'] = 'When to send notification';
$string['within'] = 'within';
$string['xfeedback'] = '{$a} feedback';
$string['xunassignedstudents'] = '{$a} unassigned students';
$string['yes'] = 'Yes';
