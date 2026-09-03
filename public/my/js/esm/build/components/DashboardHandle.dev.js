var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Accessible move and resize handle for dashboard tiles.
 *
 * @module     core_my/components/DashboardHandle
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Button } from "@moodlehq/design-system";
const DashboardHandle = /* @__PURE__ */ __name(({
  mode,
  label,
  instructionsId,
  active,
  onStart,
  onKeyDown,
  onPointerDown
}) => /* @__PURE__ */ jsxDEV(
  Button,
  {
    size: "md",
    variant: "ghost",
    className: `core-my-dashboard-handle core-my-dashboard-handle--${mode}`,
    "aria-label": label,
    "aria-describedby": instructionsId,
    "aria-pressed": active,
    title: label,
    startIcon: /* @__PURE__ */ jsxDEV(
      "i",
      {
        className: `fa fa-${mode === "move" ? "arrows-up-down-left-right" : "up-right-and-down-left-from-center"}`,
        "aria-hidden": "true"
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/components/DashboardHandle.tsx",
        lineNumber: 45,
        columnNumber: 16
      }
    ),
    onClick: (event) => {
      if (event.detail === 0) {
        onStart();
      }
    },
    onKeyDown,
    onPointerDown: (event) => {
      event.currentTarget.focus();
      onPointerDown(event);
    }
  },
  void 0,
  false,
  {
    fileName: "public/my/js/esm/src/components/DashboardHandle.tsx",
    lineNumber: 37,
    columnNumber: 29
  }
), "DashboardHandle");
var DashboardHandle_default = DashboardHandle;
export {
  DashboardHandle_default as default
};
//# sourceMappingURL=DashboardHandle.dev.js.map
