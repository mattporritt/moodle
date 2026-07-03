@core_comment @javascript
Feature: Comment component ESM module — post, delete and paginate comments
  In order to discuss content with other users
  As a course participant
  I need to be able to post, delete and paginate comments using the new ESM comment module

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | comments  | Course       | C1        | course-view-*   | side-pre      |

  Scenario: Post a comment using the ESM module
    Given I am on the "Course 1" course page logged in as student1
    When I add "Hello from ESM module" comment to comments block
    Then I should see "Hello from ESM module"

  Scenario: Posted comment persists after page reload
    Given I am on the "Course 1" course page logged in as student1
    When I add "Persistent comment" comment to comments block
    And I am on the "Course 1" course page logged in as teacher1
    Then I should see "Persistent comment"

  Scenario: Delete a comment using the ESM module
    Given the following "core_comment > Comments" exist:
      | contextlevel | reference | component      | area          | content      |
      | Course       | C1        | block_comments | page_comments | ToBeDeleted  |
    And I am on the "Course 1" course page logged in as admin
    And I should see "ToBeDeleted"
    When I click on "[id^='comment-delete-']" "css_element" in the ".block_comments" "css_element"
    Then I should not see "ToBeDeleted"

  Scenario: Paginate through comments using the ESM module
    Given the following "core_comment > Comments" exist:
      | contextlevel | reference | component      | area          | content    |
      | Course       | C1        | block_comments | page_comments | Comment 01 |
      | Course       | C1        | block_comments | page_comments | Comment 02 |
      | Course       | C1        | block_comments | page_comments | Comment 03 |
      | Course       | C1        | block_comments | page_comments | Comment 04 |
      | Course       | C1        | block_comments | page_comments | Comment 05 |
      | Course       | C1        | block_comments | page_comments | Comment 06 |
      | Course       | C1        | block_comments | page_comments | Comment 07 |
      | Course       | C1        | block_comments | page_comments | Comment 08 |
      | Course       | C1        | block_comments | page_comments | Comment 09 |
      | Course       | C1        | block_comments | page_comments | Comment 10 |
      | Course       | C1        | block_comments | page_comments | Comment 11 |
      | Course       | C1        | block_comments | page_comments | Comment 12 |
      | Course       | C1        | block_comments | page_comments | Comment 13 |
      | Course       | C1        | block_comments | page_comments | Comment 14 |
      | Course       | C1        | block_comments | page_comments | Comment 15 |
      | Course       | C1        | block_comments | page_comments | Comment 16 |
    And I am on the "Course 1" course page logged in as teacher1
    Then I should see "2" in the ".block_comments .comment-paging" "css_element"
    And I should not see "Comment 01" in the ".block_comments" "css_element"
    And I should see "Comment 16" in the ".block_comments" "css_element"
    When I click on "2" "link" in the ".block_comments .comment-paging" "css_element"
    Then I should see "Comment 01" in the ".block_comments" "css_element"
    And I should not see "Comment 16" in the ".block_comments" "css_element"

  Scenario: Comment textarea has native placeholder attribute
    Given I am on the "Course 1" course page logged in as student1
    Then the "placeholder" attribute of "textarea[id^='dlg-content-']" "css_element" should contain "Add a comment"
