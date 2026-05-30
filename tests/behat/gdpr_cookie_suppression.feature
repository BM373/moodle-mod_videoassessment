@mod @mod_videoassessment
Feature: GDPR cookie suppression toggle for external videos (#1)
  In order for a site to embed external videos without setting
  tracking cookies until the learner presses play
  As a site administrator
  I should be able to see and toggle the GDPR cookie suppression
  setting in the Video Assessment admin page

  Scenario: GDPR cookie suppression setting is visible and on by default
    Given I log in as "admin"
    When I navigate to "Plugins > Activity modules > Video Assessment" in site administration
    Then I should see "Suppress tracking cookies (GDPR)"
    And the field "Suppress tracking cookies (GDPR)" matches value "1"

  Scenario: Admin can disable GDPR cookie suppression
    Given the following config values are set as admin:
      | gdprcookiesuppression | 0 | videoassessment |
    And I log in as "admin"
    When I navigate to "Plugins > Activity modules > Video Assessment" in site administration
    Then the field "Suppress tracking cookies (GDPR)" matches value ""
