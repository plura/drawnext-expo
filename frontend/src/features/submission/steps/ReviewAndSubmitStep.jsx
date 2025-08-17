import { useMemo } from 'react'

export default function ReviewAndSubmitStep({ state, notebooks }) {
  const notebook = useMemo(
    () => notebooks.find(n => Number(n.id) === Number(state.notebookId)) || null,
    [notebooks, state.notebookId]
  )
  const sectionLabel = useMemo(() => {
    const sec = notebook?.sections?.find(s => Number(s.id) === Number(state.sectionId))
    return sec?.label || (sec ? `Section ${sec.position}` : null)
  }, [notebook, state.sectionId])

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold">Review</h2>

      <div className="rounded-xl border p-3 text-sm">
        <p><span className="font-medium">Notebook:</span> {notebook?.name || state.notebookId}</p>
        <p><span className="font-medium">Section:</span> {sectionLabel || state.sectionId}</p>
        <p><span className="font-medium">Page:</span> {state.page}</p>
        <p><span className="font-medium">Email:</span> {state.email}</p>

        {state.neighbors?.length > 0 && (
          <div className="mt-2">
            <p className="font-medium">Neighbors:</p>
            <ul className="list-disc pl-5">
              {state.neighbors.filter(n => n.page).map(n => {
                const sec = notebook?.sections?.find(s => Number(s.id) === Number(n.section_id))
                const label = sec?.label || (sec ? `Section ${sec.position}` : n.section_id)
                return <li key={n.section_id}>{label}: page {n.page}</li>
              })}
            </ul>
          </div>
        )}
      </div>

      <p className="text-xs text-gray-500">
        Tap <span className="font-medium">Submit</span> below to send your drawing.
      </p>
    </div>
  )
}
