import{jsx as i}from"react/jsx-runtime";/**
 * Replaceable grid-cell state component for the Moodle Design System.
 *
 * @module     core_my/components/GridCell
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=({column:e,row:r,label:o,positionLabel:t,prospective:a=!1,onActivate:n})=>{const l={gridColumn:`${e+1}`,gridRow:`${r+1}`};return a?i("div",{className:"core-my-grid-cell core-my-grid-cell--prospective",style:l,"aria-hidden":"true"}):i("button",{type:"button",className:"core-my-grid-cell core-my-grid-cell--available",style:l,"aria-label":`${o}, ${t.replace("{$a->row}",String(r+1)).replace("{$a->column}",String(e+1))}`,onClick:()=>n?.(e,r)})};var s=c;export{s as default};
