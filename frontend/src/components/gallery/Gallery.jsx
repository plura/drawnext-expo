// src/components/gallery/Gallery.jsx
import { useEffect, useMemo, useState } from "react";
import { useConfig } from "@/app/ConfigProvider";
import GalleryFilter from "@/components/gallery/GalleryFilter";
import GalleryMain from "@/components/gallery/GalleryMain";

/**
 * Gallery (container)
 * ---------------------------------------
 * Props:
 * - pageSize?: number                    // default 24
 * - buildItemHref?: (row) => string|null // where a card should link (default: null)
 * - initialFilters?: {
 *     notebookId?: string,
 *     sectionId?: string,
 *     page?: string,
 *     onlyWithNeighbors?: boolean
 *   }
 * - filter?: boolean                     // NEW: show/hide the filter UI (default: true)
 *
 * Notes:
 * - Keeps same API contract as your DrawingsList (expand=labels,user,thumb,neighbors).
 *
 * Update — 2025-09-05:
 * - Added `onlyWithNeighbors` state and `has_neighbors=1` query param support.
 * Update — 2025-09-08:
 * - Added `filter` prop to toggle the filter bar (UI only).
 */
export default function Gallery({
  pageSize = 24,
  buildItemHref = () => null,
  initialFilters = {},
  filter: showFilter = true, // API prop is `filter`, internal var `showFilter`
}) {
  const { notebooks, loading: configLoading, error: configError } = useConfig();

  // data + pagination
  const [items, setItems] = useState([]);
  const [offset, setOffset] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // filters (controlled here, passed down)
  const [notebookId, setNotebookId] = useState(initialFilters.notebookId ?? "");
  const [sectionId, setSectionId] = useState(initialFilters.sectionId ?? "");
  const [page, setPage] = useState(initialFilters.page ?? "");
  const [onlyWithNeighbors, setOnlyWithNeighbors] = useState(
    !!initialFilters.onlyWithNeighbors
  );

  // lookups
  const notebooksById = useMemo(() => {
    const m = new Map();
    (notebooks || []).forEach((n) => m.set(Number(n.id ?? n.notebook_id), n));
    return m;
  }, [notebooks]);

  const sectionsForNotebook = useMemo(() => {
    if (!notebookId) return [];
    const nb = notebooksById.get(Number(notebookId));
    const list = nb?.sections || [];
    return [...list].sort((a, b) => Number(a.position) - Number(b.position));
  }, [notebookId, notebooksById]);

  const notebookOptions = useMemo(
    () =>
      (notebooks || []).map((n) => ({
        value: String(n.id ?? n.notebook_id),
        label: n.title || `Notebook ${n.id ?? n.notebook_id}`,
      })),
    [notebooks]
  );

  const sectionOptions = useMemo(
    () =>
      sectionsForNotebook.map((s) => ({
        value: String(s.id ?? s.section_id),
        label: `${s.position} - ${s.label || `Section ${s.position}`}`,
      })),
    [sectionsForNotebook]
  );

  function buildQuery(nextOffset = 0) {
    const params = new URLSearchParams();
    params.set("limit", String(pageSize));
    params.set("offset", String(nextOffset));
    params.set("expand", "labels,user,thumb,neighbors");
    if (notebookId) params.set("notebook_id", String(notebookId));
    if (sectionId) params.set("section_id", String(sectionId));
    if (page) params.set("page", String(page));
    if (onlyWithNeighbors) params.set("has_neighbors", "1");
    return `/api/drawings/list?${params.toString()}`;
  }

  async function fetchPage(nextOffset = 0) {
    setLoading(true);
    setError(null);
    try {
      const url = buildQuery(nextOffset);
      const res = await fetch(url);
      const json = await res.json();

      if (!res.ok || json?.status === "error") {
        throw new Error(json?.message || `Request failed (${res.status})`);
      }
      const data = Array.isArray(json?.data) ? json.data : [];
      const meta = json?.meta || {};

      setItems((prev) => (nextOffset === 0 ? data : [...prev, ...data]));
      setHasMore(!!meta?.has_more);
      setOffset(Number(meta?.next_offset || 0));
    } catch (e) {
      setError(e?.message || "Failed to load drawings");
    } finally {
      setLoading(false);
    }
  }

  // initial
  useEffect(() => {
    fetchPage(0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // re-fetch when filters change
  useEffect(() => {
    fetchPage(0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [notebookId, sectionId, page, onlyWithNeighbors, pageSize]);

  // loading / error states
  if (configLoading || (loading && items.length === 0)) {
    return <div className="p-4">Loading…</div>;
  }
  if (configError) {
    return (
      <div className="p-4 text-red-600">
        Config failed: {String(configError)}
      </div>
    );
  }
  if (error && items.length === 0) {
    return <div className="p-4 text-red-600">{error}</div>;
  }

  return (
    <div className="p-4 space-y-4">
      {showFilter && (
        <GalleryFilter
          notebookId={notebookId}
          setNotebookId={(val) => {
            setNotebookId(val);
            setSectionId(""); // reset section when notebook changes
          }}
          sectionId={sectionId}
          setSectionId={setSectionId}
          page={page}
          setPage={setPage}
          notebookOptions={notebookOptions}
          sectionOptions={sectionOptions}
          onlyWithNeighbors={onlyWithNeighbors}
          setOnlyWithNeighbors={setOnlyWithNeighbors}
          onClear={() => {
            setNotebookId("");
            setSectionId("");
            setPage("");
            setOnlyWithNeighbors(false); // reset switch on Clear
          }}
        />
      )}

      <GalleryMain
        items={items}
        notebooksById={notebooksById}
        buildItemHref={buildItemHref}
        hasMore={hasMore}
        loading={loading}
        onLoadMore={() => fetchPage(offset)}
      />
    </div>
  );
}
