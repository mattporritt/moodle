@report_imagealt @javascript
Feature: Review AI generated image descriptions in bulk
  In order to improve the alternative text of many images at once
  As an authorised content maintainer
  I need to request descriptions, find them again, and apply the ones that are right

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Alpine   | C1        |
    # Everything in this feature is offered only where a provider can write descriptions, so one is configured for
    # all of it. The report without a provider is covered in image_alt_text.feature.
    And the following "core_ai > ai providers" exist:
      | provider          | name            | enabled | apikey | orgid |
      | aiprovider_openai | OpenAI API test | 1       | 123    | abc   |
    # The report offers AI through the report placement, which is off until a site turns it on.
    And the following config values are set as admin:
      | enabled | 1 | aiplacement_reportbuilder |
    And the following "report_imagealt > images" exist:
      | course | filename     |
      | C1     | mountain.png |
    And I log in as "admin"

  # The reason this report offers AI through a placement of its own. A site can allow AI descriptions while editing a
  # single image and still withhold the button that requests them for every image in a course, which is the one here
  # that spends real money. Turning the placement off has to withdraw the workflow, not just hide the button.
  Scenario: Disabling the report placement withdraws AI from the report
    Given the following config values are set as admin:
      | enabled | 0 | aiplacement_reportbuilder |
    When I navigate to "Reports > Image alternative text" in site administration
    Then I should see "mountain.png"
    And I should not see "Generate AI suggestions for selected images"
    # Nor any way to select rows, because selecting them is only ever for requesting descriptions.
    And "Select" "checkbox" should not exist

  Scenario: Bulk generation is offered only once images are selected
    Given I navigate to "Reports > Image alternative text" in site administration
    Then the "Generate AI suggestions for selected images" "button" should be disabled
    When I click on "Select" "checkbox"
    Then the "Generate AI suggestions for selected images" "button" should be enabled
    When I click on "Select" "checkbox"
    Then the "Generate AI suggestions for selected images" "button" should be disabled

  Scenario: A selection is sent for generation on the first press of the button
    Given the following "report_imagealt > images" exist:
      | course | filename  |
      | C1     | ridge.png |
    And I navigate to "Reports > Image alternative text" in site administration
    And I click on "Select" "checkbox" in the "mountain.png" "table_row"
    # Requesting descriptions is the first thing on this page that uses AI, so the policy is accepted here.
    When I press "Generate AI suggestions for selected images"
    And I click on "Accept and continue" "button" in the "AI usage policy" "dialogue"
    Then I should see "0 of 1 images processed"
    # Pressing again with the policy already accepted takes the other route through the handler. Note this does not
    # reproduce the swallowed resubmission fixed alongside it: that needs the policy state to already be cached in
    # the page, and here the lookup goes to the server, which puts the resubmission outside the submit event.
    When I press "Back to image alternative text report"
    And I click on "Select" "checkbox" in the "ridge.png" "table_row"
    And I press "Generate AI suggestions for selected images"
    Then I should see "0 of 1 images processed"

  Scenario: Outstanding descriptions are found from the report and applied one at a time
    Given the following "report_imagealt > suggestions" exist:
      | course | filename     | suggestion                      |
      | C1     | mountain.png | A snow covered mountain summit. |
    And I navigate to "Reports > Image alternative text" in site administration
    Then I should see "AI descriptions ready to review: 1"
    When I click on "Review AI descriptions" "link"
    Then I should see "A snow covered mountain summit."
    # Scoped to the row, because the summary above the table counts descriptions with the same words the row
    # badges use, and these assertions are about the badge tracking this one suggestion.
    And I should see "Ready to review" in the "mountain.png" "table_row"
    When I click on "Accept" "button"
    Then I should see "Image descriptions applied: 1."
    And I should see "Applied" in the "mountain.png" "table_row"
    # Applying the suggestion writes it to the image itself, so the image no longer needs attention and leaves the
    # worklist the report opens as. That the text reaches the content is asserted in batch_manager_test.
    When I press "Back to image alternative text report"
    Then I should not see "mountain.png"
    And I should not see "AI descriptions ready to review"

  Scenario: Several descriptions are accepted together
    Given the following "report_imagealt > images" exist:
      | course | filename  |
      | C1     | ridge.png |
    And the following "report_imagealt > suggestions" exist:
      | course | filename     | suggestion             | batch |
      | C1     | mountain.png | A mountain summit.     | first |
      | C1     | ridge.png    | A ridge line at dusk.  | first |
    And I navigate to "Reports > Image alternative text" in site administration
    Then I should see "AI descriptions ready to review: 2"
    When I click on "Review AI descriptions" "link"
    Then the "Accept selected descriptions" "button" should be disabled
    When I click on "#report-imagealt-selectall" "css_element"
    Then the "Accept selected descriptions" "button" should be enabled
    When I press "Accept selected descriptions"
    Then I should see "Image descriptions applied: 2."
    And I should see "Applied" in the "[data-region='report-imagealt-batch-progress']" "css_element"

  Scenario: A description the reviewer disagrees with is discarded without reaching the image
    Given the following "report_imagealt > suggestions" exist:
      | course | filename     | suggestion       |
      | C1     | mountain.png | A cat on a sofa. |
    And I navigate to "Reports > Image alternative text" in site administration
    Then I should see "AI descriptions ready to review: 1"
    When I click on "Review AI descriptions" "link"
    And I click on "Discard" "button"
    Then I should see "Image descriptions discarded: 1."
    And I should see "Discarded" in the "mountain.png" "table_row"
    # Rejecting is the only way to clear a bad description, so it has to leave nothing outstanding behind.
    When I press "Back to image alternative text report"
    Then I should not see "AI descriptions ready to review"
    And I should not see "A cat on a sofa."

  # Closing the review dialog throws away a description that dialog wrote, because leaving without saving means
  # changing your mind. It must not throw away one that was already waiting: that belongs to a bulk request somebody
  # is working through, and opening the dialog to look at it is not a decision about it.
  Scenario: Closing the review dialog leaves a description that was already waiting
    Given the following "report_imagealt > suggestions" exist:
      | course | filename     | suggestion         |
      | C1     | mountain.png | A mountain summit. |
    And I navigate to "Reports > Image alternative text" in site administration
    Then I should see "Ready to review"
    When I click on "Edit alternative text" "button" in the "mountain.png" "table_row"
    And I click on "Cancel" "button" in the "Edit alternative text" "dialogue"
    Then I should see "Ready to review"
    # And it is still the one the batch is waiting on, rather than merely still existing in some other state.
    When I click on "Ready to review" "link"
    Then I should see "A mountain summit."
    And I should see "Ready to review" in the "mountain.png" "table_row"

  Scenario: An image is not offered for generation again while its description is outstanding
    Given the following "report_imagealt > suggestions" exist:
      | course | filename     | suggestion         |
      | C1     | mountain.png | A mountain summit. |
    When I navigate to "Reports > Image alternative text" in site administration
    Then I should see "Ready to review"
    # Selecting it again would pay for a second description of an image that already has one waiting, so the row
    # offers no selection checkbox at all.
    And the "Generate AI suggestions for selected images" "button" should be disabled
    # Matched on data-toggle="target", because Report Builder always renders its select-all header checkbox under
    # the same name whether any row is selectable or not.
    And "input[name='report-select-row[]'][data-toggle='target']" "css_element" should not exist
    # Once the description is dealt with, the image can be sent for generation again.
    When I click on "Ready to review" "link"
    And I click on "Discard" "button"
    And I press "Back to image alternative text report"
    And I click on "Select" "checkbox"
    Then the "Generate AI suggestions for selected images" "button" should be enabled

  Scenario: A description the content outgrew is accounted for and explains itself
    Given the following "report_imagealt > suggestions" exist:
      | course | filename     | suggestion            | status |
      | C1     | mountain.png | A mountain at sunset. | stale  |
    And I navigate to "Reports > Image alternative text" in site administration
    When I click on "Out of date" "link"
    # The batch counted the image as processed but reported it under no heading at all, so a finished batch of one
    # looked like it had lost track of its only image.
    Then I should see "1 of 1 images processed"
    And I should see "Out of date" in the "[data-region='report-imagealt-batch-progress']" "css_element"
    # The description cannot be applied any more, and the row is the only place that can say why or what to do.
    And I should see "The image changed after this description was written"
    # Matched on the controls themselves rather than their labels, because the page furniture around the report has
    # buttons of its own whose text contains "Accept".
    And "button[name='acceptid']" "css_element" should not exist
    And "button[name='action']" "css_element" should not exist
    # Reading the image as it is now is the way forward, so that action stays.
    And "[data-action='report-imagealt-edit']" "css_element" should exist

  Scenario: A description that could not be generated is reported as failed
    Given the following "report_imagealt > suggestions" exist:
      | course | filename     | status | errormessage             |
      | C1     | mountain.png | failed | The provider timed out.  |
    And I navigate to "Reports > Image alternative text" in site administration
    # Nothing is waiting for review, so the report offers no entry point for a batch that only failed.
    Then I should not see "AI descriptions ready to review"
    And I should see "Failed"
    # A state word on its own says neither what happened nor that it can be followed, so the badge is styled as the
    # link it is and carries its own explanation. The wording of that explanation is asserted in the unit tests.
    And "a.report-imagealt-statelink[title]" "css_element" should exist
    When I click on "Failed" "link"
    Then I should see "Finished with errors"
    And I should see "The provider timed out."
