import{useEffect as c}from"react";import{Switch as l}from"@moodlehq/design-system";import{requireAsync as s}from"@moodle/lms/core/amd";import{jsx as f}from"react/jsx-runtime";/**
 * The site-wide Edit mode switch, rendered with the design system Switch component.
 *
 * @module     core/EditModeSwitch
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function u({id:e,context:i,pageurl:r,sesskey:d,checked:n,label:o}){return c(()=>{let t=!1;return s("core/edit_switch").then(a=>{t||a.init(e)}),()=>{t=!0}},[e]),f(l,{id:e,name:"setmode",variant:"enable",labelSide:"start",label:o,defaultChecked:n,"data-context":i,"data-pageurl":r,"data-sesskey":d})}export{u as default};
