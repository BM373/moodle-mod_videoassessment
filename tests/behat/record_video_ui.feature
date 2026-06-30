@mod @mod_videoassessment
Feature: Record Video option carries the 2-minute cap note (#3)
  In order to make it explicit to the learner that the in-browser
  recording is capped at two minutes
  As a student opening the activity's video submission form
  I should see the "Record New Video (max. length 2 minutes)" label
  alongside the recording option

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity        | name          | course | idnumber |
      | videoassessment | Speaking task | C1     | va1      |

  Scenario: Upload form shows the 2-minute cap label on the record radio
    Given I am on the "Speaking task" "videoassessment > upload" page logged in as student1
    Then I should see "max. length 2 minutes"
