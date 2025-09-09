// src/features/submit/steps/ImageUploadStep.jsx
import { useMemo, useState, useRef } from "react";
import { Camera as CameraIcon } from "lucide-react";
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";
import DrawingImage from "@/components/drawings/DrawingImage";
import { useObjectURL } from "@/features/submit/lib/useObjectURL";
import { uploadTempImage } from "@/features/submit/lib/api";
import CameraSheet from "@/features/submit/components/CameraSheet";
import {
  bitmapFromFile,
  cropImageBitmapToRatio,
} from "@/features/submit/lib/cropToRatio";

export const stepTitle = "Upload image";
export const stepDescription =
  "Take a photo. We'll auto-crop to a square (1:1) to match the drawing area."; // updated copy

export function validateImage(state) {
  return !!state.file;
}

const TARGET_RATIO = 1; // enforce 1:1 output
const LONG_EDGE = 2048; // clamp size for upload/derivatives

export default function ImageUploadStep({ state, patch, notebooks }) {
  const cameraInputRef = useRef(null);
  const galleryInputRef = useRef(null);

  const [cameraOpen, setCameraOpen] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState(null);

  const previewUrl = useObjectURL(state.file);

  const nb = useMemo(
    () =>
      notebooks?.find((n) => Number(n.id) === Number(state.notebookId)) || null,
    [notebooks, state.notebookId]
  );
  const aspect = nb?.aspect || { w: 1, h: 1 };
  const aspectLabel = `${aspect.w}:${aspect.h}`;

   function openCamera(e) {
     e?.preventDefault?.();
     cameraInputRef.current?.click();
   }
   function openGallery(e) {
     e?.preventDefault?.();
     galleryInputRef.current?.click();
   }

  async function handleProcessedFile(file) {
    patch({ file, uploadToken: null, imageMeta: null });
    setError(null);
    setUploading(true);
    try {
      const json = await uploadTempImage(file);
      const { token, width, height, hash } = json?.data || {};
      if (token) {
        patch({
          uploadToken: token,
          imageMeta: { width, height, hash, ratio: TARGET_RATIO },
        });
      }
    } catch (err) {
      patch({ uploadToken: null });
      setError(
        err?.message || "Could not prepare the image. You can still submit."
      );
    } finally {
      setUploading(false);
    }
  }

  async function processToSquare(file) {
    setProcessing(true);
    try {
      const bmp = await bitmapFromFile(file);
      const cropped = await cropImageBitmapToRatio(
        bmp,
        TARGET_RATIO,
        LONG_EDGE,
        "image/jpeg",
        0.9
      );
      await handleProcessedFile(cropped);
    } catch {
      await handleProcessedFile(file);
    } finally {
      setProcessing(false);
    }
  }

   async function onFileChange(e) {
     const file = e.target.files?.[0];
     e.target.value = "";
     if (!file) return;
     await processToSquare(file);
   }

  return (
    <Card className="space-y-3">
      {/* Hidden inputs (disabled) */}
      
      <input
        ref={cameraInputRef}
        id="file-camera"
        type="file"
        accept="image/*"
        className="hidden"
        onChange={onFileChange}
      />
      <input
        ref={galleryInputRef}
        id="file-gallery"
        type="file"
        accept="image/*"
        className="hidden"
        onChange={onFileChange}
      />
     

      {/* Clickable preview area opens the guided camera sheet */}
      <div
        role="button"
        tabIndex={0}
        onClick={() => setCameraOpen(true)}
        onKeyDown={(e) =>
          (e.key === "Enter" || e.key === " ") && setCameraOpen(true)
        }
        className="w-full text-left transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md overflow-hidden mb-0"
        aria-label="Open camera to take a photo"
      >
        {previewUrl ? (
          <div className="space-y-2 p-2">
            <DrawingImage
              src={previewUrl}
              alt="Selected image preview"
              notebook={nb}
            />
            <p className="px-1 text-sm text-muted-foreground">
              {processing
                ? "Processing…"
                : uploading
                ? "Uploading…"
                : "Tap to retake a photo"}
            </p>
            <p className="px-1 text-[11px] text-muted-foreground">
              Sections are roughly {aspectLabel}. We auto-crop your photo to a
              square (1:1).
            </p>
          </div>
        ) : (
          <div className="space-y-2 p-4">
            <DrawingImage
              src={null}
              alt=""
              notebook={nb}
              placeholder="No image yet. Click here."
            />
            <p className="text-base font-medium">Add photo</p>
            <p className="text-sm text-muted-foreground">
              We’ll auto-crop to 1:1 to match the square drawing section.
            </p>
          </div>
        )}
      </div>

      {/* Actions */}
      <div className="flex flex-wrap gap-2 px-4 pb-4">
        <Button
          size="lg"
          className="flex-1"
          type="button"
          onClick={() => setCameraOpen(true)}
        >
          <CameraIcon
            className="mr-1 shrink-0"
            strokeWidth={2.25}
            aria-hidden="true"
          />
          Photo
        </Button>

        
        <Button className="flex-1" type="button" variant="outline" onClick={openCamera}>
          {previewUrl ? "Retake (system)" : "Camera"}
        </Button>
        <Button className="flex-1" type="button" variant="outline" onClick={openGallery}>
          Gallery
        </Button>
       
      </div>

      {error && <p className="text-xs text-red-600 px-2">{error}</p>}

      <CameraSheet
        open={cameraOpen}
        onOpenChange={setCameraOpen}
        onReady={(file) => processToSquare(file)}
      />
    </Card>
  );
}
