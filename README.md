Video Assessment
================

Requirements
------------

* Moodle 2.5 or later
* ffmpeg
* MP4Box
* Future VAM versions may require Moodle 4.2, which requires PHP 8.0

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
