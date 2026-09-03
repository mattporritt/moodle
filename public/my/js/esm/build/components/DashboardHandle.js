import{Button as h}from"@moodlehq/design-system";import m from"./GridControls";import{jsx as e,jsxs as u}from"react/jsx-runtime";/**
 * Accessible move and resize handle for dashboard tiles.
 *
 * @module     core_my/components/DashboardHandle
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=({mode:a,label:n,labels:d,instructionsId:s,active:o,onStart:l,onKeyDown:c,onPointerDown:b,onDirection:f,onCommit:t})=>u("div",{className:`core-my-dashboard-handle-wrapper core-my-dashboard-handle-wrapper--${a}${o?" active":""}`,onBlur:r=>{const i=r.relatedTarget;o&&(!(i instanceof Node)||!r.currentTarget.contains(i))&&t()},children:[o&&e(m,{mode:a,labels:d,onDirection:f}),e(h,{size:"md",variant:"ghost",className:`core-my-dashboard-handle core-my-dashboard-handle--${a}`,"aria-label":n,"aria-describedby":s,"aria-pressed":o,title:n,startIcon:e("i",{className:`fa fa-${a==="move"?"arrows-up-down-left-right":"up-right-and-down-left-from-center fa-flip-horizontal"}`,"aria-hidden":"true"}),onClick:r=>{r.detail===0&&!o&&l()},onKeyDown:c,onPointerDown:r=>{if(o){r.preventDefault(),t();return}r.currentTarget.focus(),b(r)}})]});var y=p;export{y as default};
