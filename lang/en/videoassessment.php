<?php
defined('MOODLE_INTERNAL') || die();

$string['addmember'] = 'Add a member';
$string['addpeer'] = 'Add peer...';
$string['addpeergroup'] = 'Add a peer group';
$string['after'] = 'After';
$string['aftergrade'] = 'After grade';
$string['afterlabel'] = 'After';
$string['aftermarks'] = 'After scores';
$string['afterpeer'] = 'After - peer';
$string['afterself'] = 'After - self';
$string['afterteacher'] = 'After - teacher';
$string['aftervideo'] = 'After video';
$string['allowstudentpeerselection'] = 'Allow students to select peers';
$string['allowstudentpeerselection_help'] = 'If enabled, students can select peer partners by themselves.';
$string['allowstudentupload'] = 'Students can upload videos';
$string['allowstudentupload_help'] = 'If enabled, students can upload videos one by one. Bulk upload is available only for teachers.';
$string['allscores'] = '</span><span class="red">Self,</span> <span class="blue">Peer,</span> <span class="green">Teacher,</span> <span class="orange"> and Class</span> Scores';
$string['assess'] = 'Assess';
$string['assess_help'] = 'In the assess stage, students do self-assessment, peer assessment with the rubrics set up in Advanced Grading.  It is also possible to give a student the right to design a rubric by giving one of them ‘teacher-rights’ or by designing a rubric on paper for the teacher to input. By default, students cannot see the teacher’s scoring until after completing self assessment. Then students can view the teacher’s assessment.';
$string['assessagain'] = 'Assess again';
$string['assessedby'] = 'Assessed by';
$string['assignpeers'] = 'Assign peers';
$string['assignpeers_help'] = 'From zero to three peers can be assigned for peer assessment. One peer is the default. There are three methods to assign peers: 1) randomly across the course, 2) randomly across a group, and 3) manually. Both ways of random assignments can be adjusted manually after being auto-assigned.';
$string['assignpeersrandomly'] = 'Assign peers randomly';
$string['associate'] = 'Associate';
$string['associate_help'] = 'After uploading files, each video file must be associated to the correct performing student. The file is matched by selecting one student by their moodle login name (user) in the course. This is called the “associate” phase in the process (after “upload” and before “assess”). In this screen, a drop-down menu lists all the students (users) in the course (or in the section of the course).';
$string['associated'] = 'Associated';
$string['associations'] = 'Associations';
$string['availabledate'] = 'Available from';
$string['backupdefaults'] = 'Backup defaults';
$string['backupusers'] = 'Include user data';
$string['backupusersdesc'] = 'Sets the default for whether to include user data (videos and grades) in backups.';
$string['before'] = 'Before';
$string['beforeafter'] = 'Before/after';
$string['beforegrade'] = 'Before grade';
$string['beforelabel'] = 'Before';
$string['beforemarks'] = 'Before scores';
$string['beforepeer'] = 'Peer';
$string['beforeself'] = 'Self';
$string['beforeteacher'] = 'Teacher';
$string['beforeclass'] = 'Class';
$string['beforevideo'] = 'Before video';
$string['bulkvideoupload'] = 'Bulk video upload';
$string['confirmdeletegrade'] = 'Are you sure you want to delete this grade?';
$string['confirmdeletevideos'] = 'Are you sure you want to delete {$a} videos?';
$string['course'] = 'course';
$string['currentgrade'] = 'Current grade in gradebook';
$string['delayedteachergrade'] = 'Delayed Teacher Grade';
$string['delayedteachergrade_help'] = 'If enabled ‘yes’, the teacher assessment ratings will not be shown to the student, until he/she has completed his/her self assessment. This will alleviate any bias in student scoring by not seeing a teacher score before they start assessing.';
$string['deleteselectedvideos'] = 'Delete selected videos';
$string['deletevideo'] = 'Delete video';
$string['deletevideos'] = 'Bulk Video Deletion'; // 英和辞典でもen.wiktionaryでもdeleteに一般的な名詞用例は載ってないが、deleteを名詞的に使っている用例はいくらでもあるし視認性がいいからbulk video deleteとする分には歓迎。ただここでは原則的な名詞形にしておく
$string['deletevideos_help'] = 'A teacher can delete multiple files this way.';
$string['deletevideos_videos'] = 'Videos';
$string['deletevideos_videos_help'] = 'All selected videos are deleted from the activity. Video data on the server will be cleaned up by Moodle cron.';
$string['description'] = 'Description';
$string['disassociate'] = 'Disassociate';
$string['diskspacetmpl'] = 'Server disk space: {$a->free} free / {$a->total} total';
$string['downloadexcel'] = 'Download results in Excel';
$string['duedate'] = 'Due date';
$string['errorcheckvideostodelete'] = 'Check videos to delete.';
$string['errorinvalidtiming'] = 'Invalid timing value';
$string['erroruploadvideo'] = 'Please upload a video';
$string['existingcourse'] = 'Publish to an existing course';
$string['existingcourse_help'] = 'If set to other than (New), videos will be published to the selected course. You need to be able to add resources to the course.';
$string['feedback'] = 'Feedback';
$string['feedbackfrom'] = 'Feedback from {$a}';
$string['ffmpegcommand'] = 'FFmpeg command';
$string['ffmpegcommanddesc'] = 'FFmpeg command line with placeholders: {INPUT} {OUTPUT}';
$string['ffmpegthumbnailcommand'] = 'FFmpeg thumbnail command';
$string['ffmpegthumbnailcommanddesc'] = 'FFmpeg command line with placeholders: {INPUT} {OUTPUT}, with options to output an image';
$string['filedeleted'] = 'File is deleted.';
$string['firstassess'] = 'First assess';
$string['grade'] = 'Grade';
$string['group'] = 'group';
$string['inputnewcoursename'] = 'Input a new course name';
$string['level'] = 'Level';
$string['liststudents'] = 'List students';
$string['loading'] = 'Loading...';
$string['managegrades'] = 'Manage grades';
$string['manageuploadedvideos'] = 'Manage uploaded videos';
$string['modulename'] = 'Video Assessment';
$string['modulenameplural'] = 'Video Assessments';
$string['mp4boxcommand'] = 'MP4Box command';
$string['mp4boxcommanddesc'] = 'MP4Box command which enables progressive playback of MP4 videos';
$string['myvideos'] = 'My video';
$string['nopeergroup'] = 'No peer groups yet';
$string['notext'] = 'No text';
$string['novideo'] = 'No video';
$string['operations'] = 'Operations';
$string['or'] = 'or';
$string['originalname'] = 'Original name';
$string['path'] = 'Path';
$string['peer'] = 'Peer';
$string['peerassessments'] = 'Peer assessments';
$string['peergroup'] = 'Peer group';
$string['peerratings'] = 'Peer ratings';
$string['peers'] = 'Peers';
$string['pluginadministration'] = 'Video Assessment administration';
$string['pluginname'] = 'Video Assessment';
$string['preventlate'] = 'Prevent late submissions';
$string['previewvideo'] = 'Preview video';
$string['printrubrics'] = 'Print all rubric report';
$string['printreport'] = 'Print report';
$string['printview'] = 'Open print view';
$string['publishvideos'] = 'Publish Videos';
$string['publishvideos_help'] = 'In this stage, after all performances are assessed, a teacher can select videos for long term preservation. These videos will be published to a
separate, newly created course in your site.';
$string['publishvideos_videos'] = 'Videos';
$string['publishvideos_videos_help'] = 'Selected videos will be published to an existing course or a new course.';
$string['publishvideostocourse'] = 'Publish videos to a course';
$string['ratingpeer'] = 'Peer weighting';
$string['ratingpeer_help'] = 'Set the weighting of the peer grading within a student’s total grade. More than one peer can grade the student (up to 3), and the average score of the peers will be displayed. In reports, the peer’s scores are usually shown in the color ‘blue’.';
$string['ratings'] = 'Ratings';
$string['ratings_help'] = 'When combining self/peer/class/teacher assessments, a teacher can set the weighting of each type of assessment within 100%. Typical weightings might be Teacher-80%, Self-10%, Peer-10%, Class-0%. The total of the percentages must be 100%, or a warning will appear. If you wish for the teacher to be the only assessor, then set the percentages like this: Teacher-100%, Self-0%, Peer-0%, Class-0%.';
$string['ratingself'] = 'Self weighting';
$string['ratingself_help'] = 'Set the weighting of the self grading within a student’s total grade. Self assessment is often influenced by others” scores, so the teacher’s score is not shown until a student has completed her/his self assessment. The self scores are usually shown in the color ‘red’.';
$string['ratingteacher'] = 'Teacher weighting';
$string['ratingteacher_help'] = 'Set the weighting of the teacher’s grading of a student’s total grade. More than one teacher can grade the student, and the average score of the teachers will be displayed. If the teacher is the only rater, then this setting should be 100%, and all others 0%. The teacher’s scores are usually shown in the color ‘green’.';
$string['reallydeletevideo'] = 'Are you sure you want to delete this video?';
$string['reallyresetallpeers'] = 'This will reset peer assignments and re-assign randomly. Continue?';
$string['remark'] = 'Remark';
$string['report'] = 'Report';
$string['retakevideo'] = 'Retake a video';
$string['reuploadvideo'] = 'Re-upload a video';
$string['Reembedthelink'] = 'Re-embed the link';
$string['score'] = 'Score';
$string['scores'] = 'Scores';
$string['saveassociations'] = 'Save associations';
$string['seereport'] = 'See report';
$string['self'] = 'Self';
$string['selfassessments'] = 'Self assessments';
$string['selfratings'] = 'Self ratings';
$string['settotalratingtoahundredpercent'] = 'Four ratings (Teacher + Self + Peer + Class) must equal 100%.';
$string['singlevideoupload'] = 'Single video upload';
$string['studentrubric'] = 'Student rubric';
$string['submissionby'] = 'Submission by {$a}';
$string['takevideo'] = 'Take a video';
$string['teacher'] = 'Teacher';
$string['teacherratings'] = 'Teacher ratings';
$string['teacherrubric'] = 'Teacher rubric';
$string['teacherselfpeer'] = 'Teacher/self/peer/class';
$string['timing'] = 'Timing';
$string['timinggrade'] = '{$a} grade';
$string['timinglabel'] = 'Your word for before/after';
$string['timinglabel_help'] = 'By inputting a word here, you can customize the labels for "before" and "after." If this is left blank, standard "before" and "after" are used.';
$string['timingscores'] = '{$a} scores';
$string['total'] = 'Total';
$string['totalgrade'] = 'Total grade';
$string['unassociated'] = 'Unassociated';
$string['upload'] = 'Upload video';
$string['uploadedat'] = 'Uploaded at';
$string['uploadedtime'] = 'Uploaded time';
$string['uploadingvideo'] = 'Uploading video';
$string['uploadvideo'] = 'Upload a video';
$string['uploadvideo_help'] = 'Here a teacher can click a link and upload a single video file. The file should contain the performance of one student. Record the video of each performance separately. During video upload the file is compressed to 10% of the original size.';
$string['uploadvideos'] = 'Upload videos';
$string['usedpeers'] = 'Number of Peer Assessments';
$string['usedpeers_help'] = 'A teacher can set the number of peer assessments from 0 to 3. In the Ratings menu, the teacher can then assign the peer automatically or manually.The default is always “0” peers, except when a percentage for peer assessment is assigned to above 0%. Then the default becomes “1” and can be manually re-set from 1-3.';
$string['video'] = 'Video';
$string['videoalreadyassociated'] = '{$a} has been already associated with a video.';
$string['videoassessment:addinstance'] = 'Add a new video assessment';
$string['videoassessment:associate'] = 'Associate bulk uploaded videos with users';
$string['videoassessment:bulkupload'] = 'Bulk upload videos';
$string['videoassessment:bulkupload_help'] = 'A teacher can drag multiple video files into this window. The files will upload and be converted in order. 10-20 files may take up to an hour depending on the resolution. Avoid high resolution videos such as 4K as the size is too large for efficient handling and s unnecessary for the purpose of performance assessment. VGA quality or 720HD 30fps is preferable. In testing, all video formats were compatible.';
$string['videoassessment:exportownsubmission'] = 'Export own submission';
$string['videoassessment:grade'] = 'Grade video assessment';
$string['videoassessment:gradepeer'] = 'Grade peer video assessment';
$string['videoassessment:submit'] = 'Submit video assessment';
$string['videoassessment:view'] = 'View video assessment';
$string['videoassessmentname'] = 'Video assessment name';
$string['videoformat'] = 'Video format';
$string['videoformatdesc'] = 'Video format';
$string['videos'] = 'Videos';
$string['viewassessmentsofmyvideo'] = 'View assessments of my video';
$string['viewassociatedvideos'] = 'View associated videos';
$string['weighting'] = 'Weighting';
$string['xfeedback'] = '{$a} feedback';
$string['xunassignedstudents'] = '{$a} unassigned students';
/**
 * Le Xuan Anh Version2
 */
