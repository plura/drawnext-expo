import React, { useEffect, useRef, useState } from 'react'

// UI
export default function ImageUploadStep({ state, patch }) {
  const inputRef = useRef(null)
  const [previewUrl, setPreviewUrl] = useState('')

  useEffect(() => {
    if (!state.file) { setPreviewUrl(''); return }
    const url = URL.createObjectURL(state.file)
    setPreviewUrl(url)
    return () => URL.revokeObjectURL(url)
  }, [state.file])

  function openPicker() { inputRef.current?.click() }
  function onFileChange(e) {
    const file = e.target.files?.[0]
    if (!file) return
    patch({ file })
  }

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold">Upload image</h2>

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        capture="environment"
        onChange={onFileChange}
        className="hidden"
      />

      <button
        type="button"
        onClick={openPicker}
        className="w-full rounded-2xl border p-6 text-left hover:bg-gray-50"
      >
        {previewUrl ? (
          <div className="space-y-2">
            <img src={previewUrl} alt="preview" className="w-full rounded-lg" />
            <p className="text-sm text-gray-600">Tap to replace photo</p>
          </div>
        ) : (
          <div className="space-y-1">
            <p className="text-base font-medium">Add photo</p>
            <p className="text-sm text-gray-600">Take a new picture or choose from gallery</p>
          </div>
        )}
      </button>
    </div>
  )
}

// Validator (named export)
export function validateImage(state) {
  return !!state.file
}
