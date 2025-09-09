// src/components/wall/Wall.jsx
import WallTile from "./WallTile";
import { useWallCycle } from "./useWallCycle";

export default function Wall({
  rows,
  cols,
  gap = "0.25rem",
  intervalMs = 9000,
  tileSpreadMs = 6000,
  rand = true,
  className = "",    // allow parent to pass extra classes if needed
}) {
  const { tiles } = useWallCycle({ rows, cols, intervalMs, tileSpreadMs, rand });

  const style = {
    display: "grid",
    gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))`,
    gridTemplateRows:    `repeat(${rows}, minmax(0, 1fr))`,
    gap,
  };

  return (
    <div className={`relative h-full ${className}`} style={style}>
      {(tiles || []).map((item, i) => (
        <WallTile key={i} item={item} />
      ))}
    </div>
  );
}
