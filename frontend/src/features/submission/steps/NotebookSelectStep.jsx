// UI
export default function NotebookSelectStep({ state, patch, notebooks }) {
  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold">Select notebook</h2>

      <div className="grid grid-cols-2 gap-3">
        {notebooks.map((nb) => {
          const selected = Number(state.notebookId) === Number(nb.id)
          return (
            <button
              key={nb.id}
              type="button"
              onClick={() => patch({ notebookId: Number(nb.id), sectionId: null, neighbors: [], page: '' })}
              className={`rounded-xl border p-4 text-left ${selected ? 'ring-2 ring-black' : ''}`}
              aria-pressed={selected}
            >
              <div className="text-base font-medium">{nb.name || `Notebook ${nb.id}`}</div>
              {typeof nb.pages === 'number' && (
                <div className="text-xs text-gray-500 mt-1">{nb.pages} pages</div>
              )}
            </button>
          )
        })}
      </div>
    </div>
  )
}

// Validator
export function validateNotebook(state) {
  return !!state.notebookId
}
