@theme @theme_boost
Feature: Resize the course index drawer
  In order to read long activity names in the course index
  As a user
  I need to resize the course index drawer and have my chosen width remembered

  Background:
    Given the following "course" exists:
      | fullname     | Course 1 |
      | shortname    | C1       |
      | numsections  | 3        |
      | initsections | 1        |

  @javascript
  Scenario: Resize the course index with the keyboard
    Given I am on the "C1" "Course" page logged in as "admin"
    When I click on ".drawerresizehandle" "css_element"
    And I press the right key
    Then the "aria-valuenow" attribute of ".drawerresizehandle" "css_element" should contain "309"
    And I press the left key
    And the "aria-valuenow" attribute of ".drawerresizehandle" "css_element" should contain "285"

  @javascript
  Scenario: The chosen course index width is remembered
    Given I am on the "C1" "Course" page logged in as "admin"
    When I click on ".drawerresizehandle" "css_element"
    And I press the end key
    And I reload the page
    Then the "style" attribute of "body" "css_element" should contain "--drawer-index-width: 640px"
    And the "aria-valuenow" attribute of ".drawerresizehandle" "css_element" should contain "640"

  @javascript
  Scenario: The course index cannot be resized beyond its limits
    Given I am on the "C1" "Course" page logged in as "admin"
    When I click on ".drawerresizehandle" "css_element"
    And I press the home key
    And I press the left key
    Then the "aria-valuenow" attribute of ".drawerresizehandle" "css_element" should contain "285"
    And I press the end key
    And I press the right key
    And the "aria-valuenow" attribute of ".drawerresizehandle" "css_element" should contain "640"

  @javascript
  Scenario: Widening the course index takes space from the content column only
    Given I change window size to "large"
    When I am on the "C1" "Course" page logged in as "admin"
    Then widening the course index should only take space from the content column

  @javascript
  Scenario: The resize handle is not shown on small screens
    Given I change window size to "mobile"
    When I am on the "C1" "Course" page logged in as "admin"
    Then ".drawerresizehandle" "css_element" should not be visible

  @javascript
  Scenario: A user who has never resized the course index gets the default width
    Given I am on the "C1" "Course" page logged in as "admin"
    Then the "style" attribute of "body" "css_element" should not be set
    And the "aria-valuenow" attribute of ".drawerresizehandle" "css_element" should contain "285"
