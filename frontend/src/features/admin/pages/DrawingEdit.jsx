// src/features/admin/pages/DrawingEdit.jsx
/**
 * Admin → DrawingEdit (two-column layout)
 * - Left column: image (DrawingImage) + meta + Notebook Select
 * - Right column: Sections stack (SectionGroup for each section)
 * - Action row (Cancel / Validate / Save) below the layout
 *
 * Behavior:
 * - Validate → calls /api/drawings/validate and shows result inline (no redirect)
 * - Save → calls /api/admin/drawings/update and shows result inline (no redirect)
 */

import { useEffect, useMemo, useState } from "react";
import { useParams, useNavigate, Link } from "react-router-dom";
import { useConfig } from "@/app/ConfigProvider";
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import Select from "@/components/form/Select";
import Sections from "@/components/form/Sections";
import DrawingImage from "@/components/drawings/DrawingImage";
import { validateSlots, updateDrawing } from "@/features/admin/lib/api";

export default function DrawingEdit() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { notebooks, loading: configLoading } = useConfig();

  // Minimal fetch: get the drawing by scanning list (until a dedicated endpoint exists)
  const [base, setBase] = useState(null);
  const [loading, setLoading] = useState(true);

  // Editable state
  const [notebookId, setNotebookId] = useState("");
  const [sectionId, setSectionId] = useState("");
  const [page, setPage] = useState("");
  const [neighborPages, setNeighborPages] = useState({}); // { [sectionId]: "page" }

  // Inline UX status
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState(null);
  const [saveOk, setSaveOk] = useState(null);
  const [validating, setValidating] = useState(false);
  const [validateError, setValidateError] = useState(null);
  const [validateInfo, setValidateInfo] = useState(null);

  // Load “just enough” data by scanning /drawings/list
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        const res = await fetch(
          `/api/drawings/list?limit=200&expand=labels,thumb,user,neighbors`
        );
        const json = await res.json();
        const rows = Array.isArray(json?.data) ? json.data : [];
        const found =
          rows.find((r) => String(r.drawing_id) === String(id)) || null;
        if (!cancelled) setBase(found || null);
      } catch {
        if (!cancelled) setBase(null);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [id]);

  // Initialize editable state from base once
  useEffect(() => {
    if (!base) return;
    setNotebookId(String(base.notebook_id));
    setSectionId(String(base.section_id));
    setPage(String(base.page || ""));
    const dict = {};
    (base.neighbors || []).forEach((n) => {
      dict[String(n.section_id)] = String(n.page);
    });
    setNeighborPages(dict);
  }, [base]);

  const notebook = useMemo(() => {
    if (!notebooks || !notebookId) return null;
    return notebooks.find((n) => String(n.id) === String(notebookId)) || null;
  }, [notebooks, notebookId]);

  const sections = useMemo(() => {
    if (!notebook) return [];
    const list = notebook.sections || [];
    return [...list].sort(
      (a, b) => Number(a.position) - Number(b.position)
    );
  }, [notebook]);

  const maxPages = notebook?.pages ?? null;
  const previewUrl = base?.thumb_url || base?.preview_url || null;
  const primarySectionId = sectionId ? Number(sectionId) : null;

  // Sections stack handlers
  function handleSelectPrimary(nextSectionId) {
    setSectionId(String(nextSectionId));
    // Ensure the newly chosen primary isn't also kept as neighbor
    setNeighborPages((prev) => {
      const next = { ...prev };
      delete next[String(nextSectionId)];
      return next;
    });
  }
  function handleChangePrimaryPage(nextPage) {
    setPage(String(nextPage));
  }
  function handleChangeNeighborPage(sectionIdNum, nextPage) {
    const val = String(nextPage);
    setNeighborPages((prev) => ({
      ...prev,
      [String(sectionIdNum)]: val,
    }));
  }

  // neighbors payload (only non-empty)
  const neighborsArray = useMemo(() => {
    if (!notebook) return [];
    return sections
      .filter((s) => Number(s.id) !== Number(primarySectionId))
      .map((s) => {
        const val = neighborPages[String(s.id)] || "";
        return { section_id: Number(s.id), page: val ? Number(val) : "" };
      })
      .filter((n) => n.page !== "");
  }, [sections, neighborPages, notebook, primarySectionId]);

  async function handleValidate() {
    if (!notebookId || !sectionId || !page) return;
    setValidateError(null);
    setValidateInfo(null);
    setValidating(true);
    try {
      const payload = {
        notebook_id: Number(notebookId),
        section_id: Number(sectionId),
        page: Number(page),
        neighbors: neighborsArray.map((n) => ({
          section_id: n.section_id,
          page: n.page,
        })),
      };
      const json = await validateSlots(payload);
      setValidateInfo(json?.data || { ok: true });
    } catch (e) {
      setValidateError(e?.message || "Validation failed");
    } finally {
      setValidating(false);
    }
  }

  function handleCancel() {
    navigate("/admin/drawings");
  }

  async function handleSave() {
    if (!notebookId || !sectionId || !page) return;
    setSaveError(null);
    setSaveOk(null);
    setSaving(true);
    try {
      const payload = {
        drawing_id: Number(id),
        notebook_id: Number(notebookId),
        section_id: Number(sectionId),
        page: Number(page),
        neighbors: neighborsArray, // already cast to numbers above
      };
      const json = await updateDrawing(payload); // throws on non-2xx
      const updated = Boolean(json?.data?.updated);
      const neighborsCount = Number(json?.data?.neighbors_updated ?? 0);
      setSaveOk(
        updated
          ? `Saved${neighborsCount ? ` (neighbors: ${neighborsCount})` : ""}.`
          : "No changes to save."
      );
      // Stay on page for further edits
    } catch (e) {
      setSaveError(e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  if (configLoading || loading || !base) {
    return <div className="p-4">Loading…</div>;
  }

  return (
    <div className="p-4 space-y-4">
      {/* Top bar */}
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Edit Drawing</h1>
        <div className="flex items-center gap-2">
          <Button variant="outline" asChild>
            <Link to="/admin/drawings">Back to list</Link>
          </Button>
        </div>
      </div>

      {/* Two-column layout */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* LEFT: Image + meta + Notebook Select */}
        <Card className="p-4">
          <div className="flex gap-4">
            {/* Image (uses DrawingImage; respects notebook/global aspect) */}
            <div className="w-40 md:w-56 shrink-0">
              <DrawingImage
                src={previewUrl}
                alt={`Drawing #${base.drawing_id}`}
                notebook={notebook}
                rounded
              />
            </div>

            {/* Meta + Notebook Select */}
            <div className="flex-1 space-y-3">
              {notebook?.title && (
                <Badge
                  style={{
                    backgroundColor: notebook?.color_bg
                      ? `#${notebook.color_bg}`
                      : undefined,
                    color: notebook?.color_text
                      ? `#${notebook.color_text}`
                      : undefined,
                  }}
                >
                  {notebook.title}
                </Badge>
              )}

              <div className="text-sm text-muted-foreground space-y-1">
                <div>
                  ID: <span className="font-mono">{base.drawing_id}</span>
                </div>
                {base.user_email && (
                  <div>
                    Email: <span className="break-all">{base.user_email}</span>
                  </div>
                )}
                <div>Created: {base.created_at}</div>
              </div>

              {/* Notebook Select (shadcn wrapper) */}
              <div className="pt-1">
                <Select
                  id="edit-notebook"
                  label="Notebook"
                  value={notebookId || undefined} // avoid empty-string error
                  onChange={(val) => {
                    setNotebookId(val);
                    setSectionId("");
                    setPage("");
                    setNeighborPages({});
                    setSaveOk(null);
                    setSaveError(null);
                    setValidateInfo(null);
                    setValidateError(null);
                  }}
                  options={(notebooks || []).map((n) => ({
                    value: String(n.id),
                    label: n.title || `Notebook ${n.id}`,
                  }))}
                  placeholder="Choose notebook"
                />
              </div>
            </div>
          </div>
        </Card>

        {/* RIGHT: SectionGroup stack (wrapped here in a Card) */}
        <Card className="p-4">
          {!notebookId ? (
            <p className="text-sm text-muted-foreground">
              Choose a notebook to edit section and pages.
            </p>
          ) : (
            <Sections
              sections={sections} 
              notebookId={Number(notebook.id)} 
              primarySectionId={primarySectionId}
              page={page}
              neighborPages={neighborPages}
              maxPages={maxPages}
              onSelectPrimary={handleSelectPrimary}
              onChangePrimaryPage={handleChangePrimaryPage}
              onChangeNeighborPage={handleChangeNeighborPage} 
              excludeDrawingId={Number(id)} 
            />
          )}
        </Card>
      </div>

      {/* Action row below layout */}
      <div className="flex flex-wrap gap-2 pt-2">
        <Button variant="outline" type="button" onClick={handleCancel} disabled={saving || validating}>
          Cancel
        </Button>
        <Button
          variant="outline"
          type="button"
          onClick={handleValidate}
          disabled={!notebookId || !sectionId || !page || saving || validating}
        >
          {validating ? "Validating…" : "Validate"}
        </Button>
        <Button
          type="button"
          onClick={handleSave}
          disabled={!notebookId || !sectionId || !page || saving || validating}
        >
          {saving ? "Saving…" : "Save changes"}
        </Button>
      </div>

      {/* Inline messages */}
      {saveOk && <div className="text-sm text-green-600 pt-2">{saveOk}</div>}
      {saveError && <div className="text-sm text-red-600 pt-2">{saveError}</div>}
      {validateError && <div className="text-sm text-red-600 pt-2">{validateError}</div>}
      {validateInfo && (
        <pre className="text-[11px] text-muted-foreground bg-white/70 border rounded p-2 mt-2 overflow-auto">
{JSON.stringify(validateInfo, null, 2)}
        </pre>
      )}
    </div>
  );
}
