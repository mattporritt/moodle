@report_imagealt @javascript
Feature: Find and manually remediate image alternative text
  In order to improve the accessibility of course content
  As an authorised content maintainer
  I need to review exact image occurrences before changing them

  Background:
    # Addressed by URL rather than by a relative path, which would be an image this site cannot find and so a broken
    # one: a different thing entirely, covered in its own scenario below.
    Given the following "courses" exist:
      | fullname | shortname | summary                                                              | summaryformat |
      | Alpine   | C1        | <p>Course image <img src="https://example.com/mountain.jpg"></p>     | 1             |

  Scenario: An administrator analyses and remediates one occurrence
    Given I log in as "admin"
    And I navigate to "Reports > Image alternative text" in site administration
    When I press "Check for new or changed images"
    And I run the scheduled task "\report_imagealt\local\task\process_queue"
    And I reload the page
    Then I should see "Missing"
    And I should see "mountain.jpg"
    # Alternative text that is still one of the deterministic faults leaves the image needing attention, so the save
    # says only that it saved, and the row stays where it is.
    When I click on "Edit alternative text" "button"
    And I set the field "How would you describe this image to someone who cannot see it?" in the "Edit alternative text" "dialogue" to "photo"
    And I click on "Save" "button" in the "Edit alternative text" "dialogue"
    Then "Alternative text saved." "toast_message" should be visible
    And I should see "mountain.jpg"
    And I should see "Generic placeholder"
    When I click on "Edit alternative text" "button"
    And I set the field "How would you describe this image to someone who cannot see it?" in the "Edit alternative text" "dialogue" to "Snow-covered mountain at sunrise"
    And I click on "Save" "button" in the "Edit alternative text" "dialogue"
    # The report opens as a worklist, filtered to the images that need attention, so a remediated image leaves it. The
    # save says so, because a row disappearing with nothing said reads as a fault rather than as the work being done.
    Then "Alternative text saved. This image is no longer reported as needing attention." "toast_message" should be visible
    # The empty table then says which kind of empty it is, rather than reading as a report that found nothing.
    And I should not see "mountain.jpg"
    And I should see "No images match the current filters"

  # The table reloads itself over the dynamic table web service, a different request to the one that painted the page,
  # and one that sets up no page context of its own. Sorting is the cheapest way to make that request happen, and it
  # failed outright while this report asked whether AI was available without allowing for that.
  Scenario: The report table survives its own reloads
    Given the following "core_ai > ai providers" exist:
      | provider          | name            | enabled | apikey | orgid |
      | aiprovider_openai | OpenAI API test | 1       | 123    | abc   |
    # The report offers AI through the report placement, which is off until a site turns it on.
    And the following config values are set as admin:
      | enabled | 1 | aiplacement_reportbuilder |
    And the following "report_imagealt > images" exist:
      | course | filename     |
      | C1     | mountain.png |
    And I log in as "admin"
    When I navigate to "Reports > Image alternative text" in site administration
    And I click on "Image source" "link"
    Then I should see "mountain.png"
    And I should not see "Coding error detected"

  # An image the content claims as its own and this site cannot find is reported for what it is, and offers none of
  # the alternative text workflow: there is no image, so a description would be words about nothing. What it offers
  # instead is the way to the content, which is where the fault is.
  Scenario: An image that is not there is reported as broken rather than as missing alternative text
    Given the following "courses" exist:
      | fullname | shortname | summary                                                | summaryformat |
      | Broken   | C2        | <p><img src="@@PLUGINFILE@@/deleted.png" alt="Chart"></p> | 1           |
    And the following "core_ai > ai providers" exist:
      | provider          | name            | enabled | apikey | orgid |
      | aiprovider_openai | OpenAI API test | 1       | 123    | abc   |
    # The report offers AI through the report placement, which is off until a site turns it on.
    And the following config values are set as admin:
      | enabled | 1 | aiplacement_reportbuilder |
    And I log in as "admin"
    And I navigate to "Reports > Image alternative text" in site administration
    And I press "Check for new or changed images"
    And I run the scheduled task "\report_imagealt\local\task\process_queue"
    And I reload the page
    # Its own status and reason, so it can be filtered for and worked through as its own kind of problem, and not
    # reported as alternative text somebody forgot to write. It has alternative text; the image is the problem.
    Then I should see "Broken image"
    And I should see "Image file not found"
    And I should see "Image not found"
    # Nothing on the row invites a description of an image that is not there.
    And "Edit alternative text" "button" should not exist in the "deleted.png" "table_row"
    And the "Generate AI suggestions for selected images" "button" should be disabled
    # The one thing that helps: a way to the content holding the missing image.
    And "Open the content to fix this image" "link" should exist in the "deleted.png" "table_row"

  # This feature configures no AI provider anywhere else, so every other scenario in it already runs as a site with
  # AI switched off. This one states what such a site is entitled to: the whole report, and remediation by hand.
  Scenario: A site with no AI provider gets the report and manual remediation
    Given the following "report_imagealt > images" exist:
      | course | filename     |
      | C1     | mountain.png |
    And I log in as "admin"
    When I navigate to "Reports > Image alternative text" in site administration
    Then I should see "mountain.png"
    And I should see "Missing"
    # The report says what it can do, without advertising a suggestion it cannot produce.
    And I should not see "with an optional AI-generated suggestion"
    # Nothing that needs a provider is offered: no bulk request, and no selection to make one with.
    And "Generate AI suggestions for selected images" "button" should not exist
    And "input[data-togglegroup='report-select-all']" "css_element" should not exist
    # The image is one AI could have described, so the review modal withholds its offer because of the missing
    # provider rather than because of the image.
    When I click on "Edit alternative text" "button" in the "mountain.png" "table_row"
    Then "Generate alt text with AI" "button" should not exist
    And "Replace with AI suggestion" "button" should not exist
    # Writing alternative text by hand is the whole report on such a site, so it has to work untouched.
    And I set the field "How would you describe this image to someone who cannot see it?" in the "Edit alternative text" "dialogue" to "Snow-covered mountain at sunrise"
    And I click on "Save" "button" in the "Edit alternative text" "dialogue"
    # Described, so it drops out of the worklist, while the image that still needs attention stays in it.
    Then I should not see "mountain.png"
    And I should see "mountain.jpg"

  Scenario: Editing source content queues a bounded automatic refresh
    Given I log in as "admin"
    And I navigate to "Reports > Image alternative text" in site administration
    And I press "Check for new or changed images"
    And I run the scheduled task "\report_imagealt\local\task\process_queue"
    And I am on "Alpine" course homepage
    And I navigate to "Settings" in current page administration
    When I set the field "Course summary" to "<p>Course image <img src=\"https://example.com/mountain.jpg\" alt=\"Alpine ridge\"></p>"
    And I press "Save and display"
    And I run the scheduled task "\report_imagealt\local\task\process_queue"
    And I navigate to "Reports > Image alternative text" in site administration
    # The rescan picked up the alternative text added in the course, so the image no longer needs attention and is no
    # longer in the worklist. That it is gone for that reason, rather than never having been found, is the point.
    Then I should not see "mountain.jpg"
    And I should see "No images match the current filters"
