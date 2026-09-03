import{Badge as n,Link as t}from"@moodlehq/design-system";import{jsx as r,jsxs as p}from"react/jsx-runtime";/**
 * Editing-scope indicator and switch CTA for the dashboard grid editor.
 *
 * @module     core_my/components/DashboardScopeBanner
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=({siteDefault:o,caneditotherscope:s,urls:e,labels:a})=>p("div",{className:"core-my-dashboard-scope",children:[r(n,{variant:o?"warning":"info",subtle:!0,pill:!0,label:o?a.scopesitedefault:a.scopeown}),s&&r(t,{variant:"secondary",href:o?e.ownpage:e.sitedefault,label:o?a.switchtoown:a.switchtositedefault})]});var c=d;export{c as default};
