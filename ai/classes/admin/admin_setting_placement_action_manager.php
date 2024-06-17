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

namespace core_ai\admin;

use admin_setting;

/**
 * Admin setting plugin manager.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_placement_action_manager extends admin_setting {
    /** @var string The name of the plugin these actions related too */
    protected string $pluginname;

    /** @var array The list of action this manager covers */
    protected array $actions;

    /**
     * Constructor.
     *
     * @param string $pluginname
     * @param array $actions
     * @param string $name
     * @param string $visiblename
     * @param string $description
     * @param string $defaultsetting
     */
    public function __construct(
            string $pluginname,
            array $actions,
            string $name,
            string $visiblename,
            string $description = '',
            string $defaultsetting = '',
    ) {
        $this->nosave = true;
        $this->pluginname = $pluginname;
        $this->actions = $actions;

        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * Always returns true, does nothing
     *
     * @return true
     */
    public function get_setting(): bool {
        return true;
    }

    /**
     * Always returns '', does not write anything.
     *
     * @return string Always returns ''
     */
    // phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    public function write_setting($data): string {
        // Do not write any setting.
        return '';
    }

    /**
     * Generates the action table.
     *
     * @param \core_ai\actions\base $action
     * @throws \coding_exception
     * @return string
     */
    private function generate_action_table(\core_ai\actions\base $action): string {
        $table = new \core_ai\admin\tables\aiplacement_action_table (
                pluginname: $this->pluginname,
                action: $action);

        return $table->get_content();
    }

    /**
     * Generates the provider table.
     *
     * @param array $providers
     * @throws \coding_exception
     * @return string
     */
    private function generate_provider_table(array $providers): string {
        $table = new \core_ai\admin\tables\aiplacement_provider_table (
                pluginname: $this->pluginname,
                providers: $providers);

        return $table->get_content();
    }

    /**
     * Builds the XHTML to display the control.
     *
     * @param string $data Unused
     * @param string $query
     * @throws \coding_exception
     * @return string
     */
    public function output_html($data, $query = ''): string {
        // Get the list of providers that support the given actions.
        $provideractions = \core_ai\manager::get_providers_for_actions(array_keys($this->actions));
        $output = '';

        foreach ($this->actions as $actionname => $action) {
            $output .= \html_writer::start_tag('div', ['class' => 'border mb-2']);
            // Generate table to enable and disable this action.
            $output .= $this->generate_action_table($action);
            // Generate the table to manage the providers for this action.
            $output .= $this->generate_provider_table($provideractions[$actionname]);
            $output .= \html_writer::end_tag('div');
        }

        return highlight($query, $output);
    }

}
