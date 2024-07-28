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

/**
 * Commands helper for the Moodle tiny_aiplacement plugin.
 *
 * @module      tiny_aiplacement/commands
 * @copyright   2024 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getButtonImage} from 'editor_tiny/utils';
import {get_string as getString} from 'core/str';
import {
    component,
    placement,
    contextMenuName,
    generateImageName,
    generateTextName,
    contextMenuIcon,
    generateImageIcon,
    generateTextIcon
} from './common';
import GenerateImage from './generateimage';
import GenerateText from './generatetext';

/**
 * Get the setup function for the buttons.
 *
 * This is performed in an async function which ultimately returns the registration function as the
 * Tiny.AddOnManager.Add() function does not support async functions.
 *
 * @returns {function} The registration function to call within the Plugin.add function.
 */
export const getSetup = async() => {
    const [
        contextMenuIconText,
        generateImageIconText,
        generateTextIconText,
        contextMenuIconImage,
        generateImageIconImage,
        generateTextIconImage,
    ] = await Promise.all([
        getString('generatecontent', placement),
        getString('generateimage', placement),
        getString('generatetext', placement),
        getButtonImage(contextMenuIcon, component),
        getButtonImage(generateImageIcon, component),
        getButtonImage(generateTextIcon, component),
    ]);

    return (editor) => {
        // Register the icon SVG files as an icon suitable for use in TinyMCE toolbars and buttons.
        editor.ui.registry.addIcon(contextMenuIcon, contextMenuIconImage.html);
        editor.ui.registry.addIcon(generateImageIcon, generateImageIconImage.html);
        editor.ui.registry.addIcon(generateTextIcon, generateTextIconImage.html);

        // Add the context menu button.
        // This context menu displays the available AI placement options.
        editor.ui.registry.addMenuButton(contextMenuName, {
            icon: contextMenuIcon,
            tooltip: contextMenuIconText,
            fetch: callback => callback(`${generateImageName} ${generateTextName}`),
        });

        // Add the menu items for the context menu.
        editor.ui.registry.addMenuItem(generateImageName, {
            icon: generateImageIcon,
            text: generateImageIconText,
            onAction: () => {
                const generateImage = new GenerateImage(editor);
                generateImage.displayContentModal(editor);
            },
        });

        editor.ui.registry.addMenuItem(generateTextName, {
            icon: generateTextIcon,
            text: generateTextIconText,
            onAction: () => {
                const generateText = new GenerateText(editor);
                generateText.displayContentModal(editor);
            },
        });

    };
};
