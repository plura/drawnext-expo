// src/features/gallery/components/DynamicGalleryItem.jsx
import React from "react";
import Card from "@/components/cards/Card";
import DrawingImage from "@/components/drawings/DrawingImage";
import { Badge } from "@/components/ui/badge";

export default function DynamicGalleryItem({ data }) {
  const img = data.thumb_url || data.preview_url || null;
  const sectionText = data.section_label || `Section ${data.section_id}`;
  return (
    <Card className="p-0 overflow-hidden relative">
      <DrawingImage src={img} alt={`Drawing #${data.drawing_id}`} notebook={null} rounded={false} />
      <div className="absolute left-2 top-2 flex gap-1">
        {data.user_email && <Badge variant="secondary">{data.user_email}</Badge>}
        <Badge variant="secondary">{sectionText}</Badge>
        <Badge variant="secondary">p. {data.page}</Badge>
      </div>
      {/* neighbors (if any) */}
      {Array.isArray(data.neighbors) && data.neighbors.length > 0 && (
        <div className="absolute bottom-0 left-0 right-0 bg-black/50 px-2 py-1">
          <div className="flex flex-wrap gap-1 text-[11px] text-white/90">
            {data.neighbors.map((n) => (
              <span key={`${n.section_id}-${n.page}`} className="inline-flex items-center gap-1">
                <Badge variant="secondary">
                  {(n.section_label || `S${n.section_id}`)} · p.{n.page}
                </Badge>
              </span>
            ))}
          </div>
        </div>
      )}
    </Card>
  );
}
