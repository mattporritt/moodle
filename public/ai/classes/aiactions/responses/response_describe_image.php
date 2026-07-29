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

namespace core_ai\aiactions\responses;

use core_ai\helper;

/**
 * Describe image action response.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_describe_image extends response_base {
    /** @var string|null Provider response identifier. */
    private ?string $id = null;
    /** @var string|null Provider backend fingerprint. */
    private ?string $fingerprint = null;
    /** @var string|null Generated image description. */
    private ?string $generatedcontent = null;
    /** @var string|null Completion finish reason. */
    private ?string $finishreason = null;
    /** @var string|null Prompt token count. */
    private ?string $prompttokens = null;
    /** @var string|null Completion token count. */
    private ?string $completiontokens = null;

    /**
     * Constructor.
     *
     * @param bool $success Whether the action succeeded.
     * @param int $errorcode Error code for a failed response.
     * @param string $error Error name for a failed response.
     * @param string $errormessage Error message.
     */
    public function __construct(
        bool $success,
        int $errorcode = 0,
        string $error = '',
        string $errormessage = '',
    ) {
        parent::__construct(
            success: $success,
            actionname: 'describe_image',
            errorcode: $errorcode,
            error: $error,
            errormessage: $errormessage,
        );
    }

    #[\Override]
    public function set_response_data(array $response): void {
        $this->id = $response['id'] ?? null;
        $this->fingerprint = $response['fingerprint'] ?? null;
        $generatedcontent = $response['generatedcontent'] ?? null;
        $this->generatedcontent = $generatedcontent !== null
            ? helper::strip_code_fences(helper::strip_reasoning_tags($generatedcontent))
            : null;
        $this->finishreason = $response['finishreason'] ?? null;
        $this->prompttokens = $response['prompttokens'] ?? null;
        $this->completiontokens = $response['completiontokens'] ?? null;
        $this->model = $response['model'] ?? null;
    }

    #[\Override]
    public function get_response_data(): array {
        return [
            'id' => $this->id,
            'fingerprint' => $this->fingerprint,
            'generatedcontent' => $this->generatedcontent,
            'finishreason' => $this->finishreason,
            'prompttokens' => $this->prompttokens,
            'completiontokens' => $this->completiontokens,
            'model' => $this->model,
        ];
    }
}
