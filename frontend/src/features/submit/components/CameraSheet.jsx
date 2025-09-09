// src/features/submit/components/CameraSheet.jsx
import { useEffect, useRef, useState } from "react";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetFooter,
} from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { cropImageBitmapToRatio } from "@/features/submit/lib/cropToRatio";

const TARGET_RATIO = 1; // 1:1 for the exhibition

export default function CameraSheet({ open, onOpenChange, onReady }) {
  const videoRef = useRef(null);
  const [err, setErr] = useState(null);
  const [capturing, setCapturing] = useState(false);

  useEffect(() => {
    let stream;
    (async () => {
      if (!open) return;
      setErr(null);
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: "environment" },
            // aspectRatio is only a hint; we enforce via crop
            aspectRatio: { ideal: TARGET_RATIO },
          },
          audio: false,
        });
        if (videoRef.current) {
          videoRef.current.srcObject = stream;
          // iOS: playsInline avoids fullscreen
          await videoRef.current.play();
        }
      } catch (e) {
        setErr(e?.message || "Camera not available");
      }
    })();
    return () => {
      stream?.getTracks?.().forEach((t) => t.stop());
    };
  }, [open]);

  const capture = async () => {
    const video = videoRef.current;
    if (!video || !video.videoWidth) return;
    setCapturing(true);
    try {
      // Safest path: draw current frame to a canvas, then to ImageBitmap
      const c = document.createElement("canvas");
      c.width = video.videoWidth;
      c.height = video.videoHeight;
      const ctx = c.getContext("2d");
      ctx.drawImage(video, 0, 0, c.width, c.height);
      const blob = await new Promise((res) =>
        c.toBlob(res, "image/jpeg", 0.92)
      );
      const bmp = await createImageBitmap(blob);
      const file = await cropImageBitmapToRatio(
        bmp,
        TARGET_RATIO,
        2048,
        "image/jpeg",
        0.9
      );
      onReady?.(file);
      onOpenChange(false);
    } catch (e) {
      setErr(e?.message || "Could not capture.");
    } finally {
      setCapturing(false);
    }
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="bottom"
        className="sm:max-w-none sm:w-full md:max-w-[28rem] md:mx-auto"
      >
        <SheetHeader>
          <SheetTitle>Take a photo</SheetTitle>
        </SheetHeader>

        <div className="relative w-full">
          {/* Fixed 1:1 frame to guide composition */}
          <div
            className="relative w-full rounded-md overflow-hidden"
            style={{ aspectRatio: "1 / 1" }}
          >
            <video
              ref={videoRef}
              playsInline
              muted
              className="absolute inset-0 h-full w-full object-cover"
            />
            <div className="pointer-events-none absolute inset-0 ring-2 ring-white/80 rounded-md" />
            <div
              className="pointer-events-none absolute inset-0"
              style={{ boxShadow: "inset 0 0 0 9999px rgba(0,0,0,0.18)" }}
            />
          </div>
          {err && <p className="mt-2 text-xs text-red-600">{err}</p>}
        </div>

        <SheetFooter className="flex flex-col gap-2 p-4">
          <Button onClick={capture} disabled={capturing}>
            {capturing ? "Capturing…" : "Capture"}
          </Button>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
