// src/features/submit/steps/ImageUploadStep.jsx
import React, { useRef, useState } from "react";
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";
import DrawingImage from "@/components/drawings/DrawingImage";
import { useObjectUrl } from "../lib/useObjectUrl";
import { uploadTempImage } from "../lib/api";

export const stepTitle = "Upload image";
export const stepDescription =
	"Take a photo of your drawing or choose one from your gallery.";

export function validateImage(state) {
	return !!state.file;
}

export default function ImageUploadStep({ state, patch, notebooks }) {
	const inputRef = useRef(null);
	const previewUrl = useObjectUrl(state.file);
	const [uploading, setUploading] = useState(false);
	const [error, setError] = useState(null);

	// Current notebook (to pick up aspect ratio); fallback handled inside DrawingImage
	const nb = notebooks?.find((n) => Number(n.id) === Number(state.notebookId)) || null;
	const aspect = nb?.aspect || { w: 1, h: 1 };
	const aspectLabel = `${aspect.w}:${aspect.h}`;

	function openPicker(e) {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}
		inputRef.current?.click();
	}

	async function onFileChange(e) {
		const file = e.target.files?.[0];
		if (!file) return;

		// Local preview immediately
		patch({ file });
		setError(null);

		// Two-phase temp upload (non-blocking)
		setUploading(true);
		try {
			const json = await uploadTempImage(file); // POST /api/images/temp
			const { token, width, height, hash } = json.data || {};
			if (token) {
				patch({ uploadToken: token, imageMeta: { width, height, hash } });
			} else {
				patch({ uploadToken: null, imageMeta: null });
			}
		} catch (err) {
			patch({ uploadToken: null, imageMeta: null });
			setError(err?.message || "Could not prepare the image. You can still submit.");
		} finally {
			setUploading(false);
		}
	}

	return (
		<Card className="space-y-3">
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
				tabIndex={0}
				onClick={openPicker}
				onKeyDown={(e) => (e.key === "Enter" || e.key === " ") && openPicker(e)}
				className="w-full text-left transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md overflow-hidden"
			>
				{previewUrl ? (
					<div className="space-y-2 p-2">
						<DrawingImage
							src={previewUrl}
							alt="Selected image preview"
							notebook={nb}
						/>
						<p className="px-1 text-sm text-muted-foreground">
							{uploading ? "Uploading…" : "Tap to replace photo"}
						</p>
						<p className="px-1 text-[11px] text-muted-foreground">
							Sections in the physical ring-binder notebooks are roughly {aspectLabel}.
							Images are shown in a {aspectLabel} frame and may crop at the edges—try
							framing your photo accordingly.
						</p>
					</div>
				) : (
					<div className="space-y-2 p-4">
						<DrawingImage
							src={null}
							alt=""
							notebook={nb}
							placeholder="No image yet"
						/>
						<p className="text-base font-medium">Add photo</p>
						<p className="text-sm text-muted-foreground">
							Sections in the physical ring-binder notebooks are roughly {aspectLabel}.
							Images are shown in a {aspectLabel} frame and may crop at the edges—try
							framing your photo accordingly.
						</p>
						<div className="pt-2">
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
		</Card>
	);
}
