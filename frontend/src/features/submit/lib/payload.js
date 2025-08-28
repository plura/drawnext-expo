// src/features/submit/lib/payload.js

export function buildSubmissionPayload(state) {
	return {
		drawing: {
			email: state.email,
			notebook_id: Number(state.notebookId),
			section_id: Number(state.sectionId),
			page: Number(state.page)
		},
		neighbors: (state.neighbors || [])
			.filter(n => n.page && Number(n.page) > 0)
			.map(n => ({ section_id: Number(n.section_id), page: Number(n.page) }))
	};
}
