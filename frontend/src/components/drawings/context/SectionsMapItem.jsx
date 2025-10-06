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
import clsx from "clsx";
import DrawingImage from "@/components/drawings/DrawingImage";
import { Badge } from "@/components/ui/badge";

export default function SectionsMapItem({
  data,
  aspectRatio = "1 / 1",
  className = "",
}) {
  const { position, label, current, item } = data;

  return (
    <div
      className={clsx(
		`sections-map-item gap-0 overflow-hidden`, 
		current ? "bg-black" : "", 
		current ? "text-white" : "text-gray-800",
		className
	)}>
      {/* Left meta */}
      {/* <Badge variant={current ? "default" : "secondary"} className="section-label">{label}</Badge> */}
      <div className="section-label text-xs font-medium w-14 p-2">{label}</div>

      {/* Right: drawing (or placeholder) */}
      {item ? (
          <DrawingImage
            src={item.thumb_url ?? item.preview_url ?? null}
            alt={`Section ${position} drawing`}
            rounded
          />
      ) : (
        <div className="rounded border border-dashed" style={{ aspectRatio }} />
      )}
    </div>
  );
}
