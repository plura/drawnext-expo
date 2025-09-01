// src/features/submit/steps/SectionAndPagesStep.jsx
import React, { useMemo } from "react";
import Sections from "@/components/form/Sections";
import { getNotebook, getSections } from "../lib/sections";
import { upsertNeighbor, removeNeighbor } from "../lib/neighbors";
import { isValidPage } from "../lib/validation";

export const stepTitle = "Select section and pages";
export const stepDescription =
  "First select your section, then choose its page number. Neighbor pages are optional — fill them only if you saw drawings in those sections.";

export function validateSectionPages(state, notebooks) {
  const nb = getNotebook(notebooks, state.notebookId);
  if (!nb) return false;
  if (!state.sectionId) return false;
  if (!isValidPage(state.page, nb.pages)) return false;
  return true;
}

export default function SectionAndPagesStep({ state, patch, notebooks }) {
  const notebook = useMemo(
    () => getNotebook(notebooks, state.notebookId),
    [notebooks, state.notebookId]
  );
  if (!notebook) return <p>No notebook selected</p>;

  const sections = useMemo(() => getSections(notebook), [notebook]);
  const primarySectionId = Number(state.sectionId) || null;

  function handleSelectPrimary(sectionId) {
    patch({
      sectionId: Number(sectionId),
      // ensure neighbors no longer include the chosen primary
      neighbors: (state.neighbors || []).filter(
        (n) => Number(n.section_id) !== Number(sectionId)
      ),
    });
  }

  function handleChangePrimaryPage(next) {
    // keep as string in state; payload builder converts to numbers
    patch({ page: String(next) });
  }

  function handleChangeNeighborPage(sectionId, next) {
    const val = String(next);
    if (val === "") {
      patch({ neighbors: removeNeighbor(state.neighbors || [], sectionId) });
      return;
    }
    patch({
      neighbors: upsertNeighbor(state.neighbors || [], sectionId, val),
    });
  }

  return (
    <Sections
      sections={sections} 
      notebookId={Number(notebook.id)} 
      primarySectionId={primarySectionId}
      page={state.page}
      neighborPages={
        (state.neighbors || []).reduce((acc, n) => {
          acc[String(n.section_id)] = n.page;
          return acc;
        }, {})
      }
      maxPages={notebook.pages}
      onSelectPrimary={handleSelectPrimary}
      onChangePrimaryPage={handleChangePrimaryPage}
      onChangeNeighborPage={(sectionId, val) =>
        handleChangeNeighborPage(sectionId, val)
      }
    />
  );
}
