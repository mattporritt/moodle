var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The site-wide Edit mode switch, rendered with the design system Switch component.
 *
 * @module     core/EditModeSwitch
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect } from "react";
import { Switch } from "@moodlehq/design-system";
import { requireAsync } from "@moodle/lms/core/amd";
function EditModeSwitch({ id, context, pageurl, sesskey, checked, label }) {
  useEffect(() => {
    let cancelled = false;
    requireAsync("core/edit_switch").then((editSwitch) => {
      if (!cancelled) {
        editSwitch.init(id);
      }
      return void 0;
    });
    return () => {
      cancelled = true;
    };
  }, [id]);
  return /* @__PURE__ */ jsxDEV(
    Switch,
    {
      id,
      name: "setmode",
      variant: "enable",
      labelSide: "start",
      label,
      defaultChecked: checked,
      "data-context": context,
      "data-pageurl": pageurl,
      "data-sesskey": sesskey
    },
    void 0,
    false,
    {
      fileName: "public/lib/js/esm/src/EditModeSwitch.tsx",
      lineNumber: 78,
      columnNumber: 9
    },
    this
  );
}
__name(EditModeSwitch, "EditModeSwitch");
export {
  EditModeSwitch as default
};
//# sourceMappingURL=EditModeSwitch.dev.js.map
