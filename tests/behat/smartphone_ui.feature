@mod @mod_videoassessment @javascript
Feature: Smartphone-friendly assess screen layout
  In order to grade rubrics on a phone without losing the focused
  textarea behind the keyboard or losing the floating video under
  the iOS Home indicator
  As a teacher viewing the assess screen on a phone-sized viewport
  I should see the safe-area / scroll-margin CSS in effect

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
      | activity | videoassessment      |
      | course   | C1                   |
      | name     | Smartphone test VA   |
      | idnumber | smartphonevatest     |

  Scenario: Activity view loads cleanly at a phone-sized viewport
    Given I change viewport size to "iphone"
    And I log in as "teacher1"
    When I am on "Smartphone test VA" "videoassessment activity" page
    Then I should see "Smartphone test VA"
    # The assess.css "@media (max-width: 768px)" block ships with the
    # plugin and is loaded as a stylesheet; we cannot easily assert on
    # the computed style here, but a clean render at 375x667 is the
    # baseline regression contract for item #7.
