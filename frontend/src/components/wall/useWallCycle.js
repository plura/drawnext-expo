// src/components/wall/useWallCycle.js
/**
 * useWallCycle
 * ------------
 * Periodically fetches a new batch of drawings (size = rows*cols), preloads images,
 * then replaces tiles once each within a given window (tileSpreadMs).
 *
 * Behavior:
 * - The VERY FIRST batch also animates in as a wave: we seed the grid with `null`s
 *   and schedule per-tile inserts using the same uniform-random delays used later.
 * - Each subsequent "wave" evenly spans [0, tileSpreadMs] across ALL tiles, with a
 *   randomized order (no dead gaps, not strictly in order).
 * - Robust against small datasets or servers that ignore exclude_ids:
 *   - If "next" < V, we refill without excludes and pad to V.
 *   - When assigning, avoid reusing the exact same item per tile where possible.
 * - Pauses while the tab is hidden and prevents overlapping waves.
 *
 * Notes:
 * - Visual animations are handled in WallTileItems via Framer; this hook only controls timing.
 */

import { useCallback, useEffect, useRef, useState } from "react";

/** Soft-preload a set of image URLs with a timeout so a slow one doesn't stall the wave. */
function preloadAll(urls, timeoutMs = 1500) {
  const loaders = urls.map(
    (u) =>
      new Promise((resolve) => {
        if (!u) return resolve();
        const img = new Image();
        img.onload = img.onerror = () => resolve();
        img.src = u;
      })
  );
  return Promise.race([
    Promise.all(loaders).then(() => true),
    new Promise((r) => setTimeout(() => r(false), timeoutMs)),
  ]);
}

