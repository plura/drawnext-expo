// src/features/gallery/components/DynamicGallery.jsx
import React, { useEffect, useMemo, useState } from "react";
import DynamicGalleryItem from "./DynamicGalleryItem";

export default function DynamicGallery({ dataURL, data, limit = 40 }) {
  const [items, setItems] = useState([]);
  const [err, setErr] = useState(null);
  const [loading, setLoading] = useState(true);

  // fetch only if no in-memory data supplied
  useEffect(() => {
    let cancelled = false;
    async function run() {
      try {
        setLoading(true);
        setErr(null);
        if (Array.isArray(data)) {
          if (!cancelled) setItems(data.slice(0, limit));
          return;
        }
        const res = await fetch(
          dataURL || `/api/drawings/list?limit=${limit}&expand=user,neighbors,labels,meta,thumb`
        );
        const json = await res.json();
        if (!res.ok || json?.status === "error")
          throw new Error(json?.message || `HTTP ${res.status}`);
        const rows = Array.isArray(json?.data) ? json.data : [];
        if (!cancelled) setItems(rows);
      } catch (e) {
        if (!cancelled) setErr(e.message || "Failed to load gallery");
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    run();
    return () => { cancelled = true; };
  }, [dataURL, data, limit]);

  const safeItems = useMemo(() => Array.isArray(items) ? items : [], [items]);

  if (loading && safeItems.length === 0) return <div>Loading…</div>;
  if (err && safeItems.length === 0) return <div className="text-red-600">{err}</div>;

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
      {safeItems.map((d) => (
        <DynamicGalleryItem key={d.drawing_id} data={d} />
      ))}
    </div>
  );
}
