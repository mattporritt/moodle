@core @core_filepicker @_file_upload
Feature: Accessible file manager
  In order to manage files and folders accessibly
  As a user
  I need the file manager's views, dialogues, and actions to meet WCAG 2.2 AA

  Background:
    Given I log in as "admin"
    And I am on the "My private files" page
    And I upload "lib/tests/fixtures/empty.txt" file to "Files" filemanager

  @javascript @accessibility
  Scenario: Switching between icon, table and tree views is accessible
    Given I create "Folder 1" folder in "Files" filemanager
    When I click on "Display folder with file details" "link" in the ".filemanager" "css_element"
    Then the page should meet accessibility standards
    When I click on "Display folder as file tree" "link" in the ".filemanager" "css_element"
    Then the page should meet accessibility standards
    When I click on "Display folder with file icons" "link" in the ".filemanager" "css_element"
    Then the page should meet accessibility standards

  @javascript @accessibility
  Scenario: The create folder dialogue is accessible
    When I create "Folder 2" folder in "Files" filemanager
    Then I should see "Folder 2"
    And the page should meet accessibility standards

  @javascript @accessibility
  Scenario: The edit file dialogue is accessible and correctly labelled
    Given I click on "//div[contains(concat(' ', normalize-space(@class), ' '), ' fp-file ')]/descendant::a[normalize-space(.)='empty.txt']" "xpath_element"
    And I should see "Edit empty.txt"
    Then the page should meet accessibility standards
    When I set the following fields to these values:
      | Name | empty_edited.txt |
    And I click on "Update" "button"
    Then I should see "empty_edited.txt" in the ".fp-content .fp-file" "css_element"

  @javascript @accessibility
  Scenario: Deleting a file is accessible
    Given I create "Folder 3" folder in "Files" filemanager
    When I delete "Folder 3" from "Files" filemanager
    Then I should not see "Folder 3"
    And the page should meet accessibility standards
