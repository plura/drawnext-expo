// src/hooks/useWallDimensions.js
// Chooses rows/cols based on window width, with a safe SSR guard.
// Updates only when the tier changes to avoid unnecessary renders.

import { useEffect, useState } from "react";

function getWidth() {
  // SSR / non-browser guard
  if (typeof window === "undefined") return 1024;
  return window.innerWidth;
}

function calcDims(width) {
  // Two-tier version (you can add more later if you want):
  // < 1024px -> 5x3, >= 1024px -> 7x4
  return width < 1024 ? { rows: 5, cols: 3 } : { rows: 4, cols: 7 };
}

export function useWallDimensions() {
  const [dims, setDims] = useState(() => calcDims(getWidth()));

  useEffect(() => {
    function onResize() {
      const next = calcDims(getWidth());
      // Only update when the *tier* changes (prevents render loops).
      setDims((prev) =>
        prev.rows === next.rows && prev.cols === next.cols ? prev : next
      );
    }

    // Initial pass in case something changed before mount
    onResize();

    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);

  return dims; // { rows, cols }
}
