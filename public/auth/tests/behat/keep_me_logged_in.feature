@auth @core_auth
Feature: Keep me logged in
  In order to remain authenticated between browser sessions on a trusted device
  As a user
  I want to be able to use the "Keep me logged in" option on the login form

  Background:
    Given the following config values are set as admin:
      | keeploggedin | 1 |
    And the following "users" exist:
      | username | password | firstname | lastname | email                |
      | student1 | student1 | Student   | One      | student1@example.com |

  Scenario: The keep me logged in checkbox is visible when the feature is enabled
    When I am on "/login/index.php"
    Then I should see "Keep me logged in"

  Scenario: The keep me logged in checkbox is not visible when the feature is disabled
    Given the following config values are set as admin:
      | keeploggedin | 0 |
    When I am on "/login/index.php"
    Then I should not see "Keep me logged in"

  Scenario: An admin can configure the keep me logged in settings
    Given I log in as "admin"
    When I navigate to "Login > Login settings" in site administration
    Then I should see "Enable keep me logged in"
    And I should see "Keep me logged in duration"

  Scenario: Logging out revokes the remember me token and prevents silent re-authentication
    Given I am on "/login/index.php"
    And I set the field "Username" to "student1"
    And I set the field "Password" to "student1"
    And I check "Keep me logged in"
    And I press "Log in"
    And I should see "Student One"
    When I log out with remember me revoked
    And I am on "/my/"
    Then I should see "Username"
    And I should not see "Student One"

  @javascript
  Scenario: A user is silently re-authenticated after their session expires
    Given I am on "/login/index.php"
    And I set the field "Username" to "student1"
    And I set the field "Password" to "student1"
    And I check "Keep me logged in"
    And I press "Log in"
    And I should see "Student One"
    When the current session has expired
    And I am on "/my/"
    Then I should see "Student One"
