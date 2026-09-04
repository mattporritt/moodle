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

namespace core_my\local;

use block_manager;
use context_system;
use context_user;
use moodle_url;

/**
 * Server-side support for the responsive dashboard grid.
 *
 * @package    core_my
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dashboard {
    /** Canonical number of columns used for persisted layouts. */
    public const MAX_COLUMNS = 6;

    /** Default grid row span for legacy and newly-added blocks. */
    public const DEFAULT_ROWS = 3;

    /** Default grid row span for the first legacy block in each stack (taller than the rest). */
    public const DEFAULT_ROWS_FIRST = 4;

    /** Legacy regions which may still be attached to dashboard blocks. */
    private const REGIONS = ['content', 'side-pre', 'side-post'];

    /**
     * Legacy block editing-control classes still offered from the dashboard's block actions menu.
     *
     * Move and hide/show are superseded by the grid's own controls, and delete lives in the
     * tile's discrete delete button instead of being duplicated inside the menu as well.
     * Configure and Assign roles are dropped too: the dashboard is a personal, per-user surface,
     * so a block's own placement/role-assignment settings are redundant here in a way they are
     * not on a shared course or site page. Permissions review stays, since who can do what with
     * a block instance is still meaningful to check and change on the dashboard.
     */
    private const MENU_ACTION_ALLOWED = ['permissions', 'checkroles'];

    /**
     * Set up Moodle's block manager for a user or site-default dashboard.
     *
     * @param bool $sitedefault Whether to load the site-default dashboard.
     * @param bool $includeinvisible Whether invisible blocks should be loaded.
     * @return array The page record and context.
     */
    public static function initialise_page(bool $sitedefault, bool $includeinvisible = false): array {
        global $CFG, $PAGE, $USER;

        require_once($CFG->dirroot . '/my/lib.php');

        if ($sitedefault) {
            $context = context_system::instance();
            require_capability('moodle/my:configsyspages', $context);
            $mypage = my_get_page(null, MY_PAGE_PRIVATE);
            $PAGE->set_blocks_editing_capability('moodle/my:configsyspages');
        } else {
            $context = context_user::instance($USER->id);
            $mypage = my_get_page($USER->id, MY_PAGE_PRIVATE);
            $PAGE->set_blocks_editing_capability('moodle/my:manageblocks');
            if (
                $mypage && !$mypage->userid && !empty($USER->editing)
                    && has_capability('moodle/my:manageblocks', $context)
            ) {
                $mypage = my_copy_page($USER->id, MY_PAGE_PRIVATE);
            }
        }

        if (!$mypage) {
            throw new \moodle_exception('mymoodlesetup');
        }

        $PAGE->set_context($context);
        $PAGE->set_url($sitedefault ? '/my/indexsys.php' : '/my/index.php');
        $PAGE->set_pagelayout('mydashboard');
        $PAGE->set_pagetype('my-index');
        foreach (self::REGIONS as $region) {
            $PAGE->blocks->add_region($region);
        }
        $PAGE->set_subpage($mypage->id);
        $PAGE->blocks->load_blocks($includeinvisible);
        $PAGE->blocks->create_all_block_instances();

        return [$mypage, $context];
    }

    /**
     * Return the current dashboard payload for the React application.
     *
     * @param bool $sitedefault Whether to load the site default.
     * @return array
     */
    public static function get(bool $sitedefault): array {
        global $OUTPUT, $PAGE, $USER;

        $context = $sitedefault ? context_system::instance() : context_user::instance($USER->id);
        $capability = $sitedefault ? 'moodle/my:configsyspages' : 'moodle/my:manageblocks';
        $canedit = has_capability($capability, $context);
        $editing = $canedit && !empty($USER->editing);
        $othercapability = $sitedefault ? 'moodle/my:manageblocks' : 'moodle/my:configsyspages';
        $othercontext = $sitedefault ? context_user::instance($USER->id) : context_system::instance();
        $caneditotherscope = has_capability($othercapability, $othercontext);
        [$mypage] = self::initialise_page($sitedefault, $editing);

        // Block renderers may register JavaScript which must be returned to the React client.
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();
        $PAGE->blocks->refresh_cached_content();

        $blocks = [];
        $instances = [];
        foreach (self::REGIONS as $region) {
            foreach ($PAGE->blocks->get_blocks_for_region($region) as $instance) {
                $instances[$instance->instance->id] = $instance;
            }
        }

        $contents = $PAGE->blocks->get_content_for_all_regions($OUTPUT);
        foreach ($contents as $region => $regioncontents) {
            foreach ($regioncontents as $content) {
                if (empty($content->blockinstanceid) || !isset($instances[$content->blockinstanceid])) {
                    continue;
                }
                $instance = $instances[$content->blockinstanceid];
                $blocks[] = [
                    'id' => (int) $content->blockinstanceid,
                    'name' => $instance->instance->blockname,
                    'title' => (string) $content->title,
                    'content' => (string) $content->content,
                    'footer' => (string) $content->footer,
                    'region' => $region,
                    'weight' => (int) $instance->instance->weight,
                    'actions' => self::get_menu_actions($content->controls),
                ];
            }
        }

        $layout = self::get_layout($mypage->id, $context->id, $instances);
        $available = [];
        if ($editing) {
            foreach ($PAGE->blocks->get_addable_blocks() as $block) {
                $available[] = [
                    'name' => $block->name,
                    'title' => get_string('pluginname', 'block_' . $block->name),
                ];
            }
        }

        return [
            'blocks' => $blocks,
            'layout' => $layout,
            'availableblocks' => $available,
            'canedit' => $canedit,
            'editing' => $editing,
            'sitedefault' => $sitedefault,
            'caneditotherscope' => $caneditotherscope,
            'urls' => [
                'ownpage' => (new moodle_url('/my/index.php'))->out(false),
                'sitedefault' => (new moodle_url('/my/indexsys.php'))->out(false),
            ],
            'javascript' => $PAGE->requires->get_end_code(),
            'labels' => [
                'addblock' => get_string('addblock'),
                'addblocktop' => get_string('addblocktop', 'my'),
                'addblockbottom' => get_string('addblockbottom', 'my'),
                'close' => get_string('closebuttontitle'),
                'confirm' => get_string('confirm'),
                'confirmremove' => get_string('confirmremoveblock', 'my'),
                'confirmreset' => get_string('confirmresetdashboard', 'my'),
                'cancel' => get_string('dashboardcancel', 'my'),
                'done' => get_string('dashboarddone', 'my'),
                'down' => get_string('dashboarddown', 'my'),
                'emptycell' => get_string('emptygridcell', 'my'),
                'gridcell' => get_string('gridcellposition', 'my'),
                'left' => get_string('dashboardleft', 'my'),
                'loading' => get_string('loading'),
                'move' => get_string('moveblock', 'block'),
                'movecontrols' => get_string('dashboardmovecontrols', 'my'),
                'moveinstructions' => get_string('dashboardmovehandleinstructions', 'my'),
                'remove' => get_string('deleteblock', 'block'),
                'removeheading' => get_string('removeblockheading', 'my'),
                'reset' => get_string('resetpage', 'my'),
                'resetheading' => get_string('resetdashboardheading', 'my'),
                'resize' => get_string('resizeblock', 'my'),
                'resizecontrols' => get_string('dashboardresizecontrols', 'my'),
                'resizeinstructions' => get_string('dashboardresizehandleinstructions', 'my'),
                'right' => get_string('dashboardright', 'my'),
                'scopeown' => get_string('dashboardscopeown', 'my'),
                'scopesitedefault' => get_string('dashboardscopesitedefault', 'my'),
                'switchtoown' => get_string('dashboardswitchtoown', 'my'),
                'switchtositedefault' => get_string('dashboardswitchtositedefault', 'my'),
                'tile' => get_string('dashboardtile', 'my'),
                'up' => get_string('dashboardup', 'my'),
                'blockactions' => get_string('dashboardblockactions', 'my'),
            ],
        ];
    }

    /**
     * Translate a block's legacy editing controls into the dashboard's block actions menu.
     *
     * The menu reuses whichever controls {@see block_manager::edit_controls()} already builds
     * for the block, with the same capability checks and the same destination pages, filtered to
     * the small set the dashboard still has a use for (see MENU_ACTION_ALLOWED). Every other
     * control is either superseded by the grid's own controls, relocated to the tile's discrete
     * delete button, or simply redundant on a personal, per-user page.
     *
     * @param \action_link[] $controls The block's editing controls, from block_contents::$controls.
     * @return array
     */
    private static function get_menu_actions(array $controls): array {
        $actions = [];
        foreach ($controls as $control) {
            $classes = explode(' ', (string) ($control->attributes['class'] ?? ''));
            $id = null;
            foreach ($classes as $class) {
                if (str_starts_with($class, 'editing_')) {
                    $id = substr($class, strlen('editing_'));
                    break;
                }
            }
            if ($id === null || !in_array($id, self::MENU_ACTION_ALLOWED, true)) {
                continue;
            }
            $actions[] = [
                'id' => $id,
                'label' => (string) $control->text,
                'url' => $control->url->out(false),
                'modalform' => (string) ($control->attributes['data-blockform'] ?? ''),
            ];
        }
        return $actions;
    }

    /** Row height in pixels, matching the client's ROW_HEIGHT in js/esm/src/layout.ts. */
    private const ROW_HEIGHT_PX = 96;

    /**
     * Return a loading placeholder which is visible before the React application starts.
     *
     * When {@see self::get_skeleton_layout()} could cheaply determine the real grid shape, this
     * server-rendered placeholder is positioned to match it: the very first thing the browser
     * paints, before any JavaScript has run, already looks like the user's real dashboard, rather
     * than a generic grid that gets replaced outright once React mounts.
     *
     * @param array $layout Layout items (id, column, row, columns, rows), or [] for a generic grid.
     * @return string
     */
    public static function get_loading_placeholder(array $layout = []): string {
        $label = get_string('loading');
        if (empty($layout)) {
            $line = \html_writer::div('', 'core-my-dashboard-loading__line');
            $tile = \html_writer::div(
                \html_writer::div('', 'core-my-dashboard-loading__heading')
                    . \html_writer::div('', 'core-my-dashboard-loading__line core-my-dashboard-loading__line--long')
                    . $line
                    . \html_writer::div('', 'core-my-dashboard-loading__line core-my-dashboard-loading__line--short'),
                'core-my-dashboard-loading__tile',
            );
            return \html_writer::div(
                \html_writer::span($label, 'visually-hidden')
                    . \html_writer::div(str_repeat($tile, 6), 'core-my-dashboard-loading__grid', ['aria-hidden' => 'true']),
                'core-my-dashboard-loading',
                ['role' => 'status', 'aria-label' => $label, 'aria-busy' => 'true'],
            );
        }

        $rows = 1;
        foreach ($layout as $item) {
            $rows = max($rows, $item['row'] + $item['rows']);
        }
        $tiles = '';
        foreach ($layout as $item) {
            $tiles .= \html_writer::div(
                \html_writer::div('', 'core-my-dashboard-loading__heading')
                    . \html_writer::div('', 'core-my-dashboard-loading__line core-my-dashboard-loading__line--long')
                    . \html_writer::div('', 'core-my-dashboard-loading__line'),
                'core-my-dashboard-tile core-my-dashboard-loading__tile core-my-dashboard-loading__tile--positioned',
                ['style' => sprintf(
                    'grid-column: %d / span %d; grid-row: %d / span %d;',
                    $item['column'] + 1,
                    $item['columns'],
                    $item['row'] + 1,
                    $item['rows'],
                )],
            );
        }
        return \html_writer::div(
            \html_writer::span($label, 'visually-hidden')
                . \html_writer::div($tiles, 'core-my-dashboard-grid core-my-dashboard-loading__grid--positioned', [
                    'aria-hidden' => 'true',
                    'style' => sprintf(
                        'grid-template-columns: repeat(%d, minmax(0, 1fr)); grid-template-rows: repeat(%d, %dpx);',
                        self::MAX_COLUMNS,
                        $rows,
                        self::ROW_HEIGHT_PX,
                    ),
                ]),
            'core-my-dashboard-loading',
            ['role' => 'status', 'aria-label' => $label, 'aria-busy' => 'true'],
        );
    }

    /**
     * Cheaply read the persisted grid shape for the initial page render, without loading blocks.
     *
     * Unlike {@see self::get()}, this never instantiates block objects or renders block content,
     * the expensive part of a dashboard load. It only reads {@see self::get_layout()}'s already
     * persisted grid columns/rows directly from the database, so the very first response can draw
     * loading placeholders in the shape of the user's real dashboard instead of a generic grid.
     *
     * Returns an empty array whenever the persisted data is incomplete (a block with no saved
     * position yet, for example right after it was added) rather than guessing: the caller is
     * expected to fall back to a generic placeholder in that case.
     *
     * @param bool $sitedefault Whether to read the site-default dashboard.
     * @return array Layout items (id, column, row, columns, rows), or [] if not cheaply knowable.
     */
    public static function get_skeleton_layout(bool $sitedefault): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/my/lib.php');

        $userid = $sitedefault ? null : $USER->id;
        $mypage = my_get_page($userid, MY_PAGE_PRIVATE);
        if (!$mypage) {
            return [];
        }
        $context = $sitedefault ? context_system::instance() : context_user::instance($USER->id);

        $sql = "SELECT bi.id AS blockinstanceid, bp.gridcolumn, bp.gridrow, bp.gridcolumns, bp.gridrows
                  FROM {block_instances} bi
             LEFT JOIN {block_positions} bp
                    ON bp.blockinstanceid = bi.id
                   AND bp.contextid = :contextid
                   AND bp.pagetype = :pagetype
                   AND bp.subpage = :subpage
                 WHERE bi.parentcontextid = :parentcontextid
                   AND bi.pagetypepattern = :instancepagetype
                   AND bi.subpagepattern = :instancesubpage";
        $params = [
            'contextid' => $context->id,
            'pagetype' => 'my-index',
            'subpage' => (string) $mypage->id,
            'parentcontextid' => $context->id,
            'instancepagetype' => 'my-index',
            'instancesubpage' => (string) $mypage->id,
        ];
        $rows = $DB->get_records_sql($sql, $params);

        $layout = [];
        foreach ($rows as $row) {
            if (
                $row->gridcolumn === null || $row->gridrow === null
                    || $row->gridcolumns === null || $row->gridrows === null
            ) {
                // A block is missing its saved position: the real layout can't be known cheaply.
                return [];
            }
            $layout[] = [
                'id' => (int) $row->blockinstanceid,
                'column' => (int) $row->gridcolumn,
                'row' => (int) $row->gridrow,
                'columns' => (int) $row->gridcolumns,
                'rows' => (int) $row->gridrows,
            ];
        }

        usort($layout, static fn(array $left, array $right): int =>
            [$left['row'], $left['column'], $left['id']] <=> [$right['row'], $right['column'], $right['id']]);
        return $layout;
    }

    /**
     * Get persisted grid positions, deriving safe defaults for unmigrated blocks.
     *
     * @param int $pageid Dashboard subpage id.
     * @param int $contextid Dashboard context id.
     * @param array $instances Block instances indexed by id.
     * @return array
     */
    public static function get_layout(int $pageid, int $contextid, array $instances): array {
        global $DB;

        $positions = $DB->get_records('block_positions', [
            'contextid' => $contextid,
            'pagetype' => 'my-index',
            'subpage' => (string) $pageid,
        ]);
        $byblock = [];
        foreach ($positions as $position) {
            $byblock[$position->blockinstanceid] = $position;
        }

        $nextrow = ['content' => 0, 'drawer' => 0];
        $layout = [];
        foreach ($instances as $id => $instance) {
            $position = $byblock[$id] ?? null;
            $region = $position->region
                ?? $instance->instance->region
                ?? $instance->instance->defaultregion;
            $isdrawer = $region !== 'content';
            $stack = $isdrawer ? 'drawer' : 'content';
            $columns = $isdrawer ? 1 : 3;
            $row = $nextrow[$stack];
            if (
                $position && $position->gridcolumn !== null && $position->gridrow !== null
                    && $position->gridcolumns !== null && $position->gridrows !== null
            ) {
                $column = (int) $position->gridcolumn;
                $row = (int) $position->gridrow;
                $columns = (int) $position->gridcolumns;
                $rows = (int) $position->gridrows;
            } else {
                // Leave an empty column on each side: legacy content blocks take the next
                // three columns, legacy side-region ("drawer") blocks take the column after.
                // The first block in each stack is a row taller than the rest.
                $column = $isdrawer ? 4 : 1;
                $rows = $row === 0 ? self::DEFAULT_ROWS_FIRST : self::DEFAULT_ROWS;
            }
            $nextrow[$stack] = max($nextrow[$stack], $row + $rows);
            $layout[] = [
                'id' => (int) $id,
                'column' => $column,
                'row' => $row,
                'columns' => $columns,
                'rows' => $rows,
            ];
        }

        usort($layout, static fn(array $left, array $right): int =>
            [$left['row'], $left['column'], $left['id']] <=> [$right['row'], $right['column'], $right['id']]);
        return $layout;
    }

    /**
     * Persist a canonical six-column layout in block_positions.
     *
     * @param bool $sitedefault Whether to update the site default.
     * @param array $layout Layout items.
     */
    public static function save(bool $sitedefault, array $layout): void {
        global $DB, $PAGE;

        [$mypage, $context] = self::initialise_page($sitedefault, true);
        self::require_edit_capability($sitedefault, $context);

        $instances = [];
        foreach (self::REGIONS as $region) {
            foreach ($PAGE->blocks->get_blocks_for_region($region) as $instance) {
                $instances[$instance->instance->id] = $instance;
            }
        }
        self::validate_layout($layout, $instances);

        $transaction = $DB->start_delegated_transaction();
        foreach ($layout as $item) {
            $instance = $instances[$item['id']]->instance;
            $position = $DB->get_record('block_positions', [
                'blockinstanceid' => $item['id'],
                'contextid' => $context->id,
                'pagetype' => 'my-index',
                'subpage' => (string) $mypage->id,
            ]);
            if (!$position) {
                $position = (object) [
                    'blockinstanceid' => $item['id'],
                    'contextid' => $context->id,
                    'pagetype' => 'my-index',
                    'subpage' => (string) $mypage->id,
                    'visible' => $instance->visible ?? 1,
                    'region' => $instance->region ?? $instance->defaultregion,
                    'weight' => $instance->weight ?? $instance->defaultweight,
                ];
            }
            $position->gridcolumn = $item['column'];
            $position->gridrow = $item['row'];
            $position->gridcolumns = $item['columns'];
            $position->gridrows = $item['rows'];
            if (!empty($position->id)) {
                $DB->update_record('block_positions', $position);
            } else {
                $DB->insert_record('block_positions', $position);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Add a block to the end of the grid.
     *
     * @param bool $sitedefault Whether to update the site default.
     * @param string $blockname Block plugin name.
     * @return int New block instance id.
     */
    public static function add(bool $sitedefault, string $blockname): int {
        global $DB, $PAGE;

        [$mypage, $context] = self::initialise_page($sitedefault, true);
        self::require_edit_capability($sitedefault, $context);
        $addable = $PAGE->blocks->get_addable_blocks();
        if (!isset($addable[$blockname])) {
            throw new \moodle_exception('cannotaddthisblocktype');
        }
        $instances = [];
        foreach (self::REGIONS as $region) {
            foreach ($PAGE->blocks->get_blocks_for_region($region) as $instance) {
                $instances[$instance->instance->id] = $instance;
            }
        }
        $layout = self::get_layout($mypage->id, $context->id, $instances);
        $bottom = array_reduce($layout, static fn(int $carry, array $item): int =>
            max($carry, $item['row'] + $item['rows']), 0);
        $contentblocks = $PAGE->blocks->get_blocks_for_region('content');
        $weight = array_reduce($contentblocks, static fn(int $carry, $instance): int =>
            max($carry, (int) $instance->instance->weight + 1), 0);
        $block = $PAGE->blocks->add_block(
            $blockname,
            'content',
            $weight,
            false,
            'my-index',
            (string) $mypage->id,
        );
        if (!$block) {
            throw new \moodle_exception('blockcannotadd');
        }
        $DB->insert_record('block_positions', (object) [
            'blockinstanceid' => $block->instance->id,
            'contextid' => $context->id,
            'pagetype' => 'my-index',
            'subpage' => (string) $mypage->id,
            'visible' => $block->instance->visible ?? 1,
            'region' => $block->instance->region ?? $block->instance->defaultregion,
            'weight' => $block->instance->weight ?? $block->instance->defaultweight,
            'gridcolumn' => 0,
            'gridrow' => $bottom,
            'gridcolumns' => 2,
            'gridrows' => self::DEFAULT_ROWS,
        ]);
        return (int) $block->instance->id;
    }

    /**
     * Remove a block owned by the current dashboard.
     *
     * @param bool $sitedefault Whether to update the site default.
     * @param int $blockid Block instance id.
     */
    public static function remove(bool $sitedefault, int $blockid): void {
        global $PAGE;

        [, $context] = self::initialise_page($sitedefault, true);
        self::require_edit_capability($sitedefault, $context);
        $block = $PAGE->blocks->find_instance($blockid);
        if (
            !$block || !$block->user_can_edit() || !$block->user_can_addto($PAGE)
                || in_array($block->instance->blockname, block_manager::get_undeletable_block_types(), true)
        ) {
            throw new \moodle_exception('nopermissions', '', $PAGE->url->out(), get_string('deleteablock'));
        }
        blocks_delete_instance($block->instance);
    }

    /**
     * Reset the current user's dashboard to the site default.
     */
    public static function reset(): void {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/my/lib.php');
        $context = context_user::instance($USER->id);
        require_capability('moodle/my:manageblocks', $context);
        if (!my_reset_page($USER->id, MY_PAGE_PRIVATE)) {
            throw new \moodle_exception('reseterror', 'my');
        }
        $USER->editing = 0;
    }

    /**
     * Resolve the default minimum size through one replaceable extension point.
     *
     * @return array
     */
    public static function get_minimum_size(): array {
        return ['columns' => 1, 'rows' => 2];
    }

    /**
     * Require the appropriate editing capability.
     *
     * @param bool $sitedefault Whether the site default is being edited.
     * @param \context $context Dashboard context.
     */
    private static function require_edit_capability(bool $sitedefault, \context $context): void {
        require_capability($sitedefault ? 'moodle/my:configsyspages' : 'moodle/my:manageblocks', $context);
    }

    /**
     * Validate layout ownership, bounds, minimum size, uniqueness and overlap.
     *
     * @param array $layout Layout items.
     * @param array $instances Available block instances.
     */
    private static function validate_layout(array $layout, array $instances): void {
        $minimum = self::get_minimum_size();
        $seen = [];
        $occupied = [];
        foreach ($layout as $item) {
            if (!isset($instances[$item['id']]) || isset($seen[$item['id']])) {
                throw new \invalid_parameter_exception('The layout contains an invalid or duplicate block.');
            }
            $seen[$item['id']] = true;
            if (
                $item['column'] < 0 || $item['row'] < 0
                    || $item['columns'] < $minimum['columns'] || $item['rows'] < $minimum['rows']
                    || $item['column'] + $item['columns'] > self::MAX_COLUMNS
            ) {
                throw new \invalid_parameter_exception('The layout contains an invalid grid rectangle.');
            }
            for ($row = $item['row']; $row < $item['row'] + $item['rows']; $row++) {
                for ($column = $item['column']; $column < $item['column'] + $item['columns']; $column++) {
                    $key = $column . ':' . $row;
                    if (isset($occupied[$key])) {
                        throw new \invalid_parameter_exception('Dashboard blocks may not overlap.');
                    }
                    $occupied[$key] = true;
                }
            }
        }
        if (count($seen) !== count($instances)) {
            throw new \invalid_parameter_exception('The layout must contain every dashboard block.');
        }
    }
}
