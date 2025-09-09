// src/components/drawings/relations/DrawingRelations.jsx
/**
 * DrawingRelations
 * ----------------
 * Two-pane composition:
 *  - LEFT: <SectionsMap/> — structural view of ALL notebook sections (in position order).
 *           It receives the raw drawing (with `neighbors`) and the resolved `notebook`,
 *           and shows the author's drawing in its section plus any linked drawings in
 *           their respective sections. Sections without content show a neutral placeholder.
 *  - RIGHT: <RelatedByNeighbors/> — drawings that reference this drawing (reverse neighbors).
 *
 * Notes in this version:
 * - Uses useConfig() to read notebooks; passes the matching notebook directly to SectionsMap.
 * - SectionsMap API is simplified to: { loading, data, notebook } — all formatting is internal.
 * - Right pane uses /api/drawings/related and is rendered ONLY if there are related items.
 * - No per-neighbor fetching here; SectionsMap uses `data.neighbors` directly.
 * - Neutral Tailwind/shadcn styling only.
 */
import clsx from "clsx";
import { useEffect, useMemo, useState } from "react";
import { useConfig } from "@/app/ConfigProvider";
import SectionsMap from "./SectionsMap";
import RelatedByNeighbors from "./RelatedByNeighbors";
import { Skeleton } from "@/components/ui/skeleton";

export default function DrawingRelations({ drawingId, className = "" }) {
  // ---- App-wide config ----
  const { notebooks, loading: loadingCfg } = useConfig();

  // ---- Drawing (server truth) ----
  const [drawingData, setDrawingData] = useState(null);
  const [loadingDrawing, setLoadingDrawing] = useState(true); // fetch immediately
  const [errorDrawing, setErrorDrawing] = useState(null);

  // ---- Reverse neighbors (right pane) ----
  const [relatedRows, setRelatedRows] = useState([]);
  const [loadingRelated, setLoadingRelated] = useState(false); // only true during fetch
  const [errorRelated, setErrorRelated] = useState(null);

  // Fetch the drawing (prefer single-view endpoint; fallback to list), with neighbors+thumbs for SectionsMap.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoadingDrawing(true);
        setErrorDrawing(null);

        const expand = "labels,user,thumb,neighbors";
        let res = await fetch(`/api/drawings/${drawingId}?expand=${expand}`);
        if (res.status === 404) {
          res = await fetch(`/api/drawings/list?id=${drawingId}&limit=1&expand=${expand}`);
        }

        const json = await res.json();
        if (!res.ok || json?.status === "error") {
          throw new Error(json?.message || `Failed (${res.status})`);
        }
        const row = Array.isArray(json?.data) ? json.data[0] : json?.data;
        if (!cancelled) setDrawingData(row || null);
      } catch (e) {
        if (!cancelled) setErrorDrawing(e?.message || "Failed to load drawing");
      } finally {
        if (!cancelled) setLoadingDrawing(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [drawingId]);

  // Resolve the matching notebook from config (passed directly to SectionsMap)
  const notebook = useMemo(() => {
    if (!drawingData || !Array.isArray(notebooks)) return null;
    const nid = Number(drawingData.notebook_id);
    return notebooks.find((n) => Number(n.notebook_id ?? n.id) === nid) || null;
  }, [drawingData, notebooks]);

  // Fetch reverse neighbors — render the right pane ONLY if we have items
  useEffect(() => {
    if (!drawingId) {
      setRelatedRows([]);
      return;
    }
    let cancelled = false;
    (async () => {
      try {
        setLoadingRelated(true);
        setErrorRelated(null);

        const params = new URLSearchParams({
          drawing_id: String(drawingId),
          expand: "thumb,user,labels",
          limit: "48",
        });
        const res = await fetch(`/api/drawings/related?${params.toString()}`);
        const json = await res.json();
        if (!res.ok || json?.status === "error") {
          throw new Error(json?.message || "Failed to load related drawings");
        }
        const rows = Array.isArray(json?.data) ? json.data : [];
        if (!cancelled) setRelatedRows(rows);
      } catch (e) {
        if (!cancelled) {
          setErrorRelated(e?.message || "Failed to load related drawings");
          setRelatedRows([]); // keep empty so the pane won't render
        }
      } finally {
        if (!cancelled) setLoadingRelated(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [drawingId]);

  return (
    <div className={`flex flex-col flex-1 min-h-0 ${className}`}>
      {/* LEFT: sections in position order — drawing + linked items + placeholders */}
      <Card className="flex flex-col flex-1 min-h-0 py-0 px-0">
        {loadingDrawing ? (
          <Skeleton className="h-48 w-full rounded" />
        ) : errorDrawing ? (
          <div className="text-sm">{errorDrawing}</div>
        ) : !drawingData ? (
          <div className="text-sm">Drawing not found.</div>
        ) : (
          <SectionsMap
            loading={loadingCfg}
            data={drawingData}
            notebook={notebook} 
			className="flex-1 min-h-0"
          />
        )}
      </Card>

      {/* RIGHT: only render if there are related neighbors */}
      {Array.isArray(relatedRows) && relatedRows.length > 0 && (
        <RelatedByNeighbors loading={loadingRelated} items={relatedRows} />
      )}
    </div>
  );
}
