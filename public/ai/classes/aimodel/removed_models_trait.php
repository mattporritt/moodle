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

/**
 * Shared removed-model lookup for provider model directories.
 *
 * Using classes must define a REMOVED_MODELS array constant listing model ids
 * that are no longer available from the provider at all.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait removed_models_trait {
    /**
     * Check whether a model id has been fully removed by the provider.
     *
     * @param string $modelname Model id.
     * @return bool Whether the model has been removed.
     */
    public static function is_model_removed(string $modelname): bool {
        return in_array($modelname, static::REMOVED_MODELS, true);
    }
}
