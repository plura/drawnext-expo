//src/app/ConfigProvider.jsx

import React, { createContext, useContext, useEffect, useState } from "react"

const ConfigCtx = createContext(null)

// read cache flag from Vite env (defaults to true if not defined)
const CACHE_ENABLED =
	import.meta.env.VITE_CACHE_ENABLED !== "false" // string comparison

export function ConfigProvider({ children }) {
	const [notebooks, setNotebooks] = useState(null)
	const [loading, setLoading] = useState(true)
	const [error, setError] = useState(null)

	useEffect(() => {
		// if caching enabled, check sessionStorage first
		if (CACHE_ENABLED) {
			const cached = sessionStorage.getItem("notebooks_config")
			if (cached) {
				setNotebooks(JSON.parse(cached))
				setLoading(false)
				return
			}
		}

		;(async () => {
			try {
				const res = await fetch("/api/notebooks/config")
				const json = await res.json()
				const data = Array.isArray(json?.data) ? json.data : json

				setNotebooks(data)

				// save cache if allowed
				if (CACHE_ENABLED) {
					sessionStorage.setItem("notebooks_config", JSON.stringify(data))
				}
			} catch (e) {
				setError(e)
			} finally {
				setLoading(false)
			}
		})()
	}, [])

	return (
		<ConfigCtx.Provider value={{ notebooks, loading, error }}>
			{children}
		</ConfigCtx.Provider>
	)
}

export function useConfig() {
	const ctx = useContext(ConfigCtx)
	if (!ctx) throw new Error("useConfig must be used within ConfigProvider")
	return ctx
}
