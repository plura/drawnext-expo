// src/features/submission/steps/NotebookSelectStep.jsx

export const stepTitle = "Choose your notebook"
export const stepDescription =
	"Tap the notebook you used. If you scanned a QR code, this may be preselected."

export function validateNotebook(state) {
	return !!state.notebookId
}

export default function NotebookSelectStep({ state, patch, notebooks }) {
	return (
		<div className="grid grid-cols-2 gap-4">
			{notebooks.map((nb) => {
				const isActive = Number(state.notebookId) === Number(nb.id)
				return (
					<button
						key={nb.id}
						type="button"
						onClick={() => patch({ notebookId: Number(nb.id) })}
						aria-pressed={isActive}
						className={`aspect-[3/2] w-full rounded-xl border flex flex-col items-center justify-center text-center transition 
							${isActive ? "bg-blue-600 text-white" : "bg-white hover:bg-gray-50"}`}
					>
						<span className="font-medium truncate">
							{nb.name || `Notebook ${nb.id}`}
						</span>
						{typeof nb.pages === "number" && (
							<span
								className={`text-xs ${
									isActive ? "text-blue-100" : "text-muted-foreground"
								}`}
							>
								up to {nb.pages} pages
							</span>
						)}
					</button>
				)
			})}
		</div>
	)
}
