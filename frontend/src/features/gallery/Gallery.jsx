import DynamicGallery from "./components/DynamicGallery";

export default function Gallery() {
  return (
    <div className="p-4">
      <DynamicGallery dataURL="/mock/drawings-list.json" />
    </div>
  );
}
