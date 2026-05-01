@mod @mod_videoassessment @javascript
Feature: Finish-making-rubric navigation button
  In order to jump from the rubric editor straight to the assess
  screen without having to navigate through the activity menu
  As a teacher who just finished editing a rubric
  I should see a "Finish making rubric" button on the rubric edit page

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
    And the following "activities" exist:
      | activity        | course | name             | idnumber       |
      | videoassessment | C1     | Rubric nav test  | rubricnavtest  |

  Scenario: Activity view does not show the button (only the rubric editor does)
    Given I log in as "teacher1"
    When I am on "Rubric nav test" "videoassessment activity" page
    # The hook callback only fires on the rubric edit page, so the
    # button must NOT appear on the activity landing page itself.
    Then "vassmt-finish-rubric-btn" "css_element" should not exist
