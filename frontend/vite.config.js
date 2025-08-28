import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
	plugins: [react(), tailwindcss()],
	build: {
		// Put the build output into the PHP's public folder
		outDir: '../public',
		emptyOutDir: true
	},
	server: {
		port: 5173, // dev server port
		open: true, // auto-open browser
		host: true,
		// Optional, helps HMR from phone:
		hmr: { host: '192.168.68.101', port: 5173 },

		// Proxy all API requests during `npm run dev`
		// Frontend calls `/api/...` -> Vite rewrites to `/backend/api/... .php` on DDEV
		proxy: {
			'/api': {
				target: 'https://drawnext.ddev.site', // your local DDEV URL
				changeOrigin: true,
				secure: false, // allow self-signed certs on DDEV
				rewrite: (path) => {
					// 1) Split the incoming URL into path and query parts
					const [pathOnly, query = ''] = path.split('?')

					// 2) Replace the /api prefix with your backend folder
					const withoutApi = pathOnly.replace(/^\/api/, '/backend/api')

					// 3) Ensure the *path* ends with .php (but don't touch the query)
					const rewritten = withoutApi.endsWith('.php')
						? withoutApi
						: `${withoutApi}.php`

					// 4) Re-attach the query string only if one exists
					return query ? `${rewritten}?${query}` : rewritten
				},
			},
			'/uploads': {
				target: 'https://drawnext.ddev.site',
				changeOrigin: true,
				secure: false,
			},
		},
	},
	resolve: {
		alias: {
			'@': path.resolve(__dirname, './src'),
		},
	},
})
