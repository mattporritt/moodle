import{jsx as l,jsxs as d}from"react/jsx-runtime";/**
 * Replaceable grid-cell state component for the Moodle Design System.
 *
 * @module     core_my/components/GridCell
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=({column:e,row:r,label:i,positionLabel:o,addLabel:t,prospective:n=!1,onActivate:c})=>{const a={gridColumn:`${e+1}`,gridRow:`${r+1}`};return n?l("div",{className:"core-my-grid-cell core-my-grid-cell--prospective",style:a,"aria-hidden":"true"}):d("button",{type:"button",className:"core-my-grid-cell core-my-grid-cell--available",style:a,"aria-label":`${i}, ${o.replace("{$a->row}",String(r+1)).replace("{$a->column}",String(e+1))}`,onClick:()=>c?.(e,r),children:[l("i",{className:"fa fa-plus core-my-grid-cell__icon","aria-hidden":"true"}),l("span",{className:"core-my-grid-cell__label","aria-hidden":"true",children:t})]})};var m=s;export{m as default};
