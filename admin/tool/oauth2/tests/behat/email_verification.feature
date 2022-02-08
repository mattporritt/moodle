@tool @tool_oauth2 @external @javascript @xxpriority
Feature: OAuth2 email verification
  In order to make sure administrators understand the ramifications of email verification
  As an administrator
  I should see email verifications notifications when configuring an Oauth2 provider.

  Background:
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Server > OAuth 2 services" in site administration

  Scenario: Create, edit and delete standard service for Google toggling email verification.
    Given I press "Google"
    And I should see "Create new service: Google"
    And I set the following fields to these values:
      | Name                       | Testing service                           |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
    Then I should see "Disabling email verification can be a security issue"
    And I click on "Require email verification" "checkbox"
    And I press "Save changes"
    And I should see "Confirm form submission?"
    And I should see "Field: Require email verification (Disabling email verification can be a security issue.)"
    And I press "No"
    And I should not see "Confirm form submission?"
    And I press "Save changes"
    And I press "Yes"
    And I should see "Changes saved"
    And I click on "Edit" "link" in the "Testing service" "table_row"
    And I click on "Require email verification" "checkbox"
    And I press "Save changes"
    And I should see "Changes saved"
    And I should not see "Confirm form submission?"
