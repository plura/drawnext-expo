// src/app/ConfigProvider.jsx
import React, { createContext, useContext, useEffect, useState } from 'react'

const ConfigCtx = createContext(null)

export function ConfigProvider({ children }) {
	const [notebooks, setNotebooks] = useState(null)
	const [loading, setLoading] = useState(true)
	const [error, setError] = useState(null)

	useEffect(() => {
		const cached = sessionStorage.getItem('notebooks_config')
		if (cached) {
			setNotebooks(JSON.parse(cached))
			setLoading(false)
			return
		}
		;(async () => {
			try {
				const res = await fetch('/backend/api/notebooks/config.php')
				const json = await res.json()
				const data = Array.isArray(json?.data) ? json.data : json
				setNotebooks(data)
				sessionStorage.setItem('notebooks_config', JSON.stringify(data))
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
	if (!ctx) throw new Error('useConfig must be used within ConfigProvider')
	return ctx
}
