// src/features/submit/steps/NotebookSelectStep.jsx

import NotebookCard from "@/components/cards/NotebookCard";

export const stepTitle = "Choose your notebook";
export const stepDescription =
  "Tap the notebook you used. If you scanned a QR code, this may be preselected.";

export function validateNotebook(state) {
  return !!state.notebookId;
}

export default function NotebookSelectStep({ state, patch, notebooks }) {
  return (
	<div className="grid grid-cols-2 gap-4">
	  {notebooks.map((nb) => (
		<NotebookCard
		  key={nb.id}
		  id={nb.id}
		  title={nb.title}
		  subtitle={nb.subtitle}
		  pages={nb.pages}
		  selected={Number(state.notebookId) === Number(nb.id)}
		  onSelect={(id) => patch({ notebookId: id })}
		/>
	  ))}
	</div>
  );
}
