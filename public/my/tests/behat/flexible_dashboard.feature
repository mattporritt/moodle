@core @core_my
Feature: Arrange dashboard blocks in a responsive grid
  In order to make my dashboard useful at different sizes
  As a user
  I need to move, resize, add and remove dashboard blocks

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And I log in as "student1"

  @javascript
  Scenario: Move, resize, add and remove blocks with accessible controls
    When I turn editing mode on
    Then "Open block drawer" "button" should not exist
    And I click on "Move Course overview block" "button" in the "Course overview" "block"
    And I click on "Down" "button" in the "Course overview" "block"
    And I click on "Move Course overview block" "button" in the "Course overview" "block"
    Then I should see "Item moved to row"
    When I click on "Resize block" "button" in the "Course overview" "block"
    Then "Up" "button" should exist in the "Course overview" "block"
    And the resize directional controls are centred on the active resize handle
    And I press the down key
    And I press the enter key
    Then I should see "Item resized to"
    When I click on "Add a block at the start of the dashboard" "button"
    And I click on "Online users" "button" in the "Add a block" "dialogue"
    Then "Online users" "block" should exist
    When I click on "Delete Online users block" "button" in the "Online users" "block"
    And I click on "Confirm" "button" in the "Remove block" "dialogue"
    Then "Online users" "block" should not exist

  @javascript
  Scenario: Drag the dedicated handles to move and resize a block
    When I turn editing mode on
    And I drag ".core-my-dashboard-tile[data-block='myoverview'] .core-my-dashboard-handle--move" "css_element" and I drop it in ".core-my-dashboard-tile[data-block='myoverview'] .core-my-dashboard-tile__content" "css_element"
    Then I should see "Item moved to row"
    When I hover ".core-my-dashboard-tile[data-block='myoverview'] .core-my-dashboard-handle--resize" "css_element"
    And I drag ".core-my-dashboard-tile[data-block='myoverview'] .core-my-dashboard-handle--resize" "css_element" and I drop it in ".core-my-dashboard-tile[data-block='myoverview'] .core-my-dashboard-tile__content" "css_element"
    Then I should see "Item resized to"

  @javascript
  Scenario: Move a block into an adjacent free column
    When I turn editing mode on
    And I change viewport size to "2600x1000"
    Then "[data-columns='6']" "css_element" should exist
    And dragging the "recentlyaccesseditems" dashboard block into an adjacent free column persists

  @javascript
  Scenario: Resize follows the pointer and previews its snap target
    When I turn editing mode on
    Then keyboard activation shows resize directional controls
    And the mouse resize preview follows the pointer before snapping to a grid cell

  @javascript
  Scenario: Bumped blocks ease into their new grid position
    When I turn editing mode on
    Then blocks bumped by a pointer resize ease into their new grid position

  @javascript
  Scenario: Reflow the grid from six columns down to one
    When I turn editing mode on
    And I change viewport size to "2600x1000"
    Then "[data-columns='6']" "css_element" should exist
    When I change viewport size to "900x1000"
    Then "[data-columns='2']" "css_element" should exist
    When I change viewport size to "600x900"
    Then "[data-columns='1']" "css_element" should exist
