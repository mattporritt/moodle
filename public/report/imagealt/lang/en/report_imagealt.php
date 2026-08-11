<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for the image alternative text report.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aidescribepurpose'] = 'Provide alternative text for an image embedded in course content, suitable for someone using a screen reader.';
$string['aieligible'] = 'AI eligible';
$string['aieligible_help'] = 'Whether the image can be resolved to a Moodle-stored file that is a supported type for AI-generated alternative text suggestions. External images and unsupported file types are not AI eligible, but can still be remediated manually.';
$string['aisuggestedtext'] = 'AI suggested text';
$string['alttext'] = 'Alternative text';
$string['alttext_help'] = 'The alternative text currently set on this image in its source content. This is what screen readers announce in place of the image.';
$string['alttextdescribe'] = 'How would you describe this image to someone who cannot see it?';
$string['alttexterror'] = 'The image description could not be generated.';
$string['alttextkeep'] = 'Keep mine';
$string['alttextreplace'] = 'Replace';
$string['alttextreplaceconfirmation'] = 'This replaces the description you’ve already written. This can’t be undone.';
$string['alttextreplacewithai'] = 'Replace with AI suggestion';
$string['alttextsaved'] = 'Alternative text saved.';
$string['alttextsavedresolved'] = 'Alternative text saved. This image is no longer reported as needing attention.';
$string['alttexttryagain'] = 'Try again';
$string['analysisstate'] = 'Analysis state';
$string['analysisstate_help'] = 'Whether this occurrence reflects the latest scan of its content. "Stale" means the content has changed, been removed, or not yet been rescanned since the last refresh.';
$string['analysisstate_ready'] = 'Analysed';
$string['analysisstate_scanning'] = 'Scanning';
$string['analysisstate_stale'] = 'Stale';
$string['backtoreport'] = 'Back to image alternative text report';
$string['batchaccept'] = 'Accept';
$string['batchacceptresult'] = 'Image descriptions applied: {$a}.';
$string['batchacceptselected'] = 'Accept selected descriptions';
$string['batchacceptskipped'] = 'Not applied: {$a}. The content behind those images has changed since the description was generated, so they need reviewing individually.';
$string['batchautorefresh'] = 'This page updates automatically while images are still being processed.';
$string['batchcancel'] = 'Cancel remaining images';
$string['batchcountapplied'] = 'Applied';
$string['batchcountcancelled'] = 'Cancelled';
$string['batchcountdiscarded'] = 'Discarded';
$string['batchcountfailed'] = 'Failed';
$string['batchcountready'] = 'Ready to review';
$string['batchcountstale'] = 'Out of date';
$string['batchdiscard'] = 'Discard';
$string['batchdiscardresult'] = 'Image descriptions discarded: {$a}.';
$string['batchdiscardselected'] = 'Discard selected descriptions';
$string['batchdiscardskipped'] = 'Not discarded: {$a}. Those descriptions were no longer waiting to be reviewed.';
$string['batchintro'] = 'AI writes an alternative text suggestion for each image you selected. This happens in the background, so you can leave this page and come back to it. Nothing is changed on your images automatically. Compare each suggestion with the image and its current alternative text, then accept the ones that are right as they are, edit the ones that need changing, or discard the ones you do not want. Accept or discard several at once by selecting them and using the buttons below the table.';
$string['batchlastupdated'] = 'Last updated {$a}';
$string['batchnoitems'] = 'The images in this batch are no longer available. They may have been removed from the content they were in.';
$string['batchnosuggestionyet'] = 'Not generated yet';
$string['batchprogress'] = '{$a->processed} of {$a->total} images processed';
$string['batchstatus_cancelled'] = 'Cancelled';
$string['batchstatus_complete'] = 'Finished';
$string['batchstatus_partial'] = 'Finished with errors';
$string['batchstatus_processing'] = 'Processing';
$string['batchstatus_queued'] = 'Waiting to start';
$string['batchsuggestionstale'] = 'The image changed after this description was written, so it can no longer be applied. Select "Edit alternative text" to see the image as it is now and ask for a new description.';
$string['batchtitle'] = 'Bulk image description suggestions';
$string['brokenimage'] = 'Image not found';
$string['brokenimagefix'] = 'Open the content to fix this image';
$string['bulkgenerate'] = 'Generate AI suggestions for selected images';
$string['cannotedit'] = 'You do not have permission to edit this content item.';
$string['checkfornewimages'] = 'Check for new or changed images';
$string['classification'] = 'Alternative text status';
$string['classification_help'] = 'A deterministic classification of the alternative text based on the markup rules described in this report, such as missing, decorative, or potentially poor. This does not use AI to judge quality.

