Video Assessment
================

Requirements
------------

* Moodle 4.0 to 4.3
* ffmpeg
* MP4Box

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
UPDATE xxx_config_plugins SET value = 2025040100 WHERE plugin = ‘mod_videoassessment’;.
```
*tableprefix can vary from installation to installation.

You can find the table prefix in the config.php file from the root of your Moodle installation:
```php
$CFG->prefix    = 'mdl_';
```
5. Run the following command to check if the value has been changed correctly.

```sql
SELECT * FROM xxx_config_plugins WHERE plugin = ‘mod_videoassessment’;
```
Success if the result is as follows:
```sql
+------+---------------------+---------+------------+
| id   | plugin              | name    | value      |
+------+---------------------+---------+------------+
| xxx  | mod_videoassessment | version | 2025040100 |
+------+---------------------+---------+------------+
```
*The value of [value] column shows "2025040100".