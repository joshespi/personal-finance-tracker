# Refactor plan: retheme follow-ups (2026-11-26 review)

## Semantic accent/neutral palette instead of aliasing `indigo`/`gray`

**Why:** The teal/sage retheme works by redefining Tailwind's `indigo` and `gray` scales in `src/tailwind.config.js`. Every `indigo-*` class in the app now renders teal, so class names lie about the color, and anyone adding a "real" indigo or Tailwind gray will get the wrong one. Hex literals for the same palette are also scattered through `app.css`, Blade arbitrary values (`text-[#a9dbce]`, `bg-[#315e5f]`, …) and chart JS.

**Where:**
- `src/tailwind.config.js`: `theme.extend.colors.indigo` / `gray`
- Every Blade view that uses `indigo-*` / `gray-*` (most of `src/resources/views/**`)
- `src/resources/css/app.css`: `theme(colors.indigo.*)` / `theme(colors.gray.*)` and raw hexes (`.engraved`, masthead colors)
- Arbitrary-value hexes in `layouts/navigation.blade.php`, `home.blade.php`, `dashboard.blade.php`
- `src/resources/js/chart-utils.js`: `ACCENT`, `accentFill`, `themeColors()` hexes, tooltip defaults, plus `calendarColor` shades still on the old Tailwind palette
- `src/public/manifest.json` and `layouts/app.blade.php` `theme-color` (`#205c61`)

**Proposed approach:**
1. Add `accent` (current teal scale) and `neutral` (current sage scale) to `tailwind.config.js`, plus a small `brand` group for the masthead/engraved colors (`ink`, `cream`, `mint`, `hover`).
2. Codemod `indigo-` → `accent-` and `gray-` → `neutral-` across views/CSS (mechanical sed, then eyeball the diff).
3. Replace arbitrary-value hexes with the `brand-*` tokens.
4. Remove the `indigo`/`gray` overrides so the stock scales are back to stock.
5. Optionally feed chart JS from CSS custom properties so JS and Tailwind share one source.

**Risks / open questions:**
- Large mechanical diff; Livewire/Alpine `:class` strings and PHP-built class strings (e.g. `sidebar-link.blade.php` `$state`, `activateBtn` in chart-utils.js toggling `bg-indigo-600`) must be included or they silently lose color. Tailwind's `content` globs must cover JS that builds class names.
- Keep `gray` as an alias during transition, or cut over in one go?
- Does `calendarColor` (heatmap) move to the new palette too, or stay red/green for clarity?

**Suggested scope:** In: config, all views, app.css, chart JS class toggles, the arbitrary hexes. Out: any visual redesign; colors should render identically before/after (screenshot compare dashboard, home, a guest page, a report page in light + dark).

## Caveman-review items parked with the palette work

- **Calendar heatmap shades** — `calendarColor()` in `src/resources/js/chart-utils.js` still uses old zinc/gray hexes (`#27272a`, `#f3f4f6`, `#4b5563`, `#e5e7eb`) plus `CALENDAR_GREEN_SHADES`/`CALENDAR_RED_SHADES`; move the no-data/flat cells onto the new neutral scale (keep red/green for gain/loss). Do it as part of step 5 above.
- **`.workspace-content > .py-12` coupling** — `src/resources/css/app.css` overrides page padding by matching the `py-12` utility on each page's root div. Renaming that utility on a page silently drops the override. Fix: give page roots a semantic wrapper (e.g. a `<x-page>` component or `.page-body` class) and set padding there, or just change `py-12` at the source across views during the codemod.
