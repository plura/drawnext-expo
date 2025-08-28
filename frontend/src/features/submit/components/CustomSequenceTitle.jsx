// src/features/submit/components/CustomSequenceTitle.jsx
import SectionTitle from "@/components/typography/SectionTitle";
import Card from "@/components/cards/Card";

export default function CustomSequenceTitle({ title, notebook }) {
	return (
		<div className="flex flex-col gap-2">
			{notebook && (
				<Card
					className={notebook.color_bg ? "border-0" : undefined}
					style={{
						backgroundColor: notebook?.color_bg
							? `#${notebook.color_bg}`
							: undefined,
						color: notebook?.color_text
							? `#${notebook.color_text}`
							: undefined,
					}}
				>
					<div className="text-sm font-medium leading-tight truncate">
						{notebook.title}
					</div>
					{notebook.subtitle && (
						<div className="text-xs leading-tight opacity-90 truncate">
							{notebook.subtitle}
						</div>
					)}
				</Card>
			)}

			<SectionTitle title={title} />
		</div>
	);
}
