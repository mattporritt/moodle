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
 * Comment component - ESM rewrite of the legacy YUI2 comment module.
 *
 * Replaces public/comment/comment.js (YUI2) with a modern ESM module.
 * Uses core/fragment for server-side rendered comment list loading,
 * core/ajax for add/delete web service calls, and vanilla JS DOM manipulation.
 *
 * Accessibility: includes CSS @keyframes highlight animation, native placeholder
 * attribute, and underline indicator on username links in highlighted comments,
 * satisfying WCAG 2.2 SC 1.4.3 and SC 1.4.1 requirements from MDL-88958.
 *
 * @module     core_comment/comment
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from 'core/fragment';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {deleteComment} from 'core_comment/repository';

/**
 * Initialise a comment area.
 *
 * @param {Object} options
 * @param {string} options.client_id     Unique client identifier for this comment area.
 * @param {string} options.commentarea   Comment area name.
 * @param {number} options.itemid        Item ID the comments are attached to.
 * @param {number} options.page          Initial page (0-based).
 * @param {number} options.courseid      Course ID.
 * @param {number} options.contextid     Context ID.
 * @param {string} options.contextlevel  Context level short name (e.g. 'module', 'course').
 * @param {number} options.instanceid    Context instance ID.
 * @param {string} options.component     Component name.
 * @param {boolean} options.notoggle     True to hide the toggle link.
 * @param {boolean} options.autostart    True to expand comments immediately on load.
 */
