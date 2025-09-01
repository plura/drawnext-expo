// src/features/admin/lib/api.js
/**
 * Admin API helpers
 * ---------------------------------------
 * - All requests include credentials for PHP sessions.
 * - Responses are normalized via fetchJson (throws on non-OK / {status:"error"}).
 */

/** Low-level JSON fetch helper (throws Error with .status and .details) */
async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    credentials: "include", // keep PHP session cookies
    ...options,
    headers: {
      Accept: "application/json",
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

  // Typically { status:'success', data, meta? }
  return json;
}

/* ============================================================================
 * AUTH & SESSION
 * ========================================================================== */

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

/** GET /api/admin/me -> { email, is_admin } */
export async function getSession() {
  return fetchJson("/api/admin/me");
}

/* ============================================================================
 * DRAWINGS
 * ========================================================================== */

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
 * @param {{ notebook_id:number, section_id:number, page:number, neighbors?: {section_id:number,page:number}[] }} payload
 */
export async function validateSlots(payload) {
  return fetchJson("/api/drawings/validate", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
}

/**
 * POST /api/admin/drawings/update
 * @param {{ drawing_id:number, notebook_id:number, section_id:number, page:number, neighbors?: {section_id:number,page:number}[] }} payload
 */
export async function updateDrawing(payload) {
  return fetchJson("/api/admin/drawings/update", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
}

/**
 * POST /api/admin/drawings/delete
 * @param {number} drawing_id
 */
export async function deleteDrawing(drawing_id) {
  return fetchJson("/api/admin/drawings/delete", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ drawing_id }),
  });
}

/* ============================================================================
 * USERS (placeholder: wire when endpoints exist)
 * ========================================================================== */

/** GET /api/admin/users/list?limit=&offset=&q= */
export async function listUsers(params = {}) {
  const { limit = 24, offset = 0, q } = params;
  const qs = new URLSearchParams();
  qs.set("limit", String(limit));
  qs.set("offset", String(offset));
  if (q) qs.set("q", String(q));
  return fetchJson(`/api/admin/users/list?${qs.toString()}`);
}

/** GET /api/admin/users/view?id=123 */
export async function getUser(id) {
  const qs = new URLSearchParams();
  qs.set("id", String(id));
  return fetchJson(`/api/admin/users/view?${qs.toString()}`);
}


