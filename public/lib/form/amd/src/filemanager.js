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
 * File manager UI, reimplemented as a native ES module.
 *
 * This is a modern, framework-free rewrite of the legacy YUI2/YUI3 module
 * `lib/form/filemanager.js`. It renders the icon, tree and table views natively
 * (duplicating, not importing, `Y.Node.prototype.fp_display_filelist` from
 * `repository/filepicker.js`) and talks to `repository/draftfiles_ajax.php` with
 * `fetch()`.
 *
 * Accessibility improvements over the legacy module:
 *  1. The view-mode toggles (`.fp-vb-icons`/`.fp-vb-tree`/`.fp-vb-details`) now
 *     expose their active state programmatically via `aria-pressed`, not just a
 *     visual `checked` CSS class.
 *  2. The tree view uses native `<button aria-expanded>` disclosure controls
 *     (replacing the YUI TreeView widget) so expand/collapse is announced.
 *  3. Table-view selection checkboxes each get a visually-hidden associated
 *     `<label>` ("Select file '...'") wired via a generated id/for pair, giving
 *     every checkbox an accessible name.
 *  4. Every label in the file-info and make-folder dialogs is associated with its
 *     input/select through a real generated `id`/`for` pair rather than relying
 *     on placeholder text alone.
 *
 * @module     core_form/filemanager
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The options object, the AJAX response nodes and the interop contracts with the
// (still legacy, still YUI) filepicker.js and dndupload.js all use snake_case
// property names defined server-side (client_id, accepted_types, thumbnail_width,
// datemodified_f, filepicker_callback, ...). Renaming them would break that
// contract, so camelcase linting is disabled for this interop-heavy module.
/* eslint-disable camelcase */

import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Pending from 'core/pending';
import Notification from 'core/notification';
import {getString} from 'core/str';
import * as FormChangeChecker from 'core_form/changechecker';
import * as FormEvent from 'core_form/events';
import {setUserPreference} from 'core_user/repository';

/** @var {Number} Counter backing {@see uniqueId}. */
let uidCounter = 0;

/**
 * Generate a short, module-unique DOM id (crypto-free) for id/for associations.
 *
 * @method uniqueId
 * @param {String} prefix Prefix for the generated id.
 * @return {String} A unique id string.
 */
const uniqueId = (prefix) => `${prefix}${++uidCounter}`;

/**
 * Increment a file/folder name, e.g. "New folder" -> "New folder (1)".
 *
 * Faithful copy of the global `increment_filename()` in
 * `lib/javascript-static.js`; inlined here to avoid depending on a YUI-era
 * global from a modern ES module.
 *
 * @method incrementFilename
 * @param {String} filename The name to increment.
 * @param {Boolean} ignoreextension Do not split off an extension (for folders).
 * @return {String} The incremented name.
 */
const incrementFilename = (filename, ignoreextension) => {
    let extension = '';
    let basename = filename;

    if (!ignoreextension) {
        const dotpos = filename.lastIndexOf('.');
        if (dotpos !== -1) {
            basename = filename.substr(0, dotpos);
            extension = filename.substr(dotpos, filename.length);
        }
    }

    let number = 0;
    const hasnumber = basename.match(/^(.*) \((\d+)\)$/);
    if (hasnumber !== null) {
        number = parseInt(hasnumber[2], 10);
        basename = hasnumber[1];
    }

    number++;
    return `${basename} (${number})${extension}`;
};

/**
 * Escape a plain string for safe insertion as HTML text.
 *
 * @method escapeHtml
 * @param {String} text The raw text.
 * @return {String} HTML-escaped text.
 */
