// src/components/cards/NotebookCard.jsx
import React from "react";
import Card from "@/components/cards/Card";
import { cn } from "@/lib/utils";

export default function NotebookCard({
	id,
	title,
	subtitle,
	pages,
	selected = false,
	onSelect,
}) {
	return (
		<Card
			as="button"
			type="button"
			onClick={() => onSelect?.(id)}
			aria-pressed={selected}
			variant="borderless" // avoid double border
			className={cn(
				"aspect-[3/2] w-full p-0 rounded-xl",
				"flex flex-col items-center justify-center text-center transition",
				"space-y-3", // ← vertical spacing between children
				selected ? "bg-blue-600 text-white" : "bg-white hover:bg-gray-50",
				"focus:outline-none focus:ring-2 focus:ring-blue-500"
			)}
		>
			<span className="font-medium truncate">
				{title || `Notebook ${id}`}
			</span>

			{subtitle && (
				<span
					className={cn(
						"text-xs mt-0.5 italic",
						selected ? "text-blue-100" : "text-muted-foreground"
					)}
				>
					{subtitle}
				</span>
			)}

			{typeof pages === "number" && (
				<span
					className={cn(
						"text-xs mt-0.5",
						selected ? "text-blue-100" : "text-muted-foreground"
					)}
				>
					up to {pages} pages
				</span>
			)}
		</Card>
	);
}
