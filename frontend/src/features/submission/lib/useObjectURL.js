// src/features/submission/lib/useObjectUrl.js
import { useEffect, useState } from "react"

/**
 * React hook: returns an object URL for a given File/Blob
 * and revokes it automatically on cleanup.
 */
export function useObjectUrl(file) {
	const [url, setUrl] = useState("")

	useEffect(() => {
		if (!file) {
			setUrl("")
			return
		}
		const objectUrl = URL.createObjectURL(file)
		setUrl(objectUrl)
		return () => URL.revokeObjectURL(objectUrl)
	}, [file])

	return url
}
