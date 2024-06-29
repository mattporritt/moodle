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

/**
 * AI Modal for Tiny.
 *
 * @module      tiny_aiplacement/imagemodal
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import MediaImage from 'tiny_media/image';

const AiMediaImage = class extends MediaImage {
    constructor(editor, url) {
        super(editor); // Call the parent class constructor
        this.generatedImageUrl = url;
    }

    getSelectedImage() {
        const imgElement = document.createElement('img');

        // Set attributes for the img element
        imgElement.src = this.generatedImageUrl; // Replace with your image URL
        imgElement.alt = 'This will be filled out from the prompt';
        imgElement.width = 500;
        imgElement.height = 500;
        imgElement.role = 'presentation';

        return imgElement;
    }
};

export default AiMediaImage;
