import{useEffect as f,useRef as m}from"react";import{Button as n}from"@moodlehq/design-system";import{jsx as o,jsxs as r}from"react/jsx-runtime";/**
 * Replaceable confirmation dialog built with Moodle Design System controls.
 *
 * @module     core_my/components/ConfirmationDialog
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=({title:e,message:l,confirmLabel:t,cancelLabel:s,onConfirm:c,onCancel:a})=>{const i=m(null);return f(()=>{i.current?.showModal()},[]),r("dialog",{ref:i,className:"core-my-dashboard-confirm",onCancel:a,children:[o("h2",{children:e}),o("p",{children:l}),r("div",{className:"core-my-dashboard-confirm__actions",children:[o(n,{variant:"secondary",label:s,onClick:a}),o(n,{variant:"danger",label:t,onClick:c})]})]})};var b=d;export{b as default};
