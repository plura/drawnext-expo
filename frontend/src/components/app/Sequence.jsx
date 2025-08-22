// src/components/apps/Sequence.jsx
import React from "react"
import { Button } from "@/components/ui/button"

/**
 * Generic, agnostic stepper.
 * - steps: [{ key, component, validate?, title?, description? }]
 * - current: number
 * - componentProps: props object passed to the active step
 * - onBack(): void
 * - onNext(): void
 * - onFinish?(): Promise|void   // called when clicking Next on the last step
 * - onValidate?(): boolean|Promise<boolean> // run before advancing (non-last)
 * - allowNext: boolean          // controls Next/Submit enabled state
 * - backLabel / nextLabel: strings
 * - title / description: strings (from active step)
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
			{title && <h2 className="text-lg font-semibold">{title}</h2>}
			{description && (
				<p className="text-sm text-muted-foreground">{description}</p>
			)}

			<StepComponent {...componentProps} />

			{/* Bottom navigation — sticky only on mobile */}
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
