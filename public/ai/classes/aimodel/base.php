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

namespace core_ai\aimodel;

use MoodleQuickForm;

/**
 * Base Model class.
 *
 * @package    core_ai
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Get the display name of the model.
     * This name is used to display the model in the UI.
     *
     * @return string The display name of the model.
     */
    abstract public function get_model_display_name(): string;

    /**
     * Get the name of the model.
     * This name is used to identify the model. The system will use this model name to make the request to the AI services.
     *
     * @return string The name of the model.
     */
    abstract public function get_model_name(): string;

    /**
     * Add the model settings to the form.
     *
     * @param MoodleQuickForm $mform The form to add the model settings to.
     */
    public function add_model_settings(MoodleQuickForm $mform): void {
    }

    /**
     * Check if the model has settings.
     *
     * @return bool Whether the model has settings.
     */
    public function has_model_settings(): bool {
        return !empty($this->get_model_settings());
    }

    /**
     * Get all settings that can be configured for a model.
     *
     * @return string[] Array of settings.
     */
    public function get_model_settings(): array {
        return [];
    }

    /**
     * Whether this model is deprecated by its upstream provider but still usable.
     *
     * Deprecated models must not be removed while still usable. Fully removed
     * models (no longer available from the provider at all) must instead be
     * deleted from the codebase so their use hard-fails with an actionable error.
     *
     * @return bool Whether the model is deprecated.
     */
    public function is_deprecated(): bool {
        return false;
    }

    /**
     * Get the confirmed final removal/end-of-life date for a deprecated model.
     *
     * @return string|null The EOL date (for example '2026-10-23'), or null if not deprecated.
     */
    public function get_deprecation_eol(): ?string {
        return null;
    }

    /**
     * Get the label to display for this model in the admin model selector.
     *
     * Appends a deprecated indicator and EOL date to the display name when the
     * model is deprecated.
     *
     * @return string The model selector label.
     */
    final public function get_model_selector_label(): string {
        if (!$this->is_deprecated()) {
            return $this->get_model_display_name();
        }
        return get_string('model_deprecated_label', 'core_ai', (object) [
            'modelname' => $this->get_model_display_name(),
            'eol' => $this->get_deprecation_eol(),
        ]);
    }
}
