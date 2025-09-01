// src/components/form/SectionGroup.jsx
import { cn } from "@/lib/utils";
import { useSlotProbe } from "@/components/form/hooks/useSlotProbe";
import StatusHint from "@/components/feedback/StatusHint";

/**
 * Added props:
 * - notebookId: number              // required for probing
 * - primarySectionId?: number|null  // required to probe neighbor rows
 * - probeEnabled?: boolean          // default true
 * - excludeDrawingId?: number       // optional, ignore this drawing in primary probe
 */
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
  notebookId,
  primarySectionId = null,
  probeEnabled = true,
  excludeDrawingId, // NEW
}) {
  function handleContainerClick(e) {
    if (e.target.closest("[data-no-select='true']")) return;
    onSelectPrimary?.(section.id);
  }

  const pageCount = Number(maxPages) > 0 ? Number(maxPages) : 0;
  const pageOptions = pageCount
    ? Array.from({ length: pageCount }, (_, i) => i + 1)
    : [];
  const selectable = typeof isPrimary === "boolean";
  const active = !!isPrimary;

  // --- probing (soft, contextual) ---
  const probeParams = active
    ? {
        mode: "primary",
        notebookId,
        sectionId: section.id,
        page: Number(primaryPage || 0),
        enabled: probeEnabled,
        excludeDrawingId, // NEW
      }
    : {
        mode: "neighbor",
        notebookId,
        primarySectionId,
        sectionId: section.id,
        page: Number(neighborPage || 0),
        enabled: probeEnabled,
      };

  const {
    loading: probing,
    result: probe,
    error: probeError,
  } = useSlotProbe(probeParams);

  // decide small line hint per mode
  let hint = null;
  let variant = "neutral";
  if (probeEnabled && selectable) {
    if (active) {
      const taken = !!probe?.primary?.taken;
      const takenBySelf = !!probe?.primary?.taken_by_self;
      console.log({probe, excludeDrawingId})
      if (Number(primaryPage) > 0) {
        if (probeError) {
          hint = "Couldn’t verify this slot.";
          variant = "warning";
        } else if (takenBySelf) {
          hint = "Current drawing slot.";
          variant = "neutral";
        } else {
          hint = taken
            ? "This slot already has a drawing."
            : "This slot is free.";
          variant = taken ? "error" : "success";
        }
      }
    } else {
      if (Number(neighborPage) > 0) {
        const warns = probe?.neighbors?.warnings || [];
        const notFound = warns.some(
          (w) =>
            w.code === "neighbor_not_found" &&
            Number(w.section_id) === Number(section.id) &&
            Number(w.page) === Number(neighborPage)
        );
        const invalid = warns.some(
          (w) =>
            w.code === "invalid_neighbor" &&
            Number(w.section_id) === Number(section.id)
        );
        const sameAsPrimary = warns.some(
          (w) =>
            w.code === "neighbor_cannot_be_primary_section" &&
            Number(w.section_id) === Number(section.id)
        );

        if (notFound) {
          hint = "No drawing found at that neighbor slot.";
          variant = "error";
        } else if (invalid) {
          hint = "Invalid neighbor page for this section.";
          variant = "error";
        } else if (sameAsPrimary) {
          hint = "Cannot use the primary section as a neighbor.";
          variant = "warning";
        } else if (probeError) {
          hint = "Couldn’t verify this neighbor.";
          variant = "warning";
        }
      }
    }
  }

  return (
    <div
      onClick={handleContainerClick}
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

        {/* contextual probe hint */}
        {hint && (
          <StatusHint
            variant={probing ? "loading" : variant}
            className="mt-1"
          >
            {probing ? "Checking…" : hint}
          </StatusHint>
        )}
      </div>

      {/* Right controls */}
      {selectable && (
        <div className="flex flex-col items-end gap-1" data-no-select="true">
          {active ? (
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
