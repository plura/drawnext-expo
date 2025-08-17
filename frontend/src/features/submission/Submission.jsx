import React, { useEffect, useMemo, useState } from 'react'
import { useSubmissionStore } from './useSubmissionStore'
import { useConfig } from '../../app/ConfigProvider.jsx'
import Sequence from '../../components/Sequence.jsx'

import ImageUploadStep,      { validateImage }        from './steps/ImageUploadStep'
import NotebookSelectStep,   { validateNotebook }     from './steps/NotebookSelectStep'
import SectionAndPagesStep,  { validateSectionPages } from './steps/SectionAndPagesStep'
import EmailStep,            { validateEmail }        from './steps/EmailStep'
import ReviewAndSubmitStep                               from './steps/ReviewAndSubmitStep'
import { buildSubmissionPayload } from './lib/payload' // assuming this exists

export default function Submission() {
  const { notebooks, loading, error } = useConfig()
  const { state, patch, reset } = useSubmissionStore()

  // respect ?notebook_id
  useEffect(() => {
    const id = new URLSearchParams(window.location.search).get('notebook_id')
    if (id) patch({ notebookId: Number(id) })
  }, [patch])

  const baseSteps = useMemo(() => ([
    { key: 'image',        component: ImageUploadStep,     validate: (s) => validateImage(s) },
    { key: 'notebook',     component: NotebookSelectStep,  validate: (s) => validateNotebook(s) },
    { key: 'sectionPages', component: SectionAndPagesStep, validate: (s) => validateSectionPages(s, notebooks) },
    { key: 'email',        component: EmailStep,           validate: (s) => validateEmail(s) },
    { key: 'review',       component: ReviewAndSubmitStep } // display-only
  ]), [notebooks])

  const steps = useMemo(
    () => (state.notebookId ? baseSteps.filter(s => s.key !== 'notebook') : baseSteps),
    [baseSteps, state.notebookId]
  )

  const [current, setCurrent] = useState(0)
  useEffect(() => {
    if (current > steps.length - 1) setCurrent(steps.length - 1)
  }, [steps, current])

  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState(null)

  if (loading) return <div className="mx-auto max-w-md p-4">Loading…</div>
  if (error) return <div className="mx-auto max-w-md p-4 text-red-600">Failed to load configuration.</div>
  if (!Array.isArray(notebooks) || notebooks.length === 0) return <div className="mx-auto max-w-md p-4">No notebooks available.</div>

  const stepDef = steps[current]
  const isLast = current === steps.length - 1
  const allowNext = isLast
    ? !submitting // allow submit unless currently submitting
    : (stepDef.validate ? !!stepDef.validate(state) : true)

  const onValidate = isLast
    ? undefined
    : (stepDef.validate ? () => stepDef.validate(state) : undefined)

  async function handleFinish() {
    // Final guard: validate the step before submitting if you like (optional)
    setMessage(null)
    setSubmitting(true)
    try {
      const payload = buildSubmissionPayload(state)
      const fd = new FormData()
      if (state.file) fd.append('drawing', state.file)
      fd.append('input', JSON.stringify(payload))

      const res = await fetch('/backend/api/drawings/create.php', { method: 'POST', body: fd })
      const json = await res.json().catch(() => ({}))

      if (!res.ok) {
        if (res.status === 409) throw new Error(json?.message || 'That drawing slot is already taken.')
        if (res.status === 422) {
          const first = json?.details?.errors ? Object.values(json.details.errors)[0] : (json?.message || 'Validation failed')
          throw new Error(String(first))
        }
        throw new Error(json?.message || 'Submission failed. Please try again.')
      }

      setMessage({ type: 'success', text: 'Thank you! Your drawing was submitted.' })
      reset()
      setCurrent(0)
      // Optionally: window.location.href = '/thank-you'
    } catch (e) {
      setMessage({ type: 'error', text: e.message })
    } finally {
      setSubmitting(false)
    }
  }

  const componentProps = { state, patch, reset, notebooks }

  return (
    <div className="mx-auto max-w-md p-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">Submit your drawing</h1>
        <p className="mt-1 text-xs text-gray-500">Step {current + 1} of {steps.length}</p>
      </div>

      <Sequence
        steps={steps}
        current={current}
        componentProps={componentProps}
        onBack={() => setCurrent(i => Math.max(i - 1, 0))}
        onNext={() => setCurrent(i => Math.min(i + 1, steps.length - 1))}
        onValidate={onValidate}
        onFinish={handleFinish}                // NEW: submit from parent
        allowNext={allowNext}
        backLabel="Back"
        nextLabel={isLast ? (submitting ? 'Submitting…' : 'Submit') : 'Next'}
      />

      {message && (
        <p className={`mt-3 text-sm ${message.type === 'error' ? 'text-red-600' : 'text-green-600'}`}>
          {message.text}
        </p>
      )}

      {import.meta.env.DEV && (
        <pre className="fixed bottom-2 left-2 max-h-56 w-80 overflow-auto rounded-md border bg-white/90 p-2 text-[11px]">
          {JSON.stringify(state, null, 2)}
        </pre>
      )}
    </div>
  )
}
