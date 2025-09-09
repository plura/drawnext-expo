// src/features/wall/ExhibitionWall.jsx
import { useEffect } from "react";
import Wall from "@/components/wall/Wall";
import ShowWallBar from "./ShowWallBar";
import { useWallDimensions } from "@/hooks/useWallDimensions";

const gap = "0.25rem"; // tailwind gap-1

export default function ExhibitionWall() {
  const { rows, cols } = useWallDimensions();

  // Keep the screen awake on supported devices (quietly no-ops if unsupported)
  useEffect(() => {
    let lock;
    const run = async () => {
      try {
        if ("wakeLock" in navigator) lock = await navigator.wakeLock.request("screen");
      } catch {
        // ignore
      }
    };
    run();
    return () => { lock && lock.release?.(); };
  }, []);

  return (
    // Page holder controls total height (viewport)
    <div className="h-dvh bg-brand flex flex-col">
      {/* Wall takes the remaining space */}
      <div
        className="flex-1 min-h-0"
        style={{ "--gap": gap, padding: `${gap} ${gap} 0` }}
      >
        <Wall rows={rows} cols={cols} rand gap={gap} />
      </div>

      {/* Footer bar (horizontal by default). To use vertical, see comment below. */}
      <ShowWallBar row />
      {/*
        To use it as a vertical sidebar instead, do:
        <div className="h-dvh bg-brand flex flex-row">
          <div className="flex-1 min-h-0"><Wall rows={rows} cols={cols} rand gap={gap} /></div>
          <ExhibitionWallBar row={false} />
        </div>
      */}
    </div>
  );
}