$string['grade'] = 'Grading';
$string['grade_help'] = 'This section is for settings that combine the self/peer/class/teacher grades. One combined grade will be uploaded to the Gradebook in this class. Scoring details can be analyzed and downloaded in Excel format by going to the Assess page and finding the download link. In addition, this section has settings for pre-calibration and fairness bonus to improve self assessment.';
$string['managevideo'] = 'Manage videos';
$string['class'] = 'Class';
$string['open'] = 'Open Class Grading';
$string['close'] = 'Close Class Grading';
$string['classassessments'] = 'Class Assessments';
$string['duplicaterubric'] = 'Duplicate Rubric';
$string['duplicaterubric_help'] = 'This feature will repeat the rubric that has been created for the teacher, and duplicate the rubric into the assessments of self, peer, and class modes.';
$string['duplicatesuccess'] = 'Duplicate Success';
$string['duplicateerrors'] = 'Duplicate Errors';

/* MinhTB VERSION 2 */
$string['allparticipants'] = 'All participants';
$string['assignclass'] = 'Assign class';
$string['assignclass_help'] = 'This feature allows a teacher to turn on or turn off the “Class” mode of assessment. The “Class” mode is for all the students watching a live, real-time performance to assess the speaker without a recording. This is more difficult to assess accurately, due to the time pressure, but it gives students practice in using and understanding the rubric, and keeps them learning actively instead of half-listening to a presentation. Students must log into the class website and find the appropriate Video Assessment activity on their course. They search for the student doing the performance and begin choosing the scores on each scale. The scores of all students are averaged for one single “Class” score for the whole class, thus mitigating too high or too low scores.';
$string['sortid'] = 'Sort by ID';
$string['sortname'] = 'Sort by name';
$string['sortmanually'] = 'Sort manually';
$string['sortby'] = 'Sort by';
$string['order'] = 'Order';
$string['save'] = 'Save';
$string['orderasc'] = 'Ascending';
$string['orderdesc'] = 'Descending';
$string['namesort'] = 'First name / Surname';
$string['title'] = 'Title:';
$string['groupname'] = 'Group name';
$string['existingcourseornewcourse'] = 'Publish to an Existing Course<br /> or a New Course';
$string['insertintosection'] = 'Insert into Section';
$string['addprefixtolabel'] = 'Add Prefix to Label Name';
$string['addsuffixtolabel'] = 'Add Suffix to Label Name';
$string['inputnewcourseshortname'] = 'Input a new course short name';
$string['courseshortnameexist'] = 'Short name is already used for another course';
$string['pleasechoosevideos'] = 'Please choose videos';
$string['trainingpretest'] = 'Training Pre-test';
$string['trainingpretest_help'] = 'Similar to ‘calibrating’ for scoring a test, this Training Pre-test function forces students to first pass a training test before moving on to actual scoring. Students watch an uploaded video and rubric provided by the teacher. They can only pass when they score within a decided difference (20% for example) from the pre-entered desired scores by the teacher.';
$string['fullnamecourse'] = 'Course Full Name';
$string['shortnamecourse'] = 'Course Short Name';
$string['no'] = 'No';
$string['yes'] = 'Yes';
$string['passed'] = 'Pass';
$string['failed'] = 'Fail';
$string['training'] = 'Training';
$string['results'] = 'Results';
$string['passednotice'] = 'Congratulations! All of your scores were near standard scores!<br />Go to {$a} assessment.';
$string['failednotice'] = 'Sorry. Some of your scores were {$a->accepteddifference}% different from standard scores. You must have all “O”, no “X”.<br />{$a->button}';
$string['selfpeer'] = 'Self / Peer';
$string['tryagain'] = 'Try again';
$string['pleasedefinerubricforteacher'] = 'Please define rubric for teacher';
$string['pleasechoosegradingareas'] = 'Please choose grading areas';
$string['gradingareadefined'] = 'Can not duplicated because rubric is already exist';
$string['duplicatefor'] = 'Duplicate for';
$string['teacherassesstraining'] = 'Assess training pre-test';
$string['notattempted'] = 'Not attempted';

