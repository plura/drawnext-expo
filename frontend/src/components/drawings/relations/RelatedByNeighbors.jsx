// src/components/drawings/relations/RelatedByNeighbors.jsx
/**
 * RelatedByNeighbors
 * ------------------
 * Shows the drawings that reference the current drawing (reverse links).
 *
 * Props:
 * - loading: boolean
 * - items:   array of drawing rows (already fetched by /api/drawings/related)
 *
 * Notes:
 * - Uses GalleryMain (presentational grid) directly to avoid re-fetching.
 * - No fallback Gallery: if none found, shows a neutral Alert.
 */

import React, { useMemo } from "react";
import { useConfig } from "@/app/ConfigProvider";
import GalleryMain from "@/components/gallery/GalleryMain";
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert";

export default function RelatedByNeighbors({ loading = false, items = [] }) {
  const { notebooks } = useConfig();

  const notebooksById = useMemo(() => {
    const m = new Map();
    (notebooks || []).forEach((n) => m.set(Number(n.id ?? n.notebook_id), n));
    return m;
  }, [notebooks]);

  const hasItems = Array.isArray(items) && items.length > 0;

  if (!hasItems) {
    return (
      <div className="p-4 space-y-3">
        <h2 className="text-base font-semibold">Related drawings</h2>
        <Alert>
          <AlertTitle>No related drawings</AlertTitle>
          <AlertDescription>
            There aren’t any submissions that reference this drawing yet.
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  return (
    <div className="p-4 space-y-3">
      <h2 className="text-base font-semibold">Related drawings</h2>

      <GalleryMain
        items={items}
        notebooksById={notebooksById}
        buildItemHref={(row) => `/relations/${row.drawing_id}`}
        hasMore={false}
        loading={!!loading}
        onLoadMore={() => {}}
      />
    </div>
  );
}
