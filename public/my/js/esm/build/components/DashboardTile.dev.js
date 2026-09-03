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
import { Button } from "@moodlehq/design-system";
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
  onStart,
  onKeyDown,
  onPointerDown,
  onDirection,
  onCommit,
  onRemove
}) => {
  const moveInstructionsId = `core-my-dashboard-move-instructions-${block.id}`;
  const resizeInstructionsId = `core-my-dashboard-resize-instructions-${block.id}`;
  const displayItem = drag && dragOrigin ? dragOrigin : item;
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      className: `core-my-dashboard-tile${activeMode ? " core-my-dashboard-tile--active" : ""}${drag ? " core-my-dashboard-tile--pointer-dragging" : ""}`,
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
      children: [
        editing && /* @__PURE__ */ jsxDEV(Fragment, { children: [
          /* @__PURE__ */ jsxDEV("span", { id: moveInstructionsId, className: "visually-hidden", children: labels.moveinstructions }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 81,
            columnNumber: 13
          }),
          /* @__PURE__ */ jsxDEV("span", { id: resizeInstructionsId, className: "visually-hidden", children: labels.resizeinstructions }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 82,
            columnNumber: 13
          })
        ] }, void 0, true, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 80,
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
              lineNumber: 85,
              columnNumber: 25
            }
          ),
          /* @__PURE__ */ jsxDEV("h2", { className: "core-my-dashboard-tile__title", children: block.title }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 98,
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
                lineNumber: 106,
                columnNumber: 32
              }),
              onClick: () => onRemove(block.id)
            },
            void 0,
            false,
            {
              fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
              lineNumber: 100,
              columnNumber: 17
            }
          ) }, void 0, false, {
            fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
            lineNumber: 99,
            columnNumber: 25
          })
        ] }, void 0, true, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 84,
          columnNumber: 9
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-tile__content", dangerouslySetInnerHTML: { __html: block.content } }, void 0, false, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 111,
          columnNumber: 9
        }),
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
            lineNumber: 112,
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
            lineNumber: 117,
            columnNumber: 13
          }
        ) }, void 0, false, {
          fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
          lineNumber: 116,
          columnNumber: 21
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/my/js/esm/src/components/DashboardTile.tsx",
      lineNumber: 67,
      columnNumber: 12
    }
  );
}, "DashboardTile");
var DashboardTile_default = DashboardTile;
export {
  DashboardTile_default as default
};
//# sourceMappingURL=DashboardTile.dev.js.map
