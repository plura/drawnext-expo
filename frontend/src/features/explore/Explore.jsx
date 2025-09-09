// src/features/explore/Explore.jsx
import Gallery from "@/components/gallery/Gallery";

export default function Explore() {
  return (
    <div className="p-4 space-y-3">
      <Gallery
        pageSize={24}
        // When provided, each card becomes a <Link to={...}>
        buildItemHref={(row) => `/relations/${row.drawing_id}`}
      />
    </div>
  );
}
