<?php
defined('MOODLE_INTERNAL') || die();

$string['backupdefaults'] = 'バックアップデフォルト';
$string['backupusers'] = 'ユーザデータを含む';
$string['backupusersdesc'] = 'バックアップにユーザデータ (ビデオ、評定) を含むかどうか、デフォルトを設定します。';
$string['modulename'] = 'ビデオアセスメント';
$string['pluginadministration'] = 'ビデオアセスメント管理';
$string['pluginname'] = 'ビデオアセスメント';
/**
 * Le Xuan Anh Version2
 */
$string['grade'] = 'Grading';
$string['managevideo'] = 'Manage videos';
$string['class'] = 'Class';
$string['open'] = 'Open Class Grading';
$string['close'] = 'Close Class Grading';
$string['classassessments'] = 'Class Assessments';
$string['duplicaterubric'] = 'Duplicate Rubric';
$string['duplicatesuccess'] = 'Duplicate Success';
$string['duplicateerrors'] = 'Duplicate Errors';

/* MinhTB VERSION 2 */
$string['allparticipants'] = 'すべての参加者';
$string['assignclass'] = 'Assign class';
$string['sortid'] = 'Sort by ID';
$string['sortname'] = 'Sort by name';
$string['sortmanually'] = 'Sort manually';
$string['sortby'] = 'Sort by';
$string['order'] = 'Order';
$string['save'] = 'Save';
$string['orderasc'] = '昇順';
$string['orderdesc'] = '降順';
$string['namesort'] = 'First name / Surname';
$string['title'] = 'タイトル：';
$string['groupname'] = 'グループ名';
$string['existingcourseornewcourse'] = 'Publish to an Existing Course<br /> or a New Course';
$string['insertintosection'] = 'Insert into Section';
$string['addprefixtolabel'] = 'Add Prefix to Label Name';
$string['addsuffixtolabel'] = 'Add Suffix to Label Name';
$string['inputnewcourseshortname'] = 'Input a new course short name';
$string['courseshortnameexist'] = 'Short name is already used for another course';
$string['pleasechoosevideos'] = 'Please choose videos';
$string['trainingpretest'] = 'Training Pre-test';
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
$string['duplicatefor'] = 'Duplicate for';
$string['gradingareadefined'] = 'Can not duplicated because rubric is already exist';
$string['teacherassesstraining'] = 'Assess training pre-test';
$string['notattempted'] = 'Not attempted';

/* TienNV VERSION 2 */
$string['trainingvideo'] = 'Training video';
$string['accepteddifference'] = 'Accepted difference in scores';
$string['accepteddifference_help'] = 'Accepted difference in scores. Default 20%.';
$string['trainingdesc'] = 'Training explanation';
$string['trainingdeschelp'] = 'Training explanation text';
$string['trainingdesctext'] = 'To pass this training, you (red score) must evaluate each scale within xx% of the teacher’s score (green score). If you are xx% or under, you receive a “maru” “O”. If you are over xx%, you receive a “batsu” “X”. All scales must be “O” before you can pass.';
$string['viewresult'] = 'View result';
$string['beforetraining'] = 'Training';

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
$string['Reembedthelink'] = 'Re-embed the link';
$string['firstassess'] = '【First assess】';
$string['assessagain'] = '【Assess again】';


$string['notifications'] = 'Notifications';
$string['notificationssendtype'] = 'Notification carrier';
$string['notificationcontenttypegroup'] = 'Notification content';

$string['fairnessbonus'] = '+PeerFairness<br>bonus';
$string['selffairnessbonus'] = '+SelfFairness<br>bonus';
$string['finalscore'] = 'Final score';
$string['reminder_notifition_mail_cron'] = 'reminder notifition mail cron';
$string['upload'] = 'アップロード';
$string['upoladmessage'] = 'あなたのビデオファイルは500MBを超えています。低い解像度でビデオを撮り直すか、小さいファイルを再アップロードしてください。';

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
$string['notifications_help']  = 'Notifications send the assessment information to the students via their email inboxes or their mobile quickmail addresses. There are four kinds of notificiations:
<br />a. Teacher Comment notification
<br />b. Peer Comment notification
<br />c. Reminder notification
<br />d. Video upload/reupload notification';
$string['notificationcarriergroup'] = 'Notification carrier';
$string['notificationcarriergroup_help'] ='There are two choices for notifications: by the registered Moodle email address in the site, or by Mobile Quickmail (an optional block for using mobile phone email addresses). One or both can be selected.';
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
$string['classgrading']  = 'Whole Class Grading';
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
$string['uploadyoutube']  = 'Link to Youtube Video';
$string['uploadyoutube_help'] = 'For better performance, upload your video into your personal YouTube account or another video sharing site. Then copy the link and paste the link into the box for that link. When you link to a Youtube file, there is no thumbnail photo showing on the assessment screen. Just play the video and it will appear.';
$string['quickSetup']  = 'Quick Setup';
$string['grade_rating_name'] = 'Rating';
$string['grade_grading_name'] = 'Grading';

$string['gradeitem:beforeteacher'] = 'Teacher';
$string['gradeitem:beforetraining'] = 'Training Pre-test';
$string['gradeitem:beforeself'] = 'Self';
$string['gradeitem:beforepeer'] = 'Peer';
$string['gradeitem:beforeclass'] = 'Class';

$string['graded'] = 'Graded';
$string['recordnewvideo']  = 'Record New Video';
$string['recordradios']  = 'Record New Video';
$string['recordradios_help']  = 'Record New Video is for directly recording a video for assessment.
This function accesses the camera on your computer or mobile phone and starts a video recording.
In contrast, the “Upload Video File” selection goes to the photo/video library of files to allow you to select a previously recorded video to upload.
<br/>*Click the stop recording button, and then upload it automatically*';

$string['calendardue'] = '{$a} is due';
$string['calendargradingdue'] = '{$a} is due to be graded';

$string['assignmentisdue'] = 'videoassessment is due';
$string['latesubmissionsaccepted'] = 'Allowed until {$a}';
$string['nomoresubmissionsaccepted'] = 'Only allowed for participants who have been granted an extension';