var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Kebab menu surfacing a dashboard block's remaining legacy editing-controls actions.
 *
 * @module     core_my/components/BlockActionsMenu
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef, useState } from "react";
import { Button } from "@moodlehq/design-system";
const BlockActionsMenu = /* @__PURE__ */ __name(({ blockId, actions, label }) => {
  const [open, setOpen] = useState(false);
  const containerRef = useRef(null);
  const triggerRef = useRef(null);
  const itemRefs = useRef([]);
  const menuId = `core-my-dashboard-block-actions-${blockId}`;
  useEffect(() => {
    if (!open) {
      return void 0;
    }
    itemRefs.current[0]?.focus();
    const closeIfOutside = /* @__PURE__ */ __name((event) => {
      if (!containerRef.current?.contains(event.target)) {
        setOpen(false);
      }
    }, "closeIfOutside");
    const closeOnEscape = /* @__PURE__ */ __name((event) => {
      if (event.key === "Escape") {
        setOpen(false);
        triggerRef.current?.focus();
      }
    }, "closeOnEscape");
    document.addEventListener("pointerdown", closeIfOutside);
    document.addEventListener("keydown", closeOnEscape);
    return () => {
      document.removeEventListener("pointerdown", closeIfOutside);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, [open]);
  if (actions.length === 0) {
    return null;
  }
  const moveFocus = /* @__PURE__ */ __name((fromIndex, delta) => {
    const count = actions.length;
    const nextIndex = (fromIndex + delta + count) % count;
    itemRefs.current[nextIndex]?.focus();
  }, "moveFocus");
  const onItemKeyDown = /* @__PURE__ */ __name((event, index) => {
    switch (event.key) {
      case "ArrowDown":
        event.preventDefault();
        moveFocus(index, 1);
        break;
      case "ArrowUp":
        event.preventDefault();
        moveFocus(index, -1);
        break;
      case "Home":
        event.preventDefault();
        itemRefs.current[0]?.focus();
        break;
      case "End":
        event.preventDefault();
        itemRefs.current[actions.length - 1]?.focus();
        break;
      case "Tab":
        setOpen(false);
        break;
      default:
        break;
    }
  }, "onItemKeyDown");
  return /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-block-actions", ref: containerRef, children: [
    /* @__PURE__ */ jsxDEV(
      Button,
      {
        ref: triggerRef,
        size: "md",
        variant: "ghost",
        className: "core-my-dashboard-block-actions__trigger",
        "aria-label": label,
        "aria-haspopup": "menu",
        "aria-expanded": open,
        "aria-controls": menuId,
        startIcon: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-ellipsis-vertical", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/my/js/esm/src/components/BlockActionsMenu.tsx",
          lineNumber: 108,
          columnNumber: 24
        }),
        onClick: () => setOpen((current) => !current)
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/components/BlockActionsMenu.tsx",
        lineNumber: 99,
        columnNumber: 9
      }
    ),
    open && /* @__PURE__ */ jsxDEV(
      "div",
      {
        id: menuId,
        role: "menu",
        "aria-label": label,
        className: "core-my-dashboard-block-actions__menu",
        children: actions.map((action, index) => /* @__PURE__ */ jsxDEV(
          "a",
          {
            ref: (element) => {
              itemRefs.current[index] = element;
            },
            role: "menuitem",
            tabIndex: -1,
            className: "core-my-dashboard-block-actions__item",
            href: action.url,
            "data-action": action.modalform ? "editblock" : void 0,
            "data-blockid": action.modalform ? blockId : void 0,
            "data-blockform": action.modalform || void 0,
            "data-header": action.modalform ? action.label : void 0,
            onKeyDown: (event) => onItemKeyDown(event, index),
            onClick: () => setOpen(false),
            children: action.label
          },
          action.id,
          false,
          {
            fileName: "public/my/js/esm/src/components/BlockActionsMenu.tsx",
            lineNumber: 117,
            columnNumber: 45
          }
        ))
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/components/BlockActionsMenu.tsx",
        lineNumber: 111,
        columnNumber: 18
      }
    )
  ] }, void 0, true, {
    fileName: "public/my/js/esm/src/components/BlockActionsMenu.tsx",
    lineNumber: 98,
    columnNumber: 12
  });
}, "BlockActionsMenu");
var BlockActionsMenu_default = BlockActionsMenu;
export {
  BlockActionsMenu_default as default
};
//# sourceMappingURL=BlockActionsMenu.dev.js.map
