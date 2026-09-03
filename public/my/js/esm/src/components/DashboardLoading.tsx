// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Full-viewport dashboard loading placeholder.
 *
 * @module     core_my/components/DashboardLoading
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';

interface DashboardLoadingProps {
    label: string;
}

const DashboardLoading = ({label}: DashboardLoadingProps) => <div
    className="core-my-dashboard-loading"
    role="status"
    aria-label={label}
    aria-busy="true"
>
    <span className="visually-hidden">{label}</span>
    <div className="core-my-dashboard-loading__grid" aria-hidden="true">
        {Array.from({length: 6}, (_, index) => <div
            className="core-my-dashboard-loading__tile"
            key={index}
        >
            <div className="core-my-dashboard-loading__heading" />
            <div className="core-my-dashboard-loading__line core-my-dashboard-loading__line--long" />
            <div className="core-my-dashboard-loading__line" />
            <div className="core-my-dashboard-loading__line core-my-dashboard-loading__line--short" />
        </div>)}
    </div>
</div>;

export default DashboardLoading;
