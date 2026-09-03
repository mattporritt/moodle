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
 * Dashboard web-service repository.
 *
 * @module     core_my/repository
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import type {LayoutItem} from './layout';

export interface DashboardBlock {
    id: number;
    name: string;
    title: string;
    content: string;
    footer: string;
    region: string;
    weight: number;
}

export interface AvailableBlock {
    name: string;
    title: string;
}

export interface DashboardLabels {
    addblock: string;
    addblocktop: string;
    addblockbottom: string;
    cancel: string;
    close: string;
    confirm: string;
    confirmremove: string;
    confirmreset: string;
    done: string;
    down: string;
    emptycell: string;
    left: string;
    loading: string;
    gridcell: string;
    move: string;
    movecontrols: string;
    moveinstructions: string;
    remove: string;
    removeheading: string;
    reset: string;
    resetheading: string;
    resize: string;
    resizecontrols: string;
    resizeinstructions: string;
    right: string;
    tile: string;
    up: string;
}

export interface DashboardData {
    blocks: DashboardBlock[];
    layout: LayoutItem[];
    availableblocks: AvailableBlock[];
    canedit: boolean;
    editing: boolean;
    sitedefault: boolean;
    javascript: string;
    labels: DashboardLabels;
}

interface UpdateResult {
    status: boolean;
    blockid: number;
}

export const getDashboard = (siteDefault: boolean): Promise<DashboardData> => fetchOne<DashboardData>({
    methodname: 'core_my_get_dashboard',
    args: {sitedefault: siteDefault},
});

export const updateDashboard = (
    action: 'save' | 'add' | 'remove' | 'reset',
    siteDefault: boolean,
    layout: LayoutItem[] = [],
    blockname = '',
    blockid = 0,
): Promise<UpdateResult> => fetchOne<UpdateResult>({
    methodname: 'core_my_update_dashboard',
    args: {
        action,
        sitedefault: siteDefault,
        layout,
        blockname,
        blockid,
    },
});
