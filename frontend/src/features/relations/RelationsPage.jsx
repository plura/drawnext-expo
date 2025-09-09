// src/features/relations/RelationsPage.jsx
/**
 * RelationsPage
 * -------------
 * Minimal page wrapper to visually test the DrawingRelations view before wiring a modal.
 *
 * Route:
 *   /relations/:id
 *
 * Behavior:
 * - Reads the :id URL param.
 * - Renders <DrawingRelations drawingId={Number(id)} /> with neutral page padding.
 * - Handles bad/missing ids with a small inline message (the component itself also has
 *   robust loading / not-found states).
 *
 * Later:
 * - You can keep this page as a deep-link target when you introduce a modal route.
 * - If you prefer a different path (e.g., /explore/drawing/:id), just update the route.
 */

import { useParams } from "react-router-dom";
import DrawingRelations from "@/components/drawings/relations/DrawingRelations";

export default function RelationsPage() {
  const { id } = useParams();
  const drawingId = Number(id);

  const isValid = Number.isFinite(drawingId) && drawingId > 0;

  return (
    <div className="mx-auto flex flex-1 h-full">
      {!isValid ? (
        <div className="text-sm">Invalid or missing drawing id.</div>
      ) : (
        <DrawingRelations drawingId={drawingId} />
      )}
    </div>
  );
}
