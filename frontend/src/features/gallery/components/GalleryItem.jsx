// src/features/gallery/components/GalleryItem.jsx
import DrawingImage from "@/components/drawings/DrawingImage";
import { Badge } from "@/components/ui/badge";

export default function GalleryItem({ item, notebook }) {
	const imageUrl = item?.thumb_url || item?.preview_url || null;
	const sectionText = item?.section_label || `Section ${item.section_id}`;

	return (
		<div className="relative overflow-hidden rounded-xl border bg-white">
			{/* Notebook badge (top-left) */}
			{notebook?.title && (
				<Badge
					className="absolute left-4 top-4 z-10"
					style={{
						backgroundColor: notebook?.color_bg ? `#${notebook.color_bg}` : undefined,
						color: notebook?.color_text ? `#${notebook.color_text}` : undefined
					}}
				>
					{notebook.title}
				</Badge>
			)}

			{/* Image (respects notebook/global aspect ratio) */}
			<DrawingImage
				src={imageUrl}
				alt={`Drawing #${item.drawing_id}`}
				notebook={notebook}
				rounded={false} // parent has rounded-xl
			/>

			{/* Bottom overlay meta */}
			<div className="pointer-events-none absolute inset-x-0 bottom-0">
				<div className="bg-gradient-to-t from-black/50 to-transparent px-2 pb-2 pt-6">
					<div className="text-xs text-white/90">
						<span className="font-medium">{sectionText}</span>
						<span className="mx-1 opacity-70">|</span>
						<span>p. {item.page}</span>
					</div>
				</div>
			</div>
		</div>
	);
}
