@mod @mod_videoassessment
Feature: Reject shell-injection patterns in FFmpeg/MP4Box command settings (#9)
  In order to keep the site safe from admin-supplied shell-injection
  As a site administrator
  When I try to save a command field that contains a shell
  metacharacter the form must refuse the input and show a validation
  error

  Scenario: A semicolon-injection attempt on the FFmpeg command is rejected
    Given I log in as "admin"
    When I navigate to "Plugins > Activity modules > Video Assessment" in site administration
    And I set the field "FFmpeg command" to "ffmpeg -i {INPUT} {OUTPUT}; rm -rf /"
    And I press "Save changes"
    Then I should not see "Changes saved"
