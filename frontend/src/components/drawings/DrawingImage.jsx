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
	const style = { aspectRatio: `${a.w} / ${a.h}` };

	return (
		<div
			style={style}
			className={cn(
				"w-full overflow-hidden",
				rounded && "rounded-md",
				className
			)}
		>
			{src ? (
				<img src={src} alt={alt} className="h-full w-full object-cover" />
			) : (
				<div className="flex h-full w-full items-center justify-center bg-gray-100 text-xs text-gray-500">
					{placeholder}
				</div>
			)}
		</div>
	);
}
