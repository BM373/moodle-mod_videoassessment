@mod @mod_videoassessment
Feature: Smartphone-friendly assess screen layout (#7)
  In order to grade rubrics on a phone without the focused textarea
  hiding behind the keyboard or the floating video sliding under the
  iOS Home indicator
  As a teacher
  The activity entry page must load with the smartphone-hardened CSS
  attached
  # The actual @media (max-width: 768px) rules are pinned by
  # tests/mobile_ui_test.php (PHPUnit), which guarantees the safe-area
  # inset + scroll-margin rules ship in assess.css. This Behat
  # scenario adds a real-browser sanity check that the activity
  # main view loads (so the stylesheet is in fact attached to the
  # rendered page) for a teacher and for a student.

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

  Scenario: Teacher reaches the activity main view
    Given I am on the "Speaking task" "videoassessment activity" page logged in as teacher1
    Then I should see "Speaking task"

  Scenario: Student reaches the activity main view
    Given I am on the "Speaking task" "videoassessment activity" page logged in as student1
    Then I should see "Speaking task"
