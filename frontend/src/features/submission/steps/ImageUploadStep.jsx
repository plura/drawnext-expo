// src/features/submission/steps/ImageUploadStep.jsx
import React, { useRef } from "react"
import { Button } from "@/components/ui/button"
import { useObjectUrl } from "../lib/useObjectUrl"

export const stepTitle = "Upload image"
export const stepDescription =
  "Take a photo of your drawing or choose one from your gallery."

export function validateImage(state) {
  return !!state.file
}

export default function ImageUploadStep({ state, patch }) {
  const inputRef = useRef(null)
  const previewUrl = useObjectUrl(state.file)

  function openPicker(e) {
    if (e) {
      e.preventDefault()
      e.stopPropagation()
    }
    inputRef.current?.click()
  }

  function onFileChange(e) {
    const file = e.target.files?.[0]
    if (!file) return
    patch({ file })
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
              Tap to replace photo
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
    </div>
  )
}
