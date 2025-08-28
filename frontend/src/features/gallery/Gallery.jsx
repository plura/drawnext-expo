// src/features/gallery/Gallery.jsx
import React, { useEffect, useMemo, useState } from "react";
import { useConfig } from "@/app/ConfigProvider";
import SectionTitle from "@/components/typography/SectionTitle";
import GalleryItem from "./components/GalleryItem";

const PAGE_SIZE = 24;

export default function Gallery() {
	const { notebooks, loading: configLoading, error: configError } = useConfig();

	const [items, setItems] = useState([]);
	const [offset, setOffset] = useState(0);
	const [hasMore, setHasMore] = useState(false);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	// Map notebooks by id for quick lookup
	const notebooksById = useMemo(() => {
		const m = new Map();
		(notebooks || []).forEach((n) => m.set(Number(n.id), n));
		return m;
	}, [notebooks]);

	async function fetchPage(nextOffset = 0) {
		setLoading(true);
		setError(null);
		try {
			const res = await fetch(
				`/api/drawings/list?limit=${PAGE_SIZE}&offset=${nextOffset}&expand=labels,thumb`
			);
			const json = await res.json();
			if (!res.ok || json?.status === "error") {
				throw new Error(json?.message || `Request failed (${res.status})`);
			}
			const data = Array.isArray(json?.data) ? json.data : [];
			const meta = json?.meta || {};

			setItems((prev) => (nextOffset === 0 ? data : [...prev, ...data]));
			setHasMore(!!meta?.has_more);
			setOffset(Number(meta?.next_offset || 0));
		} catch (e) {
			setError(e?.message || "Failed to load gallery");
		} finally {
			setLoading(false);
		}
	}

	useEffect(() => {
		fetchPage(0);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	if ((configLoading || loading) && items.length === 0) {
		return <div className="p-4">Loading…</div>;
	}
	if (configError) {
		return <div className="p-4 text-red-600">Config failed: {String(configError)}</div>;
	}
	if (error && items.length === 0) {
		return <div className="p-4 text-red-600">{error}</div>;
	}

	return (
		<div className="p-4">
			<SectionTitle title="Gallery" />

			{items.length === 0 ? (
				<p className="mt-2 text-sm text-muted-foreground">No drawings yet.</p>
			) : (
				<div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
					{items.map((item) => {
						const notebook = notebooksById.get(Number(item.notebook_id));
						return (
							<GalleryItem
								key={item.drawing_id}
								item={item}
								notebook={notebook}
							/>
						);
					})}
				</div>
			)}

			{hasMore && (
				<div className="mt-4">
					<button
						type="button"
						onClick={() => fetchPage(offset)}
						className="inline-flex items-center rounded-md border px-3 py-1.5 text-sm hover:bg-gray-50"
						disabled={loading}
					>
						{loading ? "Loading…" : "Load more"}
					</button>
				</div>
			)}
		</div>
	);
}
