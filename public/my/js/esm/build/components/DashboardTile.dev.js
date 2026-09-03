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
            lineNumber: 136,
            columnNumber: 13
          }),
          /* @__PURE__ */ jsxDEV("span", { id: resizeInstructionsId, className: "visually-hidden", children: labels.resizeinstructions }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 137,
            columnNumber: 13
          })
        ] }, void 0, true, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 135,
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
              lineNumber: 140,
              columnNumber: 25
            }
          ),
          /* @__PURE__ */ jsxDEV("h2", { className: "core-my-dashboard-tile__title", children: block.title }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 153,
            columnNumber: 13
          }),
          editing && /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-tile__actions", children: /* @__PURE__ */ jsxDEV(
            Button,
            {
              size: "md",
              variant: "ghost",
              className: "core-my-dashboard-remove",
              "aria-label": labels.remove.replace("{$a}", block.title),
              title: labels.remove.replace("{$a}", block.title),
              startIcon: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-trash-can", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
                lineNumber: 161,
                columnNumber: 32
              }),
              onClick: () => onRemove(block.id)
            },
            void 0,
            false,
            {
              fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
              lineNumber: 155,
              columnNumber: 17
            }
          ) }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 154,
            columnNumber: 25
          })
        ] }, void 0, true, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 139,
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
            lineNumber: 166,
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
            lineNumber: 171,
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
            lineNumber: 176,
            columnNumber: 13
          }
        ) }, void 0, false, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 175,
          columnNumber: 21
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
      lineNumber: 119,
      columnNumber: 12
    }
  );
}, "DashboardTile");
var DashboardTile_default = DashboardTile;
export {
  DashboardTile_default as default
};
//# sourceMappingURL=DashboardTile.dev.js.map
