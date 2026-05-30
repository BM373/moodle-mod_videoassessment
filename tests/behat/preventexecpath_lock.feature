@mod @mod_videoassessment
Feature: Honour $CFG->preventexecpath on the FFmpeg/MP4Box admin fields (#8)
  In order for a Moodle site that locks executable paths to keep that
  guarantee for plugin-level commands
  As a site administrator
  When $CFG->preventexecpath is on, an attempt to save a new value to
  the FFmpeg or MP4Box command setting must be rejected with the
  locked-by-administrator notice

  Scenario: A save attempt against the FFmpeg command is rejected when locked
    Given the following config values are set as admin:
      | preventexecpath | 1 |
    And I log in as "admin"
    When I navigate to "Plugins > Activity modules > Video Assessment" in site administration
    And I set the field "FFmpeg command" to "ffmpeg -y -i {INPUT} -c:v libopenh264 {OUTPUT}"
    And I press "Save changes"
    Then I should see "This executable path is locked by the site administrator"
