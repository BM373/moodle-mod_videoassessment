@mod @mod_videoassessment
Feature: Site-admin File Uploads / Links three-way option (#2)
  In order to let site administrators turn each video submission
  channel on or off independently of the per-activity setting
  As a site administrator
  Three independent checkboxes (external links / uploads / recording)
  must appear on the Video Assessment admin page and the activity
  edit form must respect the site-level flags

  Scenario: All three independent site-level upload toggles are visible
    Given I log in as "admin"
    When I navigate to "Plugins > Activity modules > Video Assessment" in site administration
    Then I should see "Allow external video links"
    And I should see "Allow video uploads"
    And I should see "Allow video recording"
    And the field "Allow external video links" matches value "1"
    And the field "Allow video uploads" matches value "1"
    And the field "Allow video recording" matches value "1"

  Scenario: Site-level disable hard-freezes the matching activity option
    Given the following config values are set as admin:
      | allowvideouploads | 0 | videoassessment |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity        | name          | course | idnumber |
      | videoassessment | Speaking task | C1     | va1      |
    When I am on the "Speaking task" "videoassessment activity editing" page logged in as teacher1
    Then the "Allow video uploads" "field" should be disabled
    And the "Allow external video links" "field" should be enabled
    And the "Allow video recording" "field" should be enabled
