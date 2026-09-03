import{Button as l}from"@moodlehq/design-system";import{jsx as a}from"react/jsx-runtime";/**
 * Replaceable directional controls built with the Moodle Design System Button.
 *
 * @module     core_my/components/GridControls
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=[{name:"up",horizontal:0,vertical:-1,icon:"circle-arrow-up"},{name:"left",horizontal:-1,vertical:0,icon:"circle-arrow-left"},{name:"right",horizontal:1,vertical:0,icon:"circle-arrow-right"},{name:"down",horizontal:0,vertical:1,icon:"circle-arrow-down"}],s=({mode:i,labels:r,onDirection:n})=>a("div",{className:"core-my-grid-controls",role:"group","aria-label":i==="move"?r.movecontrols:r.resizecontrols,children:c.map(o=>{const e=r[o.name];return a(l,{size:"sm",variant:"secondary",className:`core-my-grid-controls__direction core-my-grid-controls__direction--${o.name}`,"aria-label":e,title:e,tabIndex:-1,startIcon:a("i",{className:`fa fa-${o.icon}`,"aria-hidden":"true"}),onPointerDown:t=>{t.preventDefault(),t.stopPropagation()},onClick:()=>n(o.horizontal,o.vertical)},o.name)})});var d=s;export{d as default};
