import { useMemo } from 'react'
import SectionGroup from './SectionGroup'
import { getNotebook, getSections } from '../lib/sections'
import { isValidPage } from '../lib/validation'
import { findNeighbor, upsertNeighbor, removeNeighbor, neighborsAreValid } from '../lib/neighbors'

// UI
export default function SectionAndPagesStep({ state, patch, notebooks }) {
  const notebook = useMemo(() => getNotebook(notebooks, state.notebookId), [notebooks, state.notebookId])
  const sections = useMemo(() => getSections(notebook), [notebook])
  const maxPages = typeof notebook?.pages === 'number' ? notebook.pages : null

  const showFields = Boolean(state.sectionId)

  const onSelectSection = (sectionId) => {
    if (Number(state.sectionId) === Number(sectionId)) return
    patch({ sectionId: Number(sectionId), page: '', neighbors: [] })
  }

  const onChangePrimaryPage = (value) => patch({ page: value })

  const onChangeNeighborPage = (sectionId, value) => {
    const trimmed = String(value).trim()
    if (trimmed === '') {
      patch({ neighbors: removeNeighbor(state.neighbors, sectionId) })
      return
    }
    patch({ neighbors: upsertNeighbor(state.neighbors, sectionId, trimmed) })
  }

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold">Section &amp; pages</h2>

      <p className="text-sm text-gray-600" aria-live="polite">
        {!state.sectionId
          ? 'First select your section, then its page number. Neighbor pages are optional — fill them only if you saw drawings in those sections.'
          : 'Enter your page for the selected section. Neighbor pages are optional — fill them only if you saw drawings in those sections.'}
      </p>

      <div className="space-y-3">
        {sections.map((s) => {
          const isPrimary = Number(state.sectionId) === Number(s.id)
          const neighbor = findNeighbor(state.neighbors, s.id)
          return (
            <SectionGroup
              key={s.id}
              section={s}
              isPrimary={isPrimary}
              showField={showFields}
              primaryPage={state.page}
              neighborPage={neighbor?.page || ''}
              maxPages={maxPages}
              autoFocus={showFields && isPrimary}
              onSelect={() => onSelectSection(s.id)}
              onChangePrimaryPage={onChangePrimaryPage}
              onChangeNeighborPage={(v) => onChangeNeighborPage(s.id, v)}
            />
          )
        })}
      </div>

      {state.sectionId && typeof maxPages === 'number' && (
        <p className="text-xs text-gray-500">Notebook has {maxPages} pages.</p>
      )}
    </div>
  )
}

// Validator (needs notebooks to enforce max pages)
export function validateSectionPages(state, notebooks) {
  const notebook = notebooks?.find(n => Number(n.id) === Number(state.notebookId)) || null
  const maxPages = typeof notebook?.pages === 'number' ? notebook.pages : null

  if (!state.sectionId || !isValidPage(state.page, maxPages)) return false
  return neighborsAreValid(state.neighbors, maxPages)
}
