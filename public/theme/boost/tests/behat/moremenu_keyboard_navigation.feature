@core @theme_boost @javascript
Feature: Keyboard navigation of the primary navigation "More" menu
  In order to use the collapsed primary navigation with a keyboard
  As a user
  I need to be able to arrow-navigate into and back out of a nested submenu

  Background:
    Given I log in as "admin"

  Scenario: Arrow key navigation can leave a nested submenu going forwards
    Given I navigate to "Appearance > Advanced theme settings" in site administration
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
    Given I navigate to "Appearance > Advanced theme settings" in site administration
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
    When I click on "More" "link" in the ".primary-navigation" "css_element"
    And I press the down key
    And I press the down key
    And I press the down key
    And the focused element is "Courses" "link"
    And I press the down key
    And the focused element is "All courses" "link"
    And I press the up key
    Then the focused element is "Site administration" "link"

  Scenario: Arrow key navigation escapes a submenu that is the first item of the "More" menu
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | more1    | More      | One      | more1@example.com |
    And I navigate to "Appearance > Advanced theme settings" in site administration
    And I set the field "Custom menu items" to multiline:
      """
      Courses
      -All courses|/course/
      -Course search|/course/search.php
      Mobile app|https://example.org/app
      """
    And I press "Save changes"
    And I log out
    And I log in as "more1"
    And I change viewport size to "920x800"
    And I am on homepage
    When I click on "More" "link" in the ".primary-navigation" "css_element"
    And I press the down key
    And the focused element is "Courses" "link"
    And I press the enter key
    And the focused element is "All courses" "link"
    And I press the up key
    Then the focused element is "Mobile app" "link"

  Scenario: Arrow key navigation escapes a submenu that is the last item of the "More" menu
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | more2    | More      | Two      | more2@example.com |
    And I navigate to "Appearance > Advanced theme settings" in site administration
    And I set the field "Custom menu items" to multiline:
      """
      Mobile app|https://example.org/app
      Courses
      -All courses|/course/
      -Course search|/course/search.php
      """
    And I press "Save changes"
    And I log out
    And I log in as "more2"
    And I change viewport size to "930x800"
    And I am on homepage
    When I click on "More" "link" in the ".primary-navigation" "css_element"
    And I press the down key
    And the focused element is "Mobile app" "link"
    And I press the down key
    And the focused element is "Courses" "link"
    And I press the enter key
    And the focused element is "All courses" "link"
    And I press the down key
    And the focused element is "Course search" "link"
    And I press the down key
    Then the focused element is "Mobile app" "link"

  Scenario: Arrow key navigation of the (non-nested) user menu still works after the primary navigation's capture-phase change
    When I click on "#user-menu-toggle" "css_element" in the ".usermenu" "css_element"
    And I press the down key
    And the focused element is "Profile" "link"
    And I press the down key
    And the focused element is "Grades" "link"
    And I press the up key
    And the focused element is "Profile" "link"
    And I press the up key
    Then the focused element is "Log out" "link"
