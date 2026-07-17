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

require_once(__DIR__ . '/../../../../../../behat/behat_base.php');

/**
 * Steps for testing the Tiny AI placement.
 *
 * @package    tiny_aiplacement
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tiny_aiplacement extends behat_base {
    /**
     * Wait for the pending state without waiting for the intentionally unresolved mock request.
     *
     * @Then /^I wait for the image description generation to be pending$/
     */
    public function i_wait_for_the_image_description_generation_to_be_pending(): void {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $ispending = $this->getSession()->evaluateScript(<<<'JS'
                return document.querySelector('[data-action="cancel-alt-text"]') !== null;
            JS);
            if ($ispending) {
                return;
            }
            usleep(100000);
        }
        throw new \Exception('The image description generation did not enter the pending state.');
    }

    /**
     * Replace only the describe-image AJAX response with a deterministic result.
     *
     * @Given /^I mock the image description response as "(?P<state>success|failure|pending)"$/
     * @param string $state Response state.
     */
    public function i_mock_the_image_description_response_as(string $state): void {
        $response = match ($state) {
            'success' => "Promise.resolve({success: true, generatedcontent: 'A Moodle logo.'})",
            'failure' => "Promise.resolve({success: false, generatedcontent: '', error: 'Generation failed'})",
            'pending' => 'new Promise(() => {})',
        };

        $this->execute_script(<<<JS
            const originalFetch = window.fetch.bind(window);
            window.fetch = (resource, options) => {
                const url = String(resource);
                const isImageRequest = url.startsWith('blob:')
                    || url.includes('/draftfile.php/')
                    || url.includes('/pluginfile.php/')
                    || /\.(?:jpe?g|png|webp)(?:[?#]|$)/i.test(url)
                    || (options?.signal instanceof AbortSignal && (!options.method || options.method === 'GET'));
                if (isImageRequest) {
                    return Promise.resolve(new Response(
                        new Blob(['fixture image'], {type: 'image/png'}),
                        {status: 200},
                    ));
                }
                return originalFetch(resource, options);
            };

            require(['core/ajax'], Ajax => {
                const originalCall = Ajax.call.bind(Ajax);
                Ajax.call = requests => requests.map(request => {
                    if (request.methodname === 'aiplacement_editor_describe_image') {
                        return {$response};
                    }
                    return originalCall([request])[0];
                });
            });
        JS);
    }
}
