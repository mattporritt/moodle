import{useEffect as n,useRef as m}from"react";import{Button as t}from"@moodlehq/design-system";import{jsx as e,jsxs as r}from"react/jsx-runtime";/**
 * Replaceable block palette built with Moodle Design System controls.
 *
 * @module     core_my/components/BlockPalette
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=({title:i,closeLabel:s,blocks:c,onSelect:d,onClose:l})=>{const o=m(null);return n(()=>{o.current?.showModal()},[]),e("dialog",{ref:o,className:"core-my-dashboard-palette",onCancel:l,children:r("div",{className:"core-my-dashboard-palette__panel",children:[r("div",{className:"core-my-dashboard-palette__header",children:[e("h2",{children:i}),e(t,{variant:"ghost",label:s,onClick:l})]}),e("div",{className:"core-my-dashboard-palette__list",children:c.map(a=>e(t,{variant:"secondary",label:a.title,onClick:()=>d(a)},a.name))})]})})};var h=p;export{h as default};
