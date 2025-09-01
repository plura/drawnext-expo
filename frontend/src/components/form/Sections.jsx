// src/components/form/Sections.jsx
import SectionGroup from "@/components/form/SectionGroup";

/**
 * Sections
 * Minimal wrapper that renders a stack of SectionGroup blocks.
 *
 * Props
 * - sections: Array<{ id, position, label? }>
 * - notebookId: number
 * - primarySectionId: number|null
 * - page: string|number (primary page value)
 * - neighborPages: Record<sectionIdString, string>  // e.g. { "12": "4" }
 * - maxPages: number|null
 * - onSelectPrimary(sectionId: number): void
 * - onChangePrimaryPage(pageNum: number): void
 * - onChangeNeighborPage(sectionId: number, pageNum: number): void
 * - className?: string
 * - excludeDrawingId?: number   // NEW, optional (used in admin edit)
 */
export default function Sections({
  sections,
  notebookId,
  primarySectionId,
  page,
  neighborPages,
  maxPages,
  onSelectPrimary,
  onChangePrimaryPage,
  onChangeNeighborPage,
  className = "space-y-3",
  excludeDrawingId, // NEW
}) {
  if (!Array.isArray(sections) || sections.length === 0) {
    return null;
  }

  // sort by position (asc) and build the stack
  const sorted = [...sections].sort(
    (a, b) => Number(a.position) - Number(b.position)
  );

  return (
    <div className={className}>
      {sorted.map((sec) => {
        const label = `${sec.position} - ${sec.label || `Section ${sec.position}`}`;
        const isPrimary =
          primarySectionId != null && Number(primarySectionId) === Number(sec.id);
        const neighborVal = neighborPages?.[String(sec.id)] ?? "";

        return (
          <SectionGroup
            key={sec.id}
            section={sec}
            label={label}
            isPrimary={primarySectionId != null ? isPrimary : undefined}
            maxPages={maxPages}
            primaryPage={isPrimary ? page : ""}
            neighborPage={!isPrimary ? neighborVal : ""}
            onSelectPrimary={onSelectPrimary}
            onChangePrimaryPage={onChangePrimaryPage}
            onChangeNeighborPage={(val) => onChangeNeighborPage(sec.id, val)}
            notebookId={notebookId}
            primarySectionId={primarySectionId}
            probeEnabled={true}
            excludeDrawingId={excludeDrawingId}   // NEW, threaded down
          />
        );
      })}
    </div>
  );
}
