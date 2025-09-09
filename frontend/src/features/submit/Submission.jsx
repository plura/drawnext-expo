// src/features/submit/Submission.jsx

import React, { useEffect, useMemo, useState } from "react";
// React Router hook to read the current URL (so we can react to ?notebook_id)
import { useLocation } from "react-router-dom";
// Local submission store (stable patch/reset via useCallback to avoid effect loops)
import { useSubmissionStore } from "./useSubmissionStore";
// Loads notebooks/config (async); effect below waits on this to avoid race conditions
import { useConfig } from "../../app/ConfigProvider.jsx";
// Stepper UI + helpers
import Sequence from "@/components/patterns/Sequence";
import AppAlert from "@/components/feedback/AppAlert";
import SuccessPanel from "./SuccessPanel";
import CustomSequenceTitle from "./components/CustomSequenceTitle";

// --- Steps (each step exports title/description/validate so the stepper is declarative)
import ImageUploadStep, {
  validateImage,
  stepTitle as titleImage,
  stepDescription as descImage,
} from "./steps/ImageUploadStep";

import NotebookSelectStep, {
  validateNotebook,
  stepTitle as titleNotebook,
  stepDescription as descNotebook,
} from "./steps/NotebookSelectStep";

import SectionAndPagesStep, {
  validateSectionPages,
  stepTitle as titleSectionPages,
  stepDescription as descSectionPages,
} from "./steps/SectionAndPagesStep";

import EmailStep, {
  validateEmail,
  stepTitle as titleEmail,
  stepDescription as descEmail,
} from "./steps/EmailStep";

import ReviewAndSubmitStep, {
  stepTitle as titleReview,
} from "./steps/ReviewAndSubmitStep";

import { buildSubmissionPayload } from "./lib/payload";

