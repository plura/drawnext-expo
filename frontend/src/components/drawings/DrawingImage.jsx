// src/components/drawings/DrawingImage.jsx
import { cn } from "@/lib/utils";

export default function DrawingImage({
	src,
	alt = "",
	notebook,
	aspect,					// optional: { w, h } override
	className,
	rounded = true,		// rounded corners by default
	placeholder = "No preview",
}) {
	const a = aspect || notebook?.aspect || { w: 1, h: 1 };

	return (
		<div
			className={cn(
				"drawing-image h-full min-w-0 overflow-hidden min-w-0",
				`aspect-[${a.w}/${a.h}]`,
				rounded && "rounded-md",
				className
			)}
		>
			{src ? (
				<img src={src} alt={alt} className="h-full block object-cover" />
			) : (
				<div className="flex h-full w-full items-center justify-center bg-gray-100 text-xs text-gray-500">
					{placeholder}
				</div>
			)}
		</div>
	);
}
