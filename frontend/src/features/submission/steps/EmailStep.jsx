// src/features/submission/steps/EmailStep.jsx
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

export const stepTitle = "Enter your email"
export const stepDescription = "We’ll only use it to link your drawing."

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function validateEmail(state) {
  return EMAIL_RE.test(String(state.email || "").trim())
}

export default function EmailStep({ state, patch }) {
  return (
    <form
      autoComplete="on"
      className="space-y-3"
      onSubmit={(e) => e.preventDefault()} // stepper handles Next/Submit
    >
      <div>
        <Label htmlFor="email" className="mb-1 block">Email</Label>
        <Input
          id="email"
          name="email"              // important for mobile autofill
          type="email"
          autoComplete="email"      // standard token
          inputMode="email"
          autoCapitalize="off"
          autoCorrect="off"
          spellCheck={false}
          value={state.email ?? ""}
          onChange={(e) => patch({ email: e.target.value })}
          onBlur={() => state.email && patch({ email: state.email.trim() })}
          placeholder="you@example.com"
          enterKeyHint="done"
        />
      </div>

      <p className="text-xs text-muted-foreground">
        Tip: your saved email should appear above the keyboard.
      </p>
    </form>
  )
}
