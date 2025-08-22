// src/features/submission/steps/ImageUploadStep.jsx
import React, { useRef, useState } from "react"
import { Button } from "@/components/ui/button"
import { useObjectUrl } from "../lib/useObjectUrl"
import { uploadTempImage } from "../lib/api" // NEW

export const stepTitle = "Upload image"
export const stepDescription =
  "Take a photo of your drawing or choose one from your gallery."

export function validateImage(state) {
  return !!state.file
}

export default function ImageUploadStep({ state, patch }) {
  const inputRef = useRef(null)
  const previewUrl = useObjectUrl(state.file)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState(null)

  function openPicker(e) {
    if (e) {
      e.preventDefault()
      e.stopPropagation()
    }
    inputRef.current?.click()
  }

  async function onFileChange(e) {
    const file = e.target.files?.[0]
    if (!file) return

    // Always set the local preview file
    patch({ file })
    setError(null)

    // Kick off temp upload (non-blocking UX; fallback is the legacy path)
    setUploading(true)
    try {
      const json = await uploadTempImage(file) // POST /api/images/temp
      const { token, width, height, hash } = json.data || {}

      if (token) {
        patch({
          uploadToken: token,
          imageMeta: { width, height, hash },
        })
      } else {
        // No token returned: ensure we don't carry a stale one
        patch({ uploadToken: null, imageMeta: null })
      }
    } catch (err) {
      // Do not block user; single-shot submit will still work
      patch({ uploadToken: null, imageMeta: null })
      setError(err?.message || "Could not prepare the image. You can still submit.")
      // Optional: console.warn(err)
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="space-y-3">
      <input
        ref={inputRef}
        id="file"
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={onFileChange}
      />

      <div
        role="button"
        onClick={openPicker}
        className="w-full overflow-hidden rounded-2xl border bg-white p-0 text-left shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        {previewUrl ? (
          <div className="space-y-2 p-2">
            <img
              src={previewUrl}
              alt="Selected image preview"
              className="aspect-[4/3] w-full rounded-lg object-cover"
            />
            <p className="px-1 pb-2 text-sm text-muted-foreground">
              {uploading ? "Uploading…" : "Tap to replace photo"}
            </p>
          </div>
        ) : (
          <div className="space-y-1 p-6">
            <p className="text-base font-medium">Add photo</p>
            <p className="text-sm text-muted-foreground">
              Take a new picture or choose from gallery
            </p>
            <div className="pt-3">
              <Button type="button" variant="outline" onClick={openPicker}>
                Choose image
              </Button>
            </div>
          </div>
        )}
      </div>

      {error && (
        <p className="text-xs text-red-600">
          {error}
        </p>
      )}
    </div>
  )
}
