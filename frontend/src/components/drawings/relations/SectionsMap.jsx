// src/components/drawings/relations/SectionsMap.jsx
/**
 * SectionsMap
 * -----------
 * Renders ALL notebook sections (ordered by position). For each section:
 * - Put the author's drawing if it's that section (current: true)
 * - Otherwise, put the first linked drawing from data.neighbors whose section matches
 * - Otherwise, show a neutral placeholder
 *
 * Props:
 * - loading   : boolean
 * - data      : drawing object (must include { section_id | id, neighbors? })
 * - notebook  : object with { sections: [{ section_id, position, label }, ...] }
 *
 * Data shaping is intentionally minimal and local to this component.
 */

import React, { useMemo } from "react";
import Card from "@/components/cards/Card";
import { Skeleton } from "@/components/ui/skeleton";
import SectionsMapItem from "./SectionsMapItem";

function formatData(sections, drawingData) {
  const list = Array.isArray(sections) ? sections : [];
  return list.map((section) => {
    // Base shape for each section row
    const obj = {
      section_id: Number(section.section_id || section.id),
      position: Number(section.position),
      label: section.label ?? `Section ${section.position ?? ""}`,
    };

    // Current (author's) section
    if (drawingData && Number(drawingData.section_id) === obj.section_id) {
      obj.current = true;
      obj.item = drawingData;
      return obj;
    }

    // Otherwise, pick the first linked item that belongs to this section
    const linked = Array.isArray(drawingData?.neighbors) ? drawingData.neighbors : [];
    const item = linked.find((n) => Number(n.section_id) === obj.section_id);
    if (item) obj.item = item;

    return obj;
  });
}

export default function SectionsMap({ loading, data, notebook, className = "" }) {
  // Build entries; derive `sections` *inside* useMemo to satisfy exhaustive-deps.
  const entries = useMemo(() => {
    const sections = Array.isArray(notebook?.sections) ? notebook.sections : [];
    return formatData(sections, data);
  }, [notebook, data]);

  if (loading) {
    return (
      <div className={`space-y-3 ${className}`}>
        <Skeleton className="h-10 w-full rounded" />
        <Skeleton className="h-10 w-full rounded" />
        <Skeleton className="h-10 w-full rounded" />
      </div>
    );
  }

  if (!entries.length) {
    return <div className={className}>No sections available.</div>;
  }

  return (
    <div className={`flex flex-col ${className}`}>
      {entries.map((entry) => (
        <SectionsMapItem key={entry.section_id} data={entry} className="flex-1 min-h-0" />
      ))}
    </div>
  );
}