/* TienNV VERSION 2 */
$string['trainingvideo'] = 'Training video';
$string['trainingvideo_help'] = 'Upload a video for the students to practice and complete scoring on the training Pre-test.';
$string['accepteddifference'] = 'Accepted difference in scores';
$string['accepteddifference_help'] = 'Accepted difference in scores. Default 20%. Here the acceptable range, or ‘difference’ can be set for students’ scores, compared to
pre-entered teacher scores. If the student score lies outside the acceptable difference for any criterion on the rubric, they fail the Training Pre-test and must take it again.';
$string['trainingdesc'] = 'Training explanation';
$string['trainingdesc_help'] = 'Add an explanation to teach students how to score and what the accepted difference from teacher scores on each rubric criterion are. The students
need to receive all ‘circles’ (within acceptable difference from teacher scores) to pass.';
$string['trainingdeschelp'] = 'Training explanation text';
$string['trainingdesctext'] = 'To pass this training, you (red score) must evaluate each scale within xx% of the teacher’s score (green score). If you are xx% or under, you
receive a “maru” “O”. If you are over xx%, you receive a “batsu” “X”. All scales must be “O” before you can pass.';
$string['viewresult'] = 'View result';
$string['beforetraining'] = 'Training Pre-test';

$string['changeuploadtype'] = 'changeuploadtype';
$string['url'] = 'URL';
$string['url_help'] = 'This is a Youtube URL';
$string['url_error'] = 'Please enter the correct Youtube URL';
$string['ratingclass'] = 'ratingclass';
$string['ratingclass_help'] = 'This rating is not used with video recordings, but for live performances, with all classmates making scores and giving comments. Usually at 0%, even if turned ‘on’, the purpose of the whole class rating is to keep the audience busy and learning the rubrics. It must be turned on in the Grading menu. In reports, the class average score is usually shown in the color ‘yellow’.';
$string['clickonthe'] = 'Click on the';
$string['donotclickhere'] = 'Do not click here.';
$string['or'] = 'Or';
$string['changetraingingwarning'] = 'changetraingingwarning';
$string['firstassess'] = '【First assess】';
$string['assessagain'] = '【Assess again】';

