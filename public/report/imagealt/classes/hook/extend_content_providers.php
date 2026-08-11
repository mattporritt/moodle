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

namespace report_imagealt\hook;

use core\hook\described_hook;
use report_imagealt\local\content\provider;

/**
 * Allows components to expose their own editable HTML fields to the report.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extend_content_providers implements described_hook {
    /** @var array<string, provider> Providers keyed by their stable identifier. */
    private array $providers = [];

    #[\Override]
    public static function get_hook_description(): string {
        return 'Register component-owned editable HTML providers for the image alternative text report';
    }

    #[\Override]
    public static function get_hook_tags(): array {
        return ['accessibility', 'content', 'report'];
    }

    /**
     * Register a provider.
     *
     * @param provider $provider Content provider.
     */
    public function add_provider(provider $provider): void {
        $key = $provider->get_key();
        if ($key === '' || isset($this->providers[$key])) {
            throw new \coding_exception("Duplicate or empty image alternative text content provider key: {$key}");
        }
        $this->providers[$key] = $provider;
    }

    /**
     * Return registered providers.
     *
     * @return array<string, provider> Registered providers.
     */
    public function get_providers(): array {
        return $this->providers;
    }
}
