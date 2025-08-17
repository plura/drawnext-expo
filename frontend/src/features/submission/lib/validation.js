/**
 * Check if a value is a positive integer.
 * @param {any} v - Value to check
 * @returns {boolean} True if positive integer
 */
export const isPositiveInt = (v) => Number.isInteger(Number(v)) && Number(v) > 0;

/**
 * Validate a page number against optional max pages.
 * @param {string|number} value - Page input
 * @param {number|null} [maxPages=null] - Optional max pages
 * @returns {boolean} True if valid page
 */
export function isValidPage(value, maxPages = null) {
	const n = Number(value);
	if (!isPositiveInt(n)) return false;
	if (typeof maxPages === 'number' && n > maxPages) return false;
	return true;
}
