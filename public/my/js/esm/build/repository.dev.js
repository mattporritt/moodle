var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Dashboard web-service repository.
 *
 * @module     core_my/repository
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
const getDashboard = /* @__PURE__ */ __name((siteDefault) => fetchOne({
  methodname: "core_my_get_dashboard",
  args: { sitedefault: siteDefault }
}), "getDashboard");
const updateDashboard = /* @__PURE__ */ __name((action, siteDefault, layout = [], blockname = "", blockid = 0) => fetchOne({
  methodname: "core_my_update_dashboard",
  args: {
    action,
    sitedefault: siteDefault,
    layout,
    blockname,
    blockid
  }
}), "updateDashboard");
export {
  getDashboard,
  updateDashboard
};
//# sourceMappingURL=repository.dev.js.map
