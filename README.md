Video Assessment
================

Current version: **1.3.0-beta2** (Build: 2026102700)

Requirements
------------

* Moodle 4.5 LTS to 5.2
* PHP 8.1 or later (8.3 recommended)
* ffmpeg
* MP4Box (Optional - Legacy)

Database: MariaDB / MySQL and PostgreSQL are both supported.

Optional parameter adjustment
-----------------------------

Some videos failed to play on Safari or Firefox.
For better playback compatibility on Safari or Firefox, you can add  "-pix_fmt yuv420p" parameter to the ffmpeg conversion command.
The ffmpeg command is as follows:
`/usr/local/bin/ffmpeg -i {INPUT} -pix_fmt yuv420p {OUTPUT}`
(The location for changing the ffmpeg command: Dashboard / Site administration / Plugins / Activity modules / Video Assessment)

Description
-----------

* This activity module adds a video window the standard Moodle rubrics for easier assessment of live performances.
* It features self and peer assessment as well as teacher assessment.
* It is part of a 12-year action research project designed by teachers of Sapporo Gakuin University and funded by the school.
* Thanks to CHIeru Communication Bridge Educational Web Programming in Sapporo, Japan for their work developing this program.


AI speech feedback (beta)
-------------------------

Since 1.3.0, learners can practise a speaking task and get written feedback
from an AI. Only audio is used, so no video leaves the site. The feature is
off in every activity until a teacher turns it on.

### For learners

The activity page shows **Practise with AI speech feedback**. On that page
the learner records in the browser (audio only) or uploads an audio file
(webm, ogg, mp3, m4a, mp4, wav or flac). The recording is transcribed and
the AI comments on the transcript, usually within a minute. The learner
then sees:

* their own words, with good phrases marked in green (S1, S2...) and
  phrases to improve in orange (I1, I2...);
* a comment for each mark, and a better way to say each weak phrase;
* one thing to focus on in the next attempt;
* a checklist of the teacher's target words.

Learners can try as often as they like. Practice never changes a grade.
Before the first recording, each learner accepts the site's AI usage policy.

### For teachers

In the activity settings, open **AI speech feedback**, tick **Give learners
AI feedback on their speech** and set:

* **Task and criteria for the AI**, for example "A 2-minute self-introduction.
  Use an introduction, three points and a conclusion." Learners see it above
  the recorder.
* **Target words and phrases**, one per line. The module checks them exactly;
  the AI does not decide whether they were used.
* **Language spoken**, which makes transcription more accurate.
* **Language of the feedback**. By default each learner gets feedback in
  their own Moodle language.

Teachers who open the practice page see **Learners' attempts**: for each
learner the number of recordings, how many have feedback, how many the
learner has read, and the first and last recording time. Each attempt's
recording, transcript and feedback can be opened from there.

### For site administrators

Site administration > Plugins > Activity modules > Video Assessment has an
**AI speech feedback** section with two choices:

| Setting | Options |
|---|---|
| Written feedback from | **Moodle AI providers**: the provider with *Generate text* enabled under Site administration > General > AI (OpenAI or Azure OpenAI in Moodle 4.5); every request is logged there. Or **Amazon Bedrock**. |
| Transcription service | **OpenAI-compatible endpoint**: OpenAI, a gateway, or a Whisper server run by your institution. Or **Amazon Transcribe**. |

**Without AWS.** Enable the OpenAI provider with *Generate text* under
General > AI and choose the OpenAI-compatible endpoint. If the transcription
API key is left empty, the provider's key is reused, but only for
api.openai.com.

**With AWS** (Bedrock does not store prompts or use them for training):

1. Create an S3 bucket in the region you will use, without dots in its name
   and with public access blocked. The plugin deletes each recording and
   transcript file as soon as it has read the text; a lifecycle rule that
   deletes `videoassessment-aifeedback/` after one day catches anything left.