"Broken image" is the exception: it says nothing about the alternative text. It means the content points at an image file that is not there, so there is nothing for a description to describe. Open the content, restore or remove the image, and the report will reclassify it on the next scan.';
$string['contentchanged'] = 'The source content has changed. Refresh the report before applying a replacement.';
$string['contentitem'] = 'Content item';
$string['contentitem_help'] = 'The name of the specific piece of content that contains this image, such as a course, category, section, or activity.';
$string['contenttype'] = 'Content type';
$string['contenttype_help'] = 'The kind of content that contains this image, for example a course summary, section summary, or activity description.';
$string['currentalttext'] = 'Current alternative text';
$string['decorative'] = 'Decorative';
$string['decorative_help'] = 'Whether this image is explicitly marked as decorative or presentational. Decorative images are not classified as missing even without alternative text.';
$string['discard'] = 'Discard';
$string['editalttext'] = 'Edit alternative text';
$string['editdestination'] = 'Open original image editor in new window';
$string['error:aiunavailable'] = 'AI image descriptions are not available in this context. Manual remediation remains available.';
$string['error:brokenimage'] = 'This image file cannot be found, so it cannot be given alternative text. Open the content to restore or remove the image.';
$string['error:imagenotavailable'] = 'The image file is not available for AI processing.';
$string['error:policyrequired'] = 'You must accept the site AI usage policy before requesting a suggestion.';
$string['error:provider'] = 'The AI provider could not generate a suggestion: {$a}';
$string['error:remotefetchfailed'] = 'This image could not be fetched from its address, so there was nothing to describe. It may have moved, or the site may not be allowed to reach it. Details: {$a}';
$string['error:remotenotimage'] = 'The address for this image returned {$a} rather than a JPEG, PNG or WebP image, so it could not be described.';
$string['error:remotetoolarge'] = 'This image is larger than the {$a} limit for images fetched from another address, so it could not be described.';
$string['error:suggestiontoolong'] = 'The AI provider returned a suggestion longer than the {$a}-character limit. Regenerate it or enter alternative text manually.';
$string['event:alttextupdated'] = 'Image alternative text updated';
$string['fieldname'] = 'Content field';
$string['fieldname_help'] = 'The name of the specific HTML field that contains this image, such as a summary, description, or content field.';
$string['generatealttext'] = 'Generate alt text with AI';
$string['generating'] = 'Generating a description of your image…';
$string['imagealt:view'] = 'View and remediate the image alternative text report';
$string['imagepreview'] = 'Image preview';
$string['imagepreview_help'] = 'A thumbnail of the image. When no preview can be generated, this shows that the image could not be resolved to a viewable file.';
$string['imagesource'] = 'Image source';
$string['imagesource_help'] = 'The image file name or source path, linked to the image itself where a direct link is available. This is the closest available way to see exactly where the image is stored.';
$string['markdecorative'] = 'Decorative image';
$string['markdecorative_help'] = 'Decorative images are skipped by screen readers and do not need alternative text. Use this only for images that are purely visual and add no information, such as a border or spacer graphic.';
$string['noimages'] = 'No indexed image occurrences are available in this context. Refresh the analysis to populate the report.';
$string['nomatchingimages'] = 'No images match the current filters. The report opens showing only images whose alternative text is missing or could be improved, so this may simply mean there is nothing here to fix. Change the filters above to see other images.';
$string['placeholderalternatives'] = 'image,photo,picture,graphic,icon,img,spacer';
$string['pluginname'] = 'Image alternative text';
$string['privacy:metadata:batch'] = 'Bulk alternative text suggestion batches requested by users.';
$string['privacy:metadata:batch:contextid'] = 'The context in which the batch was requested.';
$string['privacy:metadata:batch:status'] = 'The processing status of the batch.';
$string['privacy:metadata:batch:timecreated'] = 'The time the batch was created.';
$string['privacy:metadata:batch:userid'] = 'The user who requested the batch.';
$string['privacy:metadata:occurrence'] = 'Indexed editable image occurrences and their current alternative text.';
$string['privacy:metadata:occurrence:alttext'] = 'The current alternative text in the source content.';
$string['privacy:metadata:occurrence:contextid'] = 'The context containing the image occurrence.';
$string['privacy:metadata:occurrence:itemname'] = 'The name of the content item containing the image.';
$string['privacy:metadata:occurrence:src'] = 'The source value of the image occurrence.';
$string['privacy:metadata:suggestion'] = 'Unpublished alternative text suggestions generated for review.';
$string['privacy:metadata:suggestion:errormessage'] = 'An error recorded while generating the suggestion.';
$string['privacy:metadata:suggestion:status'] = 'The review and processing status of the suggestion.';
$string['privacy:metadata:suggestion:suggestion'] = 'The unpublished generated alternative text.';
$string['privacy:metadata:suggestion:timecreated'] = 'The time the suggestion was created.';
$string['privacy:metadata:suggestion:userid'] = 'The user who requested the suggestion.';
$string['reason'] = 'Issue reason';
$string['reason_broken'] = 'Image file not found';
$string['reason_filename'] = 'Filename or path';
$string['reason_help'] = 'The specific deterministic rule that produced this alternative text status, for example a placeholder value or a filename used as the description.';
$string['reason_linkedimage'] = 'Linked image without a meaningful name';
$string['reason_missing'] = 'Missing or empty';
$string['reason_none'] = 'No deterministic issue';
$string['reason_placeholder'] = 'Generic placeholder';
$string['refreshcomplete'] = 'The image analysis for this context is complete and the report below is up to date.';
$string['refreshqueued'] = 'A background scan has been queued for this context. Larger sites may take some time; the table below will update as the scan completes.';
$string['regenerate'] = 'Regenerate';
$string['reportdescription'] = 'Alternative text describes an image for people who use a screen reader or cannot otherwise see it. This report lists images in this course, category, or site that are missing alternative text or where it could be improved. Review each one below to add or update its description, with an optional AI-generated suggestion to help.';
$string['reportdescriptionnoai'] = 'Alternative text describes an image for people who use a screen reader or cannot otherwise see it. This report lists images in this course, category, or site that are missing alternative text or where it could be improved. Review each one below to add or update its description.';
$string['reportheading'] = 'Review and improve image alternative text';
$string['reportsuggestionsgenerating'] = 'AI descriptions still being written: {$a}';
$string['reportsuggestionsmorebatches'] = 'Across {$a} requests. This opens the most recent; open the others from an image\'s suggestion state.';
$string['reportsuggestionsready'] = 'AI descriptions ready to review: {$a}';
$string['retryfailed'] = 'Retry failed items';
$string['reviewsuggestions'] = 'Review AI descriptions';
$string['scanprogress'] = 'A background refresh is discovering content. Course or category scans queued so far: {$a}.';
$string['status_broken'] = 'Broken image';
$string['status_decorative'] = 'Decorative';
$string['status_missing'] = 'Missing';
$string['status_potentiallypoor'] = 'Potentially poor';
$string['status_present'] = 'Present';
$string['suggestionready'] = 'Review the AI-generated description for accuracy before saving.';
$string['suggestionstatus'] = 'Suggestion state';
$string['suggestionstatus_accepted'] = 'Applied';
$string['suggestionstatus_cancelled'] = 'Cancelled';
$string['suggestionstatus_discarded'] = 'Discarded';
$string['suggestionstatus_failed'] = 'Failed';
$string['suggestionstatus_help'] = 'What has happened to the AI-written description for this image. An empty cell means none has been asked for. Where one has, the states are:

