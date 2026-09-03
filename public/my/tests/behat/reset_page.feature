@core @core_my
Feature: Reset dashboard page to default
  In order to remove customisations from dashboard page
  As a user
  I need to reset dashboard page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |

  @javascript
  Scenario: Add blocks to page and reset
    When I log in as "student1"
    And I turn editing mode on
    And I click on "Add a block at the start of the dashboard" "button"
    And I click on "Latest announcements" "button" in the "Add a block" "dialogue"
    And I click on "Reset page to default" "button" in the ".core-my-dashboard-toolbar" "css_element"
    And I click on "Confirm" "button" in the "Reset dashboard" "dialogue"
    Then "Latest announcements" "block" should not exist
    And "Course overview" "block" should exist
    And "Timeline" "block" should exist
    And "Calendar" "block" should exist
    And I should not see "Reset page to default"
