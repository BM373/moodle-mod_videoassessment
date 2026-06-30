@mod @mod_videoassessment
Feature: Activity views render on PostgreSQL without SQL errors (#11)
  In order for the SGU PostgreSQL deployment to remain operational
  As a teacher or student opening the activity entry page
  No PostgreSQL-specific SQL syntax error should be raised by the
  view, the teacher grade table, or the peer-sort query
  # PostgreSQL rejects queries that GROUP BY one column but ORDER BY
  # another non-aggregated column, and rejects double-quoted string
  # literals like CONCAT(.., " ", ..). Both used to crash the activity
  # main view on PostgreSQL; both are fixed and pinned by
  # tests/va_test::test_get_peers_sort_* and
  # tests/va_test::test_get_students_sort_by_name_runs_on_all_databases
  # (PHPUnit). This Behat scenario confirms the rendered view itself
  # is healthy when CI runs against the pgsql matrix cell.

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
      | activity        | name          | course | idnumber |
      | videoassessment | Speaking task | C1     | va1      |

  Scenario: Teacher view (grade table) renders with no DB error
    Given I am on the "Speaking task" "videoassessment activity" page logged in as teacher1
    Then I should not see "syntax error"
    And I should not see "Database error"
    And I should not see "dml_read_exception"

  Scenario: Student view renders with no DB error
    Given I am on the "Speaking task" "videoassessment activity" page logged in as student1
    Then I should not see "syntax error"
    And I should not see "Database error"
    And I should not see "dml_read_exception"
