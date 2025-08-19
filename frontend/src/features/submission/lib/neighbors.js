//src/features/submission/lib/neighbors.js

/**
 * Find a neighbor entry for a given section.
 * @param {Array<{section_id:number, page:string}>} neighbors - Current neighbor list
 * @param {number} sectionId - Section to look for
 * @returns {object|null} Neighbor object or null if not found
 */
export function findNeighbor(neighbors, sectionId) {
	return neighbors.find(n => Number(n.section_id) === Number(sectionId)) || null;
}

/**
 * Insert or update (upsert) a neighbor entry.
 * @param {Array<{section_id:number, page:string}>} neighbors - Current neighbor list
 * @param {number} sectionId - Section ID to upsert
 * @param {string|number} page - Page number (stored as string)
 * @returns {Array} Updated neighbors array
 */
export function upsertNeighbor(neighbors, sectionId, page) {
	const idx = neighbors.findIndex(n => Number(n.section_id) === Number(sectionId));
	const item = { section_id: Number(sectionId), page: String(page) };
	if (idx === -1) return [...neighbors, item];
	const next = neighbors.slice();
	next[idx] = item;
	return next;
}

/**
 * Remove a neighbor entry by section ID.
 * @param {Array<{section_id:number, page:string}>} neighbors - Current neighbor list
 * @param {number} sectionId - Section ID to remove
 * @returns {Array} Filtered neighbors array
 */
export function removeNeighbor(neighbors, sectionId) {
	return neighbors.filter(n => Number(n.section_id) !== Number(sectionId));
}

/**
 * Validate neighbor entries for correctness.
 * @param {Array<{section_id:number, page:string}>} neighbors - Current neighbor list
 * @param {number|null} [maxPages=null] - Optional max pages limit
 * @returns {boolean} True if all neighbors are valid
 */
export function neighborsAreValid(neighbors, maxPages = null) {
	for (const n of neighbors) {
		if (!n.page) continue; // optional
		const val = Number(n.page);
		if (!(val > 0)) return false;
		if (typeof maxPages === 'number' && val > maxPages) return false;
	}
	return true;
}
