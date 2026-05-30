@mod @mod_videoassessment
Feature: Activity view logs the standard course_module_viewed event (#10)
  In order for analytics layers to inspect activity use through the
  logstore
  As a teacher opening the activity
  A "Course module viewed" entry should be recorded against the
  videoassessment activity
  # The four mod_videoassessment-specific events
  # (video_uploaded / peer_review_submitted / grade_assigned /
  # report_viewed) are exhaustively unit-tested in
  # tests/event/event_test.php. This Behat scenario adds a real
  # end-to-end check that opening the activity flows into the
  # standard logstore so the four custom events also reach it.

  Background:
    Given the following "courses" exist:
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

  Scenario: Viewing the activity records a course_module_viewed event
    Given I am on the "Speaking task" "videoassessment activity" page logged in as teacher1
    When I am on the "Course 1" course page logged in as admin
    And I navigate to "Reports > Logs" in current page administration
    And I press "Get these logs"
    Then I should see "Course module viewed"
    And I should see "Speaking task"