export default function Submission() {
  // Config (includes notebooks). These flags drive early return loading/error UIs.
  const { notebooks, loading, error } = useConfig();

  // Local submission state + stable actions
  const { state, patch, reset } = useSubmissionStore();

  // Router location (we only need the search/query string)
  const { search } = useLocation();

  // ──────────────────────────────────────────────────────────────────────────────
  // Respect ?notebook_id from QR links.
  //
  // Why an effect?
  // - We want to *sync external state (URL)* into our *internal store*.
  // - We wait for `notebooks` so we can validate the id before setting it.
  // Dependencies:
  // - `search`: runs when URL search changes (first load + later changes)
  // - `notebooks`: wait for data; avoids running with empty list
  // - `state.notebookId`: guard to avoid redundant updates
  // - `patch`: stable (useCallback), so this effect doesn’t loop
  // Guards:
  // - ignore invalid numbers
  // - no-op if id already selected
  // - only set if that notebook actually exists
  useEffect(() => {
    if (!Array.isArray(notebooks) || notebooks.length === 0) return;

    const idParam = new URLSearchParams(search).get("notebook_id");
    const nextId = Number(idParam);

    if (!Number.isFinite(nextId)) return;
    if (state.notebookId === nextId) return;

    const exists = notebooks.some((n) => Number(n.id) === nextId);
    if (!exists) return;

    patch({ notebookId: nextId });
  }, [search, notebooks, state.notebookId, patch]);

  // ──────────────────────────────────────────────────────────────────────────────
  // Base step definitions (static order). useMemo avoids re-allocating this array
  // unless `notebooks` changes (only needed for validateSectionPages).
  const baseSteps = useMemo(
    () => [
      {
        key: "email",
        component: EmailStep,
        validate: (s) => validateEmail(s),
        title: titleEmail,
        description: descEmail,
      },
      {
        key: "image",
        component: ImageUploadStep,
        validate: (s) => validateImage(s),
        title: titleImage,
        description: descImage,
      },
      {
        key: "notebook",
        component: NotebookSelectStep,
        validate: (s) => validateNotebook(s),
        title: titleNotebook,
        description: descNotebook,
      },
      {
        key: "sectionPages",
        component: SectionAndPagesStep,
        validate: (s) => validateSectionPages(s, notebooks),
        title: titleSectionPages,
        description: descSectionPages,
      },
      {
        key: "review",
        component: ReviewAndSubmitStep,
        title: titleReview,
      },
    ],
    [notebooks]
  );

  // If a notebook is preselected (QR), we drop the "notebook" step entirely.
  // useMemo so we only recompute when baseSteps or notebookId changes.
  const steps = useMemo(
    () =>
      state.notebookId
        ? baseSteps.filter((s) => s.key !== "notebook")
        : baseSteps,
    [baseSteps, state.notebookId]
  );

  // Index of the current step in the *filtered* `steps`.
  const [current, setCurrent] = useState(0);

  // IMPORTANT: If `steps` shrinks (e.g., notebook gets selected and we drop a step),
  // `current` might suddenly be out-of-bounds. This effect clamps the index *every*
  // time the length changes so we never read `steps[current]` as undefined.
  useEffect(() => {
    setCurrent((i) => Math.min(i, Math.max(steps.length - 1, 0)));
  }, [steps.length]);

  // When a notebook becomes preselected (from QR or otherwise), jump back to the first step.
  // Prevents landing on a removed step index.
  useEffect(() => {
    if (state.notebookId != null) {
      setCurrent(0);
    }
  }, [state.notebookId]);

  // Submission UX flags/messages
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState(null); // { type: "success" | "error", text: string }

  // Early exits for loading/error/empty config
  if (loading) {
    return <div className="mx-auto max-w-md p-4">Loading…</div>;
  }
  if (error) {
    return (
      <div className="mx-auto max-w-md p-4">
        <AppAlert variant="destructive" title="Couldn’t load configuration">
          Please refresh the page. If the problem persists, try again later.
        </AppAlert>
      </div>
    );
  }
  if (!Array.isArray(notebooks) || notebooks.length === 0) {
    return <div className="mx-auto max-w-md p-4">No notebooks available.</div>;
  }

  // If the submission succeeded, show a success panel instead of the stepper.
  if (message?.type === "success") {
    return (
      <div className="mx-auto max-w-md p-4">
        <SuccessPanel
          onAnother={() => {
            // Clear all state but keep notebook if you like (handled in RESET)
            reset();
            setCurrent(0);
            setMessage(null);
          }}
          homeHref="/"
        />
      </div>
    );
  }

  // Now that we’re sure `current` is clamped, grab the current step definition.
  const stepDef = steps[current];

  // Defensive guard: in case React Strict Mode or any async transition
  // briefly leaves us without a step, show a tiny placeholder instead of crashing.
  if (!stepDef) {
    return <div className="mx-auto max-w-md p-4">Preparing…</div>;
  }

  // Button enable/disable logic:
  // - On last step: block when submitting
  // - Else: rely on the step's validate function if defined
  const isLast = current === steps.length - 1;
  const allowNext = isLast
    ? !submitting
    : stepDef.validate
    ? !!stepDef.validate(state)
    : true;

  // Optional pre-validation handler for the stepper (only if the step provides it)
  const onValidate = isLast
    ? undefined
    : stepDef.validate
    ? () => stepDef.validate(state)
    : undefined;

  // Submission handler (two-phase if uploadToken is present, else multipart)
  // Notes:
  // - We clear any previous message
  // - We guard against double-submits with `submitting`
  // - We surface nicer error messages for 409/422
  async function handleFinish() {
    if (submitting) return;
    setMessage(null);
    setSubmitting(true);

    try {
      const payload = buildSubmissionPayload(state);
      let res;

      if (state.uploadToken) {
        // Two-phase: file already in temp storage, send JSON with token
        payload.drawing.upload_token = state.uploadToken;
        res = await fetch("/api/drawings/create", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
      } else {
        // Fallback: send file in multipart
        const fd = new FormData();
        if (state.file) fd.append("drawing", state.file);
        fd.append("input", JSON.stringify(payload));
        res = await fetch("/api/drawings/create", {
          method: "POST",
          body: fd,
        });
      }

      const json = await res.json().catch(() => ({}));

      if (!res.ok) {
        if (res.status === 409) {
          // Slot conflict (unique constraint)
          throw new Error(
            json?.message || "That drawing slot is already taken."
          );
        }
        if (res.status === 422) {
          // Validation error (use first error if present)
          const first = json?.details?.errors
            ? Object.values(json.details.errors)[0]
            : json?.message || "Validation failed";
          throw new Error(String(first));
        }
        throw new Error(
          json?.message || "Submission failed. Please try again."
        );
      }

      // Success → show panel (RESET clears file/uploadToken/imageMeta)
      setMessage({
        type: "success",
        text: "Thank you! Your drawing was submitted.",
      });
      reset();
      setCurrent(0);
    } catch (e) {
      setMessage({ type: "error", text: e.message });
    } finally {
      setSubmitting(false);
    }
  }

  // Props passed down to each step component (they can read/patch the same store)
  const componentProps = { state, patch, reset, notebooks };

  // Layout: the Sequence component renders the step UI and navigation.
  // We pass computed labels, guards, and a custom title that shows the selected notebook.
  return (
    <div className="md:block flex-1 min-h-0 flex flex-col">
      <div className="md:block flex-1 min-h-0 flex flex-col">
        <Sequence
          steps={steps}
          current={current}
          componentProps={componentProps}
          onBack={() => setCurrent((i) => Math.max(i - 1, 0))}
          onNext={() => setCurrent((i) => Math.min(i + 1, steps.length - 1))}
          onValidate={onValidate}
          onFinish={handleFinish}
          allowNext={allowNext}
          backLabel="Back"
          nextLabel={isLast ? (submitting ? "Submitting…" : "Submit") : "Next"}
          title={
            state.notebookId ? (
              <CustomSequenceTitle
                title={stepDef.title}
                // Numeric compare is safer in case ids are strings from API
                notebook={notebooks.find(
                  (n) => Number(n.id) === Number(state.notebookId)
                )}
              />
            ) : (
              stepDef.title
            )
          }
          description={stepDef.description}
        />

        {message?.type === "error" && (
          <AppAlert variant="destructive" title="Submission failed">
            {message.text}
          </AppAlert>
        )}

        {import.meta.env.DEV && (
          <pre className="hidden lg:block fixed bottom-2 left-2 max-h-56 w-80 overflow-auto rounded-md border bg-white/90 p-2 text-[11px]">
            {JSON.stringify(state, null, 2)}
          </pre>
        )}
      </div>
    </div>
  );
}
