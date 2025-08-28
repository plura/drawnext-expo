// src/features/test/Test.jsx
import React, { useEffect, useState } from "react";
import { useConfig } from "@/app/ConfigProvider";
import DrawingInfoCard from "@/components/drawings/DrawingInfoCard";
import SectionTitle from "@/components/typography/SectionTitle";

export default function Test() {
  const { notebooks, loading: configLoading, error: configError } = useConfig();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        setError(null);
        // Include labels + thumb for richer UI
        const res = await fetch("/api/drawings/list?limit=20&expand=user,neighbors,labels,thumb");
        const json = await res.json();
        if (!res.ok || json?.status === "error") {
          throw new Error(json?.message || `Request failed (${res.status})`);
        }
        if (!cancelled) setItems(Array.isArray(json?.data) ? json.data : []);
      } catch (e) {
        if (!cancelled) setError(e?.message || "Failed to load drawings");
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true };
  }, []);

  if (configLoading || loading) return <div className="p-4">Loading…</div>;
  if (configError) return <div className="p-4 text-red-600">Config failed: {String(configError)}</div>;
  if (error) return <div className="p-4 text-red-600">{error}</div>;

  return (
    <div className="p-4">
      <SectionTitle title="Latest drawings (dev)" />
      {items.length === 0 ? (
        <p className="mt-2 text-sm text-muted-foreground">No drawings yet.</p>
      ) : (
        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((item) => {
            const notebook = notebooks?.find((n) => Number(n.id) === Number(item.notebook_id));
            return (
              <DrawingInfoCard key={item.drawing_id} item={item} notebook={notebook} />
            );
          })}
        </div>
      )}
    </div>
  );
}
