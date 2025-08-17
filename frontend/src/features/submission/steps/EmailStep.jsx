// UI
export default function EmailStep({ state, patch }) {
  function onChange(e) {
    patch({ email: e.target.value })
  }
  function onBlur() {
    if (!state.email) return
    patch({ email: state.email.trim() })
  }

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold">Email</h2>
      <input
        type="email"
        autoComplete="email"
        inputMode="email"
        className="mt-1 block w-full rounded-lg border p-2"
        value={state.email}
        onChange={onChange}
        onBlur={onBlur}
        placeholder="your@email.com"
      />
      <p className="text-xs text-gray-500">We’ll only use this to associate your submission.</p>
    </div>
  )
}

// Validator
export function validateEmail(state) {
  return typeof state.email === 'string' && /\S+@\S+\.\S+/.test(state.email)
}
