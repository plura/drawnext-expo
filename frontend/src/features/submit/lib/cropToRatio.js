// src/features/submit/lib/cropToRatio.js
// Crop any ImageBitmap to a fixed ratio (e.g., 1:1) and scale to a longEdge.
// Returns a File ready for your existing temp upload.
export async function cropImageBitmapToRatio(
  imageBitmap,
  ratio = 1,         // e.g. 1 for 1:1, 3/4 for 3:4
  longEdge = 2048,   // output resolution guard
  mime = "image/jpeg",
  quality = 0.9
) {
  const srcW = imageBitmap.width;
  const srcH = imageBitmap.height;
  const srcRatio = srcW / srcH;

  let cropW, cropH, sx, sy;
  if (srcRatio > ratio) {
    cropH = srcH;
    cropW = Math.round(cropH * ratio);
    sx = Math.round((srcW - cropW) / 2);
    sy = 0;
  } else {
    cropW = srcW;
    cropH = Math.round(cropW / ratio);
    sx = 0;
    sy = Math.round((srcH - cropH) / 2);
  }

  const scale = cropW > cropH ? longEdge / cropW : longEdge / cropH;
  const outW = Math.max(1, Math.round(cropW * scale));
  const outH = Math.max(1, Math.round(cropH * scale));

  const canvas = document.createElement("canvas");
  canvas.width = outW;
  canvas.height = outH;
  const ctx = canvas.getContext("2d");
  ctx.drawImage(imageBitmap, sx, sy, cropW, cropH, 0, 0, outW, outH);

  const blob = await new Promise((res) => canvas.toBlob(res, mime, quality));
  const ext = mime.includes("webp") ? "webp" : mime.includes("png") ? "png" : "jpg";
  return new File([blob], `photo-${Date.now()}.${ext}`, { type: blob.type });
}

// Quick util: build ImageBitmap from a File/Blob with wide browser support
export async function bitmapFromFile(file) {
  // Some iOS versions prefer createImageBitmap from ImageData/Canvas; this path is robust.
  const buf = await file.arrayBuffer();
  const blob = new Blob([buf], { type: file.type || "image/*" });
  try {
    return await createImageBitmap(blob);
  } catch {
    // Fallback: draw into a canvas first
    const img = document.createElement("img");
    const url = URL.createObjectURL(blob);
    try {
      await new Promise((ok, err) => {
        img.onload = ok;
        img.onerror = err;
        img.src = url;
      });
      const c = document.createElement("canvas");
      c.width = img.naturalWidth || img.width;
      c.height = img.naturalHeight || img.height;
      c.getContext("2d").drawImage(img, 0, 0);
      return await createImageBitmap(c);
    } finally {
      URL.revokeObjectURL(url);
    }
  }
}
