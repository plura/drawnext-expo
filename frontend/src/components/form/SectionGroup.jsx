// src/components/form/SectionGroup.jsx
// Minimal, commented version used by Submit's SectionAndPagesStep.
// Keeps native <select> elements (no shadcn Select yet) and identical prop API.

/**
 * Props (unchanged):
 * - section:      { id, ... } the section object
 * - label:        string label to show (e.g., "1 – Space")
 * - isPrimary:    boolean | undefined
 *                  • true  → this row is the chosen primary section
 *                  • false → this row is a neighbor section
 *                  • undefined → no section chosen yet (all rows look neutral, but still selectable)
 * - maxPages:     number total pages in the notebook
 * - primaryPage:  string|number current primary page (only used when isPrimary === true)
 * - neighborPage: string|number current neighbor page (only used when isPrimary === false)
 * - onSelectPrimary(sectionId)
 * - onChangePrimaryPage(nextPageNumber)
 * - onChangeNeighborPage(nextPageNumber)
 */

import { cn } from "@/lib/utils";

export default function SectionGroup({
  section,
  label,
  isPrimary,
  maxPages,
  primaryPage,
  neighborPage,
  onSelectPrimary,
  onChangePrimaryPage,
  onChangeNeighborPage,
}) {
  // Clicking anywhere on the container selects this section as primary,
  // except when the click originated inside the controls column
  // (we mark that column with [data-no-select='true'] so it doesn't toggle).
  function handleContainerClick(e) {
    if (e.target.closest("[data-no-select='true']")) return;
    onSelectPrimary?.(section.id);
  }

  // Build page options [1..maxPages] once per render
  const pageOptions = Array.from({ length: maxPages }, (_, i) => i + 1);

  const selectable = typeof isPrimary === "boolean";
  const active = !!isPrimary;

  return (
    <div
      onClick={handleContainerClick}
      // Visual + semantic feedback:
      aria-pressed={selectable ? active : undefined}
      className={cn(
        "flex items-center gap-3 rounded-xl border p-3 transition cursor-pointer",
        selectable && active
          ? "bg-primary border-primary text-primary-foreground"
          : "bg-white hover:bg-gray-50 text-gray-900"
      )}
    >
      {/* Left: section label + hint */}
      <div className="min-w-0 flex-1">
        <div className="truncate text-base font-medium">{label}</div>
        <p
          className={cn(
            "mt-0.5 text-xs",
            selectable && active ? "text-blue-100" : "text-muted-foreground"
          )}
        >
          {selectable
            ? active
              ? "Selected section"
              : "Tap to select this as your section"
            : "Tap to select this as your section"}
        </p>
      </div>

      {/* Right: page controls
          Only rendered when we know if this row is primary or neighbor (selectable=true). */}
      {selectable && (
        <div className="flex flex-col items-end gap-1" data-no-select="true">
          {active ? (
            /* Primary: show "Your page" select */
            <>
              <label
                htmlFor={`page-primary-${section.id}`}
                className={cn(
                  "text-xs",
                  active ? "text-blue-100" : "text-muted-foreground"
                )}
              >
                Your page
              </label>
              <select
                id={`page-primary-${section.id}`}
                value={primaryPage ?? ""}
                onChange={(e) => onChangePrimaryPage?.(Number(e.target.value))}
                className={cn(
                  "w-24 rounded-md border text-sm p-1 bg-white text-gray-900",
                  active ? "border-primary" : "border-gray-300"
                )}
              >
                <option value="">Page</option>
                {pageOptions.map((num) => (
                  <option key={num} value={num}>
                    {num}
                  </option>
                ))}
              </select>
            </>
          ) : (
            /* Neighbor: show optional neighbor page select */
            <>
              <label
                htmlFor={`page-neighbor-${section.id}`}
                className="text-xs text-muted-foreground"
              >
                Neighbor page (optional)
              </label>
              <select
                id={`page-neighbor-${section.id}`}
                value={neighborPage ?? ""}
                onChange={(e) => onChangeNeighborPage?.(Number(e.target.value))}
                className={cn(
                  "w-28 rounded-md border text-sm p-1 bg-white text-gray-900",
                  active ? "border-primary" : "border-gray-300"
                )}
              >
                <option value="">Page</option>
                {pageOptions.map((num) => (
                  <option key={num} value={num}>
                    {num}
                  </option>
                ))}
              </select>
            </>
          )}
        </div>
      )}
    </div>
  );
}
