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
					// /api/notebooks/config  -> /backend/api/notebooks/config.php
					// /api/drawings/create   -> /backend/api/drawings/create.php
					const withoutApi = path.replace(/^\/api/, '/backend/api')
					return withoutApi.endsWith('.php') ? withoutApi : `${withoutApi}.php`
				},
			},
		},
	},
	resolve: {
		alias: {
			'@': path.resolve(__dirname, './src'),
		},
	},
})
