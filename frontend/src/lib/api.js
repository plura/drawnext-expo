// src/lib/api.js

/**
 * Upload a file to the temp image endpoint.
 * @param {File} file
 * @returns {Promise<{ status: 'success', data: { token:string, width:number, height:number, hash:string } }>}
 * Throws on non-2xx responses.
 */
export async function uploadTempImage(file) {
	const fd = new FormData();
	fd.append('image', file);

	const res = await fetch('/api/images/temp', { method: 'POST', body: fd });

	let json = {};
	try {
		json = await res.json();
	} catch {
		// If response isn't valid JSON (e.g. PHP fatal, HTML error page),
		// ignore here — we'll throw a unified error below instead of leaking
		// "Unexpected token < in JSON" style errors.
	}

	if (!res.ok || json?.status !== 'success') {
		const message =
			json?.message ||
			`Temp upload failed${res.status ? ` (HTTP ${res.status})` : ''}`;
		throw new Error(message);
	}

	return json; // { status:'success', data: { token, width, height, hash } }
}


// Probe a prospective slot + neighbors (lightweight, non-authoritative)
export async function probeSlot(payload, fetchOpts = {}) { console.log('call started');
  const res = await fetch('/api/drawings/probe', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    // include cookies if your PHP session is cookie-based:
    credentials: 'include',
    body: JSON.stringify(payload),
    ...fetchOpts, // lets us pass { signal }
  }); console.log('call finished');

  let json = {};
  try { json = await res.json(); } catch {}

  if (!res.ok || json?.status === 'error') {
    const msg = json?.message || `Probe failed (HTTP ${res.status})`;
    throw Object.assign(new Error(msg), { name: 'ProbeHttpError' });
  }
  return json; // { status:'success', data:{ primary?, neighbors? } }
}



//to use with DynamicGallery
export async function listDrawingsWithNeighbors({ limit=50, offset=0 } = {}) {
  const qs = new URLSearchParams({
    limit: String(limit),
    offset: String(offset),
    expand: "thumb,labels,neighbors", // neighbors include section_id, page (+ labels if your backend supports)
  });
  const res = await fetch(`/api/drawings/list?${qs.toString()}`);
  const json = await res.json().catch(() => ({}));
  if (!res.ok || json?.status === "error") throw new Error(json?.message || "Fetch failed");
  return { items: Array.isArray(json?.data) ? json.data : [], meta: json?.meta || {} };
}
