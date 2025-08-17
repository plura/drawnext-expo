import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

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
		proxy: {
			// Proxy all backend requests during `npm run dev`
			'/backend': {
				target: 'http://drawnext.ddev.site', // your local PHP URL
				changeOrigin: true
			}
		}
	}
})
