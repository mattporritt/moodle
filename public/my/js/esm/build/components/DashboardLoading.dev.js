var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Full-viewport dashboard loading placeholder.
 *
 * @module     core_my/components/DashboardLoading
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef, useState } from "react";
import { columnsForWidth, packLayout, ROW_HEIGHT } from "../layout";
const GenericGrid = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__grid", "aria-hidden": "true", children: Array.from({ length: 6 }, (_, index) => /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__tile", children: [
  /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__heading" }, void 0, false, {
    fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
    lineNumber: 26,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__line core-my-dashboard-loading__line--long" }, void 0, false, {
    fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
    lineNumber: 27,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__line" }, void 0, false, {
    fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
    lineNumber: 28,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__line core-my-dashboard-loading__line--short" }, void 0, false, {
    fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
    lineNumber: 29,
    columnNumber: 9
  })
] }, index, true, {
  fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
  lineNumber: 25,
  columnNumber: 44
})) }, void 0, false, {
  fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
  lineNumber: 24,
  columnNumber: 27
}), "GenericGrid");
const PositionedGrid = /* @__PURE__ */ __name(({ layout }) => {
  const gridRef = useRef(null);
  const [columnCount, setColumnCount] = useState(1);
  useEffect(() => {
    const grid = gridRef.current;
    if (!grid) {
      return void 0;
    }
    const measure = /* @__PURE__ */ __name(() => setColumnCount(columnsForWidth(grid.getBoundingClientRect().width)), "measure");
    const observer = new ResizeObserver(measure);
    observer.observe(grid);
    measure();
    return () => observer.disconnect();
  }, []);
  const positioned = packLayout(layout, columnCount);
  const rows = Math.max(1, ...positioned.map((item) => item.row + item.rows));
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: gridRef,
      className: "core-my-dashboard-grid core-my-dashboard-loading__grid--positioned",
      style: {
        gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))`,
        gridTemplateRows: `repeat(${rows}, ${ROW_HEIGHT}px)`
      },
      "aria-hidden": "true",
      "data-columns": columnCount,
      children: positioned.map((item) => /* @__PURE__ */ jsxDEV(
        "div",
        {
          className: "core-my-dashboard-tile core-my-dashboard-loading__tile core-my-dashboard-loading__tile--positioned",
          style: {
            gridColumn: `${item.column + 1} / span ${item.columns}`,
            gridRow: `${item.row + 1} / span ${item.rows}`
          },
          children: [
            /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__heading" }, void 0, false, {
              fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
              lineNumber: 72,
              columnNumber: 13
            }),
            /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__line core-my-dashboard-loading__line--long" }, void 0, false, {
              fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
              lineNumber: 73,
              columnNumber: 13
            }),
            /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-loading__line" }, void 0, false, {
              fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
              lineNumber: 74,
              columnNumber: 13
            })
          ]
        },
        item.id,
        true,
        {
          fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
          lineNumber: 64,
          columnNumber: 33
        }
      ))
    },
    void 0,
    false,
    {
      fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
      lineNumber: 54,
      columnNumber: 12
    }
  );
}, "PositionedGrid");
const DashboardLoading = /* @__PURE__ */ __name(({ label, layout = [] }) => /* @__PURE__ */ jsxDEV(
  "div",
  {
    className: "core-my-dashboard-loading",
    role: "status",
    "aria-label": label,
    "aria-busy": "true",
    children: [
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: label }, void 0, false, {
        fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
        lineNumber: 85,
        columnNumber: 5
      }),
      layout.length > 0 ? /* @__PURE__ */ jsxDEV(PositionedGrid, { layout }, void 0, false, {
        fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
        lineNumber: 86,
        columnNumber: 26
      }) : /* @__PURE__ */ jsxDEV(GenericGrid, {}, void 0, false, {
        fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
        lineNumber: 86,
        columnNumber: 63
      })
    ]
  },
  void 0,
  true,
  {
    fileName: "public/my/js/esm/src/components/DashboardLoading.tsx",
    lineNumber: 79,
    columnNumber: 75
  }
), "DashboardLoading");
var DashboardLoading_default = DashboardLoading;
export {
  DashboardLoading_default as default
};
//# sourceMappingURL=DashboardLoading.dev.js.map
