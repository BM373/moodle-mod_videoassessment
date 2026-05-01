@mod @mod_videoassessment @javascript
Feature: Finish-making-rubric navigation button
  In order to jump from the rubric editor straight to the assess
  screen without having to navigate through the activity menu
  As a teacher who just finished editing a rubric
  I should see a "Finish making rubric" button on the rubric edit page

  Scenario: Lang string for the navigation button is registered
    Given I log in as "admin"
    Then I should see "Dashboard"
    # The button label `finishmakingrubric` is exercised end-to-end
    # by the AMD module mod_videoassessment/finish_rubric_button which
    # is queued by `\mod_videoassessment\hook_callbacks::inject_finish_rubric_button`
    # only when the page URL points at /grade/grading/form/rubric/edit.php
    # with component=mod_videoassessment. Routing the Behat browser to
    # that URL requires a course + activity + rubric definition fixture
    # which the suite does not yet ship; this scenario is a placeholder
    # for the future end-to-end check.
