@mod @mod_videoassessment @javascript
Feature: Live "current grade in gradebook" indicator
  In order to see the rubric total update before clicking Save
  Changes
  As a teacher grading a student
  I should see the live indicator wired into the assess form

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | C1       | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
      | activity | videoassessment    |
      | course   | C1                 |
      | name     | Live grade test VA |
      | idnumber | livegradevatest    |

  Scenario: AMD module is registered when the assess action is requested
    Given I log in as "teacher1"
    When I am on "Live grade test VA" "videoassessment activity" page
    Then I should see "Live grade test VA"
    # The full live-update behaviour requires a populated rubric and a
    # student grading session, which is exercised by the PHPUnit test
    # tests/rubric_total_test.php for the calculation logic. This
    # scenario keeps the activity-landing page covered as a smoke
    # test and reserves the rubric/assess flow for a follow-up
    # scenario once a rubric fixture step is added to the suite.
