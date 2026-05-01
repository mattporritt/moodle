@editor_tiny @editor @javascript
Feature: Admin can manage TinyMCE standard features
  In order to control the TinyMCE editor experience sitewide
  As a site administrator
  I can enable and disable native TinyMCE plugins from the settings page

  Background:
    Given I am logged in as "admin"

  Scenario: The TinyMCE settings page shows three distinct sections
    When I navigate to "Plugins > Text editors > TinyMCE editor > General settings" in site administration
    Then I should see "Manage TinyMCE standard features"
    And I should see "Manage TinyMCE Moodle plugins"
    And I should see "Manage miscellaneous settings"

  Scenario: The standard features section contains expected native plugin controls
    When I navigate to "Plugins > Text editors > TinyMCE editor > General settings" in site administration
    Then I should see "Table"
    And I should see "Character map"
    And I should see "Full screen"
    And I should see "Word count"

  Scenario: Admin can disable a native TinyMCE plugin via AJAX toggle
    When I navigate to "Plugins > Text editors > TinyMCE editor > General settings" in site administration
    And I toggle the "Disable Character map" admin switch "off"
    Then I should see "Character map disabled."
    And I reload the page
    And I should see "Enable Character map"

  Scenario: Admin can re-enable a previously disabled native TinyMCE plugin
    Given the following config values are set as admin:
      | standard_plugin_charmap | 0 | editor_tiny |
    When I navigate to "Plugins > Text editors > TinyMCE editor > General settings" in site administration
    And I toggle the "Enable Character map" admin switch "on"
    Then I should see "Character map enabled."
    And I reload the page
    And I should see "Disable Character map"

  Scenario: Disabling a native plugin removes its button from the TinyMCE editor
    Given the following config values are set as admin:
      | standard_plugin_fullscreen | 0 | editor_tiny |
    When I am on the "Profile advanced editing" page logged in as "admin"
    Then "Fullscreen" button should not exist in the "Description" TinyMCE editor

  Scenario: Re-enabling a previously disabled native plugin restores its button in the TinyMCE editor
    Given the following config values are set as admin:
      | standard_plugin_fullscreen | 1 | editor_tiny |
    When I am on the "Profile advanced editing" page logged in as "admin"
    Then "Fullscreen" button should exist in the "Description" TinyMCE editor
