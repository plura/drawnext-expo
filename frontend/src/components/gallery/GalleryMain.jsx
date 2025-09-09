// src/components/gallery/GalleryMain.jsx
import { Button } from "@/components/ui/button";
import DrawingInfoCard from "@/components/drawings/DrawingInfoCard";

/**
 * GalleryMain (grid + pagination)
 * ---------------------------------------
 * Props:
 * - items: Array (rows from /api/drawings/list)
 * - notebooksById: Map<number, notebook>
 * - buildItemHref: (row) => string|null   // per-card link
 * - hasMore: boolean
 * - loading: boolean
 * - onLoadMore: () => void
 */
export default function GalleryMain({
  items = [],
  notebooksById,
  buildItemHref = () => null,
  hasMore = false,
  loading = false,
  onLoadMore,
}) {
  if (items.length === 0) {
    return <p className="text-sm text-muted-foreground">No results.</p>;
  }

  return (
    <>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        {items.map((row) => {
          const nb = notebooksById.get(Number(row.notebook_id));
          const to = buildItemHref(row);
          return (
            <DrawingInfoCard
              key={row.drawing_id}
              item={row}
              notebook={nb}
              to={to || undefined}
              hideNeighbors={false}
              className="hover:bg-gray-50"
            />
          );
        })}
      </div>

      {hasMore && (
        <div>
          <Button
            variant="outline"
            type="button"
            onClick={onLoadMore}
            disabled={loading}
          >
            {loading ? "Loading…" : "Load more"}
          </Button>
        </div>
      )}
    </>
  );
}
