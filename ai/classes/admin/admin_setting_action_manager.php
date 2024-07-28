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
class admin_setting_action_manager extends admin_setting {
    /** @var string The name of the plugin these actions related too */
    protected string $pluginname;

    /** @var array The list of action this manager covers */
    protected array $actions;

    /** @var string The class of the management table to use */
    protected string $tableclass;

    /**
     * Constructor.
     *
     * @param string $pluginname
     * @param array $actions
     * @param string $tableclass
     * @param string $name
     * @param string $visiblename
     * @param string $description
     * @param string $defaultsetting
     */
    public function __construct(
            string $pluginname,
            array $actions,
            string $tableclass,
            string $name,
            string $visiblename,
            string $description = '',
            string $defaultsetting = '',
    ) {
        $this->nosave = true;
        $this->pluginname = $pluginname;
        $this->actions = $actions;
        $this->tableclass = $tableclass;

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
     * Builds the XHTML to display the control.
     *
     * @param string $data Unused
     * @param string $query
     * @throws \coding_exception
     * @return string
     */
    public function output_html($data, $query = ''): string {
        $table = new $this->tableclass(
                pluginname: $this->pluginname,
                actions: $this->actions);
        if (!($table instanceof \core_ai\table\aiprovider_action_management_table)
                && !($table instanceof \core_ai\table\aiplacement_action_management_table)) {
            throw new \coding_exception(
                $this->tableclass
                . 'must be an instance aiprovider_action_management_table or aiplacement_action_management_table'
            );
        }
        return highlight($query, $table->get_content());
    }

}