/** In-place Fisher–Yates shuffle. */
function shuffleInPlace(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = (Math.random() * (i + 1)) | 0;
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

/**
 * Uniform-random wave:
 *  - Evenly spans [0, tileSpreadMs] across *all* tiles (no dead gaps)
 *  - Randomizes *which* tile fires at each slice
 *  - Adds a small jitter per slice so it feels organic
 */
function computeWaveDelaysUniformRandom({ rows, cols, tileSpreadMs, jitterPct = 0.12 }) {
  const V = Math.max(0, Number(rows) * Number(cols));
  const delays = new Array(V).fill(0);
  if (V <= 1) return delays;

  // Randomize the order in which tiles will get their delay slot
  const ids = Array.from({ length: V }, (_, i) => i);
  shuffleInPlace(ids);

  // Even spacing across the window; V tiles → V-1 intervals
  const step = tileSpreadMs / (V - 1);

  for (let k = 0; k < V; k++) {
    const idx = ids[k];
    const base = step * k;
    const jitter = (Math.random() * 2 - 1) * step * jitterPct;
    const d = Math.max(0, Math.min(tileSpreadMs, base + jitter));
    delays[idx] = d;
  }
  return delays;
}

/**
 * Ensure we have a pool of at least V items to assign.
 * - Try "next" (with excludes). If too small, refill without excludes.
 * - Pad by cycling from whatever we have so pool.length === V.
 */
async function buildPool({ V, next, fetchWithoutExcludes }) {
  let pool = Array.isArray(next) ? next.slice() : [];

  if (pool.length < V) {
    const refill = await fetchWithoutExcludes();
    if (Array.isArray(refill) && refill.length) {
      pool = pool.concat(refill);
    }
  }

  // If still short, pad by cycling from what we have (fallback).
  if (pool.length === 0) return pool; // nothing we can do (degenerate case)
  if (pool.length < V) {
    const out = new Array(V);
    for (let i = 0; i < V; i++) out[i] = pool[i % pool.length];
    return out;
  }

  return pool.slice(0, V);
}

/**
 * Assign a (possibly duplicated) pool to tiles in a randomized order,
 * trying to avoid mapping the same drawing_id back to the same tile.
 */
function scheduleAssignments({
  V,
  rows,
  cols,
  tileSpreadMs,
  pool,
  visibleRef,
  setVisible,
  timeoutsRef,
}) {
  // Randomized tile order & evenly spaced delays
  const delays = computeWaveDelaysUniformRandom({ rows, cols, tileSpreadMs });
  const order = Array.from({ length: V }, (_, i) => i);
  shuffleInPlace(order);

  // We'll step through the pool linearly; if we accidentally pick the same id for a tile,
  // advance until we find a different one (unless pool size is 1).
  let poolPtr = 0;
  const poolLen = pool.length;

  for (let n = 0; n < V; n++) {
    const tileIdx = order[n];
    const delay = Math.floor(delays[tileIdx]);

    const t = setTimeout(() => {
      setVisible((prev) => {
        if (!Array.isArray(prev) || prev.length !== V) return prev;

        const cur = prev[tileIdx];
        const curId = cur?.drawing_id ?? null;

        // Pick a candidate from the pool
        let candidate = pool[poolPtr % poolLen];
        let tries = 0;

        // Avoid assigning same id back to the same tile (when possible)
        while (poolLen > 1 && candidate?.drawing_id === curId && tries < poolLen) {
          poolPtr++;
          candidate = pool[poolPtr % poolLen];
          tries++;
        }

        poolPtr++;

        // Commit
        const copy = prev.slice();
        copy[tileIdx] = candidate ?? prev[tileIdx];
        return copy;
      });
    }, delay);

    timeoutsRef.current.push(t);
  }
}

export function useWallCycle({
  rows,
  cols,
  rand = true,
  expand = "user,labels,thumb",
  intervalMs = 24000,   // full refresh wave cadence (e.g., every 24s)
  tileSpreadMs = 6000,  // each wave spans this window (e.g., all tiles swap within 6s)
}) {
  const V = Math.max(0, Number(rows) * Number(cols));

  // IMPORTANT: start with a fixed-length array of nulls so the grid is stable from the start.
  const [visible, setVisible] = useState(() => Array(V).fill(null));
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // If rows/cols change, reset to nulls so the grid shape stays consistent
  useEffect(() => {
    setVisible((prev) => {
      if (Array.isArray(prev) && prev.length === V) return prev;
      return Array(V).fill(null);
    });
  }, [V]);

  // Keep scheduled timeouts so we can clear on unmount or restart
  const timeoutsRef = useRef([]);
  const clearScheduled = useCallback(() => {
    timeoutsRef.current.forEach(clearTimeout);
    timeoutsRef.current = [];
  }, []);

  // Track current visible list without causing re-renders during scheduling
  const visibleRef = useRef(visible);
  useEffect(() => {
    visibleRef.current = visible;
  }, [visible]);

  // Prevent overlapping waves
  const waveActiveRef = useRef(false);

  const buildUrl = useCallback(
    (excludeIds = []) => {
      const params = new URLSearchParams();
      params.set("limit", String(V));
      params.set("offset", "0");
      params.set("expand", expand);
      if (rand) params.set("rand", "1"); // server-side shuffle if supported
      if (excludeIds.length) params.set("exclude_ids", excludeIds.join(","));
      return `/api/drawings/list?${params.toString()}`;
    },
    [V, expand, rand]
  );

  const fetchBatch = useCallback(
    async (excludeIds = []) => {
      const res = await fetch(buildUrl(excludeIds));
      const json = await res.json().catch(() => ({}));
      if (!res.ok || json?.status === "error") {
        throw new Error(json?.message || `Request failed (${res.status})`);
      }
      const list = Array.isArray(json?.data) ? json.data : [];
      return list.slice(0, V);
    },
    [buildUrl, V]
  );

  const fetchWithoutExcludes = useCallback(async () => fetchBatch([]), [fetchBatch]);

  // INITIAL LOAD — insert each first item with the same uniform-random delays
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        setError(null);

        const batch = await fetchBatch();
        await preloadAll(batch.map((d) => d.thumb_url || d.preview_url));

        if (cancelled) return;

        // Schedule initial wave (so first items also animate in, not appear all at once)
        waveActiveRef.current = true;
        clearScheduled();

        // Ensure array length is V and filled with nulls before inserts
        setVisible((prev) => (Array.isArray(prev) && prev.length === V ? prev : Array(V).fill(null)));

        // Build a pool of at least V items (pad/refill if needed)
        const pool = await buildPool({ V, next: batch, fetchWithoutExcludes });

        scheduleAssignments({
          V,
          rows: Number(rows),
          cols: Number(cols),
          tileSpreadMs,
          pool,
          visibleRef,
          setVisible,
          timeoutsRef,
        });

        // Unlock after initial wave finishes (small buffer)
        const unlock = setTimeout(() => {
          waveActiveRef.current = false;
          timeoutsRef.current = [];
        }, Math.max(0, tileSpreadMs + 200));
        timeoutsRef.current.push(unlock);
      } catch (e) {
        if (!cancelled) setError(e?.message || "Failed to load drawings.");
        waveActiveRef.current = false;
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
      clearScheduled();
    };
  }, [V, rows, cols, tileSpreadMs, fetchBatch, fetchWithoutExcludes, clearScheduled]);

  // SUBSEQUENT WAVES — same uniform-random schedule, avoids overlaps, pauses when hidden
  useEffect(() => {
    if (V === 0) return;

    let active = true;

    const tick = async () => {
      if (!active) return;
      if (typeof document !== "undefined" && document.visibilityState !== "visible") return;
      if (waveActiveRef.current) return; // don't overlap waves

      try {
        // Snapshot current IDs so backend can avoid repeats
        const currentIds = (visibleRef.current || [])
          .map((d) => d?.drawing_id)
          .filter(Boolean);

        const next = await fetchBatch(currentIds);
        await preloadAll(next.map((d) => d.thumb_url || d.preview_url));

        // Start a new wave
        waveActiveRef.current = true;
        clearScheduled();

        // Build a robust pool of V items (handle small datasets / ignored excludes)
        const pool = await buildPool({ V, next, fetchWithoutExcludes });

        scheduleAssignments({
          V,
          rows: Number(rows),
          cols: Number(cols),
          tileSpreadMs,
          pool,
          visibleRef,
          setVisible,
          timeoutsRef,
        });

        // Unlock after the wave finishes (with a small buffer)
        const unlock = setTimeout(() => {
          waveActiveRef.current = false;
          timeoutsRef.current = [];
        }, Math.max(0, tileSpreadMs + 200));
        timeoutsRef.current.push(unlock);
      } catch (e) {
        console.warn("[useWallCycle] refresh failed:", e?.message || e);
        waveActiveRef.current = false;
      }
    };

    const intervalId = setInterval(tick, Math.max(1000, intervalMs));
    const onVis = () => {
      if (typeof document === "undefined") return;
      if (document.visibilityState === "visible") tick();
    };
    if (typeof document !== "undefined") {
      document.addEventListener("visibilitychange", onVis);
    }

    return () => {
      active = false;
      clearInterval(intervalId);
      clearScheduled();
      if (typeof document !== "undefined") {
        document.removeEventListener("visibilitychange", onVis);
      }
      waveActiveRef.current = false;
    };
  }, [V, rows, cols, intervalMs, tileSpreadMs, fetchBatch, fetchWithoutExcludes, clearScheduled]);

  // Return both "tiles" (preferred) and "visible" (back-compat) plus meta
  return { tiles: visible, visible, loading, error };
}