$string['notifications'] = 'Notifications';
$string['notificationssendtype'] = 'Notification carrier';
$string['notificationcontenttypegroup'] = 'Notification content';

$string['fairnessbonus'] = '+PeerFairness<br>bonus';
$string['selffairnessbonus'] = '+SelfFairness<br>bonus';
$string['finalscore'] = 'Final score';
$string['reminder_notifition_mail_cron'] = 'reminder notifition mail cron';
$string['uploadfile'] = 'Upload Video File';
$string['upoladmessage'] = 'Your video file is over 500MB. Please retake the video at a lower resolution or re-upload a smaller file';

$string['managevideos'] = 'Manage videos';
$string['managevideos_help'] = 'The “Manage Videos” admin page has nine functions to click. You do not have to touch any function unless you wish to change the default settings.
<br />a. Upload a Video
<br />b. Bulk Upload Videos
<br />c. Bulk Video Deletion
<br />d. Associate
<br />e.  Assess
<br />f.  Assign Peers
<br />g. Publish Videos
<br />h. Assign Class
<br />i.  Duplicate Rubric';
$string['notifications_help'] = 'Notifications send the assessment information to the students via their email inboxes or their mobile quickmail addresses. There are four kinds of notificiations:
<br />a. Teacher Comment notification
<br />b. Peer Comment notification
<br />c. Reminder notification
<br />d. Video upload/reupload notification';
$string['notificationcarriergroup'] = 'Notification carrier';
$string['notificationcarriergroup_help'] = 'There are two choices for notifications: by the registered Moodle email address in the site, or by Mobile Quickmail (an optional block for using mobile phone email addresses). One or both can be selected.';
$string['teachercommentnotification'] = 'Notification content';
$string['teachercommentnotification_help'] = 'a. Teacher Comment notification is to send an email to the student whenever a teacher makes comments and saves the assessment.
<br />b. Peer Comment notification: is to send an email notification to the student whenever a peer makes comments and saves the assessment.
<br />c. Reminder notification is given when a student has forgotten an assignment or is late.
<br />d. Video upload/reupload notification gives a notification to the teacher whenever a video is uploaded or re-uploaded into the video assessment module.
The email message format for each type of notification must be set up by the teacher.';

