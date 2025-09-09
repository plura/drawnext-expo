// src/features/admin/pages/DrawingsList.jsx
// Admin list now delegates data, filters, grid, and pagination to the shared Gallery.
// Each card links to /admin/drawings/:id via buildItemHref.

import Gallery from "@/components/gallery/Gallery";

export default function DrawingsList() {
  return (
    <div className="space-y-4">
      {/* Keep the admin page header */}
      <div className="flex items-center justify-between gap-3 px-4 pt-4">
        <h1 className="text-xl font-semibold">Drawings</h1>
      </div>

      {/* Shared gallery handles filters + grid + pagination */}
      <Gallery
        pageSize={24}
        buildItemHref={(row) => `/admin/drawings/${row.drawing_id}`}
        // initialFilters={{ notebookId: "", sectionId: "", page: "" }} // optional
      />
    </div>
  );
}
