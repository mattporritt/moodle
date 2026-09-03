var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Replaceable grid-cell state component for the Moodle Design System.
 *
 * @module     core_my/components/GridCell
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const GridCell = /* @__PURE__ */ __name(({ column, row, label, positionLabel, prospective = false, onActivate }) => {
  const style = {
    gridColumn: `${column + 1}`,
    gridRow: `${row + 1}`
  };
  if (prospective) {
    return /* @__PURE__ */ jsxDEV("div", { className: "core-my-grid-cell core-my-grid-cell--prospective", style, "aria-hidden": "true" }, void 0, false, {
      fileName: "public/my/js/esm/src/components/GridCell.tsx",
      lineNumber: 33,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "core-my-grid-cell core-my-grid-cell--available",
      style,
      "aria-label": `${label}, ${positionLabel.replace("{$a->row}", String(row + 1)).replace("{$a->column}", String(column + 1))}`,
      onClick: () => onActivate?.(column, row)
    },
    void 0,
    false,
    {
      fileName: "public/my/js/esm/src/components/GridCell.tsx",
      lineNumber: 35,
      columnNumber: 12
    }
  );
}, "GridCell");
var GridCell_default = GridCell;
export {
  GridCell_default as default
};
//# sourceMappingURL=GridCell.dev.js.map