$string['modgrade'] = 'Grade Type';
$string['modgrade_help'] = 'For video assessment, do not change the default settings here for “Grade Type”. The Grade Type is “Point” and the maximum grade is “100”.  If you change the settings, the video assessment system may not work.';

$string['advancedgradingmethodsgroup'] = 'Grading Methods';
$string['advancedgradingmethodsgroup_help'] = 'For video assessment, do not change the default settings here for “Grading Method”. All settings use rubric, because that is the best method of performance assessment. If you change the settings, the video assessment system may not work.';
$string['classgrading'] = 'Whole Class Grading';
$string['classgrading_help'] = 'If you want the whole class of students to watch a live performance and assess it in real time, use this feature. To turn on whole class grading, click ‘Open Class Grading’.  The default is “Close class grading”.  All the student’s grades will be totaled into one average grade.';
$string['fairnessbonus'] = 'Peer Fairness Bonus';
$string['fairnessbonus_help'] = 'The Peer Fairness bonus rewards students who score ‘fairly’, that is, their scores are not all ‘100s” or “0s” and fairly close to what the teacher is scoring. The options for setting up this tool include deciding how much of a bonus (% of final score) you will assign, and how much of that bonus students will receive based on proximity to the teachers score.';
$string['selffairnessbonus'] = 'Self Fairness Bonus';
$string['selffairnessbonus_help'] = 'The Self Fairness bonus rewards students who score ‘fairly’, that is, their scores are not all ‘100s” or “0s” and fairly close to what the teacher is scoring. The options for setting up this tool include deciding how much of a bonus (% of final score) you will assign, and how much of that bonus students will receive based on proximity to the teachers score.';
$string['uploadfile_help'] = 'There are two stages: Uploading the file, and then converting the file. The converting process compresses the file to 1/10th the size. Sometimes this takes a long time—over ten minutes. Please check if your camera is set on 4K, which is too high, and lower the resolution and lower the fps. VGA or 720hd, 30fps is better.';
$string['uploadingvideo_help'] = 'You can share your recorded performance for assessment with three methods. In this screen, students and teachers can:
<br />1) Upload a single file of your video performance here or 
<br />2) upload your file into YouTube and link to that video. Set your camera to its lowest resolution for fast response. Record a single video file in your device and upload here. Additionally, teachers can: 
<br />3) record on an SD card for bulk upload. Go to Manage Videos >> Bulk upload for that process. Note: this screen is only available to students if the default for “Allow student upload of videos” is kept at “Yes”.';
$string['uploadyoutube'] = 'Link to Youtube Video';
$string['uploadyoutube_help'] = 'For better performance, upload your video into your personal YouTube account or another video sharing site. Then copy the link and paste the link into the box for that link. When you link to a Youtube file, there is no thumbnail photo showing on the assessment screen. Just play the video and it will appear.';


