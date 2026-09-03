import{Button as c}from"@moodlehq/design-system";import{jsx as r}from"react/jsx-runtime";/**
 * Accessible move and resize handle for dashboard tiles.
 *
 * @module     core_my/components/DashboardHandle
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const l=({mode:a,label:o,instructionsId:t,active:n,onStart:i,onKeyDown:d,onPointerDown:s})=>r(c,{size:"md",variant:"ghost",className:`core-my-dashboard-handle core-my-dashboard-handle--${a}`,"aria-label":o,"aria-describedby":t,"aria-pressed":n,title:o,startIcon:r("i",{className:`fa fa-${a==="move"?"arrows-up-down-left-right":"up-right-and-down-left-from-center"}`,"aria-hidden":"true"}),onClick:e=>{e.detail===0&&i()},onKeyDown:d,onPointerDown:e=>{e.currentTarget.focus(),s(e)}});var h=l;export{h as default};
