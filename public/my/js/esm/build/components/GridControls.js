import{Button as r}from"@moodlehq/design-system";import{jsx as i,jsxs as l}from"react/jsx-runtime";/**
 * Replaceable directional controls built with the Moodle Design System Button.
 *
 * @module     core_my/components/GridControls
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=({mode:e,labels:o,onDirection:a,onCommit:t,onCancel:s})=>l("div",{className:"core-my-grid-controls",role:"toolbar","aria-label":e==="move"?o.movecontrols:o.resizecontrols,children:[i(r,{size:"sm",variant:"ghost",label:o.up,onClick:()=>a(0,-1)}),i(r,{size:"sm",variant:"ghost",label:o.left,onClick:()=>a(-1,0)}),i(r,{size:"sm",variant:"ghost",label:o.right,onClick:()=>a(1,0)}),i(r,{size:"sm",variant:"ghost",label:o.down,onClick:()=>a(0,1)}),i(r,{size:"sm",variant:"primary",label:o.done,onClick:t}),i(r,{size:"sm",variant:"secondary",label:o.cancel,onClick:s})]});var c=n;export{c as default};
