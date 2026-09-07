var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Responsive dashboard tile.
 *
 * @module     core_my/components/DashboardTile
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { Button } from "@moodlehq/design-system";
import { NARROW_BLOCK_WIDTH } from "../layout";
import DashboardHandle from "./DashboardHandle";
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
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 114,
          columnNumber: 24
        }),
        onClick: () => setOpen((current) => !current)
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
        lineNumber: 105,
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
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 123,
            columnNumber: 45
          }
        ))
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
        lineNumber: 117,
        columnNumber: 18
      }
    )
  ] }, void 0, true, {
    fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
    lineNumber: 104,
    columnNumber: 12
  });
}, "BlockActionsMenu");
const DashboardTile = /* @__PURE__ */ __name(({
  block,
  item,
  labels,
  editing,
  activeMode,
  showControls,
  drag,
  dragOrigin,
  shouldAnimatePosition = false,
  isBumped = false,
  onStart,
  onKeyDown,
  onPointerDown,
  onDirection,
  onCommit,
  onRemove
}) => {
  const tileRef = useRef(null);
  const contentRef = useRef(null);
  const previousPosition = useRef(null);
  const positionAnimation = useRef(null);
  const [narrow, setNarrow] = useState(false);
  const moveInstructionsId = `core-my-dashboard-move-instructions-${block.id}`;
  const resizeInstructionsId = `core-my-dashboard-resize-instructions-${block.id}`;
  const displayItem = drag && dragOrigin ? dragOrigin : item;
  useEffect(() => {
    const content = contentRef.current;
    if (!content || typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const measure = /* @__PURE__ */ __name(() => setNarrow(content.getBoundingClientRect().width < NARROW_BLOCK_WIDTH), "measure");
    const observer = new ResizeObserver(measure);
    observer.observe(content);
    measure();
    return () => observer.disconnect();
  }, []);
  useLayoutEffect(() => {
    const tile = tileRef.current;
    if (!tile) {
      return;
    }
    positionAnimation.current?.cancel();
    const currentPosition = tile.getBoundingClientRect();
    const priorPosition = previousPosition.current;
    previousPosition.current = currentPosition;
    if (!shouldAnimatePosition || !priorPosition || window.matchMedia?.("(prefers-reduced-motion: reduce)").matches || !tile.animate) {
      return;
    }
    const horizontal = priorPosition.left - currentPosition.left;
    const vertical = priorPosition.top - currentPosition.top;
    if (!horizontal && !vertical) {
      return;
    }
    positionAnimation.current = tile.animate([
      { transform: `translate3d(${horizontal}px, ${vertical}px, 0)` },
      { transform: "translate3d(0, 0, 0)" }
    ], {
      duration: 180,
      easing: "cubic-bezier(.2, 0, 0, 1)"
    });
  }, [item.column, item.columns, item.row, item.rows, shouldAnimatePosition]);
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      ref: tileRef,
      className: `core-my-dashboard-tile${activeMode ? " core-my-dashboard-tile--active" : ""}${drag ? " core-my-dashboard-tile--pointer-dragging" : ""}${isBumped ? " core-my-dashboard-tile--bumped" : ""}`,
      style: {
        gridColumn: `${displayItem.column + 1} / span ${displayItem.columns}`,
        gridRow: `${displayItem.row + 1} / span ${displayItem.rows}`,
        transform: drag && activeMode === "move" ? `translate3d(${drag.x}px, ${drag.y}px, 0)` : void 0,
        width: drag?.width ? `${drag.width}px` : void 0,
        height: drag?.height ? `${drag.height}px` : void 0
      },
      "aria-label": labels.tile.replace("{$a}", block.title),
      "data-block": block.name,
      "data-block-id": block.id,
      "data-blockregion": narrow ? "side-pre" : "content",
      "data-bumped": isBumped || void 0,
      children: [
        editing && /* @__PURE__ */ jsxDEV(Fragment, { children: [
          /* @__PURE__ */ jsxDEV("span", { id: moveInstructionsId, className: "visually-hidden", children: labels.moveinstructions }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 259,
            columnNumber: 13
          }),
          /* @__PURE__ */ jsxDEV("span", { id: resizeInstructionsId, className: "visually-hidden", children: labels.resizeinstructions }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 260,
            columnNumber: 13
          })
        ] }, void 0, true, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 258,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("header", { className: "core-my-dashboard-tile__header", children: [
          editing && /* @__PURE__ */ jsxDEV(
            DashboardHandle,
            {
              mode: "move",
              label: labels.move.replace("{$a}", block.title),
              labels,
              instructionsId: moveInstructionsId,
              active: activeMode === "move",
              showControls,
              onStart: () => onStart(block.id, "move"),
              onKeyDown: (event) => onKeyDown(event, block.id, "move"),
              onPointerDown: (event) => onPointerDown(event, block.id, "move"),
              onDirection,
              onCommit
            },
            void 0,
            false,
            {
              fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
              lineNumber: 263,
              columnNumber: 25
            }
          ),
          /* @__PURE__ */ jsxDEV("h2", { className: "core-my-dashboard-tile__title", children: block.title }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 276,
            columnNumber: 13
          }),
          editing && /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-tile__actions", children: [
            /* @__PURE__ */ jsxDEV(
              Button,
              {
                size: "md",
                variant: "ghost",
                className: "core-my-dashboard-remove",
                "aria-label": labels.remove.replace("{$a}", block.title),
                title: labels.remove.replace("{$a}", block.title),
                startIcon: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-trash-can", "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
                  lineNumber: 284,
                  columnNumber: 32
                }),
                onClick: () => onRemove(block.id)
              },
              void 0,
              false,
              {
                fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
                lineNumber: 278,
                columnNumber: 17
              }
            ),
            /* @__PURE__ */ jsxDEV(
              BlockActionsMenu,
              {
                blockId: block.id,
                actions: block.actions,
                label: labels.blockactions.replace("{$a}", block.title)
              },
              void 0,
              false,
              {
                fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
                lineNumber: 287,
                columnNumber: 17
              }
            )
          ] }, void 0, true, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 277,
            columnNumber: 25
          })
        ] }, void 0, true, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 262,
          columnNumber: 9
        }),
        /* @__PURE__ */ jsxDEV(
          "div",
          {
            ref: contentRef,
            className: `core-my-dashboard-tile__content block block_${block.name}`,
            dangerouslySetInnerHTML: { __html: block.content }
          },
          void 0,
          false,
          {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 294,
            columnNumber: 9
          }
        ),
        block.footer && /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "core-my-dashboard-tile__block-footer",
            dangerouslySetInnerHTML: { __html: block.footer }
          },
          void 0,
          false,
          {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 299,
            columnNumber: 26
          }
        ),
        editing && /* @__PURE__ */ jsxDEV("footer", { className: "core-my-dashboard-tile__dashboard-footer", children: /* @__PURE__ */ jsxDEV(
          DashboardHandle,
          {
            mode: "resize",
            label: labels.resize,
            labels,
            instructionsId: resizeInstructionsId,
            active: activeMode === "resize",
            showControls,
            onStart: () => onStart(block.id, "resize"),
            onKeyDown: (event) => onKeyDown(event, block.id, "resize"),
            onPointerDown: (event) => onPointerDown(event, block.id, "resize"),
            onDirection,
            onCommit
          },
          void 0,
          false,
          {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 304,
            columnNumber: 13
          }
        ) }, void 0, false, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 303,
          columnNumber: 21
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
      lineNumber: 242,
      columnNumber: 12
    }
  );
}, "DashboardTile");
var DashboardTile_default = DashboardTile;
export {
  BlockActionsMenu,
  DashboardTile_default as default
};
//# sourceMappingURL=DashboardTile.dev.js.map
