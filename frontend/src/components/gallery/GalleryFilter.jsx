// src/components/gallery/GalleryFilter.jsx
/**
 * GalleryFilter (pure UI)
 * - Adds explicit "All" option to Notebook & Section selects.
 * - Maps the sentinel "__ALL__" back to "" in state so the API filter is omitted.
 *
 * Update — 2025-09-05:
 * - Added "Has neighbors" switch (shadcn/ui Switch).
 * - Clear filters also resets the switch.
 */

import { useMemo } from "react";
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import Select from "@/components/form/Select";
import { Switch } from "@/components/ui/switch";

const ALL = "__ALL__";

export default function GalleryFilter({
  notebookId,
  setNotebookId,
  sectionId,
  setSectionId,
  page,
  setPage,
  notebookOptions = [],
  sectionOptions = [],
  onClear,
  onlyWithNeighbors,          // NEW
  setOnlyWithNeighbors,       // NEW
}) {
  // Add explicit "All" to both selects; keep state as "" when All is chosen.
  const notebookOptionsWithAll = useMemo(
    () => [{ value: ALL, label: "All" }, ...notebookOptions],
    [notebookOptions]
  );
  const sectionOptionsWithAll = useMemo(
    () => [{ value: ALL, label: "All" }, ...sectionOptions],
    [sectionOptions]
  );

  return (
    <Card className="flex flex-wrap items-end gap-3 p-3">
      <div className="w-44">
        <Select
          id="filter-notebook"
          label="Notebook"
          value={notebookId || ALL}
          onChange={(val) => {
            const next = val === ALL ? "" : val;
            setNotebookId(next);
            if (next === "") setSectionId(""); // reset Section when Notebook goes to All
          }}
          options={notebookOptionsWithAll}
          placeholder="All"
        />
      </div>

      <div className="w-44">
        <Select
          id="filter-section"
          label="Section"
          value={sectionId || ALL}
          onChange={(val) => setSectionId(val === ALL ? "" : val)}
          options={sectionOptionsWithAll}
          disabled={!notebookId}
          placeholder="All"
        />
      </div>

      <div className="w-28">
        <label className="block text-xs text-muted-foreground mb-1">Page</label>
        <Input
          type="number"
          min={1}
          value={page}
          onChange={(e) => setPage(e.target.value)}
          placeholder="Any"
        />
      </div>

      {/* Has neighbors switch */}
      <div className="flex items-center gap-2 ml-2">
        <Switch
          id="filter-has-neighbors"
          checked={!!onlyWithNeighbors}
          onCheckedChange={setOnlyWithNeighbors}
        />
        <label htmlFor="filter-has-neighbors" className="text-sm">
          Has neighbors
        </label>
      </div>

      <Button
        variant="outline"
        className="ml-auto"
        type="button"
        onClick={() => {
          onClear?.();
          setOnlyWithNeighbors(false);
        }}
      >
        Clear filters
      </Button>
    </Card>
  );
}
