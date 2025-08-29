// src/features/admin/pages/DrawingsList.jsx
/**
 * Admin → DrawingsList
 * --------------------
 * Paginated list with simple filters:
 * - Filters: notebook, section, page (matches backend /api/drawings/list)
 * - Uses expand=labels,user,thumb to enrich rows for admin review
 * - Click a row to navigate to /admin/drawings/:id
 *
 * Notes:
 * - Reuses ConfigProvider (global) to populate notebook/section filters
 * - Keeps UI minimal; replace with shadcn components later if you want
 */

import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { useConfig } from "@/app/ConfigProvider";
import { cn } from "@/lib/utils";

const PAGE_SIZE = 24;

export default function DrawingsList() {
	const { notebooks, loading: configLoading, error: configError } = useConfig();

	const [items, setItems] = useState([]);
	const [offset, setOffset] = useState(0);
	const [hasMore, setHasMore] = useState(false);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	// filters
	const [notebookId, setNotebookId] = useState("");
	const [sectionId, setSectionId] = useState("");
	const [page, setPage] = useState("");

	// lookup helpers
	const notebooksById = useMemo(() => {
		const m = new Map();
		(notebooks || []).forEach((n) => m.set(Number(n.id), n));
		return m;
	}, [notebooks]);

	const sectionsForNotebook = useMemo(() => {
		if (!notebookId) return [];
		const nb = notebooksById.get(Number(notebookId));
		return nb?.sections || [];
	}, [notebookId, notebooksById]);

	/**
	 * Build query string for /api/drawings/list based on current filters & pagination.
	 */
	function buildQuery(nextOffset = 0) {
		const params = new URLSearchParams();
		params.set("limit", String(PAGE_SIZE));
		params.set("offset", String(nextOffset));
		params.set("expand", "labels,user,thumb");
		if (notebookId) params.set("notebook_id", String(notebookId));
		if (sectionId) params.set("section_id", String(sectionId));
		if (page) params.set("page", String(page));
		return `/api/drawings/list?${params.toString()}`;
	}

	/**
	 * Fetch a page of results; when nextOffset=0 we reset the list.
	 */
	async function fetchPage(nextOffset = 0) {
		setLoading(true);
		setError(null);
		try {
			const url = buildQuery(nextOffset);
			const res = await fetch(url);
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
			setError(e?.message || "Failed to load drawings");
		} finally {
			setLoading(false);
		}
	}

	// initial load
	useEffect(() => {
		fetchPage(0);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// re-fetch when filters change
	useEffect(() => {
		// reset pagination when filter changes
		fetchPage(0);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [notebookId, sectionId, page]);

	if (configLoading || (loading && items.length === 0)) {
		return <div className="p-4">Loading…</div>;
	}
	if (configError) {
		return <div className="p-4 text-red-600">Config failed: {String(configError)}</div>;
	}
	if (error && items.length === 0) {
		return <div className="p-4 text-red-600">{error}</div>;
	}

	return (
		<div className="p-4 space-y-4">
			<div className="flex items-center justify-between gap-3">
				<h1 className="text-xl font-semibold">Drawings</h1>
			</div>

			{/* Filters */}
			<div className="flex flex-wrap items-end gap-3 rounded-lg border bg-white p-3">
                {/* Notebook */}
				<div>
					<label className="block text-xs text-muted-foreground mb-1">Notebook</label>
					<select
						className="w-44 rounded-md border bg-white p-1 text-sm"
						value={notebookId}
						onChange={(e) => {
							setNotebookId(e.target.value);
							setSectionId(""); // reset section when notebook changes
						}}
					>
						<option value="">All</option>
						{(notebooks || []).map((n) => (
							<option key={n.id} value={n.id}>
								{n.title || `Notebook ${n.id}`}
							</option>
						))}
					</select>
				</div>

				{/* Section */}
				<div>
					<label className="block text-xs text-muted-foreground mb-1">Section</label>
					<select
						className="w-44 rounded-md border bg-white p-1 text-sm"
						value={sectionId}
						onChange={(e) => setSectionId(e.target.value)}
						disabled={!notebookId}
					>
						<option value="">All</option>
						{sectionsForNotebook.map((s) => (
							<option key={s.id} value={s.id}>
                                {s.label || `Section ${s.position}`}
							</option>
						))}
					</select>
				</div>

				{/* Page */}
				<div>
					<label className="block text-xs text-muted-foreground mb-1">Page</label>
					<input
						type="number"
						min={1}
						className="w-28 rounded-md border bg-white p-1 text-sm"
						value={page}
						onChange={(e) => setPage(e.target.value)}
						placeholder="Any"
					/>
				</div>

				<button
					type="button"
					onClick={() => {
						setNotebookId("");
						setSectionId("");
						setPage("");
					}}
					className="ml-auto inline-flex items-center rounded-md border px-3 py-1.5 text-sm hover:bg-gray-50"
				>
					Clear filters
				</button>
			</div>

			{/* List */}
			{items.length === 0 ? (
				<p className="text-sm text-muted-foreground">No results.</p>
			) : (
				<div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
					{items.map((row) => {
						const nb = notebooksById.get(Number(row.notebook_id));
						const img = row?.thumb_url || row?.preview_url || null;

						return (
							<Link
								to={`/admin/drawings/${row.drawing_id}`}
								key={row.drawing_id}
								className={cn(
									"rounded-lg border bg-white hover:bg-gray-50 overflow-hidden"
								)}
							>
								{/* Image */}
								{img ? (
									<img
										src={img}
										alt={`Drawing #${row.drawing_id}`}
										className="aspect-[1/1] w-full object-cover"
										loading="lazy"
										decoding="async"
									/>
								) : (
									<div className="flex aspect-[1/1] items-center justify-center bg-gray-100 text-xs text-gray-500">
										No preview
									</div>
								)}

								{/* Meta */}
								<div className="p-2">
									<div className="text-xs text-muted-foreground">
										{nb?.title || `Notebook ${row.notebook_id}`}
									</div>
									<div className="text-xs">
										<span className="font-medium">{row.section_label || `Section ${row.section_id}`}</span>
										<span className="mx-1 opacity-60">|</span>
										<span>p. {row.page}</span>
									</div>
									{row.user_email && (
										<div className="mt-0.5 text-[11px] text-muted-foreground break-all">
											{row.user_email}
										</div>
									)}
								</div>
							</Link>
						);
					})}
				</div>
			)}

			{/* Pagination */}
			{hasMore && (
				<div>
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
