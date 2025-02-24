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

namespace aiprovider_awsbedrock\test;

/**
 * Trait for test cases.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait testcase_helper_trait {

    /**
     * Get the provider configuration.
     *
     * @return array The provider configuration.
     */
    public function get_provider_config(): array {
        return [
            'apikey' => '123',
            'apisecret' => '456',
            'enableuserratelimit' => true,
            'userratelimit' => 1,
            'enableglobalratelimit' => true,
            'globalratelimit' => 1,
        ];
    }

    /**
     * Get the action configuration.
     *
     * @param string $actionclass The action class to use.
     * @param array $actionconfig The action configuration to use.
     * @return array The action configuration.
     */
    public function get_action_config(string $actionclass, array $actionconfig = []): array {
        $defaultactionconfig = [
            $actionclass => [
                'settings' => [
                    'model' => 'amazon.nova-pro-v1:0',
                    'awsregion' => 'ap-southeast-2',
                ],
            ],
        ];
        foreach ($actionconfig as $key => $value) {
            $defaultactionconfig[$actionclass]['settings'][$key] = $value;
        }

        return $defaultactionconfig;
    }

    /**
     * Create the provider object.
     *
     * @param string $actionclass The action class to use.
     * @param array $actionconfig The action configuration to use.
     */
    public function create_provider(
        string $actionclass,
        array $actionconfig = [],
    ): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        $config = $this->get_provider_config();
        $defaultactionconfig = [
            $actionclass => [
                'settings' => [
                    'model' => 'amazon.nova-pro-v1:0',
                    'awsregion' => 'ap-southeast-2',
                ],
            ],
        ];
        foreach ($actionconfig as $key => $value) {
            $defaultactionconfig[$actionclass]['settings'][$key] = $value;
        }
        $provider = $manager->create_provider_instance(
            classname: '\aiprovider_awsbedrock\provider',
            name: 'dummy',
            config: $config,
            actionconfig: $defaultactionconfig,
        );

        return $provider;
    }
}
