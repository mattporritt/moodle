var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Pure responsive dashboard layout helpers.
 *
 * @module     core_my/layout
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const MAX_COLUMNS = 6;
const SIX_COLUMN_THRESHOLD = 2100;
const FOUR_COLUMN_THRESHOLD = 920;
const TWO_COLUMN_THRESHOLD = 690;
const ROW_HEIGHT = 96;
const GRID_GAP = 16;
const MIN_COLUMNS = 1;
const MIN_ROWS = 2;
const columnsForWidth = /* @__PURE__ */ __name((width) => {
  if (width >= SIX_COLUMN_THRESHOLD) {
    return 6;
  }
  if (width >= FOUR_COLUMN_THRESHOLD) {
    return 4;
  }
  if (width >= TWO_COLUMN_THRESHOLD) {
    return 2;
  }
  return 1;
}, "columnsForWidth");
const fits = /* @__PURE__ */ __name((occupied, column, row, columns, rows) => {
  for (let y = row; y < row + rows; y++) {
    for (let x = column; x < column + columns; x++) {
      if (occupied.has(`${x}:${y}`)) {
        return false;
      }
    }
  }
  return true;
}, "fits");
const occupy = /* @__PURE__ */ __name((occupied, item) => {
  for (let y = item.row; y < item.row + item.rows; y++) {
    for (let x = item.column; x < item.column + item.columns; x++) {
      occupied.add(`${x}:${y}`);
    }
  }
}, "occupy");
const packInOrder = /* @__PURE__ */ __name((items, columnCount) => {
  const occupied = /* @__PURE__ */ new Set();
  return items.map((item) => {
    const columns = Math.min(item.columns, columnCount);
    let placed = null;
    for (let row = 0; row < 1e4 && !placed; row++) {
      for (let column = 0; column + columns <= columnCount; column++) {
        if (fits(occupied, column, row, columns, item.rows)) {
          placed = {
            ...item,
            column,
            row,
            columns,
            sourceColumns: item.sourceColumns ?? item.columns
          };
          break;
        }
      }
    }
    if (!placed) {
      throw new Error("Unable to place dashboard block.");
    }
    occupy(occupied, placed);
    return placed;
  });
}, "packInOrder");
const packLayout = /* @__PURE__ */ __name((items, columnCount) => packInOrder(
  [...items].sort(
    (left, right) => left.row - right.row || left.column - right.column || left.id - right.id
  ),
  columnCount
), "packLayout");
const packWithPinned = /* @__PURE__ */ __name((items, columnCount, pinned) => {
  const columns = Math.min(pinned.columns, columnCount);
  const safePinned = {
    ...pinned,
    column: Math.max(0, Math.min(pinned.column, columnCount - columns)),
    row: Math.max(0, pinned.row),
    columns,
    rows: Math.max(MIN_ROWS, pinned.rows)
  };
  const occupied = /* @__PURE__ */ new Set();
  occupy(occupied, safePinned);
  const result = [safePinned];
  const remaining = items.filter((item) => item.id !== pinned.id).sort((left, right) => left.row - right.row || left.column - right.column || left.id - right.id);
  for (const item of remaining) {
    const columns2 = Math.min(item.columns, columnCount);
    let placed = null;
    for (let row = 0; row < 1e4 && !placed; row++) {
      for (let column = 0; column + columns2 <= columnCount; column++) {
        if (fits(occupied, column, row, columns2, item.rows)) {
          placed = { ...item, column, row, columns: columns2 };
          break;
        }
      }
    }
    if (!placed) {
      throw new Error("Unable to place dashboard block.");
    }
    occupy(occupied, placed);
    result.push(placed);
  }
  return result;
}, "packWithPinned");
const writeBack = /* @__PURE__ */ __name((canonical, derived, resizedId) => {
  const original = new Map(canonical.map((item) => [item.id, item]));
  const restored = [...derived].sort((left, right) => left.row - right.row || left.column - right.column || left.id - right.id).map((item) => ({
    ...item,
    columns: item.id === resizedId ? item.columns : item.sourceColumns ?? original.get(item.id)?.columns ?? item.columns,
    sourceColumns: void 0
  }));
  return packInOrder(restored, MAX_COLUMNS).map((item) => ({
    id: item.id,
    column: item.column,
    row: item.row,
    columns: item.columns,
    rows: item.rows
  }));
}, "writeBack");
const disturbedCount = /* @__PURE__ */ __name((before, after, activeId) => {
  const positions = new Map(before.map((item) => [item.id, `${item.column}:${item.row}`]));
  return after.filter((item) => item.id !== activeId && positions.get(item.id) !== `${item.column}:${item.row}`).length;
}, "disturbedCount");
const maxRow = /* @__PURE__ */ __name((items) => items.reduce(
  (maximum, item) => Math.max(maximum, item.row + item.rows),
  0
), "maxRow");
export {
  FOUR_COLUMN_THRESHOLD,
  GRID_GAP,
  MAX_COLUMNS,
  MIN_COLUMNS,
  MIN_ROWS,
  ROW_HEIGHT,
  SIX_COLUMN_THRESHOLD,
  TWO_COLUMN_THRESHOLD,
  columnsForWidth,
  disturbedCount,
  maxRow,
  packInOrder,
  packLayout,
  packWithPinned,
  writeBack
};
//# sourceMappingURL=layout.dev.js.map
