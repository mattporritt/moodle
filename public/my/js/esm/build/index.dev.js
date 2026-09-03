var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Flexible responsive dashboard React application.
 *
 * @module     core_my/index
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Button } from "@moodlehq/design-system";
import { getString } from "@moodle/lms/core/stringUtils";
import { requireManyAsync } from "@moodle/lms/core/amd";
import DashboardTile from "./components/DashboardTile";
import ConfirmationDialog from "./components/ConfirmationDialog";
import BlockPalette from "./components/BlockPalette";
import GridCell from "./components/GridCell";
import DashboardLoading from "./components/DashboardLoading";
import DashboardScopeBanner from "./components/DashboardScopeBanner";
import {
  GRID_GAP,
  MIN_COLUMNS,
  MIN_ROWS,
  ROW_HEIGHT,
  columnsForWidth,
  disturbedCount,
  maxRow,
  packLayout,
  packWithPinned,
  writeBack
} from "./layout";
import {
  getDashboard,
  updateDashboard
} from "./repository";
const isSiteDefault = /* @__PURE__ */ __name(() => window.location.pathname.endsWith("/my/indexsys.php"), "isSiteDefault");
const layoutChanged = /* @__PURE__ */ __name((original, draft) => original.column !== draft.column || original.row !== draft.row || original.columns !== draft.columns || original.rows !== draft.rows, "layoutChanged");
const Dashboard = /* @__PURE__ */ __name(({ loadingLabel = "" }) => {
  const [data, setData] = useState(null);
  const [canonical, setCanonical] = useState([]);
  const [columnCount, setColumnCount] = useState(1);
  const [interaction, setInteraction] = useState(null);
  const [announcement, setAnnouncement] = useState("");
  const [error, setError] = useState("");
  const [palette, setPalette] = useState(null);
  const [confirmAction, setConfirmAction] = useState(null);
  const [saving, setSaving] = useState(false);
  const gridRef = useRef(null);
  const pointerRef = useRef(null);
  const interactionRef = useRef(null);
  const canonicalRef = useRef([]);
  const displayLayoutRef = useRef([]);
  const columnCountRef = useRef(1);
  const dataRef = useRef(null);
  const siteDefault = isSiteDefault();
  const load = useCallback(async () => {
    try {
      const response = await getDashboard(siteDefault);
      setData(response);
      dataRef.current = response;
      setCanonical(response.layout);
      canonicalRef.current = response.layout;
      setError("");
      return response;
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : String(caught));
      return null;
    }
  }, [siteDefault]);
  useEffect(() => {
    void load();
  }, [load]);
  useEffect(() => {
    if (!data?.javascript) {
      return void 0;
    }
    let superseded = false;
    void requireManyAsync(["core/fragment", "core/templates"]).then(([fragment, templates]) => {
      if (superseded) {
        return void 0;
      }
      const processed = fragment.processCollectedJavascript(data.javascript);
      templates.runTemplateJS(processed);
      return void 0;
    });
    return () => {
      superseded = true;
    };
  }, [data]);
  useEffect(() => {
    const grid = gridRef.current;
    if (!grid) {
      return;
    }
    const measure = /* @__PURE__ */ __name(() => {
      const next = columnsForWidth(grid.getBoundingClientRect().width);
      columnCountRef.current = next;
      setColumnCount(next);
    }, "measure");
    const observer = new ResizeObserver(measure);
    observer.observe(grid);
    measure();
    return () => observer.disconnect();
  }, [data]);
  const displayLayout = useMemo(() => packLayout(canonical, columnCount), [canonical, columnCount]);
  displayLayoutRef.current = displayLayout;
  const previewLayout = useMemo(() => interaction ? packWithPinned(displayLayout, columnCount, interaction.draft) : displayLayout, [columnCount, displayLayout, interaction]);
  const bumpedBlockIds = useMemo(() => {
    if (interaction?.origin !== "pointer") {
      return /* @__PURE__ */ new Set();
    }
    const originalItems = new Map(displayLayout.map((item) => [item.id, item]));
    return new Set(previewLayout.filter((item) => {
      const original = originalItems.get(item.id);
      return item.id !== interaction.id && original && (item.column !== original.column || item.row !== original.row);
    }).map((item) => item.id));
  }, [displayLayout, interaction, previewLayout]);
  const blocksById = useMemo(() => new Map((data?.blocks ?? []).map((block) => [block.id, block])), [data]);
  const announce = useCallback(async (key, value) => {
    setAnnouncement(await getString(key, "my", value));
  }, []);
  const start = useCallback((id, mode, origin = "keyboard") => {
    const item = displayLayout.find((candidate) => candidate.id === id);
    const block = blocksById.get(id);
    if (!item || !block) {
      return;
    }
    const next = { id, mode, origin, original: item, draft: item, before: displayLayout };
    interactionRef.current = next;
    setInteraction(next);
    void announce(mode === "move" ? "dashboardmovebegin" : "dashboardresizebegin", block.title);
  }, [announce, blocksById, displayLayout]);
  const shift = useCallback((horizontal, vertical) => {
    setInteraction((current) => {
      if (!current) {
        return current;
      }
      const draft = { ...current.draft };
      if (current.mode === "move") {
        draft.column = Math.max(0, Math.min(columnCount - draft.columns, draft.column + horizontal));
        draft.row = Math.max(0, draft.row + vertical);
      } else {
        const nextColumns = draft.columns + horizontal;
        const nextRows = draft.rows + vertical;
        if (nextColumns < MIN_COLUMNS || nextRows < MIN_ROWS) {
          void announce("dashboardminimumsize", `${MIN_COLUMNS} \xD7 ${MIN_ROWS}`);
          return current;
        }
        draft.columns = Math.min(columnCount - draft.column, nextColumns);
        draft.rows = nextRows;
      }
      const next = { ...current, draft };
      interactionRef.current = next;
      return next;
    });
  }, [announce, columnCount]);
  const commit = useCallback(async () => {
    const current = interactionRef.current;
    const currentData = dataRef.current;
    const currentDisplay = displayLayoutRef.current;
    const currentCanonical = canonicalRef.current;
    const currentColumns = columnCountRef.current;
    if (!current || !currentData) {
      return;
    }
    const derived = packWithPinned(currentDisplay, currentColumns, current.draft);
    const disturbed = disturbedCount(current.before, derived, current.id);
    const next = writeBack(currentCanonical, derived, current.id);
    setSaving(true);
    try {
      await updateDashboard("save", siteDefault, next);
      setCanonical(next);
      canonicalRef.current = next;
      const item = derived.find((candidate) => candidate.id === current.id);
      await announce(current.mode === "move" ? "dashboardmovecommitted" : "dashboardresizecommitted", {
        row: item.row + 1,
        column: item.column + 1,
        columns: item.columns,
        rows: item.rows,
        disturbed
      });
      interactionRef.current = null;
      setInteraction(null);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : String(caught));
    } finally {
      setSaving(false);
    }
  }, [announce, siteDefault]);
  const cancel = useCallback(() => {
    if (interaction) {
      void announce("dashboardoperationdiscarded");
    }
    interactionRef.current = null;
    setInteraction(null);
  }, [announce, interaction]);
  const keyDown = useCallback((event, id, mode) => {
    if (!interaction && (event.key === " " || event.key === "Enter")) {
      event.preventDefault();
      start(id, mode);
      return;
    }
    if (!interaction || interaction.id !== id || interaction.mode !== mode) {
      return;
    }
    if (event.key === "Escape") {
      event.preventDefault();
      cancel();
    } else if (event.key === " " || event.key === "Enter") {
      event.preventDefault();
      void commit();
    } else if (event.key.startsWith("Arrow")) {
      event.preventDefault();
      const directions = {
        ArrowLeft: [-1, 0],
        ArrowRight: [1, 0],
        ArrowUp: [0, -1],
        ArrowDown: [0, 1]
      };
      shift(...directions[event.key]);
    }
  }, [cancel, commit, interaction, shift, start]);
  const pointerDown = useCallback((event, id, mode) => {
    event.preventDefault();
    start(id, mode, "pointer");
    pointerRef.current = { x: event.clientX, y: event.clientY, moved: false };
    const origin = displayLayout.find((item) => item.id === id);
    if (!origin) {
      return;
    }
    const grid = gridRef.current;
    const cellWidth = grid ? (grid.getBoundingClientRect().width - GRID_GAP * (columnCount - 1)) / columnCount : 1;
    const columnStride = cellWidth + GRID_GAP;
    const rowStride = ROW_HEIGHT + GRID_GAP;
    const originalWidth = origin.columns * cellWidth + (origin.columns - 1) * GRID_GAP;
    const originalHeight = origin.rows * ROW_HEIGHT + (origin.rows - 1) * GRID_GAP;
    const move = /* @__PURE__ */ __name((pointerEvent) => {
      const pointer = pointerRef.current;
      if (!pointer) {
        return;
      }
      const deltaX = pointerEvent.clientX - pointer.x;
      const deltaY = pointerEvent.clientY - pointer.y;
      pointer.moved = pointer.moved || Math.abs(deltaX) >= 4 || Math.abs(deltaY) >= 4;
      const horizontal = Math.round(deltaX / columnStride);
      const vertical = Math.round(deltaY / rowStride);
      setInteraction((current) => {
        if (!current) {
          return current;
        }
        const draft = { ...origin };
        let drag;
        if (mode === "move") {
          draft.column = Math.max(0, Math.min(columnCount - draft.columns, origin.column + horizontal));
          draft.row = Math.max(0, origin.row + vertical);
          drag = {
            x: Math.max(
              -origin.column * columnStride,
              Math.min((columnCount - origin.column - origin.columns) * columnStride, deltaX)
            ),
            y: Math.max(-origin.row * rowStride, deltaY)
          };
        } else {
          draft.columns = Math.max(
            MIN_COLUMNS,
            Math.min(columnCount - draft.column, origin.columns + horizontal)
          );
          draft.rows = Math.max(MIN_ROWS, origin.rows + vertical);
          drag = {
            x: 0,
            y: 0,
            width: Math.max(cellWidth, Math.min(
              (columnCount - origin.column) * columnStride - GRID_GAP,
              originalWidth + deltaX
            )),
            height: Math.max(ROW_HEIGHT, originalHeight + deltaY),
            shrinking: deltaX < 0 || deltaY < 0
          };
        }
        const next = { ...current, draft, drag };
        interactionRef.current = next;
        return next;
      });
    }, "move");
    const cleanup = /* @__PURE__ */ __name(() => {
      window.removeEventListener("pointermove", move);
      window.removeEventListener("pointerup", up);
      window.removeEventListener("pointercancel", abort);
      pointerRef.current = null;
    }, "cleanup");
    const up = /* @__PURE__ */ __name(() => {
      const current = interactionRef.current;
      if (pointerRef.current?.moved && current && layoutChanged(current.original, current.draft)) {
        void commit();
      } else if (!pointerRef.current?.moved) {
        setInteraction((previous) => {
          if (!previous) {
            return previous;
          }
          const next = { ...previous, origin: "mouseclick" };
          interactionRef.current = next;
          return next;
        });
      } else {
        cancel();
      }
      cleanup();
    }, "up");
    const abort = /* @__PURE__ */ __name(() => {
      cleanup();
      cancel();
    }, "abort");
    window.addEventListener("pointermove", move);
    window.addEventListener("pointerup", up);
    window.addEventListener("pointercancel", abort);
  }, [cancel, columnCount, commit, displayLayout, start]);
  const remove = useCallback(async (id) => {
    if (!data) {
      return;
    }
    setSaving(true);
    try {
      await updateDashboard("remove", siteDefault, [], "", id);
      await load();
      await announce("dashboardblockremoved");
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : String(caught));
    } finally {
      setSaving(false);
    }
  }, [announce, data, load, siteDefault]);
  const add = useCallback(async (block) => {
    if (!data) {
      return;
    }
    setSaving(true);
    try {
      const result = await updateDashboard("add", siteDefault, [], block.name);
      const response = await load();
      if (response && palette && result.blockid) {
        const item = response.layout.find((candidate) => candidate.id === result.blockid);
        if (item && palette.column !== void 0 && palette.row !== void 0) {
          const pinned = {
            ...item,
            column: Math.min(palette.column, columnCount - Math.min(item.columns, columnCount)),
            row: palette.row,
            columns: Math.min(item.columns, columnCount)
          };
          const next = writeBack(
            response.layout,
            packWithPinned(packLayout(response.layout, columnCount), columnCount, pinned),
            item.id
          );
          await updateDashboard("save", siteDefault, next);
          setCanonical(next);
          canonicalRef.current = next;
        } else if (item && palette.position === "start") {
          const next = writeBack(
            response.layout,
            packWithPinned(
              packLayout(response.layout, columnCount),
              columnCount,
              { ...item, column: 0, row: 0 }
            ),
            item.id
          );
          await updateDashboard("save", siteDefault, next);
          setCanonical(next);
          canonicalRef.current = next;
        }
      }
      setPalette(null);
      await announce("dashboardblockadded", block.title);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : String(caught));
    } finally {
      setSaving(false);
    }
  }, [announce, columnCount, data, load, palette, siteDefault]);
  const reset = useCallback(async () => {
    if (!data) {
      return;
    }
    setSaving(true);
    try {
      await updateDashboard("reset", false);
      window.location.reload();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : String(caught));
    } finally {
      setSaving(false);
    }
  }, [data]);
  if (!data) {
    return error ? /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-status alert alert-danger", role: "alert", children: error }, void 0, false, {
      fileName: "public/my/js/esm/src/index.tsx",
      lineNumber: 455,
      columnNumber: 15
    }) : /* @__PURE__ */ jsxDEV(DashboardLoading, { label: loadingLabel }, void 0, false, {
      fileName: "public/my/js/esm/src/index.tsx",
      lineNumber: 456,
      columnNumber: 15
    });
  }
  const rows = Math.max(1, maxRow(previewLayout));
  const prospective = interaction && (layoutChanged(interaction.original, interaction.draft) || interaction.origin === "pointer" && interaction.mode === "resize" && interaction.drag?.shrinking) ? interaction.draft : void 0;
  return /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-app", "aria-busy": saving, children: [
    error && /* @__PURE__ */ jsxDEV("div", { className: "alert alert-danger", role: "alert", children: error }, void 0, false, {
      fileName: "public/my/js/esm/src/index.tsx",
      lineNumber: 464,
      columnNumber: 19
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "visually-hidden", "aria-live": "polite", "aria-atomic": "true", children: announcement }, void 0, false, {
      fileName: "public/my/js/esm/src/index.tsx",
      lineNumber: 465,
      columnNumber: 9
    }),
    data.editing && /* @__PURE__ */ jsxDEV(
      DashboardScopeBanner,
      {
        siteDefault,
        caneditotherscope: data.caneditotherscope,
        urls: data.urls,
        labels: data.labels
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/index.tsx",
        lineNumber: 466,
        columnNumber: 26
      }
    ),
    data.editing && /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-toolbar", children: [
      /* @__PURE__ */ jsxDEV(Button, { variant: "secondary", label: data.labels.addblocktop, onClick: () => setPalette({ position: "start" }) }, void 0, false, {
        fileName: "public/my/js/esm/src/index.tsx",
        lineNumber: 473,
        columnNumber: 13
      }),
      !siteDefault && /* @__PURE__ */ jsxDEV(
        Button,
        {
          variant: "outline-danger",
          label: data.labels.reset,
          onClick: () => setConfirmAction({ type: "reset" })
        },
        void 0,
        false,
        {
          fileName: "public/my/js/esm/src/index.tsx",
          lineNumber: 474,
          columnNumber: 30
        }
      )
    ] }, void 0, true, {
      fileName: "public/my/js/esm/src/index.tsx",
      lineNumber: 472,
      columnNumber: 26
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: gridRef,
        className: "core-my-dashboard-grid",
        style: {
          gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))`,
          gridTemplateRows: `repeat(${rows}, ${ROW_HEIGHT}px)`
        },
        "data-columns": columnCount,
        children: [
          data.editing && Array.from(
            { length: rows },
            (_, row) => Array.from({ length: columnCount }, (__, column) => {
              const isProspective = Boolean(prospective && column >= prospective.column && column < prospective.column + prospective.columns && row >= prospective.row && row < prospective.row + prospective.rows);
              const isOccupied = previewLayout.some(
                (item) => column >= item.column && column < item.column + item.columns && row >= item.row && row < item.row + item.rows
              );
              return !isOccupied || isProspective ? /* @__PURE__ */ jsxDEV(
                GridCell,
                {
                  column,
                  row,
                  label: data.labels.emptycell,
                  positionLabel: data.labels.gridcell,
                  addLabel: data.labels.addblock,
                  prospective: isProspective,
                  onActivate: (targetColumn, targetRow) => setPalette({ column: targetColumn, row: targetRow })
                },
                `${column}:${row}`,
                false,
                {
                  fileName: "public/my/js/esm/src/index.tsx",
                  lineNumber: 498,
                  columnNumber: 59
                }
              ) : null;
            })
          ),
          previewLayout.map((item) => {
            const block = blocksById.get(item.id);
            if (!block) {
              return null;
            }
            return /* @__PURE__ */ jsxDEV(
              DashboardTile,
              {
                block,
                item,
                labels: data.labels,
                editing: data.editing,
                activeMode: interaction?.id === item.id ? interaction.mode : void 0,
                showControls: interaction?.id === item.id && interaction.origin !== "pointer",
                drag: interaction?.id === item.id ? interaction.drag : void 0,
                dragOrigin: interaction?.id === item.id ? interaction.original : void 0,
                shouldAnimatePosition: interaction?.origin === "pointer" && interaction.id !== item.id,
                isBumped: bumpedBlockIds.has(item.id),
                onStart: start,
                onKeyDown: keyDown,
                onPointerDown: pointerDown,
                onDirection: shift,
                onCommit: () => void commit(),
                onRemove: (id) => setConfirmAction({ type: "remove", id })
              },
              item.id,
              false,
              {
                fileName: "public/my/js/esm/src/index.tsx",
                lineNumber: 515,
                columnNumber: 24
              }
            );
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/my/js/esm/src/index.tsx",
        lineNumber: 480,
        columnNumber: 9
      }
    ),
    data.editing && /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-toolbar core-my-dashboard-toolbar--bottom", children: /* @__PURE__ */ jsxDEV(Button, { variant: "secondary", label: data.labels.addblockbottom, onClick: () => setPalette({ position: "end" }) }, void 0, false, {
      fileName: "public/my/js/esm/src/index.tsx",
      lineNumber: 537,
      columnNumber: 13
    }) }, void 0, false, {
      fileName: "public/my/js/esm/src/index.tsx",
      lineNumber: 536,
      columnNumber: 26
    }),
    palette && /* @__PURE__ */ jsxDEV(
      BlockPalette,
      {
        title: data.labels.addblock,
        closeLabel: data.labels.close,
        blocks: data.availableblocks,
        onSelect: (block) => void add(block),
        onClose: () => setPalette(null)
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/index.tsx",
        lineNumber: 539,
        columnNumber: 21
      }
    ),
    confirmAction && /* @__PURE__ */ jsxDEV(
      ConfirmationDialog,
      {
        title: confirmAction.type === "remove" ? data.labels.removeheading : data.labels.resetheading,
        message: confirmAction.type === "remove" ? data.labels.confirmremove : data.labels.confirmreset,
        confirmLabel: data.labels.confirm,
        cancelLabel: data.labels.cancel,
        onConfirm: () => {
          const action = confirmAction;
          setConfirmAction(null);
          if (action.type === "remove") {
            void remove(action.id);
          } else {
            void reset();
          }
        },
        onCancel: () => setConfirmAction(null)
      },
      void 0,
      false,
      {
        fileName: "public/my/js/esm/src/index.tsx",
        lineNumber: 546,
        columnNumber: 27
      }
    )
  ] }, void 0, true, {
    fileName: "public/my/js/esm/src/index.tsx",
    lineNumber: 463,
    columnNumber: 12
  });
}, "Dashboard");
var index_default = Dashboard;
export {
  index_default as default
};
//# sourceMappingURL=index.dev.js.map