$string['quickSetup'] = 'Quick Setup';
$string['quickSetup_help'] = 'Quick Setup';
$string['grade_rating_name'] = 'Rating';
$string['grade_grading_name'] = 'Grading';


$string['gradeitem:beforeteacher'] = 'Teacher';
$string['gradeitem:beforetraining'] = 'Training Pre-test';
$string['gradeitem:beforeself'] = 'Self';
$string['gradeitem:beforepeer'] = 'Peer';
$string['gradeitem:beforeclass'] = 'Class';

$string['graded'] = 'Graded';
$string['recordnewvideo'] = 'Record New Video';
$string['recordradios'] = 'Record New Video';
$string['recordradios_help'] = 'Record New Video is for directly recording a video for assessment.
This function accesses the camera on your computer or mobile phone and starts a video recording.
In contrast, the “Upload Video File” selection goes to the photo/video library of files to allow you to select a previously recorded video to upload.
<br/>*Click the stop recording button, and then upload it automatically*';

$string['calendardue'] = '{$a} is due';
$string['calendargradingdue'] = '{$a} is due to be graded';

$string['assignmentisdue'] = 'videoassessment is due';
$string['latesubmissionsaccepted'] = 'Allowed until {$a}';
$string['nomoresubmissionsaccepted'] = 'Only allowed for participants who have been granted an extension';
