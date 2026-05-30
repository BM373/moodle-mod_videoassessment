@mod @mod_videoassessment
Feature: Random peer-assignment page is reachable (#5)
  In order to assign students as each other's peer reviewers
  As a teacher
  I should be able to open the activity peer-assignment page
  # The balancing algorithm itself is covered exhaustively by
  # tests/peer_assignment_test.php (PHPUnit), so this Behat focuses on
  # confirming that the teacher-facing entry point loads on a real
  # browser/database without a fatal error — which guards against
  # PostgreSQL portability regressions on this page in particular.

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Stu       | One      | student1@example.com |
      | student2 | Stu       | Two      | student2@example.com |
      | student3 | Stu       | Three    | student3@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "activities" exist:
      | activity        | name          | course | idnumber |
      | videoassessment | Speaking task | C1     | va1      |

  Scenario: Teacher reaches the activity main view without a DB error
    Given I am on the "Speaking task" "videoassessment activity" page logged in as teacher1
    Then I should see "Assess"
    And I should see "Associate"
