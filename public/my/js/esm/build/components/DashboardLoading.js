import{jsx as a,jsxs as o}from"react/jsx-runtime";/**
 * Full-viewport dashboard loading placeholder.
 *
 * @module     core_my/components/DashboardLoading
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=({label:d})=>o("div",{className:"core-my-dashboard-loading",role:"status","aria-label":d,"aria-busy":"true",children:[a("span",{className:"visually-hidden",children:d}),a("div",{className:"core-my-dashboard-loading__grid","aria-hidden":"true",children:Array.from({length:6},(r,i)=>o("div",{className:"core-my-dashboard-loading__tile",children:[a("div",{className:"core-my-dashboard-loading__heading"}),a("div",{className:"core-my-dashboard-loading__line core-my-dashboard-loading__line--long"}),a("div",{className:"core-my-dashboard-loading__line"}),a("div",{className:"core-my-dashboard-loading__line core-my-dashboard-loading__line--short"})]},i))})]});var e=s;export{e as default};
