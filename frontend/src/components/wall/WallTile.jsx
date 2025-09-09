// src/components/wall/WallTile.jsx
/**
 * WallTile
 * --------
 * A single cell in the grid.
 * - Provides a fixed-size viewport for animated enter/exit layers.
 * - No Tailwind animations; Framer handles motion in WallTileItems.
 */
import WallTileItems from "./WallTileItems";

export default function WallTile({ item, sizeClass = "w-full h-full" }) {
  return (
    <div className={`relative overflow-hidden ${sizeClass}`}>
      {/* Animated content */}
      <WallTileItems item={item} />
    </div>
  );
}
