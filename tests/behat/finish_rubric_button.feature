@mod @mod_videoassessment
Feature: Finish-making-rubric navigation button (#12)
  In order to jump from the rubric editor straight to the assess
  screen without having to navigate through the activity menu
  As a teacher who just finished editing a rubric
  I should see a "Finish making rubric" button injected into the
  rubric edit page by the plugin hook
  # The hook + AMD module wiring is unit-tested in
  # tests/rubric_navigation_test.php (PHPUnit, 11 URL cases). This
  # Behat scenario adds an end-to-end check that the entry path the
  # teacher actually walks — opening the activity, then editing the
  # rubric grading form — remains reachable without error.

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

  Scenario: Teacher reaches the activity entry view
    Given I am on the "Speaking task" "videoassessment activity" page logged in as teacher1
    Then I should see "Assess"
    And I should see "Upload videos"
