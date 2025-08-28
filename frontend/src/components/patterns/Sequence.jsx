// src/components/patterns/Sequence.jsx

import React from "react"
import { Button } from "@/components/ui/button"
import SectionTitle from "@/components/typography/SectionTitle"

/**
 * Generic, agnostic stepper.
 * - steps: [{ key, component, validate?, title?, description? }]
 * - current: number
 * - componentProps: props object passed to the active step
 * - onBack(): void
 * - onNext(): void
 * - onFinish?(): Promise|void
 * - onValidate?(): boolean|Promise<boolean>
 * - allowNext: boolean
 * - backLabel / nextLabel: strings
 * - title / description: string | JSX
 */
export default function Sequence({
	steps,
	current,
	componentProps = {},
	onBack,
	onNext,
	onFinish,
	onValidate,
	allowNext = true,
	backLabel = "Back",
	nextLabel,
	title,
	description,
}) {
	const step = steps[current]
	const StepComponent = step.component
	const canBack = current > 0
	const isLast = current === steps.length - 1

	async function handleNext() {
		if (isLast) {
			await onFinish?.()
			return
		}
		if (typeof onValidate === "function") {
			const ok = await onValidate()
			if (!ok) return
		}
		onNext()
	}

	return (
		<div className="md:block flex flex-col flex-1 min-h-0 space-y-4">
			{/* Title: accept string or JSX */}
			{typeof title === "string" ? (
				<SectionTitle title={title} />
			) : (
				title
			)}

			{description && (
				<p className="text-sm text-muted-foreground">{description}</p>
			)}

			<StepComponent {...componentProps} />

			{/* Bottom navigation */}
			<div
				className="
					md:mt-0 md:static md:border-0 md:bg-transparent md:p-0 md:mx-0
					mt-auto sticky bottom-0 left-0 right-0
					bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/60
					border-t p-3 -mx-4
					pb-[calc(0.75rem+env(safe-area-inset-bottom))]
				"
			>
				<div className="flex gap-3">
					<Button
						variant="outline"
						className="flex-1"
						onClick={onBack}
						disabled={!canBack}
						type="button"
					>
						{backLabel}
					</Button>

					<Button
						className="flex-1"
						onClick={handleNext}
						disabled={!allowNext}
						type="button"
					>
						{nextLabel ?? (isLast ? "Submit" : "Next")}
					</Button>
				</div>
			</div>

			<p className="text-center text-xs text-gray-500">
				Step {current + 1} of {steps.length}
			</p>
		</div>
	)
}
