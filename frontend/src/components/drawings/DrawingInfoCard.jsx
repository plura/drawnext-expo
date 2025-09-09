import { Link } from "react-router-dom";
import Card from "@/components/cards/Card";
import { Badge } from "@/components/ui/badge";
import DrawingImage from "@/components/drawings/DrawingImage";

/**
 * item = {
 *   drawing_id, preview_url, thumb_url?,
 *   notebook_id, section_id, page, created_at,
 *   user_email?, neighbors?: [{ section_id, section_label?, page }]
 * }
 * notebook = { id, title, color_bg?, color_text?, sections?, aspect? }
 *
 * Extra props:
 * - to?: string              -> wraps the card in a <Link> if provided
 * - hideNeighbors?: boolean  -> suppress neighbors section for compact grids
 * - className?: string       -> extra classes to Card wrapper
 */
export default function DrawingInfoCard({
  item,
  notebook,
  to,
  hideNeighbors = false,
  className,
}) {
  const neighbors = Array.isArray(item?.neighbors) ? item.neighbors : [];
  const imageUrl = item?.thumb_url || item?.preview_url || null;

  const findSectionLabel = (id) => {
    const fromItem = neighbors.find(
      (n) => Number(n.section_id) === Number(id)
    )?.section_label;
    if (fromItem) return fromItem;
    const s = notebook?.sections?.find((sec) => Number(sec.id) === Number(id));
    return s?.label ?? String(id);
  };
  const sectionLabel = findSectionLabel(item?.section_id);

  const Inner = (
    <Card className={`relative ${className || ""}`}>
      {/* Notebook badge */}
      {notebook?.title && (
        <Badge
          className="absolute top-6 left-6"
          style={{
            backgroundColor: notebook?.color_bg
              ? `#${notebook.color_bg}`
              : undefined,
            color: notebook?.color_text ? `#${notebook.color_text}` : undefined,
          }}
        >
          {notebook.title}
        </Badge>
      )}

      {/* Image with global (or notebook) aspect ratio */}
      <div className="mb-3">
        <DrawingImage
          src={imageUrl}
          alt={`Drawing #${item.drawing_id}`}
          notebook={notebook}
        />
      </div>

      <div className="flex gap-6 text-xs">

        {/* Details */}
        <div className="flex gap-6">
          {/*         <div className="min-w-[6rem]">
          <div className="text-muted-foreground">Email</div>
          <div className="font-medium break-all">{item?.user_email ?? "—"}</div>
        </div> */}
          <div>
            <div className="text-muted-foreground">Section</div>
            <div className="font-medium md:mt-2">{sectionLabel}</div>
          </div>
          <div>
            <div className="text-muted-foreground">Page</div>
            <div className="font-medium md:mt-2">{item?.page}</div>
          </div>
        </div>

        {/* Neighbors */}
        {!hideNeighbors && neighbors.length > 0 && (
          <div className="text-xs">
            <div className="mb-1 text-muted-foreground">Neighbor drawings</div>
            <div className="flex flex-wrap gap-2">
              {neighbors.map((n) => (
                <Badge key={`${n.section_id}-${n.page}`} variant="outline">
                  <span className="font-medium">
                    {n.section_label || findSectionLabel(n.section_id)}
                  </span>
                  <span className="" aria-hidden="true">|</span>
                  <span>p. {n.page}</span>
                </Badge>
              ))}
            </div>
          </div>
        )}
      </div>
    </Card>
  );

  return to ? (
    <Link to={to} className="block hover:bg-gray-50 rounded-xl transition">
      {Inner}
    </Link>
  ) : (
    Inner
  );
}