* **Queued** - requested, and waiting to be written.
* **Processing** - being written now.
* **Ready to review** - written, and waiting for you to check it and save or discard it.
* **Applied** - checked and saved, so it is now the image\'s alternative text.
* **Discarded** - rejected, and not saved to the image.
* **Out of date** - the content changed after the description was written, so it no longer matches the image in place. Request a new one.
* **Failed** - could not be written. Open the image to see the reason.
* **Cancelled** - the bulk request was cancelled before this image was reached.

Descriptions belong to whoever asked for them. This shows the latest one you asked for, so somebody else working on the same image can have their own without it appearing here.

While a description is queued, being written, or waiting for you to review it, the image cannot be selected for generation again. Where it came from a bulk request, selecting its state opens that request.';
$string['suggestionstatus_processing'] = 'Processing';
$string['suggestionstatus_queued'] = 'Queued';
$string['suggestionstatus_ready'] = 'Ready to review';
$string['suggestionstatus_stale'] = 'Out of date';
$string['suggestionstatusinfo_accepted'] = 'This AI-written description was saved, and is now the image\'s alternative text.';
$string['suggestionstatusinfo_cancelled'] = 'The bulk request was cancelled before a description was written for this image.';
$string['suggestionstatusinfo_discarded'] = 'This AI-written description was rejected, and was not saved to the image.';
$string['suggestionstatusinfo_failed'] = 'A description could not be written for this image. Open the image to see the reason.';
$string['suggestionstatusinfo_none'] = 'No AI-written description has been requested for this image.';
$string['suggestionstatusinfo_processing'] = 'A description is being written for this image now.';
$string['suggestionstatusinfo_queued'] = 'A description has been requested for this image, and is waiting to be written.';
$string['suggestionstatusinfo_ready'] = 'A description has been written, and is waiting for you to check it and save or discard it.';
$string['suggestionstatusinfo_stale'] = 'The content changed after this description was written, so it no longer matches the image in place. Request a new one.';
$string['suggestionstatuslink'] = '{$a} Select to open the batch it belongs to.';
$string['suggestionwaiting_processing'] = 'An AI-generated suggestion is being generated for this image. This can take a moment; reload this page to check for it.';
$string['suggestionwaiting_queued'] = 'An AI-generated suggestion has been requested for this image and is waiting to be processed. Reload this page shortly to check for it.';
$string['surroundingcontent'] = 'Surrounding content';
$string['taskgenerate'] = 'Generate image alternative text suggestions';
$string['taskprocessqueue'] = 'Process pending image alternative text scan targets';
$string['taskreconcile'] = 'Reconcile the image alternative text index';
$string['timeanalysed'] = 'Last analysed';
$string['timeanalysed_help'] = 'When this occurrence was last scanned. Use "Check for new or changed images" to refresh it.';
