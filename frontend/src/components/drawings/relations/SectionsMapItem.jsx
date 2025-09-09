// src/components/drawings/relations/SectionsMapItem.jsx
/**
 * SectionsMapItem
 * ---------------
 * Displays one section row:
 * - Left: section label + position (+ "current" badge if applicable)
 * - Right: the drawing image (if present) or a neutral placeholder
 *
 * Props:
 * - data: {
 *     section_id: number
 *     position: number
 *     label: string
 *     current?: boolean
 *     item?: object // drawing row with thumb/preview URLs
 *   }
 * - aspectRatio?: string (CSS aspect-ratio value), default "1 / 1"
 */

import React from "react";
import DrawingImage from "@/components/drawings/DrawingImage";
import { Badge } from "@/components/ui/badge";

export default function SectionsMapItem({
  data,
  aspectRatio = "1 / 1",
  className = "",
}) {
  const { position, label, current, item } = data;

  return (
    <div className={`flex items-start justify-between ${className}`}>
      {/* Left meta */}
      <Badge variant={current ? "default" : "secondary"}>{label}</Badge>

      {/* Right: drawing (or placeholder) */}
      {item ? (
        <div className="overflow-hidden h-full" style={{ aspectRatio }}>
          <DrawingImage
            src={item.thumb_url ?? item.preview_url ?? null}
            alt={`Section ${position} drawing`}
            rounded
          />
        </div>
      ) : (
        <div className="rounded border border-dashed" style={{ aspectRatio }} />
      )}
    </div>
  );
}