export const init = (options) => {
    const cid = options.client_id;

    const ctrl = document.getElementById(`comment-ctrl-${cid}`);
    if (!ctrl) {
        // Fail gracefully: element not present (e.g. embedded view with discarded blocks).
        return;
    }

    const toggleLink = document.getElementById(`comment-link-${cid}`);
    const container = document.getElementById(`comment-list-container-${cid}`);
    const textarea = document.getElementById(`dlg-content-${cid}`);
    const postBtn = document.getElementById(`comment-action-post-${cid}`);
    const cancelBtn = document.getElementById(`comment-action-cancel-${cid}`);

    /**
     * Load the comment list fragment for the given page and inject it into the container.
     *
     * @param {number} page 0-based page number.
     * @returns {Promise}
     */
    const loadFragment = (page) => {
        const pending = new Pending('core_comment/comment:load');
        return Fragment.loadFragment('core_comment', 'comment_list', options.contextid, {
            contextid: options.contextid,
            component: options.component,
            itemid: options.itemid,
            area: options.commentarea,
            courseid: options.courseid,
            page: page,
            // eslint-disable-next-line camelcase
            client_id: cid,
        }).then((html, js) => {
            if (container) {
                // Replace the entire container contents with the fragment.
                container.innerHTML = html;
                if (js) {
                    Templates.runTemplateJS(js);
                }
                // Re-register pagination and delete button handlers on the new DOM.
                registerPagination();
                registerDeleteButtons();
            }
            return html;
        }).catch(Notification.exception)
        .then(() => {
            pending.resolve();
            return;
        });
    };

    /**
     * Register click handlers on all pagination links within this comment area.
     */
    const registerPagination = () => {
        if (!container) {
            return;
        }
        const paginationLinks = container.querySelectorAll(`[id^="comment-page-${cid}-"]`);
        paginationLinks.forEach((link) => {
            // Avoid attaching duplicate listeners by cloning the node.
            const fresh = link.cloneNode(true);
            link.parentNode.replaceChild(fresh, link);
            const idMatch = fresh.id.match(new RegExp(`^comment-page-${cid}-(\\d+)$`));
            if (idMatch) {
                const targetPage = parseInt(idMatch[1], 10);
                fresh.addEventListener('click', (e) => {
                    e.preventDefault();
                    loadFragment(targetPage);
                });
            }
        });
    };

    /**
     * Register click handlers on all delete buttons within this comment area.
     */
    const registerDeleteButtons = () => {
        if (!container) {
            return;
        }
        const deleteLinks = container.querySelectorAll(`[id^="comment-delete-${cid}-"]`);
        deleteLinks.forEach((link) => {
            const fresh = link.cloneNode(true);
            link.parentNode.replaceChild(fresh, link);
            const idMatch = fresh.id.match(new RegExp(`^comment-delete-${cid}-(\\d+)$`));
            if (idMatch) {
                const commentId = parseInt(idMatch[1], 10);
                fresh.addEventListener('click', (e) => {
                    e.preventDefault();
                    doDelete(commentId);
                });
                fresh.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        doDelete(commentId);
                    }
                });
            }
        });
    };

    /**
     * Delete a comment by ID, removing only its DOM node.
     *
     * Deliberately avoids a full loadFragment() reload: reloading always fetches page 0,
     * which would incorrectly reset the visible page after deleting a comment from a
     * paginated (non-zero) page, and would discard any comments appended locally by
     * postComment() that are not present on the server's page-0 fragment.
     *
     * @param {number} commentId Comment ID to delete.
     */
    const doDelete = (commentId) => {
        const pending = new Pending('core_comment/comment:delete');
        deleteComment(commentId)
            .then(() => {
                if (container) {
                    const item = container.querySelector(`#comment-${commentId}-${cid}`);
                    if (item) {
                        item.remove();
                    }
                }
                pending.resolve();
                return;
            })
            .catch((err) => {
                pending.resolve();
                Notification.exception(err);
            });
    };

    /**
     * Post a new comment using the core_comment_add_comments web service, then append
     * just the newly created comment to the existing list.
     *
     * Deliberately avoids a full loadFragment() reload after posting: reloading always
     * fetches page 0, which would drop any older comments already visible beyond a single
     * page from the DOM (regressing the pre-existing append-as-you-go behaviour of the
     * legacy YUI2 module). Appending only the new item preserves all previously visible
     * comments, matching legacy behaviour, while explicit pagination navigation still goes
     * through loadFragment() as normal.
     */
    const postComment = () => {
        if (!textarea) {
            return;
        }
        const content = textarea.value.trim();
        if (!content) {
            return;
        }

        const pending = new Pending('core_comment/comment:post');
        textarea.disabled = true;

        Ajax.call([{
            methodname: 'core_comment_add_comments',
            args: {
                comments: [{
                    contextlevel: options.contextlevel,
                    instanceid: options.instanceid,
                    component: options.component,
                    content: content,
                    itemid: options.itemid,
                    area: options.commentarea,
                }],
            },
        }])[0]
        .then(() => {
            textarea.value = '';
            textarea.disabled = false;
            return appendNewestComment();
        })
        .then(() => {
            pending.resolve();
            return;
        })
        .catch((err) => {
            textarea.disabled = false;
            pending.resolve();
            Notification.exception(err);
        });
    };

    /**
     * Fetch the page-0 fragment and append only the newest comment item from it onto the
     * end of the currently displayed list, then apply the highlight animation to it.
     *
     * @returns {Promise}
     */
    const appendNewestComment = () => {
        return Fragment.loadFragment('core_comment', 'comment_list', options.contextid, {
            contextid: options.contextid,
            component: options.component,
            itemid: options.itemid,
            area: options.commentarea,
            courseid: options.courseid,
            page: 0,
            // eslint-disable-next-line camelcase
            client_id: cid,
        }).then((html, js) => {
            if (!container) {
                return null;
            }
            const existingList = container.querySelector(`#comment-list-${cid}`);
            if (!existingList) {
                // Comment area had no rendered list yet (e.g. first comment ever posted).
                container.innerHTML = html;
                if (js) {
                    Templates.runTemplateJS(js);
                }
                registerPagination();
                registerDeleteButtons();
                const items = container.querySelectorAll('.comment-list > li:not(.first)');
                return items.length > 0 ? items[items.length - 1] : null;
            }

            const temp = document.createElement('div');
            temp.innerHTML = html;
            const freshList = temp.querySelector(`#comment-list-${cid}`);
            const newest = freshList ? freshList.lastElementChild : null;
            if (newest && newest.tagName === 'LI') {
                existingList.appendChild(newest);
                registerDeleteButtons();
                return newest;
            }
            return null;
        }).then((newest) => {
            highlightComment(newest);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Apply the CSS highlight animation to the given comment <li> element.
     * The animation is defined in theme/boost/scss/moodle/core.scss using MDS tokens.
     *
     * @param {HTMLElement|null} item The comment list item to highlight.
     */
    const highlightComment = (item) => {
        if (!item) {
            return;
        }
        item.classList.remove('comment-highlighted');
        requestAnimationFrame(() => {
            item.classList.add('comment-highlighted');
        });
    };

    /**
     * Show the comment control area and load comments if not yet loaded.
     *
     * @param {number} page Page to load (0-based).
     */
    const show = (page) => {
        ctrl.style.display = 'block';
        if (toggleLink) {
            toggleLink.setAttribute('aria-expanded', 'true');
        }
        if (!options.autostart) {
            loadFragment(page);
        } else {
            registerDeleteButtons();
            registerPagination();
        }
    };

    /**
     * Hide the comment control area.
     */
    const hide = () => {
        ctrl.style.display = 'none';
        if (toggleLink) {
            toggleLink.setAttribute('aria-expanded', 'false');
        }
        if (textarea) {
            textarea.value = '';
        }
    };

    // Wire up toggle link.
    if (toggleLink) {
        if (options.notoggle) {
            toggleLink.style.display = 'none';
        }
        toggleLink.addEventListener('click', (e) => {
            e.preventDefault();
            const isVisible = ctrl.style.display !== 'none' && ctrl.style.display !== '';
            if (isVisible) {
                hide();
            } else {
                show(0);
            }
        });
        toggleLink.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleLink.click();
            }
        });
    }

    // Wire up post button.
    if (postBtn) {
        postBtn.addEventListener('click', (e) => {
            e.preventDefault();
            postComment();
        });
    }

    // Wire up cancel button.
    if (cancelBtn) {
        cancelBtn.addEventListener('click', (e) => {
            e.preventDefault();
            hide();
        });
    }

    // Auto-expand if configured.
    if (options.autostart) {
        show(options.page || 0);
    }
};
