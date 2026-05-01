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

declare(strict_types=1);

namespace editor_tiny\table;

use context_system;
use core_table\dynamic as dynamic_table;
use editor_tiny\manager;
use flexible_table;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once("{$CFG->libdir}/tablelib.php");

/**
 * Table for managing native TinyMCE standard plugin enable/disable state.
 *
 * Renders a two-column (Name, Enabled) table with AJAX toggle switches,
 * visually consistent with the Moodle plugin manager table.
 *
 * @package    editor_tiny
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class standard_plugin_management_table extends flexible_table implements dynamic_table {
    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;
        parent::__construct('standard_plugin_management_table-editor_tiny');
        require_once($CFG->libdir . '/adminlib.php');

        $this->guess_base_url();
        $this->define_columns(['name', 'enabled']);
        $this->define_headers([
            get_string('name', 'core'),
            get_string('pluginenabled', 'core_plugin'),
        ]);
        $this->set_filterset(new standard_plugin_management_table_filterset());
        $this->setup();
    }

    /**
     * Set the base URL to the TinyMCE settings page.
     */
    public function guess_base_url(): void {
        $this->define_baseurl(
            new moodle_url('/admin/settings.php', ['section' => 'editorsettingstiny'])
        );
    }

    /**
     * Return the system context for capability checks.
     *
     * @return context_system
     */
    public function get_context(): context_system {
        return context_system::instance();
    }

    /**
     * Check that the current user can manage site configuration.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('moodle/site:config', $this->get_context());
    }

    /**
     * Return the AJAX web service used to toggle a plugin's state.
     *
     * @return string
     */
    protected function get_toggle_service(): string {
        return 'editor_tiny_set_standard_plugin_state';
    }

    /**
     * Return the table HTML as a string.
     *
     * @return string
     */
    public function get_content(): string {
        ob_start();
        $this->out();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * Output all plugin rows.
     */
    public function out(): void {
        foreach (manager::get_configurable_standard_plugins() as $pluginname) {
            $enabled = get_config('editor_tiny', 'standard_plugin_' . $pluginname);
            $enabled = ($enabled === false) ? true : (bool) $enabled;
            $rowdata = (object) [
                'plugin'      => $pluginname,
                'displayname' => get_string('standard_plugin_' . $pluginname, 'editor_tiny'),
                'enabled'     => $enabled,
            ];
            $this->add_data_keyed(
                $this->format_row($rowdata),
                $enabled ? '' : 'dimmed_text',
            );
        }
        $this->finish_output(false);
    }

    /**
     * Render the Name column.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_name(stdClass $row): string {
        return $row->displayname;
    }

    /**
     * Render the Enabled column as an AJAX toggle switch.
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_enabled(stdClass $row): string {
        global $OUTPUT;

        $labelstr = $row->enabled
            ? get_string('disableplugin', 'core_admin', $row->displayname)
            : get_string('enableplugin', 'core_admin', $row->displayname);

        return $OUTPUT->render_from_template('core_admin/setting_configtoggle', [
            'id'             => 'admin-toggle-standard-' . $row->plugin,
            'checked'        => $row->enabled,
            'dataattributes' => [
                'name'          => 'id',
                'value'         => $row->plugin,
                'toggle-method' => $this->get_toggle_service(),
                'action'        => 'togglestate',
                'plugin'        => $row->plugin,
                'state'         => $row->enabled ? 1 : 0,
            ],
            'title'       => $labelstr,
            'label'       => $labelstr,
            'labelclasses' => 'visually-hidden',
        ]);
    }

    // phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    /**
     * This table is not downloadable.
     *
     * @param bool $downloadable
     * @return bool
     */
    public function is_downloadable($downloadable = null): bool {
        return false;
    }
    // phpcs:enable

    /**
     * Initialise the AMD plugin management table module so the toggles are wired up.
     *
     * @return string
     */
    protected function get_dynamic_table_html_end(): string {
        global $PAGE;
        $PAGE->requires->js_call_amd('core_admin/plugin_management_table', 'init');
        return parent::get_dynamic_table_html_end();
    }

    /**
     * Return the filterset class name for this table.
     *
     * @return string
     */
    public static function get_filterset_class(): string {
        return standard_plugin_management_table_filterset::class;
    }
}
