// src/features/submit/steps/ReviewAndSubmitStep.jsx
import { useMemo } from "react";
import Card from "@/components/cards/Card";
import DrawingImage from "@/components/drawings/DrawingImage";
import { useObjectURL } from "../lib/useObjectURL";

export const stepTitle = "Review";

export default function ReviewAndSubmitStep({ state, notebooks }) {
	const notebook = useMemo(
		() => notebooks.find((n) => Number(n.id) === Number(state.notebookId)) || null,
		[notebooks, state.notebookId]
	);

	const sectionLabel = useMemo(() => {
		const sec = notebook?.sections?.find(
			(s) => Number(s.id) === Number(state.sectionId)
		);
		return sec?.label || (sec ? `Section ${sec.position}` : null);
	}, [notebook, state.sectionId]);

	const neighborChips = useMemo(() => {
		if (!state.neighbors?.length) return [];
		return state.neighbors
			.filter((n) => n.page)
			.map((n) => {
				const sec = notebook?.sections?.find(
					(s) => Number(s.id) === Number(n.section_id)
				);
				const label = sec?.label || (sec ? `Section ${sec.position}` : n.section_id);
				return { key: `${n.section_id}-${n.page}`, label, page: n.page };
			});
	}, [state.neighbors, notebook]);

	const previewUrl = useObjectURL(state.file);

	return (
		<div className="space-y-4">
			{/* Image Preview (uses global/notebook aspect via DrawingImage) */}
			{previewUrl && (
                <Card className="p-2">
					<DrawingImage
						src={previewUrl}
						alt="Your uploaded drawing"
						notebook={notebook}
					/>
				</Card>
			)}

			{/* Review Details */}
			<Card className="p-4">
				<dl className="grid grid-cols-[auto,1fr] items-center gap-x-3 gap-y-2 text-sm">
					<dt className="text-muted-foreground">Notebook</dt>
					<dd className="font-medium">{notebook?.title || state.notebookId}</dd>

					<dt className="text-muted-foreground">Section</dt>
					<dd className="font-medium">
						{sectionLabel || state.sectionId}
					</dd>

					<dt className="text-muted-foreground">Page</dt>
					<dd className="font-medium">{state.page}</dd>

					<dt className="text-muted-foreground">Email</dt>
					<dd className="font-medium break-all">{state.email}</dd>

					{neighborChips.length > 0 && (
						<>
							<dt className="text-muted-foreground self-start">Neighbors</dt>
							<dd>
								<div className="flex flex-wrap gap-2">
									{neighborChips.map((n) => (
										<span
											key={n.key}
											className="inline-flex items-center rounded-full border px-2 py-0.5 text-xs bg-white"
										>
											<span className="mr-1 font-medium">{n.label}</span>
											<span className="text-muted-foreground">p. {n.page}</span>
										</span>
									))}
								</div>
							</dd>
						</>
					)}
				</dl>
			</Card>

			<p className="text-xs text-muted-foreground">
				Tap <span className="font-medium">Submit</span> below to send your drawing.
			</p>
		</div>
	);
}