const escapeHtml = (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

/**
 * Initialise a single file manager instance.
 *
 * The full options payload (the `form_filemanager` PHP class's data, extended
 * with `templates` - the pre-rendered HTML template strings - and `strings` -
 * a flat map of pre-fetched language strings) is too large to pass as a
 * `js_call_amd()` argument (Moodle warns above ~1KB of encoded arguments -
 * see `page_requirements_manager::js_call_amd()`). Instead, `render_form_filemanager()`
 * embeds it as a JSON `<script>` tag alongside the file manager markup, and
 * `init()` is only ever passed the small `client_id` string needed to find it.
 *
 * @method init
 * @param {String} clientId The file manager instance id.
 */
export const init = (clientId) => {
    const root = document.getElementById('filemanager-' + clientId);
    if (!root) {
        return;
    }

    const initDataEl = document.getElementById('filemanager-' + clientId + '-initdata');
    const options = initDataEl ? JSON.parse(initDataEl.textContent) : {};

    const strings = options.strings || {};
    const templates = options.templates || {};

    // Mutable per-instance state (was `this.*` on the legacy helper).
    let currentpath = '/';
    let viewmode = 1;
    let filecount = options.filecount ? options.filecount : 0;
    let listOptions = options; // Latest `list` response: {list, path, tree, filecount, filepath}.
    let availablePaths = ['/']; // Folder paths for the "Path" select (was set_current_tree).
    let tableSortKey = 'displayname';
    let tableSortDesc = false;

    const maxfiles = options.maxfiles;
    const maxbytes = options.maxbytes;
    const areamaxbytes = options.areamaxbytes;
    const enablemainfile = options.mainfile;
    const userprefs = options.userprefs || {};
    const progressBars = {};

    // Build the filepicker options (shared object mutated at show time, like legacy).
    const filepickerOptions = Object.assign({}, options.filepicker || {});
    filepickerOptions.client_id = clientId;
    filepickerOptions.context = options.context;
    filepickerOptions.maxfiles = maxfiles;
    filepickerOptions.maxbytes = maxbytes;
    filepickerOptions.areamaxbytes = areamaxbytes;
    filepickerOptions.env = 'filemanager';
    filepickerOptions.itemid = options.itemid;

    // Resolve the drag-and-drop container id (mirrors the legacy dndcontainer logic).
    let dndcontainerId;
    if (root.classList.contains('filemanager-container') || !root.querySelector('.filemanager-container')) {
        dndcontainerId = root.id;
    } else {
        const dndcontainer = root.querySelector('.filemanager-container');
        if (!dndcontainer.id) {
            dndcontainer.id = uniqueId('fm-dndcontainer-');
        }
        dndcontainerId = dndcontainer.id;
    }

    // Remove the path-element template from the breadcrumb bar and keep it for cloning.
    const pathbar = root.querySelector('.fp-pathbar');
    let pathnodeTemplate = null;
    const pathfolder = root.querySelector('.fp-path-folder');
    if (pathfolder) {
        pathnodeTemplate = pathfolder.cloneNode(true);
        pathfolder.remove();
    }

    /**
     * Look up a pre-fetched language string, substituting `{$a}` / `{$a->x}`
     * placeholders client-side exactly as the legacy `M.util.get_string` did.
     *
     * @method getStr
     * @param {String} key String identifier.
     * @param {Object|String|Number} [param] Substitution value(s).
     * @return {String} The resolved string.
     */
    const getStr = (key, param) => {
        let str = strings[key] !== undefined ? strings[key] : '[[' + key + ']]';
        if (param !== undefined && param !== null) {
            if (typeof param === 'object') {
                Object.keys(param).forEach((prop) => {
                    str = str.replace(new RegExp('\\{\\$a->' + prop + '\\}', 'g'), () => param[prop]);
                });
            } else {
                str = str.replace(/\{\$a\}/g, () => param);
            }
        }
        return str;
    };

    /**
     * Whether this file manager is inside a disabled form item.
     *
     * @method isDisabled
     * @return {Boolean}
     */
    const isDisabled = () => root.parentElement ? !!root.parentElement.closest('.fitem.disabled') : false;

    /**
     * Clone a trusted, server-rendered HTML template string into a DOM element.
     *
     * Only ever called with the fixed `options.templates.*` strings; never with
     * file-supplied content.
     *
     * @method cloneTemplate
     * @param {String} html Template HTML.
     * @return {Element} The first element of the parsed template.
     */
    const cloneTemplate = (html) => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = (html || '').trim();
        return wrapper.firstElementChild;
    };

    /**
     * Show the "updating" spinner state.
     *
     * @method wait
     */
    const wait = () => root.classList.add('fm-updating');

    /**
     * Send an AJAX request to draftfiles_ajax.php.
     *
     * Reproduces the legacy request shape with `fetch()`. Errors (network,
     * invalid JSON, or `data.error`) are surfaced once via `Notification.exception`
     * and the returned promise rejects so callers do not act on bad data. Every
     * request is wrapped in a `core/pending` marker resolved in all paths.
     *
     * @method request
     * @param {String} action The draftfiles_ajax action.
     * @param {Object} [params] Extra POST parameters.
     * @param {Boolean} [redraw] Show the updating spinner while in flight.
     * @return {Promise<Object>} Resolves with the parsed response data.
     */
    const request = (action, params = {}, redraw = false) => {
        const pending = new Pending('core_form/filemanager:' + action);
        if (redraw) {
            wait();
        }
        const url = `${M.cfg.wwwroot}/repository/draftfiles_ajax.php?action=${encodeURIComponent(action)}`;
        const body = new URLSearchParams(Object.assign({
            sesskey: M.cfg.sesskey,
            client_id: clientId,
            filepath: currentpath || '/',
            itemid: options.itemid ? options.itemid : 0,
        }, params)).toString();

        return fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body,
        })
            .then((response) => response.text())
            .then((text) => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    // Mirror the legacy 'invalidjson' handling: surface the raw response text.
                    throw new Error(getStr('invalidjson') + ':\n' + text);
                }
                if (data.error) {
                    throw data;
                }
                if (data.tree) {
                    setCurrentTree(data.tree);
                }
                return data;
            })
            .catch((error) => {
                Notification.exception(error);
                throw error;
            })
            .finally(() => pending.resolve());
    };

    /**
     * Toggle the empty/max-files body classes based on the current file count.
     *
     * @method checkButtons
     */
    const checkButtons = () => {
        root.classList.toggle('fm-nofiles', !(filecount > 0));
        root.classList.toggle('fm-maxfiles', filecount >= maxfiles && maxfiles !== -1);
    };

    /**
     * Reload the file list for a path and re-render.
     *
     * @method refresh
     * @param {String} filepath Path to list.
     * @param {Object} [action] Optional post-render focus hint {action, newfilename}.
     * @return {Promise<Object>} Resolves with the list response.
     */
    const refresh = (filepath, action) => {
        currentpath = filepath || currentpath;
        return request('list', {filepath: currentpath}, true).then((data) => {
            filecount = data.filecount;
            listOptions = data;
            checkButtons();
            render(action);
            return data;
        }).catch(() => {
            // Error already surfaced by request(); callers fire-and-forget this.
        });
    };

    /**
     * Rebuild the breadcrumb path bar from the current list response.
     *
     * @method printPath
     */
    const printPath = () => {
        if (!pathbar || !pathnodeTemplate) {
            return;
        }
        pathbar.textContent = '';
        pathbar.classList.add('empty');
        const p = listOptions.path;
        if (p && p.length !== 0 && viewmode !== 2) {
            p.forEach((segment, i) => {
                const el = pathnodeTemplate.cloneNode(true);
                pathbar.appendChild(el);
                if (i === 0) {
                    el.classList.add('first');
                }
                if (i === p.length - 1) {
                    el.classList.add('last');
                }
                el.classList.add(i % 2 ? 'even' : 'odd');
                const link = el.querySelector('.fp-path-folder-name');
                if (link) {
                    link.textContent = segment.name;
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (!isDisabled()) {
                            refresh(segment.path);
                        }
                    });
                }
            });
            pathbar.classList.remove('empty');
        }
    };

    /**
     * Build the CSS class list for a file/folder node (was classnamecallback).
     *
     * @method classesForNode
     * @param {Object} node File node.
     * @return {String[]} Class names.
     */
    const classesForNode = (node) => {
        const classes = [];
        if (node.type === 'folder' || (!node.type && !node.filename)) {
            classes.push('fp-folder');
        }
        if (node.filename || node.filepath || (node.path && node.path !== '/')) {
            classes.push('fp-hascontextmenu');
        }
        if (node.isref) {
            classes.push('fp-isreference');
        }
        if (node.refcount) {
            classes.push('fp-hasreferences');
        }
        if (node.originalmissing) {
            classes.push('fp-originalmissing');
        }
        if (node.sortorder === 1) {
            classes.push('fp-mainfile');
        }
        return classes;
    };

    /**
     * Whether a file/folder node is a folder.
     *
     * @method fileIsFolder
     * @param {Object} node File node.
     * @return {Boolean} Whether the node is a folder.
     */
    const fileIsFolder = (node) => !!node.children || node.type === 'folder';

    /**
     * The node's raw file name.
     *
     * @method fileName
     * @param {Object} node File node.
     * @return {String} The node's file name.
     */
    const fileName = (node) => node.title ? node.title : node.fullname;

    /**
     * The node's display name.
     *
     * @method fileDisplayName
     * @param {Object} node File node.
     * @return {String} The node's display name.
     */
    const fileDisplayName = (node) => node.shorttitle ? node.shorttitle : fileName(node);

    /**
     * The node's description/tooltip text.
     *
     * @method fileDescription
     * @param {Object} node File node.
     * @return {String} The node's description/tooltip.
     */
    const fileDescription = (node) => node.description || node.thumbnail_title || fileName(node);

    /**
     * Value for a table cell, preferring the server-formatted variants.
     *
     * @method cellValue
     * @param {Object} node File node.
     * @param {String} key Column key.
     * @return {String}
     */
    const cellValue = (node, key) => {
        if (key === 'displayname') {
            return fileDisplayName(node);
        }
        if (node[key + '_f_s']) {
            return node[key + '_f_s'];
        }
        if (node[key + '_f']) {
            return node[key + '_f'];
        }
        if (node[key]) {
            return node[key];
        }
        return '';
    };

    /**
     * Shared click handler for a file/folder row across all three views.
     *
     * @method selectionCallback
     * @param {Event} e The originating event.
     * @param {Object} node File node.
     */
    const selectionCallback = (e, node) => {
        // Clicking the small context-menu icon always opens the file-info/actions
        // dialog, even for folders (reimplements the legacy rightclickcallback,
        // which fp_display_filelist routed .fp-contextmenu clicks to instead of
        // the plain folder-navigate/file-select callback).
        if (e.target && e.target.closest('.fp-contextmenu')) {
            selectFile(node);
            return;
        }
        if (fileIsFolder(node)) {
            if (!isDisabled()) {
                refresh(node.filepath);
            }
        } else {
            selectFile(node);
        }
    };

    /**
     * Render the icon (grid) view.
     *
     * @method renderIconView
     * @param {Element} content The `.fp-content` container.
     * @param {Object[]} list Files to render.
     */
    const renderIconView = (content, list) => {
        const view = document.createElement('div');
        view.className = 'fp-iconview';
        content.appendChild(view);

        list.forEach((node) => {
            const element = cloneTemplate(templates.iconfilename);
            classesForNode(node).forEach((c) => element.classList.add(c));

            const filenamediv = element.querySelector('.fp-filename');
            if (filenamediv) {
                filenamediv.textContent = fileDisplayName(node);
            }

            let width;
            let height;
            let src;
            if (node.thumbnail) {
                width = node.thumbnail_width || 90;
                height = node.thumbnail_height || 90;
                src = node.realthumbnail || node.thumbnail;
            } else {
                width = 16;
                height = 16;
                src = node.realicon || node.icon;
            }
            if (filenamediv) {
                filenamediv.style.width = width + 'px';
            }
            const imgdiv = element.querySelector('.fp-thumbnail');
            if (imgdiv) {
                imgdiv.style.width = width + 'px';
                imgdiv.style.height = height + 'px';
                const img = document.createElement('img');
                img.title = fileDescription(node);
                // SC 1.1.1 (Non-text Content, A) / image-redundant-alt: the filename is
                // already shown as real text next to this icon (see .fp-filename below),
                // so a non-empty alt here would have every screen reader announce it
                // twice. Match the table/tree view icon renderers, which already use ''.
                img.alt = '';
                img.style.maxWidth = width + 'px';
                img.style.maxHeight = height + 'px';
                img.src = src;
                imgdiv.appendChild(img);
            }

            element.addEventListener('click', (e) => {
                e.preventDefault();
                selectionCallback(e, node);
            });
            view.appendChild(element);
        });
    };

    /**
     * Build a single tree `<li>` for a node (folder or leaf).
     *
     * @method buildTreeNode
     * @param {Object} node File node.
     * @return {Element} The list item.
     */
    const buildTreeNode = (node) => {
        const li = document.createElement('li');
        const row = cloneTemplate(templates.listfilename);
        classesForNode(node).forEach((c) => row.classList.add(c));

        const filenamespan = row.querySelector('.fp-filename');
        if (filenamespan) {
            filenamespan.textContent = fileDisplayName(node);
        }
        const iconspan = row.querySelector('.fp-icon');
        if (iconspan && node.icon) {
            const img = document.createElement('img');
            img.src = node.realicon || node.icon;
            img.alt = '';
            iconspan.appendChild(img);
        }
        const rowlink = row.querySelector('a');

        if (fileIsFolder(node)) {
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'fp-tree-toggle btn btn-icon btn-sm';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', fileDisplayName(node));

            const childrenUl = document.createElement('ul');
            childrenUl.hidden = true;

            li.appendChild(toggle);
            li.appendChild(row);
            li.appendChild(childrenUl);

            const doToggle = () => toggleTreeFolder(node, toggle, childrenUl);
            toggle.addEventListener('click', doToggle);
            if (rowlink) {
                rowlink.addEventListener('click', (e) => {
                    e.preventDefault();
                    doToggle();
                });
            }
        } else {
            li.appendChild(row);
            if (rowlink) {
                rowlink.addEventListener('click', (e) => {
                    e.preventDefault();
                    selectionCallback(e, node);
                });
            }
        }
        return li;
    };

    /**
     * Expand/collapse a tree folder, lazily loading (and caching) its children.
     *
     * Mirrors the legacy `treeview_dynload`: children are fetched once on first
     * expand and cached so re-expanding does not re-fetch.
     *
     * @method toggleTreeFolder
     * @param {Object} node Folder node.
     * @param {Element} toggle The disclosure button.
     * @param {Element} childrenUl The children container.
     */
    const toggleTreeFolder = (node, toggle, childrenUl) => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        if (expanded) {
            toggle.setAttribute('aria-expanded', 'false');
            childrenUl.hidden = true;
            return;
        }
        toggle.setAttribute('aria-expanded', 'true');
        childrenUl.hidden = false;
        currentpath = node.filepath || node.path || '/';
        if (childrenUl.dataset.loaded) {
            return;
        }
        request('list', {filepath: node.filepath || ''}).then((data) => {
            childrenUl.dataset.loaded = '1';
            (data.list || []).forEach((child) => childrenUl.appendChild(buildTreeNode(child)));
            return data;
        }).catch(() => {
            // Allow a retry on the next expand if the request failed.
            toggle.setAttribute('aria-expanded', 'false');
            childrenUl.hidden = true;
        });
    };

    /**
     * Render the tree view natively (no widget library).
     *
     * @method renderTreeView
     * @param {Element} content The `.fp-content` container.
     * @param {Object[]} list Files at the current level.
     */
    const renderTreeView = (content, list) => {
        const view = document.createElement('div');
        view.className = 'fp-treeview';
        content.appendChild(view);
        const ul = document.createElement('ul');
        view.appendChild(ul);
        list.forEach((node) => ul.appendChild(buildTreeNode(node)));
    };

    /**
     * Render the table (details) view natively (no DataTable widget).
     *
     * @method renderTableView
     * @param {Element} content The `.fp-content` container.
     * @param {Object[]} list Files to render.
     */
    const renderTableView = (content, list) => {
        const view = document.createElement('div');
        view.className = 'fp-tableview';
        const parentId = uniqueId('fp-tableview-');
        view.id = parentId;
        content.appendChild(view);

        const table = document.createElement('table');
        table.className = 'table table-sm';
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');

        // Visually-hidden select-all checkbox column.
        const selectTh = document.createElement('th');
        selectTh.scope = 'col';
        const togglerId = uniqueId('fm-selectall-');
        const toggler = document.createElement('input');
        toggler.type = 'checkbox';
        toggler.id = togglerId;
        toggler.setAttribute('data-action', 'toggle');
        toggler.setAttribute('data-toggle', 'toggler');
        toggler.setAttribute('data-togglegroup', 'file-selections-' + parentId);
        const togglerLabel = document.createElement('label');
        togglerLabel.className = 'visually-hidden';
        togglerLabel.setAttribute('for', togglerId);
        togglerLabel.textContent = getStr('selectallornone');
        selectTh.appendChild(togglerLabel);
        selectTh.appendChild(toggler);
        headRow.appendChild(selectTh);

        const columns = [
            {key: 'displayname', label: getStr('name')},
            {key: 'datemodified', label: getStr('lastmodified')},
            {key: 'size', label: getStr('size')},
            {key: 'mimetype', label: getStr('type')},
        ];
        columns.forEach((col) => {
            const th = document.createElement('th');
            th.scope = 'col';
            if (tableSortKey === col.key) {
                th.setAttribute('aria-sort', tableSortDesc ? 'descending' : 'ascending');
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'fp-sort-btn btn btn-link p-0';
            btn.textContent = col.label;
            btn.addEventListener('click', () => {
                if (tableSortKey === col.key) {
                    tableSortDesc = !tableSortDesc;
                } else {
                    tableSortKey = col.key;
                    tableSortDesc = false;
                }
                content.textContent = '';
                renderTableView(content, list);
            });
            th.appendChild(btn);
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        // Folders always sort first, then by the active column (in-memory sort).
        const sorted = list.slice().sort((a, b) => {
            const af = fileIsFolder(a);
            const bf = fileIsFolder(b);
            if (af && !bf) {
                return -1;
            }
            if (!af && bf) {
                return 1;
            }
            const av = ('' + cellValue(a, tableSortKey)).toLowerCase();
            const bv = ('' + cellValue(b, tableSortKey)).toLowerCase();
            const dir = tableSortDesc ? -1 : 1;
            if (av > bv) {
                return dir;
            }
            if (av < bv) {
                return -dir;
            }
            return 0;
        });

        const tbody = document.createElement('tbody');
        sorted.forEach((node) => {
            const tr = document.createElement('tr');

            const tdCheck = document.createElement('td');
            const cbId = uniqueId('fm-selectfile-');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = cbId;
            checkbox.setAttribute('data-fieldtype', 'checkbox');
            checkbox.setAttribute('data-fullname', node.fullname);
            checkbox.setAttribute('data-action', 'toggle');
            checkbox.setAttribute('data-toggle', 'target');
            checkbox.setAttribute('data-togglegroup', 'file-selections-' + parentId);
            const cbLabel = document.createElement('label');
            cbLabel.className = 'visually-hidden';
            cbLabel.setAttribute('for', cbId);
            // Accessible name for the selection checkbox. Prefer a lang string if the
            // PHP step provides one; otherwise fall back to the legacy English literal.
            cbLabel.textContent = strings.selectfile
                ? getStr('selectfile', node.fullname)
                : `Select file '${node.fullname}'`;
            tdCheck.appendChild(checkbox);
            tdCheck.appendChild(cbLabel);
            tr.appendChild(tdCheck);

            const tdName = document.createElement('td');
            const nameContent = cloneTemplate(templates.listfilename);
            classesForNode(node).forEach((c) => nameContent.classList.add(c));
            const namespan = nameContent.querySelector('.fp-filename');
            if (namespan) {
                namespan.textContent = fileDisplayName(node);
            }
            const iconspan = nameContent.querySelector('.fp-icon');
            if (iconspan && node.icon) {
                const img = document.createElement('img');
                img.src = node.realicon || node.icon;
                img.alt = '';
                iconspan.appendChild(img);
            }
            tdName.appendChild(nameContent);
            tr.appendChild(tdName);

            ['datemodified', 'size', 'mimetype'].forEach((key) => {
                const td = document.createElement('td');
                td.textContent = cellValue(node, key);
                tr.appendChild(td);
            });

            tr.addEventListener('click', (e) => {
                // Clicks on the checkbox cell must not open the file dialog.
                if (e.target.closest('td') === tdCheck) {
                    return;
                }
                e.preventDefault();
                selectionCallback(e, node);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        view.appendChild(table);
    };

    /**
     * Render the file list into `.fp-content` for the current view mode.
     *
     * @method viewFiles
     * @param {Object} [action] Optional focus hint after render.
     */
    const viewFiles = (action) => {
        root.classList.remove('fm-updating', 'fm-noitems');
        const list = listOptions.list || [];

        // Notify about any files with a problem status (was in fp_display_filelist).
        list.forEach((file) => {
            if (!fileIsFolder(file) && file.status && file.status !== 0) {
                getString('storedfilecannotreadfile', 'error', file.fullname)
                    .then((str) => Notification.addNotification({message: str, type: 'error'}))
                    .catch(Notification.exception);
            }
        });

        if (list.length === 0 && viewmode !== 2) {
            root.classList.add('fm-noitems');
            if (action && action.action === 'delete') {
                const addbtn = root.querySelector('.fp-btn-add a');
                if (addbtn) {
                    addbtn.focus();
                }
            }
            return;
        }

        // The bulk-delete button only makes sense in the table (checkbox) view.
        const deletebtn = root.querySelector('.fp-btn-delete');
        if (deletebtn) {
            deletebtn.classList.toggle('d-none', viewmode === 1 || viewmode === 2);
        }

        const content = root.querySelector('.fp-content');
        content.textContent = '';
        if (viewmode === 2) {
            renderTreeView(content, list);
        } else if (viewmode === 3) {
            renderTableView(content, list);
        } else {
            renderIconView(content, list);
        }

        // Restore focus to a sensible target after certain actions.
        if (action) {
            if (action.action === 'updatefile' || action.action === 'setmainfile') {
                content.querySelectorAll('.fp-filename').forEach((fn) => {
                    if (fn.textContent === action.newfilename) {
                        const link = fn.closest('a');
                        if (link) {
                            link.focus();
                        }
                    }
                });
            } else if (action.action === 'delete') {
                const delLink = root.querySelector('.fp-btn-delete a');
                if (delLink) {
                    delLink.focus();
                }
            }
        }
    };

    /**
     * Redraw the breadcrumb and file list.
     *
     * @method render
     * @param {Object} [action] Optional focus hint.
     */
    const render = (action) => {
        printPath();
        viewFiles(action);
    };

    /**
     * Return the currently-selected files (table view checkboxes).
     *
     * @method getSelectedFiles
     * @return {Object[]} Array of {filepath, filename}.
     */
    const getSelectedFiles = () => {
        const marked = root.querySelectorAll('[data-togglegroup][data-toggle="target"]:checked');
        const filenames = [];
        marked.forEach((item) => {
            const fileinfo = (listOptions.list || []).find((el) => item.dataset.fullname === el.fullname);
            if (fileinfo) {
                filenames.push({filepath: fileinfo.filepath, filename: fileinfo.filename});
            }
        });
        return filenames;
    };

    /**
     * Build the list of folder paths for the "Path" select (was set_current_tree).
     *
     * @method setCurrentTree
     * @param {Object} tree The directory tree from a list response.
     */
    const setCurrentTree = (tree) => {
        const list = ['/'];
        const walk = (node) => {
            if (!node || !node.children || !node.children.length) {
                return;
            }
            node.children.forEach((child) => {
                list.push(child.filepath);
                walk(child);
            });
        };
        walk(tree);
        availablePaths = list;
    };

    /**
     * Display an inline error/info notification (replaces the legacy msg dialog).
     *
     * The `core/notification` error/info templates render the message with
     * `{{{message}}}` (raw HTML), so the pre-escaped/plain strings used here render
     * correctly and the `invalidfiletype` case can pass its accepted-types HTML
     * list through unchanged, matching the legacy behaviour.
     *
     * @method printMsg
     * @param {String} message The message (may contain trusted HTML).
     * @param {String} type 'error' or 'info'.
     */
    const printMsg = (message, type) => {
        Notification.addNotification({message: message, type: type === 'error' ? 'error' : 'info'});
    };

    /**
     * Show a save/cancel confirmation dialog (reimplements show_confirm_dialog).
     *
     * @method showConfirmDialog
     * @param {Object} config Dialog config.
     * @param {String|Promise} config.message The message body.
     * @param {Function} config.onConfirm Called when the user confirms.
     * @param {String} [config.titleKey] Title string key (default 'confirm').
     * @param {String} [config.titleComponent] Title component (default 'moodle').
     */
    const showConfirmDialog = ({message, onConfirm, titleKey, titleComponent}) => {
        // Deliberately NOT wrapped in a core/pending marker: the promise only
        // settles once the user clicks a button, and Behat cannot interact with
        // the dialog to do that until it observes the page as JS-idle. Wrapping an
        // indefinite human-interaction wait in Pending deadlocks acceptance tests.
        const tKey = titleKey || 'confirm';
        const tComponent = titleComponent || 'moodle';
        const buttonKey = titleKey ? 'rename' : 'yes';
        Notification.saveCancelPromise(
            getString(tKey, tComponent),
            message,
            getString(buttonKey, 'moodle'),
        ).then(() => {
            onConfirm();
            return;
        }).catch(() => {
            // User cancelled.
        });
    };

    /**
     * Trigger a browser download without navigating away (hidden iframe), as legacy.
     *
     * @method downloadViaIframe
     * @param {String} url File URL.
     */
    const downloadViaIframe = (url) => {
        const iframe = document.createElement('iframe');
        iframe.style.visibility = 'hidden';
        iframe.style.width = '1px';
        iframe.style.height = '1px';
        iframe.src = url;
        document.body.appendChild(iframe);
    };

    /**
     * Read a user preference from the pre-loaded prefs (was get_preference).
     *
     * @method getPreference
     * @param {String} name Preference name.
     * @return {String|Boolean}
     */
    const getPreference = (name) => userprefs[name] ? userprefs[name] : false;

    /**
     * Persist a user preference (was set_preference).
     *
     * @method setPreference
     * @param {String} name Preference name.
     * @param {String|Number} value Preference value.
     */
    const setPreference = (name, value) => {
        if (userprefs[name] !== value) {
            setUserPreference('filemanager_' + name, value);
            userprefs[name] = value;
        }
    };

    /**
     * Whether a folder with the given name exists in the current listing.
     *
     * @method hasFolder
     * @param {String} foldername Folder name.
     * @return {Boolean}
     */
    const hasFolder = (foldername) =>
        (listOptions.list || []).some((el) => el.type === 'folder' && el.fullname === foldername);

    /**
     * Compute the parent folder path of a node (was get_parent_folder_name).
     *
     * @method getParentFolderName
     * @param {Object} node File node.
     * @return {String}
     */
    const getParentFolderName = (node) => {
        if (node.type !== 'folder' || node.filepath.length < node.fullname.length + 1) {
            return node.filepath;
        }
        const basedir = node.filepath.substr(0, node.filepath.length - node.fullname.length - 1);
        const lastdir = node.filepath.substr(node.filepath.length - node.fullname.length - 2);
        if (lastdir === '/' + node.fullname + '/') {
            return basedir;
        }
        return node.filepath;
    };

    /**
     * Validate a file extension against accepted types (verbatim from legacy).
     *
     * @method isValidFileType
     * @param {String} fileExtension Extension including dot (e.g. ".jpg").
     * @param {String} acceptedTypes Space-separated accepted extension names or "".
     * @return {Boolean} Whether the file type is allowed.
     */
    const isValidFileType = (fileExtension, acceptedTypes) => {
        if (!acceptedTypes) {
            return true;
        }
        const ext = fileExtension.toLowerCase().startsWith('.')
            ? fileExtension.toLowerCase()
            : `.${fileExtension.toLowerCase()}`;
        const accepted = acceptedTypes
            .toLowerCase()
            .split(/\s+/)
            .filter(Boolean);
        return accepted.includes(ext);
    };

    /**
     * Populate a license `<select>` (reimplements populateLicensesSelect).
     *
     * @method populateLicensesSelect
     * @param {Element} selectEl The select element.
     * @param {Object} node The file node (or undefined for a fresh selection).
     */
    const populateLicensesSelect = (selectEl, node) => {
        if (!selectEl) {
            return;
        }
        selectEl.textContent = '';
        let selectedlicense = filepickerOptions.defaultlicense;
        if (node) {
            selectedlicense = node.license;
        } else if (filepickerOptions.rememberuserlicensepref && getPreference('recentlicense')) {
            selectedlicense = getPreference('recentlicense');
        }
        const licenses = filepickerOptions.licenses || [];
        Object.keys(licenses).forEach((i) => {
            const license = licenses[i];
            // Include the file's current license even if disabled, to avoid misleading info.
            if (license.enabled === true || (node !== undefined && node !== null && license.shortname === node.license)) {
                const option = document.createElement('option');
                option.selected = (license.shortname === selectedlicense);
                option.value = license.shortname;
                option.textContent = license.fullname;
                selectEl.appendChild(option);
            }
        });
    };

    /**
     * Check for an extension change or a reference-count warning before a
     * file rename/move is applied, showing a validation message or a
     * confirmation dialog when one is needed.
     *
     * Split out of updateFile() to keep its cyclomatic complexity down; the
     * onConfirm callbacks re-enter updateFile(..., true) once the user
     * responds.
     *
     * @method checkRenameWarnings
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     * @param {Modal} modal The file-info modal.
     * @param {String} newfilename The new file name.
     * @return {Boolean} True if a message or confirmation dialog was shown
     *     and the caller (updateFile) should stop for now.
     */
    const checkRenameWarnings = (body, node, modal, newfilename) => {
        let warnings = '';
        const originalArr = node.fullname.split('.');
        const originalExtension = originalArr.length > 1 ? originalArr.pop() : '';
        const newArr = newfilename.split('.');
        const newExtension = newArr.length > 1 ? newArr.pop() : '';
        const filetypesDesc = root.parentNode
            ? root.parentNode.querySelector('.form-filetypes-descriptions') : null;
        const acceptedTypes = filetypesDesc
            ? filetypesDesc.getAttribute('data-all-allowed-extensions') : '';

        if (newExtension !== originalExtension) {
            if (newExtension === '') {
                warnings += '<li>' + getStr('originalextensionremove', escapeHtml(originalExtension)) + '</li>';
            } else if (!isValidFileType(`.${newExtension}`, acceptedTypes)) {
                const stringVars = {
                    fileextension: escapeHtml(newExtension),
                    acceptedfiletypes: filetypesDesc ? filetypesDesc.innerHTML : '',
                };
                printMsg(getStr('updateinvalidfiletype', stringVars), 'error');
                // Revert the filename input to the original value.
                body.querySelector('.fp-saveas input').value = node.fullname;
                return true;
            } else {
                const stringVars = {
                    originalextension: escapeHtml(originalExtension),
                    newextension: escapeHtml(newExtension),
                };
                showConfirmDialog({
                    message: getStr('originalextensionchange', stringVars),
                    titleKey: 'updatefileextensiontitle',
                    titleComponent: 'repository',
                    onConfirm: () => updateFile(body, node, modal, true),
                });
                return true;
            }
        }
        if (node.refcount) {
            warnings += '<li>' + getStr('aliaseschange', node.refcount) + '</li>';
        }
        if (warnings.length > 0) {
            const confirmmsg = getStr('confirmrenamefile', node.refcount);
            const message = '<p>' + confirmmsg + '</p><ul class="px-5">' + warnings + '</ul>';
            showConfirmDialog({message: message, onConfirm: () => updateFile(body, node, modal, true)});
            return true;
        }
        return false;
    };

    /**
     * Determine the update action/params for a folder rename/move,
     * prompting for confirmation first if needed.
     *
     * @method resolveFolderUpdate
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     * @param {Modal} modal The file-info modal.
     * @param {Boolean} confirmed Whether a confirmation has been accepted.
     * @param {String} newfilename The new folder name.
     * @param {String} targetpath The new folder path.
     * @param {Boolean} changed Whether the name or path changed.
     * @return {Object|String|undefined} {action, params} to proceed with,
     *     'handled' if the caller should just return (a message or
     *     confirmation dialog was already shown), or undefined if nothing
     *     changed and the caller should fall through to its own cleanup.
     */
    const resolveFolderUpdate = (body, node, modal, confirmed, newfilename, targetpath, changed) => {
        if (!newfilename) {
            printMsg(getStr('entername'), 'error');
            return 'handled';
        }
        if (!changed) {
            return undefined;
        }
        if (!confirmed) {
            showConfirmDialog({
                message: getStr('confirmrenamefolder'),
                onConfirm: () => updateFile(body, node, modal, true),
            });
            modal.hide();
            return 'handled';
        }
        return {
            action: 'updatedir',
            params: {filepath: node.filepath, newdirname: newfilename, newfilepath: targetpath},
        };
    };

    /**
     * Determine the update action/params for a file rename/move/license/
     * author change, prompting for confirmation first if needed.
     *
     * @method resolveFileUpdate
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     * @param {Modal} modal The file-info modal.
     * @param {Boolean} confirmed Whether a confirmation has been accepted.
     * @param {String} newfilename The new file name.
     * @param {String} targetpath The new file path.
     * @param {Boolean} changed Whether the name or path changed.
     * @param {Boolean} licensechanged Whether the license changed.
     * @param {Boolean} authorchanged Whether the author changed.
     * @param {String} newlicense The new license.
     * @param {String} newauthor The new author.
     * @return {Object|String|undefined} Same shape as resolveFolderUpdate().
     */
    const resolveFileUpdate = (
        body, node, modal, confirmed, newfilename, targetpath, changed, licensechanged, authorchanged, newlicense, newauthor
    ) => {
        if (!newfilename) {
            printMsg(getStr('enternewname'), 'error');
            return 'handled';
        }
        if (changed && !confirmed && checkRenameWarnings(body, node, modal, newfilename)) {
            return 'handled';
        }
        if (!(changed || licensechanged || authorchanged)) {
            return undefined;
        }
        return {
            action: 'updatefile',
            params: {
                filepath: node.filepath,
                filename: node.fullname,
                newfilename: newfilename,
                newfilepath: targetpath,
                newlicense: newlicense,
                newauthor: newauthor,
            },
        };
    };

    /**
     * Apply a rename/move/license/author change to a file or folder.
     *
     * Reimplements the legacy `update_file()`, including extension-change and
     * reference-count confirmations and invalid-type warnings.
     *
     * @method updateFile
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     * @param {Modal} modal The file-info modal.
     * @param {Boolean} [confirmed] Whether a confirmation has been accepted.
     */
    const updateFile = (body, node, modal, confirmed) => {
        const selectRoot = body.querySelector('.fp-select') || body;
        const newfilename = (body.querySelector('.fp-saveas input').value || '').trim();
        const filenamechanged = (newfilename && newfilename !== node.fullname);
        const pathSelect = body.querySelector('.fp-path select');
        const targetpath = pathSelect.options[pathSelect.selectedIndex]
            ? pathSelect.options[pathSelect.selectedIndex].value : '';
        const filepathchanged = (targetpath !== getParentFolderName(node));
        const newauthor = (body.querySelector('.fp-author input').value || '').trim();
        const authorchanged = (newauthor !== (node.author || '').trim());
        const licenseSelect = body.querySelector('.fp-license select');
        const newlicense = licenseSelect.options[licenseSelect.selectedIndex]
            ? licenseSelect.options[licenseSelect.selectedIndex].value : '';
        const licensechanged = (newlicense !== node.license);
        const changed = filenamechanged || filepathchanged;

        const result = node.type === 'folder'
            ? resolveFolderUpdate(body, node, modal, confirmed, newfilename, targetpath, changed)
            : resolveFileUpdate(
                body, node, modal, confirmed, newfilename, targetpath, changed, licensechanged, authorchanged, newlicense, newauthor
            );

        if (result === 'handled') {
            return;
        }
        if (!result) {
            modal.hide();
            return;
        }

        selectRoot.classList.add('loading');
        request(result.action, result.params).then((data) => {
            modal.hide();
            refresh((data && data.filepath) ? data.filepath : '/', {action: result.action, newfilename: newfilename});
            FormChangeChecker.markFormChangedFromNode(root);
            return data;
        }).catch(() => selectRoot.classList.remove('loading'));
    };

    /**
     * Wire up the buttons inside the file-info modal (reimplements setup_select_file).
     *
     * @method setupSelectButtons
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     * @param {Modal} modal The modal.
     */
    const setupSelectButtons = (body, node, modal) => {
        const q = (sel) => body.querySelector(sel);
        const selectRoot = q('.fp-select') || body;

        const updateBtn = q('.fp-file-update');
        if (updateBtn) {
            updateBtn.addEventListener('click', (e) => {
                e.preventDefault();
                updateFile(body, node, modal);
            });
        }
        // Enter in any form input triggers an update (was the 'enter' key binding).
        body.querySelectorAll('form input').forEach((input) => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    updateFile(body, node, modal);
                }
            });
        });

        const downloadBtn = q('.fp-file-download');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (node.type !== 'folder') {
                    downloadViaIframe(node.url);
                }
            });
        }

        const deleteBtn = q('.fp-file-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const params = {filepath: node.filepath};
                let message;
                if (node.type === 'folder') {
                    params.filename = '.';
                    message = getStr('confirmdeletefolder');
                } else {
                    params.filename = node.fullname;
                    message = node.refcount
                        ? getStr('confirmdeletefilewithhref', node.refcount)
                        : getStr('confirmdeletefile');
                }
                modal.hide();
                showConfirmDialog({
                    message: message,
                    onConfirm: () => {
                        request('delete', params).then((data) => {
                            filecount--;
                            refresh(data.filepath, {action: 'delete'});
                            FormChangeChecker.markFormChangedFromNode(root);
                            FormEvent.notifyUploadChanged(root.id);
                            return data;
                        }).catch(() => {
                            // Error already surfaced by request().
                        });
                    },
                });
            });
        }

        const zipBtn = q('.fp-file-zip');
        if (zipBtn) {
            zipBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (node.type !== 'folder') {
                    return;
                }
                selectRoot.classList.add('loading');
                request('zip', {filepath: node.filepath, filename: '.'}).then((data) => {
                    modal.hide();
                    refresh(data.filepath);
                    return data;
                }).catch(() => selectRoot.classList.remove('loading'));
            });
        }

        const unzipBtn = q('.fp-file-unzip');
        if (unzipBtn) {
            unzipBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (node.type !== 'zip') {
                    return;
                }
                selectRoot.classList.add('loading');
                request('unzip', {
                    filepath: node.filepath,
                    filename: node.fullname,
                    // FILE_AREA_MAX_BYTES_UNLIMITED is -1.
                    areamaxbytes: areamaxbytes ? areamaxbytes : -1,
                }).then((data) => {
                    modal.hide();
                    refresh(data.filepath);
                    return data;
                }).catch(() => selectRoot.classList.remove('loading'));
            });
        }

        const setmainBtn = q('.fp-file-setmain');
        if (setmainBtn) {
            setmainBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (!enablemainfile || node.type === 'folder') {
                    return;
                }
                selectRoot.classList.add('loading');
                request('setmainfile', {filepath: node.filepath, filename: node.fullname}).then((data) => {
                    modal.hide();
                    refresh(node.filepath, {action: 'setmainfile', newfilename: node.fullname});
                    return data;
                }).catch(() => selectRoot.classList.remove('loading'));
            });
        }

        body.querySelectorAll('.fp-file-cancel').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                modal.hide();
            });
        });
    };

    // Lazily-created, reused file-info modal (mirrors the legacy this.selectui,
    // which was created once and shown/hidden repeatedly). Creating a *new* Modal
    // instance on every selection left the previous instance behind in the DOM
    // (core/modal's removeOnClose does not reliably remove it - confirmed via a
    // Behat faildump showing two stacked '.fp-select' modals, one stale-hidden and
    // one fresh-visible), and Behat's xpath-based lookups take the *first* DOM
    // match, so they kept finding the stale hidden copy. The body content is
    // replaced via setBody() on every open instead, which gives fresh DOM nodes
    // for the action buttons each time (so re-wiring their listeners below never
    // accumulates duplicate handlers), while the modal/backdrop shell itself is
    // reused rather than duplicated.
    //
    // The promise itself (not just its resolved value) is the singleton guard:
    // it is assigned synchronously before the first await, so a second call
    // arriving while creation is still in flight sees the promise already set
    // and awaits that same promise instead of starting a second Modal.create().
    let selectModalPromise = null;

    /**
     * Set the select-file dialog's type-specific classes and bind each
     * field's label to its control with a real id/for pair.
     *
     * @method prepareSelectFileLayout
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     */
    const prepareSelectFileLayout = (body, node) => {
        const selectRoot = body.querySelector('.fp-select') || body;
        ['fp-folder', 'fp-file', 'fp-zip', 'fp-cansetmain', 'loading'].forEach((c) => selectRoot.classList.remove(c));
        if (node.type === 'folder' || node.type === 'zip') {
            selectRoot.classList.add('fp-' + node.type);
        } else {
            selectRoot.classList.add('fp-file');
        }
        if (enablemainfile && node.sortorder !== 1 && node.type === 'file') {
            selectRoot.classList.add('fp-cansetmain');
        }

        // Accessibility: bind each field label to its control with a real id/for pair.
        body.querySelectorAll('.fp-saveas, .fp-path, .fp-author, .fp-license').forEach((group) => {
            const label = group.querySelector('label');
            const field = group.querySelector('input, select');
            if (label && field) {
                if (!field.id) {
                    field.id = uniqueId('fm-field-');
                }
                label.setAttribute('for', field.id);
            }
        });
    };

    /**
     * Populate the select-file dialog's editable fields (name, author,
     * license, target path) for the given node.
     *
     * @method populateSelectFileFields
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     */
    const populateSelectFileFields = (body, node) => {
        body.querySelector('.fp-saveas input').value = node.fullname;
        const authorInput = body.querySelector('.fp-author input');
        authorInput.value = node.author ? node.author : '';
        const licenseSelect = body.querySelector('.fp-license select');
        populateLicensesSelect(licenseSelect, node);

        const pathSelect = body.querySelector('.fp-path select');
        pathSelect.textContent = '';
        const parentFolder = getParentFolderName(node);
        availablePaths.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = p;
            if (p === parentFolder) {
                opt.selected = true;
            }
            pathSelect.appendChild(opt);
        });

        const disable = node.type === 'folder';
        authorInput.disabled = disable;
        if (licenseSelect) {
            licenseSelect.disabled = disable;
        }
    };

    /**
     * Populate the select-file dialog's read-only static information
     * (dates, size, dimensions, thumbnail, reference count).
     *
     * @method populateSelectFileStaticInfo
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     */
    const populateSelectFileStaticInfo = (body, node) => {
        // Static file information (use server-formatted `_f` variants when present).
        ['datemodified', 'datecreated', 'size', 'dimensions', 'original', 'reflist'].forEach((attr) => {
            const el = body.querySelector('.fp-' + attr);
            if (!el) {
                return;
            }
            const raw = node[attr + '_f'] || node[attr] || '';
            el.classList.toggle('fp-unknown', ('' + raw) === '');
            const valueEl = el.querySelector('.fp-value');
            if (valueEl) {
                if (attr === 'reflist') {
                    // The reflist attribute is a server-built list of <li> items (HTML).
                    valueEl.innerHTML = raw;
                } else {
                    valueEl.textContent = raw;
                }
            }
        });

        const thumb = body.querySelector('.fp-thumbnail');
        if (thumb) {
            thumb.textContent = '';
            const img = document.createElement('img');
            img.src = node.realthumbnail ? node.realthumbnail : node.thumbnail;
            img.style.maxHeight = (node.thumbnail_height ? node.thumbnail_height : 90) + 'px';
            img.style.maxWidth = (node.thumbnail_width ? node.thumbnail_width : 90) + 'px';
            // SC 1.1.1 (Non-text Content, A): this thumbnail is decorative - the
            // filename is already given as real text via the Name field above.
            img.alt = '';
            thumb.appendChild(img);
        }

        const refcountEl = body.querySelector('.fp-refcount');
        if (refcountEl) {
            refcountEl.textContent = node.refcount ? getStr('referencesexist', node.refcount) : '';
        }
    };

    /**
     * Lazily fetch and render the original location of a reference file.
     *
     * @method fetchSelectFileOriginal
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     */
    const fetchSelectFileOriginal = (body, node) => {
        if (!node.isref || node.original) {
            return;
        }
        const originalEl = body.querySelector('.fp-original');
        if (originalEl) {
            originalEl.classList.remove('fp-unknown');
            originalEl.classList.add('fp-loading');
        }
        request('getoriginal', {filepath: node.filepath, filename: node.fullname}).then((data) => {
            if (originalEl) {
                originalEl.classList.remove('fp-loading');
                const v = originalEl.querySelector('.fp-value');
                if (data.original) {
                    node.original = data.original;
                    if (v) {
                        v.textContent = data.original;
                    }
                } else if (v) {
                    v.textContent = getStr('unknownsource');
                }
            }
            return data;
        }).catch(() => {
            // Error already surfaced by request().
        });
    };

    /**
     * Lazily fetch and render the list of references to a file.
     *
     * @method fetchSelectFileReferences
     * @param {Element} body The modal body element.
     * @param {Object} node The file node.
     */
    const fetchSelectFileReferences = (body, node) => {
        if (!node.refcount || node.reflist) {
            return;
        }
        const reflistEl = body.querySelector('.fp-reflist');
        if (reflistEl) {
            reflistEl.classList.remove('fp-unknown');
            reflistEl.classList.add('fp-loading');
        }
        request('getreferences', {filepath: node.filepath, filename: node.fullname}).then((data) => {
            if (reflistEl) {
                reflistEl.classList.remove('fp-loading');
                const v = reflistEl.querySelector('.fp-value');
                if (v) {
                    if (data.references) {
                        v.innerHTML = data.references.map((ref) => '<li>' + escapeHtml(ref) + '</li>').join('');
                    } else {
                        v.textContent = '';
                    }
                }
            }
            return data;
        }).catch(() => {
            // Error already surfaced by request().
        });
    };

    /**
     * Enhance the select-file dialog's help icons with popovers. Loaded
     * dynamically (mirroring the legacy require) so this core module keeps
     * only a soft, runtime dependency on the theme-provided Bootstrap popover.
     *
     * @method enhanceSelectFilePopovers
     * @param {Element} body The modal body element.
     */
    const enhanceSelectFilePopovers = (body) => {
        import('theme_boost/bootstrap/popover').then(({'default': Popover}) => {
            body.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => new Popover(el));
            return;
        }).catch(() => {
            // Popovers are a progressive enhancement; ignore if unavailable.
        });
    };

    /**
     * Open the file-info/edit dialog for a file (reimplements select_file()).
     *
     * @method selectFile
     * @param {Object} node The file node.
     */
    const selectFile = async(node) => {
        if (isDisabled()) {
            return;
        }
        const pending = new Pending('core_form/filemanager:selectfile');
        if (!selectModalPromise) {
            selectModalPromise = Modal.create({large: true});
        }
        const modal = await selectModalPromise;
        modal.setTitle(getStr('edita', node.fullname));
        modal.setBody(templates.fileselectlayout);
        // The core/modal API returns a jQuery object; de-jQuery immediately.
        const body = modal.getBody()[0];

        prepareSelectFileLayout(body, node);
        populateSelectFileFields(body, node);
        populateSelectFileStaticInfo(body, node);
        fetchSelectFileOriginal(body, node);
        fetchSelectFileReferences(body, node);
        enhanceSelectFilePopovers(body);

        setupSelectButtons(body, node, modal);
        modal.show();
        pending.resolve();
    };

    // Lazily-created, reused mkdir modal (mirrors the legacy this.mkdir_dialog,
    // which was created once and shown/hidden repeatedly). Creating a *new*
    // Modal instance on every click left the previous instance's backdrop
    // element behind (core/modal's removeOnClose does not appear to guarantee
    // synchronous backdrop cleanup), which made a freshly-opened second dialog's
    // input unreachable to WebDriver ("not interactable") in Behat.
    //
    // As with selectModalPromise above, the promise is the singleton guard: it
    // is assigned before the first await so a second call arriving mid-creation
    // awaits the same promise instead of creating a second modal.
    let mkdirModalPromise = null;
    let mkdirModalWired = false;

    /**
     * Open the "make a folder" dialog (reimplements the mkdir branch of setup_buttons).
     *
     * @method openMkdirDialog
     */
    const openMkdirDialog = async() => {
        if (isDisabled()) {
            return;
        }
        const pending = new Pending('core_form/filemanager:mkdir');

        if (!mkdirModalPromise) {
            mkdirModalPromise = Modal.create({body: templates.mkdir});
        }
        const mkdirModal = await mkdirModalPromise;

        if (!mkdirModalWired) {
            const dialog = mkdirModal.getBody()[0];
            const input = dialog.querySelector('input');
            const label = dialog.querySelector('label');
            const createBtn = dialog.querySelector('.fp-dlg-butcreate');
            const cancelBtns = dialog.querySelectorAll('.fp-dlg-butcancel');

            // Accessibility: associate the label with the input via a generated id.
            const inputId = uniqueId('fm-newname-');
            input.id = inputId;
            if (label) {
                label.setAttribute('for', inputId);
            }

            const validate = () => {
                const valid = input.value.length > 0;
                if (createBtn) {
                    createBtn.disabled = !valid;
                }
                return valid;
            };
            const performAction = () => {
                const foldername = input.value;
                if (!foldername) {
                    mkdirModal.hide();
                    return;
                }
                request('mkdir', {filepath: currentpath, newdirname: foldername}).then((data) => {
                    mkdirModal.hide();
                    refresh(data.filepath);
                    FormChangeChecker.markFormChangedFromNode(root);
                    return data;
                }).catch(() => {
                    // Error already surfaced by request().
                });
            };

            if (createBtn) {
                createBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    performAction();
                });
            }
            input.addEventListener('keydown', (e) => {
                if (validate() && e.key === 'Enter') {
                    e.preventDefault();
                    performAction();
                }
            });
            input.addEventListener('keyup', validate);
            input.addEventListener('change', validate);
            cancelBtns.forEach((btn) => btn.addEventListener('click', (e) => {
                e.preventDefault();
                mkdirModal.hide();
            }));

            // Mark the modal as wired so a later re-open never re-attaches listeners.
            mkdirModalWired = true;
        }

        const dialog = mkdirModal.getBody()[0];
        const input = dialog.querySelector('input');
        const createBtn = dialog.querySelector('.fp-dlg-butcreate');
        const curpaths = dialog.querySelectorAll('.fp-dlg-curpath');

        // Default (auto-incrementing) folder name.
        let foldername = getStr('newfolder');
        while (hasFolder(foldername)) {
            foldername = incrementFilename(foldername, true);
        }
        input.value = foldername;
        if (input.value.length > 0 && createBtn) {
            createBtn.disabled = false;
        }
        curpaths.forEach((el) => {
            el.textContent = currentpath;
        });

        mkdirModal.show();
        // One-shot: avoids stacking a new persistent listener on the reused modal
        // every time this dialog is opened.
        mkdirModal.getRoot().one(ModalEvents.shown, () => {
            input.focus();
            input.select();
        });
        pending.resolve();
    };

    /**
     * Refresh callback invoked after a successful filepicker upload
     * (reimplements filepicker_callback).
     *
     * @method filepickerCallback
     */
    const filepickerCallback = () => {
        filecount++;
        checkButtons();
        refresh(currentpath);
        FormChangeChecker.markFormChangedFromNode(root);
        FormEvent.notifyUploadChanged(root.id);
    };

    /**
     * Launch the (still YUI) file picker on demand via a fresh YUI sandbox.
     *
     * @method showFilePicker
     * @param {Event} [e] The triggering event.
     */
    const showFilePicker = (e) => {
        if (e) {
            e.preventDefault();
        }
        if (isDisabled()) {
            return;
        }
        const fpOptions = filepickerOptions;
        fpOptions.formcallback = filepickerCallback;
        // Magic scope: the filepicker invokes formcallback bound to this object.
        fpOptions.magicscope = manager;
        fpOptions.savepath = currentpath;
        fpOptions.previousActiveElement = e && e.target ? e.target.closest('a') : null;
        // Use the 'core_filepicker' *friendly* module name (registered globally on
        // every page by page_requirements_manager, fullpath repository/filepicker.js),
        // not the internal 'moodle-core_filepicker' YUI.add() name that file registers
        // itself under once loaded. The friendly module's `requires` list is what pulls
        // in 'moodle-core-notification-dialogue' (needed for M.core.dialogue, used
        // internally by filepicker.js's own panels) and the other YUI pieces filepicker.js
        // needs, so requesting it here is what makes this interop bridge reliable
        // regardless of whether anything else on the page already loaded the filepicker.
        // YUI is the legacy global YUI loader factory function, not a constructor.
        // eslint-disable-next-line no-undef, @babel/new-cap
        YUI().use('core_filepicker', (Y) => {
            M.core_filepicker.show(Y, fpOptions);
        });
    };

    /**
     * Reflect the active view mode on the toggle buttons, including aria-pressed.
     *
     * @method updateViewModeButtons
     */
    const updateViewModeButtons = () => {
        const selectors = {'1': '.fp-vb-icons', '2': '.fp-vb-tree', '3': '.fp-vb-details'};
        root.querySelectorAll('.fp-vb-icons, .fp-vb-tree, .fp-vb-details').forEach((btn) => {
            btn.classList.remove('checked');
            btn.setAttribute('aria-pressed', 'false');
        });
        const active = root.querySelector(selectors[viewmode]);
        if (active) {
            active.classList.add('checked');
            active.setAttribute('aria-pressed', 'true');
        }
    };

    /**
     * Wire up the toolbar buttons and view-mode toggles.
     *
     * @method setupButtons
     */
    const setupButtons = () => {
        const addBtn = root.querySelector('.fp-btn-add');
        if (addBtn) {
            addBtn.addEventListener('click', showFilePicker);
        }
        const dndarrow = root.querySelector('.dndupload-arrow');
        if (dndarrow) {
            dndarrow.addEventListener('click', showFilePicker);
        }

        const createBtn = root.querySelector('.fp-btn-mkdir');
        if (options.subdirs) {
            if (createBtn) {
                createBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    openMkdirDialog();
                });
            }
        } else {
            root.classList.add('fm-nomkdir');
        }

        const downloadBtn = root.querySelector('.fp-btn-download');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (isDisabled()) {
                    return;
                }
                const spinner = root.querySelector('.fp-img-downloading');
                if (spinner && spinner.style.display === 'inline') {
                    return;
                }
                if (spinner) {
                    spinner.style.display = 'inline';
                }
                const filenames = getSelectedFiles();
                request('downloadselected', {selected: JSON.stringify(filenames)}).then((data) => {
                    if (spinner) {
                        spinner.style.display = 'none';
                    }
                    if (data) {
                        refresh(data.filepath);
                        downloadViaIframe(data.fileurl);
                    } else {
                        printMsg(getStr('draftareanofiles'), 'error');
                    }
                    return data;
                }).catch(() => {
                    if (spinner) {
                        spinner.style.display = 'none';
                    }
                });
            });
        }

        const deleteBtn = root.querySelector('.fp-btn-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const filenames = getSelectedFiles();
                if (!filenames.length) {
                    printMsg(getStr('nofilesselected'), 'error');
                    return;
                }
                showConfirmDialog({
                    message: getStr('confirmdeleteselectedfile', filenames.length),
                    onConfirm: () => {
                        request('deleteselected', {selected: JSON.stringify(filenames)}).then((data) => {
                            filecount -= filenames.length;
                            if (data && data.length) {
                                refresh(data[0], {action: 'delete'});
                            }
                            FormChangeChecker.markFormChangedFromNode(root);
                            FormEvent.notifyUploadChanged(root.id);
                            return data;
                        }).catch(() => {
                            // Error already surfaced by request().
                        });
                    },
                });
            });
        }

        root.querySelectorAll('.fp-vb-icons, .fp-vb-tree, .fp-vb-details').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const viewbar = root.querySelector('.fp-viewbar');
                if (isDisabled() || (viewbar && viewbar.classList.contains('disabled'))) {
                    return;
                }
                if (btn.classList.contains('fp-vb-tree')) {
                    viewmode = 2;
                } else if (btn.classList.contains('fp-vb-details')) {
                    viewmode = 3;
                } else {
                    viewmode = 1;
                }
                updateViewModeButtons();
                render();
                const content = root.querySelector('.fp-content');
                if (content) {
                    content.setAttribute('tabindex', '0');
                    content.focus();
                }
                setPreference('recentviewmode', viewmode);
            });
        });
    };

    // --- Progress bar (MDL-70533), ported verbatim in behaviour with native DOM. ---

    /**
     * Show the upload-in-progress state.
     *
     * @method showProgress
     */
    const showProgress = () => root.classList.add('fpupload-inprogress');

    /**
     * Hide the upload-in-progress state (only once all bars are cleared).
     *
     * @method hideProgress
     */
    const hideProgress = () => {
        if (!Object.keys(progressBars).length) {
            root.classList.remove('fpupload-inprogress');
        }
    };

    /**
     * Remove all progress bars.
     *
     * @method clearProgress
     */
    const clearProgress = () => {
        Object.keys(progressBars).forEach((fileName) => {
            progressBars[fileName].progressOuter.remove();
            delete progressBars[fileName];
        });
    };

    /**
     * Begin showing progress for a file.
     *
     * @method startProgress
     * @param {String} fileName Name of the uploading file.
     */
    const startProgress = (fileName) => {
        if (progressBars[fileName] !== undefined) {
            return;
        }
        let displayFileName = fileName;
        if (displayFileName.length > 50) {
            displayFileName = `${displayFileName.slice(0, 49)}…`;
        }
        const progressOuter = document.createElement('div');
        progressOuter.textContent = displayFileName;
        const progress = document.createElement('div');
        progress.className = 'progress';
        const progressInner = document.createElement('div');
        progressInner.className = 'progress-bar';
        progressInner.setAttribute('role', 'progressbar');
        progressInner.setAttribute('aria-valuenow', '0');
        progressInner.setAttribute('aria-valuemin', '0');
        progressInner.setAttribute('aria-valuemax', '100');
        const progressInnerText = document.createElement('span');
        progressInnerText.className = 'visually-hidden';
        progressInner.appendChild(progressInnerText);
        progress.appendChild(progressInner);
        progressOuter.appendChild(progress);

        const progressContainer = root.querySelector('.fpupload-progressbars');
        if (progressContainer) {
            progressContainer.textContent = '';
            progressContainer.appendChild(progressOuter);
        }

        progressBars[fileName] = {
            progressOuter: progressOuter,
            progressInner: progressInner,
            progressInnerText: progressInnerText,
        };
    };

    /**
     * Update the progress display for a file.
     *
     * @method updateProgress
     * @param {String} fileName Name of the uploading file.
     * @param {Number} percent Completion percentage.
     */
    const updateProgress = (fileName, percent) => {
        startProgress(fileName);
        progressBars[fileName].progressInner.style.width = `${percent}%`;
        progressBars[fileName].progressInner.setAttribute('aria-valuenow', percent);
        progressBars[fileName].progressInnerText.textContent = `${percent}% ${getStr('complete')}`;
    };

    /**
     * Start the upload process.
     *
     * @method startUpload
     */
    const startUpload = () => {
        clearProgress();
        showProgress();
        FormEvent.notifyUploadStarted(root.id);
        const addbtn = root.querySelector('.fp-btn-add a');
        if (addbtn) {
            addbtn.style.display = 'none';
        }
    };

    /**
     * Finish the upload process.
     *
     * @method uploadFinished
     */
    const uploadFinished = () => {
        clearProgress();
        hideProgress();
        FormEvent.notifyUploadCompleted(root.id);
        const addbtn = root.querySelector('.fp-btn-add a');
        if (addbtn) {
            addbtn.style.display = '';
        }
    };

    // Object handed to the legacy (untouched) dndupload.js as its `filemanager`
    // option. dndupload.js is still YUI and reads a YUI-Node-like helper: it calls
    // `.filepicker_callback()`, and reads `.filecount`, `.options.list`,
    // `.currentpath`, and `.filemanager.get('id')`. Getters keep the first four in
    // sync with live state; `.filemanager` is a minimal shim exposing `.get('id')`.
    // The progress/refresh methods are exposed for parity with the legacy helper.
    const manager = {
        get filecount() {
            return filecount;
        },
        get currentpath() {
            return currentpath;
        },
        get options() {
            return listOptions;
        },
        // Required verbatim by dndupload.js update_filemanager().
        filepicker_callback: filepickerCallback,
        filemanager: {
            get: (attr) => (attr === 'id' ? root.id : root.getAttribute(attr)),
        },
        refresh: refresh,
        startUpload: startUpload,
        uploadFinished: uploadFinished,
        startProgress: startProgress,
        updateProgress: updateProgress,
        showProgress: showProgress,
        hideProgress: hideProgress,
        clearProgress: clearProgress,
    };

    // --- Init sequence: reveal the UI, restore view mode, wire up, draw. ---
    root.classList.remove('fm-loading');
    root.classList.add('fm-loaded');

    viewmode = parseInt(getPreference('recentviewmode'), 10);
    if (viewmode !== 2 && viewmode !== 3) {
        viewmode = 1;
    }
    updateViewModeButtons();
    setupButtons();
    refresh(currentpath); // MDL-31113: always get the latest list from the server.

    // Bridge to the legacy drag-and-drop upload helper. Shape matches the legacy
    // bottom-of-file dndoptions block exactly.
    const dndoptions = {
        filemanager: manager,
        acceptedtypes: (options.filepicker || {}).accepted_types,
        clientid: clientId,
        author: options.author,
        maxfiles: maxfiles,
        maxbytes: maxbytes,
        areamaxbytes: areamaxbytes,
        itemid: options.itemid,
        repositories: filepickerOptions.repositories,
        containerid: dndcontainerId,
        contextid: options.context ? options.context.id : 0,
    };
    // 'core_dndupload' (fullpath lib/form/dndupload.js) is only registered with the
    // page's YUI loader when something explicitly requires it - previously that was
    // the legacy $module['requires'] array passed to js_init_call() for this same
    // filemanager. Since PHP now loads this module via js_call_amd() instead, the PHP
    // loader change for this rewrite must explicitly register 'core_dndupload' with
    // $PAGE->requires->js_module(...) (see files/renderer.php) so this resolves here.
    // 'core_filepicker' pulls in 'moodle-core-notification-dialogue' for M.core.dialogue,
    // used internally by both filepicker.js and dndupload.js's own panels.
    // YUI is the legacy global YUI loader factory function, not a constructor.
    // eslint-disable-next-line no-undef, @babel/new-cap
    YUI().use('core_filepicker', 'core_dndupload', (Y) => {
        M.form_dndupload.init(Y, dndoptions);
    });
};
