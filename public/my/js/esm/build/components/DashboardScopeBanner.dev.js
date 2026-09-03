var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Editing-scope indicator and switch CTA for the dashboard grid editor.
 *
 * @module     core_my/components/DashboardScopeBanner
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Badge, Link } from "@moodlehq/design-system";
const DashboardScopeBanner = /* @__PURE__ */ __name(({ siteDefault, caneditotherscope, urls, labels }) => /* @__PURE__ */ jsxDEV("div", { className: "core-my-dashboard-scope", children: [
  /* @__PURE__ */ jsxDEV(
    Badge,
    {
      variant: siteDefault ? "warning" : "info",
      subtle: true,
      pill: true,
      label: siteDefault ? labels.scopesitedefault : labels.scopeown
    },
    void 0,
    false,
    {
      fileName: "public/my/js/esm/src/components/DashboardScopeBanner.tsx",
      lineNumber: 29,
      columnNumber: 9
    }
  ),
  caneditotherscope && /* @__PURE__ */ jsxDEV(
    Link,
    {
      variant: "secondary",
      href: siteDefault ? urls.ownpage : urls.sitedefault,
      label: siteDefault ? labels.switchtoown : labels.switchtositedefault
    },
    void 0,
    false,
    {
      fileName: "public/my/js/esm/src/components/DashboardScopeBanner.tsx",
      lineNumber: 35,
      columnNumber: 31
    }
  )
] }, void 0, true, {
  fileName: "public/my/js/esm/src/components/DashboardScopeBanner.tsx",
  lineNumber: 28,
  columnNumber: 5
}), "DashboardScopeBanner");
var DashboardScopeBanner_default = DashboardScopeBanner;
export {
  DashboardScopeBanner_default as default
};
//# sourceMappingURL=DashboardScopeBanner.dev.js.map
