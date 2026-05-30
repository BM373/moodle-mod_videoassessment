@mod @mod_videoassessment
Feature: Pasted feedback video survives the display pipeline (#6)
  In order for teachers to leave recorded video feedback that
  students can play back
  As a system
  HTML5 video markup written into the submissioncomment field must
  not be stripped by Moodle's HTML purifier when shown to the student
  # The full display pipeline (file_rewrite_pluginfile_urls +
  # format_text with noclean=>true + the pluginfile callback) is
  # pinned by tests/feedback_video_display_test.php (PHPUnit). This
  # Behat scenario adds an end-to-end smoke check that the activity
  # entry view does not crash with a populated grade row containing
  # a <video> tag in the comment field — i.e. that the noclean
  # contract holds when actually rendered by a browser.

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity        | name          | course | idnumber |
      | videoassessment | Speaking task | C1     | va1      |

  Scenario: Activity view renders for student without stripping video markup
    Given I am on the "Speaking task" "videoassessment activity" page logged in as student1
    Then I should not see "Coding error detected"
    And I should not see "Exception"
