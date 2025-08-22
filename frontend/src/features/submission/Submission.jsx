// src/features/submission/Submission.jsx
import React, { useEffect, useMemo, useState } from "react";
import { useSubmissionStore } from "./useSubmissionStore";
import { useConfig } from "../../app/ConfigProvider.jsx";
import Sequence from "../../components/app/Sequence.jsx";
import AppAlert from "@/components/app/AppAlert";
import SuccessPanel from "./SuccessPanel";

// Steps
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
  const { notebooks, loading, error } = useConfig();
  const { state, patch, reset } = useSubmissionStore();

  // respect ?notebook_id
  useEffect(() => {
	const id = new URLSearchParams(window.location.search).get("notebook_id");
	if (id) patch({ notebookId: Number(id) });
  }, [patch]);

  const baseSteps = useMemo(
	() => [
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
		key: "email",
		component: EmailStep,
		validate: (s) => validateEmail(s),
		title: titleEmail,
		description: descEmail,
	  },
	  {
		key: "review",
		component: ReviewAndSubmitStep,
		title: titleReview,
	  },
	],
	[notebooks]
  );

  // skip notebook step if preselected
  const steps = useMemo(
	() =>
	  state.notebookId
		? baseSteps.filter((s) => s.key !== "notebook")
		: baseSteps,
	[baseSteps, state.notebookId]
  );

  const [current, setCurrent] = useState(0);
  useEffect(() => {
	if (current > steps.length - 1) setCurrent(steps.length - 1);
  }, [steps, current]);

  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState(null); // { type: "success" | "error", text: string }

  /* ---------------- Loading / Error ---------------- */
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

  /* ---------------- Success replaces the stepper ---------------- */
  if (message?.type === "success") {
	return (
	  <div className="mx-auto max-w-md p-4">
		<SuccessPanel
		  onAnother={() => {
			reset();
			setCurrent(0);
			setMessage(null);
		  }}
		  homeHref="/"
		/>
	  </div>
	);
  }

  /* ---------------- Stepper wiring ---------------- */
  const stepDef = steps[current];
  const isLast = current === steps.length - 1;
  const allowNext = isLast
	? !submitting
	: stepDef.validate
	? !!stepDef.validate(state)
	: true;

  const onValidate = isLast
	? undefined
	: stepDef.validate
	? () => stepDef.validate(state)
	: undefined;

  async function handleFinish() {
	if (submitting) return; // prevent double-submit
	setMessage(null);
	setSubmitting(true);

	try {
	  const payload = buildSubmissionPayload(state);
	  let res;

	  if (state.uploadToken) {
		// two-phase: include token, send JSON
		payload.drawing.upload_token = state.uploadToken;
		res = await fetch("/api/drawings/create", {
		  method: "POST",
		  headers: { "Content-Type": "application/json" },
		  body: JSON.stringify(payload),
		});
	  } else {
		// fallback: multipart with file
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
		if (res.status === 409)
		  throw new Error(
			json?.message || "That drawing slot is already taken."
		  );
		if (res.status === 422) {
		  const first = json?.details?.errors
			? Object.values(json.details.errors)[0]
			: json?.message || "Validation failed";
		  throw new Error(String(first));
		}
		throw new Error(
		  json?.message || "Submission failed. Please try again."
		);
	  }

	  // Success → show panel (reset clears file/uploadToken/imageMeta)
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

  const componentProps = { state, patch, reset, notebooks };

  /* ---------------- Layout (mobile app-like) ---------------- */
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
		  title={stepDef.title}
		  description={stepDef.description}
		/>

		{message?.type === "error" && (
		  <AppAlert variant="destructive" title="Submission failed">
			{message.text}
		  </AppAlert>
		)}

		{import.meta.env.DEV && (
		  <pre className="fixed bottom-2 left-2 max-h-56 w-80 overflow-auto rounded-md border bg-white/90 p-2 text-[11px]">
			{JSON.stringify(state, null, 2)}
		  </pre>
		)}
	  </div>
	</div>
  );
}
