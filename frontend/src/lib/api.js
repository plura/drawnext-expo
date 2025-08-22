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
