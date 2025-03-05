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
use core_ai\ai_image;

/**
 * Class process image generation.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {

    /**
     * Create the request object for the Amazon models.
     *
     * @param \stdClass $requestobj The base request object to extend.
     * @param array $modelsettings The model settings to append to the request object.
     * @return \stdClass $requestobj The extended request object.
     */
    private function create_amazon_request(\stdClass $requestobj, array $modelsettings): \stdClass {
        $requestobj->taskType = 'TEXT_IMAGE';
        $aspectratio = $this->action->get_configuration('aspectratio');
        $quality = $this->action->get_configuration('quality') == 'hd' ? 'premium' : 'standard';
        $model = $this->get_model();

        // Calculate the dimensions based on the aspect ratio and quality.
        if (str_contains($model, 'amazon.nova')) {
            $dimensions = [
                'premium' => [
                    'square' => ['width' => 1792, 'height' => 1792],
                    'portrait' => ['width' => 1024, 'height' => 1792],
                    'landscape' => ['width' => 1792, 'height' => 1024],
                ],
                'standard' => [
                    'square' => ['width' => 1024, 'height' => 1024],
                    'portrait' => ['width' => 592, 'height' => 1024],
                    'landscape' => ['width' => 1024, 'height' => 592],
                ],
            ];
        } else {
            // Titan models have different dimensions.
            $dimensions = [
                'premium' => [
                    'square'    => ['width' => 1024, 'height' => 1024],
                    'portrait'  => ['width' => 768, 'height' => 1408],
                    'landscape' => ['width' => 1173, 'height' => 640],
                ],
                'standard' => [
                    'square'    => ['width' => 768, 'height' => 768],
                    'portrait'  => ['width' => 684,  'height' => 704],
                    'landscape' => ['width' => 1024, 'height' => 592],
                ],
            ];
        }

        if (isset($dimensions[$quality][$aspectratio])) {
            $width  = $dimensions[$quality][$aspectratio]['width'];
            $height = $dimensions[$quality][$aspectratio]['height'];
        } else {
            throw new \coding_exception('Unknown aspect ratio or quality.');
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
            $imggenconfig->$setting = is_numeric($value) ? ($value + 0) : $value;
        }

        $requestobj->imageGenerationConfig = $imggenconfig;

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
        $requestobj->taskType = 'TEXT_IMAGE';
        $aspectratio = $this->action->get_configuration('aspectratio');
        $quality = $this->action->get_configuration('quality') == 'hd' ? 'premium' : 'standard';

        $dimensions = [
            'premium' => [
                'square' => ['width' => 1024, 'height' => 1024],
                'portrait' => ['width' => 640, 'height' => 1536],
                'landscape' => ['width' => 1536, 'height' => 640],
            ],
            'standard' => [
                'square' => ['width' => 1024, 'height' => 1024],
                'portrait' => ['width' => 832, 'height' => 1216],
                'landscape' => ['width' => 1216, 'height' => 832],
            ],
        ];

        if (isset($dimensions[$quality][$aspectratio])) {
            $width  = $dimensions[$quality][$aspectratio]['width'];
            $height = $dimensions[$quality][$aspectratio]['height'];
        } else {
            throw new \coding_exception('Unknown aspect ratio or quality.');
        }

        // Create the prompt object.
        $promptobj = new \stdClass();
        $promptobj->text = $this->action->get_configuration('prompttext');
        $requestobj->text_prompts = [$promptobj];
        $requestobj->width = $width;
        $requestobj->height = $height;

        // Add the model settings to the request object.
        foreach ($modelsettings as $setting => $value) {
            $requestobj->$setting = is_numeric($value) ? ($value + 0) : $value;
        }

        return $requestobj;
    }

    #[\Override]
    protected function create_request(): array {
        $requestobj = new \stdClass();
        $modelsettings = $this->get_model_settings();
        $model = $this->get_model();

        if (str_contains($model, 'amazon')) {
            $requestobj = $this->create_amazon_request($requestobj, $modelsettings);
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
        $model = $this->get_model();
        $response = [
            'success' => true,
            'fingerprint' => $responseheaders['x-amzn-requestid'],
            'prompttokens' => $responseheaders['x-amzn-bedrock-input-token-count'],
            'completiontokens' => $responseheaders['x-amzn-bedrock-output-token-count'],
            'revisedprompt' => $this->action->get_configuration('prompttext'), // No revised prompt in AWS Bedrock.
            'model' => $model,
        ];

        if (str_contains($model, 'amazon')) {
            $response['draftfile'] = $this->base64_to_file($bodyobj->images[0]);
        } else if (str_contains($model, 'stability')) {
            $response['draftfile'] = $this->base64_to_file($bodyobj->artifacts[0]->base64);
        } else {
            throw new \coding_exception('Unknown model class type.');
        }

        return $response;
    }

    /**
     * Convert the base64 for the image to a file.
     *
     * Placements can't interact with the provider AI directly,
     * therefore we need to provide the image file in a format that can
     * be used by placements. So we use the file API.
     *
     * @param string $base64 The base64 encoded image.
     * @return \stored_file The file object.
     */
    protected function base64_to_file(string $base64): \stored_file {
        global $CFG, $USER;
        require_once("{$CFG->libdir}/filelib.php");

        // Decode the base64 image into a binary format we can use.
        $binarydata = base64_decode($base64);

        // Construct a filename for the image, because we don't get one explicitly.
        $imageinfo = getimagesizefromstring($binarydata);
        $fileext = image_type_to_extension($imageinfo[2]);
        $filename = substr(hash('sha512', ($base64)), 0, 16) . $fileext;

        // Save the image to a temp location and add the watermark.
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tempdst, $binarydata);
        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        // We put the file in the user draft area initially.
        // Placements (on behalf of the user) can then move it to the correct location.
        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($USER->id)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        $fs = get_file_storage();
        return $fs->create_file_from_string($fileinfo, file_get_contents($tempdst));
    }
}