2. Create an IAM user with only the policy below. Replace `REGION`,
   `ACCOUNT`, `BUCKET` and the server address. The default model
   `jp.anthropic.claude-haiku-5-5` runs in Tokyo and Osaka, so its
   foundation model is allowed in every region (`*`); if you choose another
   model in the settings, change both Bedrock lines. AWS access keys do not
   expire, so the last statement refuses the key from anywhere but your
   Moodle server. Calls that Transcribe makes to the bucket on the user's
   behalf come from AWS, which is why `aws:ViaAWSService` is exempt.

   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Sid": "Bedrock",
         "Effect": "Allow",
         "Action": "bedrock:InvokeModel",
         "Resource": [
           "arn:aws:bedrock:REGION:ACCOUNT:inference-profile/jp.anthropic.claude-haiku-5-5",
           "arn:aws:bedrock:*::foundation-model/anthropic.claude-haiku-5-5"
         ]
       },
       {
         "Sid": "Transcribe",
         "Effect": "Allow",
         "Action": [
           "transcribe:StartTranscriptionJob",
           "transcribe:GetTranscriptionJob",
           "transcribe:DeleteTranscriptionJob"
         ],
         "Resource": "arn:aws:transcribe:*:ACCOUNT:transcription-job/vam-*"
       },
       {
         "Sid": "Recordings",
         "Effect": "Allow",
         "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
         "Resource": "arn:aws:s3:::BUCKET/videoassessment-aifeedback/*"
       },
       {
         "Sid": "OnlyFromMoodleServer",
         "Effect": "Deny",
         "Action": "*",
         "Resource": "*",
         "Condition": {
           "NotIpAddress": {"aws:SourceIp": ["203.0.113.10/32"]},
           "Bool": {"aws:ViaAWSService": "false"}
         }
       }
     ]
   }
   ```

3. Create an access key for the user and enter the key ID, the secret key,
   the region, the bucket and the model under **Amazon Web Services**.
4. Before using real learners' recordings, apply an AI services opt-out
   policy for Amazon Transcribe in your AWS organisation (from its management
   account). Without it, Transcribe may use the audio to improve AWS
   services.

### Data and limits

* Recordings are stored in Moodle. The transcription service receives the
  audio; the AI receives the transcript, the task and the target words.
* Attempts, transcripts, feedback and recordings are covered by the privacy
  API (export and deletion) and are included in course backups with user
  data.
* Speech recognisers tend to correct small grammar slips (a test phrase
  "our team is build" came back as "our team has built"), so some errors
  never reach the feedback.
* The recorder needs a browser with MediaRecorder (current Chrome, Edge,
  Firefox or Safari). Learners on other browsers can upload a file.

Attention
-----------
** For upgrades only. Ignore for clean installs.**

Please check the version number <ins>if the Video Assessment module is already installed on your Moodle.</ins>

*How to check the version number.

Go to Site administration > Plugins > Plugins overview and click the [Additional plugins] tab. The grey number shown in the [Version] column is the version number.
<ins>If the version number is 11 digits eg. "20230926001", then you need to perform these following steps before installing the new version.</ins>

In order to change the version number of Video Assessment module on the Moodle database you can simply run a MySQL command. This can be accomplished from your control panel (cPanel, DirectAdmin etc >> phpMyAdmin) or from the command line.

1. Log into your control panel – cPanel, DirectAdmin
2. Navigate to phpMyAdmin
3. Select the Moodle database on the left.
4. Go to the SQL tab and run the following command:

```sql
UPDATE xxx_config_plugins SET value = 2026013000 WHERE plugin = 'mod_videoassessment';
```
*tableprefix can vary from installation to installation.

You can find the table prefix in the config.php file from the root of your Moodle installation:
```php
$CFG->prefix    = 'mdl_';
```
5. Run the following command to check if the value has been changed correctly.

```sql
SELECT * FROM xxx_config_plugins WHERE plugin = 'mod_videoassessment';
```
Success if the result is as follows:
```sql
+------+---------------------+---------+------------+
| id   | plugin              | name    | value      |
+------+---------------------+---------+------------+
| xxx  | mod_videoassessment | version | 2026013000 |
+------+---------------------+---------+------------+
```
*The value of [value] column shows "2026013000".

Changelog
---------

The full, per-release change history is maintained in
[CHANGELOG.md](CHANGELOG.md) (Keep a Changelog / Semantic Versioning).

The current release line covers Moodle 4.5 LTS through 5.2 support, PostgreSQL
compatibility, the 2026-04 customer-requested fixes (#1–#13), and external
video-host embedding (YouTube, Vimeo, PeerTube, Esup-Pod, Dailymotion,
Opencast) with a fail-closed trusted-host allowlist. 1.2.0 adds the
*New Report Available* / *Report Viewed* labels, locks a video once it has
been assessed, and lets teachers delete a video together with its
assessments. 1.3.0 (beta) adds AI speech feedback, described above, and
fixes backup and restore, which used to drop most activity settings and
unlink rubric assessments.

For version history prior to the 1.0.5 baseline, please refer to the upgrade.txt file.
