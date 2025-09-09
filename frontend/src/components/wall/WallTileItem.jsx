// src/components/wall/WallTileItem.jsx
/**
 * WallTileItem
 * ------------
 * Pure renderer for a single drawing in a tile.
 * - No animations here; Framer animates the wrapper in WallTileItems.
 * - Keep markup minimal; ensure the <img> fills the tile area nicely.
 */
export default function WallTileItem({ item }) {
  const src = item?.thumb_url || item?.preview_url || item?.image_url || "";
  const alt = `Drawing #${item?.drawing_id ?? ""}`;

  return (
    <img
      src={src}
      alt={alt}
      loading="eager"
      decoding="async"
      className="block w-full h-full object-cover"
      draggable={false}
    />
  );
}
