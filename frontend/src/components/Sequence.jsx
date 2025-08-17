import React from 'react'

/**
 * Generic, agnostic stepper.
 * - steps: [{ key, component }]
 * - current: number
 * - componentProps: props object passed to the active step
 * - onBack(): void
 * - onNext(): void
 * - onFinish?(): Promise|void   // called when clicking Next on the last step
 * - onValidate?(): boolean|Promise<boolean> // run before advancing (non-last)
 * - allowNext: boolean          // controls Next/Submit enabled state
 * - backLabel / nextLabel: strings
 */
export default function Sequence({
  steps,
  current,
  componentProps = {},
  onBack,
  onNext,
  onFinish,          // NEW
  onValidate,
  allowNext = true,
  backLabel = 'Back',
  nextLabel
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
    if (typeof onValidate === 'function') {
      const ok = await onValidate()
      if (!ok) return
    }
    onNext()
  }

  return (
    <div className="space-y-4">
      <StepComponent {...componentProps} />

      <div className="flex gap-3 pt-2">
        <button
          type="button"
          className="w-1/2 rounded-xl border px-4 py-3 disabled:opacity-60"
          onClick={onBack}
          disabled={!canBack}
        >
          {backLabel}
        </button>

        <button
          type="button"
          className="w-1/2 rounded-xl border px-4 py-3 disabled:opacity-60"
          onClick={handleNext}
          disabled={!allowNext}
        >
          {nextLabel ?? (isLast ? 'Submit' : 'Next')}
        </button>
      </div>

      <p className="text-center text-xs text-gray-500">
        Step {current + 1} of {steps.length}
      </p>
    </div>
  )
}
