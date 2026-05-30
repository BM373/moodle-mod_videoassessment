@mod @mod_videoassessment
Feature: Educator landscape-recording notice (#4)
  In order to remind teachers to advise students to record in landscape
  As a teacher viewing the activity entry page
  I should see the educator landscape-recording info notification
  # Reviewer Brendon reported "Can't find message to tell students to
  # record in portrait." Item #4 renders the note on the activity
  # entry page (view_main) so the teacher sees it without having to
  # drill into a per-student assess view.

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

  Scenario: Teacher sees the educator landscape note on the activity main page
    Given I am on the "Speaking task" "videoassessment activity" page logged in as teacher1
    Then I should see "Note to educators"
    And I should see "landscape"

  Scenario: Student does not see the educator landscape note
    Given I am on the "Speaking task" "videoassessment activity" page logged in as student1
    Then I should not see "Note to educators"
