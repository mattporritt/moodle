import{Button as m}from"@moodlehq/design-system";import p from"./GridControls";import{jsx as e,jsxs as D}from"react/jsx-runtime";/**
 * Accessible move and resize handle for dashboard tiles.
 *
 * @module     core_my/components/DashboardHandle
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=({mode:a,label:n,labels:d,instructionsId:s,active:r,showControls:l,onStart:c,onKeyDown:b,onPointerDown:h,onDirection:f,onCommit:t})=>D("div",{className:`core-my-dashboard-handle-wrapper core-my-dashboard-handle-wrapper--${a}${r?" active":""}`,onBlur:o=>{const i=o.relatedTarget;r&&(!(i instanceof Node)||!o.currentTarget.contains(i))&&t()},children:[r&&l&&e(p,{mode:a,labels:d,onDirection:f}),e(m,{size:"md",variant:"ghost",className:`core-my-dashboard-handle core-my-dashboard-handle--${a}`,"aria-label":n,"aria-describedby":s,"aria-pressed":r,title:n,startIcon:e("i",{className:`fa fa-${a==="move"?"arrows-up-down-left-right":"up-right-and-down-left-from-center fa-flip-horizontal"}`,"aria-hidden":"true"}),onClick:o=>{o.detail===0&&!r&&c()},onKeyDown:b,onPointerDown:o=>{if(r){o.preventDefault(),t();return}o.currentTarget.focus(),h(o)}})]});var y=u;export{y as default};
