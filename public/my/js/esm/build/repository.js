import{fetchOne as e}from"@moodle/lms/core/ajax";/**
 * Dashboard web-service repository.
 *
 * @module     core_my/repository
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=t=>e({methodname:"core_my_get_dashboard",args:{sitedefault:t}}),g=(t,r,s=[],o="",a=0)=>e({methodname:"core_my_update_dashboard",args:{action:t,sitedefault:r,layout:s,blockname:o,blockid:a}});export{n as getDashboard,g as updateDashboard};
