@mod @mod_videoassessment
Feature: Live "current grade in gradebook" indicator
  In order to see the rubric total update before clicking Save Changes
  As a teacher grading a student
  I need the assess screen and its live grade indicator to load
  # These scenarios assert page reachability and role-based navigation
  # only, so they run without @javascript. The live in-browser update
  # of the total is covered without a browser by tests/rubric_total_test
  # (maths), tests/live_grade_js_test (DOM wiring) and
  # tests/live_grade_aria_test (ARIA live region).

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
      | activity        | name             | course | idnumber |
      | videoassessment | Speaking task    | C1     | va1      |

  Scenario: Teacher reaches the assess screen task navigation
    Given I am on the "Speaking task" "videoassessment activity" page logged in as teacher1
    Then I should see "Assess"
    And I should see "Upload videos"

  Scenario: A student does not see the teacher assess navigation
    Given I am on the "Speaking task" "videoassessment activity" page logged in as student1
    Then I should not see "Upload videos"
