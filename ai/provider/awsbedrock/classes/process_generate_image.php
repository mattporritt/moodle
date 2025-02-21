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

namespace aiprovider_awsbedrock;

use Aws\Result;
use core\http_client;
use core_ai\ai_image;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class process image generation.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {

    /**
     * Create the request object for the Amazon Nova models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_amazon_nova_request(\stdClass $requestobj, array $modelsettings): \stdClass {
        //$requestobj->prompt = $this->action->get_configuration('prompttext');
        //$requestobj->n = $this->numberimages;
        //$requestobj->quality = $this->action->get_configuration('quality');
        //$requestobj->size = $this->calculate_size($this->action->get_configuration('aspectratio'));
        //$requestobj->style = $this->action->get_configuration('style');

        $requestobj->taskType = 'TEXT_IMAGE';
        $aspectratio = $this->action->get_configuration('aspectratio');
        $quality = $this->action->get_configuration('quality') == 'hd' ? 'premium' : 'standard';

        if ($aspectratio == 'square' && $quality == 'premium') {
            $width = 1792;
            $height = 1792;
        } else if ($aspectratio == 'portrait' && $quality == 'premium') {
            $width = 1024;
            $height = 1792;
        } else if ($aspectratio == 'landscape' && $quality == 'premium') {
            $width = 1792;
            $height = 1024;
        } else if ($aspectratio == 'square' && $quality == 'standard') {
            $width = 1024;
            $height = 1024;
        } else if ($aspectratio == 'portrait' && $quality == 'standard') {
            $width = 592;
            $height = 1024;
        } else if ($aspectratio == 'landscape' && $quality == 'standard') {
            $width = 1024;
            $height = 592;
        } else {
            throw new \coding_exception('Unknown aspect ratio.');
        }

        // Create the prompt object.
        $promptobj = new \stdClass();
        $promptobj->text = $this->action->get_configuration('prompttext');
        $requestobj->textToImageParams = $promptobj;

        // Create the image generation config object.
        $imggenconfig = new \stdClass();
        $imggenconfig->numberOfImages = $this->action->get_configuration('numimages');
        $imggenconfig->width = $width;
        $imggenconfig->height = $height;
        $imggenconfig->quality = $quality;

        // Add the model settings to the request object.
        foreach ($modelsettings as $setting => $value) {
            if (in_array($setting, ['awsregion', 'cross_region_inference'])) {
                continue;
            }

            $imggenconfig->$setting = is_numeric($value) ? ($value + 0) : $value;
        }

        $requestobj->imageGenerationConfig = $imggenconfig;

        return $requestobj;
    }

    /**
     * Create the request object for the Amazon Titan models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_amazon_titan_request(\stdClass $requestobj, array $modelsettings): \stdClass {

        return $requestobj;
    }

    /**
     * Create the request object for Stability AI models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_stability_request(\stdClass $requestobj, array $modelsettings): \stdClass {

        return $requestobj;
    }

    #[\Override]
    protected function create_request(): array {
        $requestobj = new \stdClass();
        $modelsettings = $this->get_model_settings();
        $model = $this->get_model();

        if (str_contains($model, 'amazon.nova')) {
            $requestobj = $this->create_amazon_nova_request($requestobj, $modelsettings);
        } else if (str_contains($model, 'amazon.titan')) {
            $requestobj = $this->create_amazon_titan_request($requestobj, $modelsettings);
        } else if (str_contains($model, 'stability')) {
            $requestobj = $this->create_stability_request($requestobj, $modelsettings);
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        return [
            'ContentType' => 'application/json',
            'Accept' => 'application/json',
            'modelId' => $model,
            'body' => json_encode($requestobj),
        ];
    }

    #[\Override]
    protected function handle_api_success(Result $result): array {
        $bodyobj = json_decode($result['body']->getContents());
        $responseheaders = $result['@metadata']['headers'];
        $response = [
            'success' => true,
            'fingerprint' => $responseheaders['x-amzn-requestid'],
            'prompttokens' => $responseheaders['x-amzn-bedrock-input-token-count'],
            'completiontokens' => $responseheaders['x-amzn-bedrock-output-token-count'],
        ];


        return [
            'success' => true,
            'sourceurl' => $bodyobj->data[0]->url,
            'revisedprompt' => $bodyobj->data[0]->revised_prompt,
            'model' => $this->get_model(), // There is no model in the response, use config.
        ];
    }

    /**
     * Convert the url for the image to a file.
     *
     * Placements can't interact with the provider AI directly,
     * therefore we need to provide the image file in a format that can
     * be used by placements. So we use the file API.
     *
     * @param int $userid The user id.
     * @param string $url The URL to the image.
     * @return \stored_file The file object.
     */
    private function url_to_file(int $userid, string $url): \stored_file {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        $parsedurl = parse_url($url, PHP_URL_PATH); // Parse the URL to get the path.
        $filename = basename($parsedurl); // Get the basename of the path.

        $client = \core\di::get(http_client::class);

        // Download the image and add the watermark.
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        $client->get($url, [
            'sink' => $tempdst,
            'timeout' => $CFG->repositorygetfiletimeout,
        ]);

        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        // We put the file in the user draft area initially.
        // Placements (on behalf of the user) can then move it to the correct location.
        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($userid)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        $fs = get_file_storage();
        return $fs->create_file_from_string($fileinfo, file_get_contents($tempdst));
    }
}
