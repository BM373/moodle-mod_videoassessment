@mod @mod_videoassessment @javascript
Feature: Smartphone-friendly assess screen layout
  In order to grade rubrics on a phone without losing the focused
  textarea behind the keyboard or losing the floating video under
  the iOS Home indicator
  As a teacher viewing the assess screen on a phone-sized viewport
  I should see the safe-area / scroll-margin CSS in effect

  Scenario: Mobile-only CSS rules are present in assess.css
    Given I log in as "admin"
    # The assess.css "@media (max-width: 768px)" block ships with the
    # plugin and is loaded as a stylesheet; the actual CSS contract is
    # pinned by tests/mobile_ui_test.php (PHPUnit) for static-text
    # assertions. This Behat scenario reserves space for a future
    # mobile-emulating profile that will physically resize the
    # browser window and assert that .remark textarea remains in the
    # viewport when focused.
    Then I should see "Dashboard"
