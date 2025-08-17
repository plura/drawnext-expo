/**
 * Get notebook object by ID.
 * @param {Array<{id:number, sections:Array}>} notebooks - List of notebooks
 * @param {number} notebookId - ID to find
 * @returns {object|null} Notebook or null
 */
export function getNotebook(notebooks, notebookId) {
	return notebooks?.find(n => Number(n.id) === Number(notebookId)) || null;
}

/**
 * Get all sections for a notebook.
 * @param {object|null} notebook - Notebook object
 * @returns {Array} Sections list (empty if none)
 */
export function getSections(notebook) {
	return notebook?.sections || [];
}

/**
 * Get a section label (fallback if missing).
 * @param {object|null} section - Section object
 * @returns {string} Section label
 */
export function getSectionLabel(section) {
	return section?.label || (section ? `Section ${section.position}` : '');
}
