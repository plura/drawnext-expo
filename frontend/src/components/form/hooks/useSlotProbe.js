// src/components/form/hooks/useSlotProbe.js
import { useEffect, useMemo, useRef, useState } from 'react';
import { probeSlot } from '@/lib/api';

/**
 * PRIMARY:  { mode:'primary',  notebookId, sectionId, page, excludeDrawingId? }
 * NEIGHBOR: { mode:'neighbor', notebookId, primarySectionId, sectionId, page }
 */
export function useSlotProbe(params) {
  const {
    mode,
    notebookId,
    sectionId,
    page,
    primarySectionId,
    excludeDrawingId,
    enabled = true,
    fetchOpts,
  } = params || {};

  const [loading, setLoading] = useState(false);
  const [result,  setResult]  = useState(null);
  const [error,   setError]   = useState(null);

  // gate
  const ready = useMemo(() => {
    if (!enabled) return false;
    if (!notebookId || !sectionId || !page) return false;
    if (mode === 'neighbor' && !primarySectionId) return false;
    return true;
  }, [enabled, mode, notebookId, sectionId, page, primarySectionId]);

  // stable key
  const key = useMemo(() => {
    if (!ready) return 'idle';
    const ex = mode === 'primary' && excludeDrawingId ? String(excludeDrawingId) : '';
    return `${mode}:${notebookId}:${sectionId}:${page}:${primarySectionId || ''}:${ex}`;
  }, [ready, mode, notebookId, sectionId, page, primarySectionId, excludeDrawingId]);

  const reqIdRef = useRef(0);

  useEffect(() => {
    if (!ready) {
      setLoading(false);
      setResult(null);
      setError(null);
      return;
    }

    const myId = ++reqIdRef.current;
    const abort = new AbortController();

    (async () => {
      try {
        setLoading(true);
        setError(null);

        let json;
        if (mode === 'primary') {
          const payload = {
            notebook_id: Number(notebookId),
            section_id: Number(sectionId),
            page: Number(page),
            neighbors: [],
            ...(excludeDrawingId ? { exclude_drawing_id: Number(excludeDrawingId) } : {}),
          };
          json = await probeSlot(payload, { signal: abort.signal, ...(fetchOpts || {}) });
        } else {
          const payload = {
            notebook_id: Number(notebookId),
            section_id: Number(primarySectionId),
            page: 1,
            neighbors: [{ section_id: Number(sectionId), page: Number(page) }],
          };
          json = await probeSlot(payload, { signal: abort.signal, ...(fetchOpts || {}) });
        }

        if (reqIdRef.current === myId) {
          setResult(json?.data || null);
          setLoading(false);
        }
      } catch (e) {
        if (e.name === 'AbortError') return;
        if (reqIdRef.current === myId) {
          setError(e?.message || 'Probe failed');
          setLoading(false);
        }
      }
    })();

    return () => abort.abort();
  }, [ready, key]); // <— stable trigger only

  return { loading, result, error };
}
