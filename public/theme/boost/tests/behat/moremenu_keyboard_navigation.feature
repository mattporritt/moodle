@core @theme_boost @javascript
Feature: Keyboard navigation of the primary navigation "More" menu
  In order to use the collapsed primary navigation with a keyboard
  As a user
  I need to be able to arrow-navigate into and back out of a nested submenu

  Background:
    Given I log in as "admin"
    And I navigate to "Appearance > Advanced theme settings" in site administration
    And I set the field "Custom menu items" to multiline:
      """
      Courses
      -All courses|/course/
      -Course search|/course/search.php
      Mobile app|https://example.org/app
      """
    And I press "Save changes"
    And I change viewport size to "850x800"
    And I am on homepage

  Scenario: Arrow key navigation can leave a nested submenu going forwards
    When I click on "More" "link" in the ".primary-navigation" "css_element"
    And I press the down key
    And I press the down key
    And I press the down key
    And the focused element is "Courses" "link"
    And I press the enter key
    And the focused element is "All courses" "link"
    And I press the down key
    And the focused element is "Course search" "link"
    And I press the down key
    Then the focused element is "Mobile app" "link"

  Scenario: Arrow key navigation can leave a nested submenu going backwards
    When I click on "More" "link" in the ".primary-navigation" "css_element"
    And I press the down key
    And I press the down key
    And I press the down key
    And the focused element is "Courses" "link"
    And I press the down key
    And the focused element is "All courses" "link"
    And I press the up key
    Then the focused element is "Site administration" "link"
