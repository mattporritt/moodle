var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Replaceable block palette built with Moodle Design System controls.
 *
 * @module     core_my/components/BlockPalette
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef } from "react";
import { Button } from "@moodlehq/design-system";
const BlockPalette = /* @__PURE__ */ __name(({ title, closeLabel, blocks, onSelect, onClose }) => {
  const dialogRef = useRef(null);
  useEffect(() => {
    dialogRef.current?.showModal();
  }, []);
  return /* @__PURE__ */ jsxDEV("dialog", { ref: dialogRef, className: "core-my-dashboard-palette", onCancel: onClose, children: /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-palette__panel", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-palette__header", children: [
      /* @__PURE__ */ jsxDEV("h2", { children: title }, void 0, false, {
        fileName: "public/my/js/esm/src/components/BlockPalette.tsx",
        lineNumber: 38,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(Button, { variant: "ghost", label: closeLabel, onClick: onClose }, void 0, false, {
        fileName: "public/my/js/esm/src/components/BlockPalette.tsx",
        lineNumber: 39,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/my/js/esm/src/components/BlockPalette.tsx",
      lineNumber: 37,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-palette__list", children: blocks.map((block) => /* @__PURE__ */ jsxDEV(
      Button,
      {
        variant: "secondary",
        label: block.title,
        onClick: () => onSelect(block)
      },
      block.name,
      false,
      {
        fileName: "public/my/js/esm/src/components/BlockPalette.tsx",
        lineNumber: 42,
        columnNumber: 38
      }
    )) }, void 0, false, {
      fileName: "public/my/js/esm/src/components/BlockPalette.tsx",
      lineNumber: 41,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/my/js/esm/src/components/BlockPalette.tsx",
    lineNumber: 36,
    columnNumber: 9
  }) }, void 0, false, {
    fileName: "public/my/js/esm/src/components/BlockPalette.tsx",
    lineNumber: 35,
    columnNumber: 12
  });
}, "BlockPalette");
var BlockPalette_default = BlockPalette;
export {
  BlockPalette_default as default
};
//# sourceMappingURL=BlockPalette.dev.js.map
