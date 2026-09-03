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
const GridControls = /* @__PURE__ */ __name(({ mode, labels, onDirection, onCommit, onCancel }) => /* @__PURE__ */ jsxDEV(
  "div",
  {
    className: "core-my-grid-controls",
    role: "toolbar",
    "aria-label": mode === "move" ? labels.movecontrols : labels.resizecontrols,
    children: [
      /* @__PURE__ */ jsxDEV(Button, { size: "sm", variant: "ghost", label: labels.up, onClick: () => onDirection(0, -1) }, void 0, false, {
        fileName: "public/my/js/esm/src/components/GridControls.tsx",
        lineNumber: 33,
        columnNumber: 5
      }),
      /* @__PURE__ */ jsxDEV(Button, { size: "sm", variant: "ghost", label: labels.left, onClick: () => onDirection(-1, 0) }, void 0, false, {
        fileName: "public/my/js/esm/src/components/GridControls.tsx",
        lineNumber: 34,
        columnNumber: 5
      }),
      /* @__PURE__ */ jsxDEV(Button, { size: "sm", variant: "ghost", label: labels.right, onClick: () => onDirection(1, 0) }, void 0, false, {
        fileName: "public/my/js/esm/src/components/GridControls.tsx",
        lineNumber: 35,
        columnNumber: 5
      }),
      /* @__PURE__ */ jsxDEV(Button, { size: "sm", variant: "ghost", label: labels.down, onClick: () => onDirection(0, 1) }, void 0, false, {
        fileName: "public/my/js/esm/src/components/GridControls.tsx",
        lineNumber: 36,
        columnNumber: 5
      }),
      /* @__PURE__ */ jsxDEV(Button, { size: "sm", variant: "primary", label: labels.done, onClick: onCommit }, void 0, false, {
        fileName: "public/my/js/esm/src/components/GridControls.tsx",
        lineNumber: 37,
        columnNumber: 5
      }),
      /* @__PURE__ */ jsxDEV(Button, { size: "sm", variant: "secondary", label: labels.cancel, onClick: onCancel }, void 0, false, {
        fileName: "public/my/js/esm/src/components/GridControls.tsx",
        lineNumber: 38,
        columnNumber: 5
      })
    ]
  },
  void 0,
  true,
  {
    fileName: "public/my/js/esm/src/components/GridControls.tsx",
    lineNumber: 28,
    columnNumber: 94
  }
), "GridControls");
var GridControls_default = GridControls;
export {
  GridControls_default as default
};
//# sourceMappingURL=GridControls.dev.js.map
