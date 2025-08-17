// src/features/submission/Submission.jsx
import React, { useEffect, useMemo, useState } from 'react'
import { useSubmissionStore } from './useSubmissionStore'
import { useConfig } from '../../app/ConfigProvider.jsx'

// step components you already created
import ImageUploadStep from './steps/ImageUploadStep'
import NotebookSelectStep from './steps/NotebookSelectStep'
import SectionAndPagesStep from './steps/SectionAndPagesStep'
import EmailStep from './steps/EmailStep'
import ReviewAndSubmitStep from './steps/ReviewAndSubmitStep'

export default function Submission() {
	// app-wide config (fetched once by ConfigProvider)
	const { notebooks, loading, error } = useConfig()

	// central store for the submission flow
	const { state, patch, reset } = useSubmissionStore()

	// read ?notebook_id on mount (skip notebook step if present)
	useEffect(() => {
		const id = new URLSearchParams(window.location.search).get('notebook_id')
		if (id) patch({ notebookId: Number(id) })
	}, [patch])

	// base step order (we'll drop "notebook" at runtime if preselected)
	const baseSteps = useMemo(() => ([
		{ key: 'image', component: ImageUploadStep },
		{ key: 'notebook', component: NotebookSelectStep },
		{ key: 'sectionPages', component: SectionAndPagesStep },
		{ key: 'email', component: EmailStep },
		{ key: 'review', component: ReviewAndSubmitStep }
	]), [])

	// compute steps, possibly skipping the notebook step
	const steps = useMemo(() => {
		if (state.notebookId) {
			return baseSteps.filter(s => s.key !== 'notebook')
		}
		return baseSteps
	}, [baseSteps, state.notebookId])

	// current step index
	const [current, setCurrent] = useState(0)

	// keep index valid when steps array changes
	useEffect(() => {
		if (current > steps.length - 1) setCurrent(steps.length - 1)
	}, [steps, current])

	// navigation helpers
	const next = () => setCurrent(i => Math.min(i + 1, steps.length - 1))
	const back = () => setCurrent(i => Math.max(i - 1, 0))

	// loading/error handling for shared config
	if (loading) {
		return <div className="mx-auto max-w-md p-4">Loading…</div>
	}
	if (error) {
		return <div className="mx-auto max-w-md p-4 text-red-600">Failed to load configuration.</div>
	}
	if (!Array.isArray(notebooks) || notebooks.length === 0) {
		return <div className="mx-auto max-w-md p-4">No notebooks available.</div>
	}

	// render current step
	const StepComponent = steps[current].component

	return (
		<div className="mx-auto max-w-md p-4">
			{/* header + progress */}
			<div className="mb-4">
				<h1 className="text-xl font-semibold">Submit your drawing</h1>
				<p className="text-xs text-gray-500 mt-1">
					Step {current + 1} of {steps.length}
				</p>
			</div>

			{/* step content */}
			<div className="rounded-2xl border p-4 shadow-sm">
				<StepComponent
					state={state}
					patch={patch}
					reset={reset}
					next={next}
					back={back}
					/* pass shared config where relevant */
					notebooks={notebooks}
				/>
			</div>

			{/* footer nav (optional; you can remove if steps render their own buttons) */}
			<div className="mt-4 flex items-center justify-between">
				<button
                    type="button"
                    className="rounded-xl border px-4 py-3 disabled:opacity-60"
                    onClick={back}
                    disabled={current === 0}
                >
					Back
				</button>
				<button
                    type="button"
                    className="rounded-xl border px-4 py-3"
                    onClick={next}
                    disabled={current === steps.length - 1}
                >
					Next
				</button>
			</div>
		</div>
	)
}
