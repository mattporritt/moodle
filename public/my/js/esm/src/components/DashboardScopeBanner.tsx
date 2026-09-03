// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Editing-scope indicator and switch CTA for the dashboard grid editor.
 *
 * @module     core_my/components/DashboardScopeBanner
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {Badge, Link} from '@moodlehq/design-system';
import type {DashboardLabels, DashboardUrls} from '../repository';

interface DashboardScopeBannerProps {
    siteDefault: boolean;
    caneditotherscope: boolean;
    urls: DashboardUrls;
    labels: DashboardLabels;
}

const DashboardScopeBanner = ({siteDefault, caneditotherscope, urls, labels}: DashboardScopeBannerProps) =>
    <div className="core-my-dashboard-scope">
        <Badge
            variant={siteDefault ? 'warning' : 'info'}
            subtle
            pill
            label={siteDefault ? labels.scopesitedefault : labels.scopeown}
        />
        {caneditotherscope && <Link
            variant="secondary"
            href={siteDefault ? urls.ownpage : urls.sitedefault}
            label={siteDefault ? labels.switchtoown : labels.switchtositedefault}
        />}
    </div>;

export default DashboardScopeBanner;
