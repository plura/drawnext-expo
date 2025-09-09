// src/features/explore/pages/DrawingRelationsPage.jsx
/**
 * Public page wrapper for DrawingRelations.
 * URL: /explore/drawing/:id (adjust the route path to your preference)
 */
import { useParams } from "react-router-dom";
import { DrawingRelations } from "@/components/drawings/relations";

export default function DrawingRelationsPage() {
  const { id } = useParams();
  const drawingId = Number(id || 0);
  return (
    <div className="p-4">
      <DrawingRelations drawingId={drawingId} />
    </div>
  );
}
