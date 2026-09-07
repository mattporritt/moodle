var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Replaceable directional controls built with the Moodle Design System Button.
 *
 * @module     core_my/components/GridControls
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Button } from "@moodlehq/design-system";
const directions = [
  { name: "up", horizontal: 0, vertical: -1, icon: "circle-arrow-up" },
  { name: "left", horizontal: -1, vertical: 0, icon: "circle-arrow-left" },
  { name: "right", horizontal: 1, vertical: 0, icon: "circle-arrow-right" },
  { name: "down", horizontal: 0, vertical: 1, icon: "circle-arrow-down" }
];
const GridControls = /* @__PURE__ */ __name(({ mode, labels, onDirection }) => /* @__PURE__ */ jsxDEV(
  "div",
  {
    className: "core-my-grid-controls",
    role: "group",
    "aria-label": mode === "move" ? labels.movecontrols : labels.resizecontrols,
    children: directions.map((direction) => {
      const label = labels[direction.name];
      return /* @__PURE__ */ jsxDEV(
        Button,
        {
          size: "sm",
          variant: "secondary",
          className: `core-my-grid-controls__direction core-my-grid-controls__direction--${direction.name}`,
          "aria-label": label,
          title: label,
          tabIndex: -1,
          startIcon: /* @__PURE__ */ jsxDEV("i", { className: `fa fa-${direction.icon}`, "aria-hidden": "true" }, void 0, false, {
            fileName: "public/my/js/esm/src/components/GridControls.tsx",
            lineNumber: 48,
            columnNumber: 24
          }),
          onPointerDown: (event) => {
            event.preventDefault();
            event.stopPropagation();
          },
          onClick: () => onDirection(direction.horizontal, direction.vertical)
        },
        direction.name,
        false,
        {
          fileName: "public/my/js/esm/src/components/GridControls.tsx",
          lineNumber: 40,
          columnNumber: 16
        }
      );
    })
  },
  void 0,
  false,
  {
    fileName: "public/my/js/esm/src/components/GridControls.tsx",
    lineNumber: 33,
    columnNumber: 74
  }
), "GridControls");
var GridControls_default = GridControls;
export {
  GridControls_default as default
};
//# sourceMappingURL=GridControls.dev.js.map
