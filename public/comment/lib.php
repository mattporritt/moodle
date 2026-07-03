<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Functions and classes for commenting
 *
 * @package   core_comment
 * @copyright 2010 Dongsheng Cai {@link http://dongsheng.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @deprecated since Moodle 5.1, please use core_comment\manager and core_comment\comment_exception instead.
 * @todo Remove this file in Moodle 6.0 (MDL-86257)
 */

// Nothing to do here, both \comment and \comment_exception will be autoloaded by the legacyclasses autoload system.
// They are fully replaced by core_comment\manager and core_comment\comment_exception respectively.
// However, we cannot add any deprecation message here as this file is autoloaded by
// the before_standard_top_of_body_html_generation hook.

/**
 * Serves the comment list fragment for a given context, component, and item.
 *
 * Called by core_get_fragment when component='core_comment' and callback='comment_list'.
 *
 * @param array $args {
 *     int    contextid  Context ID.
 *     string component  Component name.
 *     int    itemid     Item ID.
 *     string area       Comment area.
 *     int    courseid   Course ID.
 *     int    page       Page number (0-based).
 * }
 * @return string Rendered HTML fragment.
 */
function core_comment_output_fragment_comment_list(array $args): string {
    global $OUTPUT;

    // Use the context object that core_get_fragment has already validated and injected.
    // Do NOT re-parse $args['contextid']; that would bypass the WS-level context check.
    $context   = $args['context'];
    $component = clean_param($args['component'], PARAM_COMPONENT);
    $itemid    = clean_param($args['itemid'], PARAM_INT);
    $area      = clean_param($args['area'] ?? '', PARAM_AREA);
    $courseid  = clean_param($args['courseid'] ?? SITEID, PARAM_INT);
    $page      = clean_param($args['page'] ?? 0, PARAM_INT);
    $clientid  = clean_param($args['client_id'] ?? '', PARAM_ALPHANUMEXT);

    $options = new stdClass();
    $options->context   = $context;
    $options->component = $component;
    $options->itemid    = $itemid;
    $options->area      = $area;
    $options->courseid  = $courseid;
    if ($clientid !== '') {
        $options->client_id = $clientid;
    }

    $manager = new \core_comment\manager($options);

    // Gate everything on view permission so pagination does not leak counts either.
    if (!$manager->can_view()) {
        $templatecontext = [
            'cid'        => $manager->get_cid(),
            'comments'   => '',
            'pagination' => '',
        ];
        return $OUTPUT->render_from_template('core_comment/comment_list', $templatecontext);
    }

    // Render comment items directly via get_comments()/print_comment() rather than
    // print_comments(). print_comments() silently forces $page back to 0 whenever the
    // manager's itemid/context/area/component matches the (unset) legacy nonjs static
    // properties, which default to 0/''. That legacy short-circuit is only meaningful for
    // the old non-AJAX comment.php reload path and incorrectly matches common cases such as
    // itemid = 0 (e.g. block_comments page comments), breaking pagination for this fragment.
    $comments = $manager->get_comments($page);
    $cid = $manager->get_cid();
    $commentitems = '';
    foreach (array_reverse($comments) as $cmt) {
        $commentitems .= html_writer::tag(
            'li',
            $manager->print_comment($cmt, false),
            ['id' => 'comment-' . $cmt->id . '-' . $cid],
        );
    }
    $pagination = $manager->get_pagination($page);

    $templatecontext = [
        'cid'        => $cid,
        'comments'   => $commentitems,
        'pagination' => $pagination,
    ];

    return $OUTPUT->render_from_template('core_comment/comment_list', $templatecontext);
}
