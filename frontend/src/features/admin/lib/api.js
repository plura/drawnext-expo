// src/features/admin/lib/api.js
/**
 * Admin API helpers (frontend)
 * ---------------------------------------
 * Wraps fetch() with consistent JSON parsing + errors
 * and exposes typed helpers for the admin endpoints we
 * have right now:
 *
 * - POST /api/auth/login     -> login({ email })
 * - POST /api/auth/logout    -> logout()
 * - GET  /api/admin/me       -> getSession()  (checks is_admin + who)
 * - GET  /api/drawings/list  -> listDrawings(params)
 * - POST /api/drawings/validate -> validateSlots(payload)
 *
 * All requests include credentials for PHP sessions.
 */

async function fetchJson(url, options = {}) {
	const res = await fetch(url, {
		credentials: "include", // keep PHP session cookies
		...options,
		headers: {
			"Accept": "application/json",
			...(options.headers || {}),
		},
	});
	let json = null;
	try {
		json = await res.json();
	} catch {
		// ignore parse error; throw unified error below
	}
	if (!res.ok || json?.status === "error") {
		const message = json?.message || `Request failed (${res.status})`;
		const error = new Error(message);
		error.status = res.status;
		error.details = json?.details || null;
		throw error;
	}
	return json; // usually { status:'success', data, meta? }
}

/** POST /api/auth/login { email } */
export async function login(email) {
	return fetchJson("/api/auth/login", {
		method: "POST",
		headers: { "Content-Type": "application/json" },
		body: JSON.stringify({ email }),
	});
}

/** POST /api/auth/logout */
export async function logout() {
	return fetchJson("/api/auth/logout", { method: "POST" });
}

/**
 * GET /api/admin/me
 * Returns:
 *   { status:'success', data: { is_admin:boolean, email:string|null } }
 */
export async function getSession() {
	return fetchJson("/api/admin/me");
}

/**
 * GET /api/drawings/list
 * @param {Object} params
 * @param {number} [params.limit=24]
 * @param {number} [params.offset=0]
 * @param {number} [params.notebook_id]
 * @param {number} [params.section_id]
 * @param {number} [params.page]
 * @param {string[]} [params.expand] e.g. ['labels','thumb','user','neighbors']
 */
export async function listDrawings(params = {}) {
	const {
		limit = 24,
		offset = 0,
		notebook_id,
		section_id,
		page,
		expand,
	} = params;

	const qs = new URLSearchParams();
	qs.set("limit", String(limit));
	qs.set("offset", String(offset));
	if (notebook_id != null) qs.set("notebook_id", String(notebook_id));
	if (section_id != null) qs.set("section_id", String(section_id));
	if (page != null) qs.set("page", String(page));
	if (Array.isArray(expand) && expand.length) {
		qs.set("expand", expand.join(","));
	}

	return fetchJson(`/api/drawings/list?${qs.toString()}`);
}

/**
 * POST /api/drawings/validate
 * Validates a proposed slot and optional neighbor existence.
 *
 * Payload:
 * {
 *   notebook_id:number,
 *   section_id:number,
 *   page:number,
 *   neighbors?: [{ section_id:number, page:number }]
 * }
 */
export async function validateSlots(payload) {
	return fetchJson("/api/drawings/validate", {
		method: "POST",
		headers: { "Content-Type": "application/json" },
		body: JSON.stringify(payload),
	});
}
