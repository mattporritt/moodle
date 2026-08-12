@core @editor_tiny @javascript
Feature: A user can change font family and font size in the TinyMCE editor
  In order to format text without resorting to raw HTML
  As a content author
  I can use the built-in TinyMCE font family and font size controls

  Scenario: The toolbar shows font family and font size controls
    When I am on the "Profile advanced editing" page logged in as "admin"
    And I expand all toolbars for the "Description" TinyMCE editor
    Then "[data-mce-name=\"fontfamily\"]" "css_element" should exist
    And "[data-mce-name=\"fontsize\"]" "css_element" should exist

  Scenario: The Format menu shows Fonts and Font sizes submenus
    Given I am on the "Profile advanced editing" page logged in as "admin"
    When I click on the "Format" button for the "Description" TinyMCE editor
    Then I should see "Fonts"
    And I should see "Font sizes"

  Scenario: An author can change the font family of text using the toolbar and the change is saved
    Given I am on the "Profile advanced editing" page logged in as "admin"
    And I set the field "Description" to "<p>Sample text</p>"
    And I expand all toolbars for the "Description" TinyMCE editor
    When I select the "p" element in position "0" of the "Description" TinyMCE editor
    And I click on the "Arial" menu item for the "Font Default" button of the "Description" TinyMCE editor
    And I click on "Update profile" "button"
    And I am on the "Profile advanced editing" page logged in as "admin"
    Then the field "Description" matches expression "@font-family:\s*arial@i"

  Scenario: An author can change the font size of text using the toolbar and the change is saved
    Given I am on the "Profile advanced editing" page logged in as "admin"
    And I set the field "Description" to "<p>Sample text</p>"
    And I expand all toolbars for the "Description" TinyMCE editor
    When I select the "p" element in position "0" of the "Description" TinyMCE editor
    And I click on the "18pt" menu item for the "Font size 12pt" button of the "Description" TinyMCE editor
    And I click on "Update profile" "button"
    And I am on the "Profile advanced editing" page logged in as "admin"
    Then the field "Description" matches expression "@font-size:\s*18pt@"

  Scenario: Pre-existing inline font styling is preserved when the editor loads and saves unmodified content
    Given I am on the "Profile advanced editing" page logged in as "admin"
    When I set the field "Description" to "<p><span style='font-family: georgia, serif;'>Styled text</span></p>"
    And I click on "Update profile" "button"
    And I am on the "Profile advanced editing" page logged in as "admin"
    Then the field "Description" matches expression "@font-family:\s*georgia@i"
