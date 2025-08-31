import { useEffect, useMemo, useState } from "react";
import { useConfig } from "@/app/ConfigProvider";
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import Select from "@/components/form/Select";
import DrawingInfoCard from "@/components/drawings/DrawingInfoCard";

const PAGE_SIZE = 24;

export default function DrawingsList() {
  const { notebooks, loading: configLoading, error: configError } = useConfig();

  const [items, setItems] = useState([]);
  const [offset, setOffset] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // filters
  const [notebookId, setNotebookId] = useState(""); // '' => All
  const [sectionId, setSectionId] = useState("");   // '' => All
  const [page, setPage] = useState("");

  // lookups
  const notebooksById = useMemo(() => {
    const m = new Map();
    (notebooks || []).forEach((n) => m.set(Number(n.id), n));
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
        value: String(n.id),
        label: n.title || `Notebook ${n.id}`,
      })),
    [notebooks]
  );

  const sectionOptions = useMemo(
    () =>
      sectionsForNotebook.map((s) => ({
        value: String(s.id),
        label: `${s.position} - ${s.label || `Section ${s.position}`}`,
      })),
    [sectionsForNotebook]
  );

  function buildQuery(nextOffset = 0) {
    const params = new URLSearchParams();
    params.set("limit", String(PAGE_SIZE));
    params.set("offset", String(nextOffset));
    params.set("expand", "labels,user,thumb,neighbors"); // neighbors available for the card
    if (notebookId) params.set("notebook_id", String(notebookId));
    if (sectionId) params.set("section_id", String(sectionId));
    if (page) params.set("page", String(page));
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

  // initial load
  useEffect(() => {
    fetchPage(0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // re-fetch when filters change
  useEffect(() => {
    fetchPage(0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [notebookId, sectionId, page]);

  if (configLoading || (loading && items.length === 0)) {
    return <div className="p-4">Loading…</div>;
  }
  if (configError) {
    return <div className="p-4 text-red-600">Config failed: {String(configError)}</div>;
  }
  if (error && items.length === 0) {
    return <div className="p-4 text-red-600">{error}</div>;
  }

  return (
    <div className="p-4 space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Drawings</h1>
      </div>

      {/* Filters */}
      <Card className="flex flex-wrap items-end gap-3 p-3">
        <div className="w-44">
          <Select
            id="filter-notebook"
            label="Notebook"
            value={notebookId}
            onChange={(val) => {
              setNotebookId(val);
              setSectionId("");
            }}
            options={notebookOptions}
            placeholder="All"
          />
        </div>

        <div className="w-44">
          <Select
            id="filter-section"
            label="Section"
            value={sectionId}
            onChange={setSectionId}
            options={sectionOptions}
            disabled={!notebookId}
            placeholder="All"
          />
        </div>

        <div className="w-28">
          <label className="block text-xs text-muted-foreground mb-1">Page</label>
          <Input
            type="number"
            min={1}
            value={page}
            onChange={(e) => setPage(e.target.value)}
            placeholder="Any"
          />
        </div>

        <Button
          variant="outline"
          className="ml-auto"
          type="button"
          onClick={() => {
            setNotebookId("");
            setSectionId("");
            setPage("");
          }}
        >
          Clear filters
        </Button>
      </Card>

      {/* Grid list (compact cards that navigate) */}
      {items.length === 0 ? (
        <p className="text-sm text-muted-foreground">No results.</p>
      ) : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          {items.map((row) => {
            const nb = notebooksById.get(Number(row.notebook_id));
            return (
              <DrawingInfoCard
                key={row.drawing_id}
                item={row}
                notebook={nb}
                to={`/admin/drawings/${row.drawing_id}`}
                hideNeighbors
                className="hover:bg-gray-50"
              />
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {hasMore && (
        <div>
          <Button
            variant="outline"
            type="button"
            onClick={() => fetchPage(offset)}
            disabled={loading}
          >
            {loading ? "Loading…" : "Load more"}
          </Button>
        </div>
      )}
    </div>
  );
}
