// vite.config.js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import svgr from 'vite-plugin-svgr' // 👈 add this
import path from 'path'
import fs from 'fs'

const LAN_IP = process.env.LAN_IP || '192.168.68.104'
const HTTPS_KEY_PATH = './.cert/dev-key.pem'
const HTTPS_CERT_PATH = './.cert/dev-cert.pem'

export default defineConfig({
	plugins: [
		react(),
		tailwindcss(),
		svgr({ svgo: false }), // 👈 and register it
	],
	build: {
		outDir: 'dist',       // Build goes to 'dist/'
		assetsDir: 'assets',  // CSS/JS hashed files live under dist/assets/
		emptyOutDir: true,    // Clear 'dist/' on build
		copyPublicDir: true,  // Copies everything from 'public/' into 'dist/'
		/* outDir: '../public',
		emptyOutDir: true, */
	},
	server: {
		port: 5173,
		open: true,
		host: true,
		https: {
			key: fs.readFileSync(HTTPS_KEY_PATH),
			cert: fs.readFileSync(HTTPS_CERT_PATH),
		},
		hmr: {
			host: LAN_IP,
			port: 5173,
			protocol: 'wss',
		},
		proxy: {
			'/api': {
				target: 'https://drawnext.ddev.site',
				changeOrigin: true,
				secure: false,
				rewrite: (path) => {
					const [pathOnly, query = ''] = path.split('?')
					const withoutApi = pathOnly.replace(/^\/api/, '/backend/api')
					const rewritten = withoutApi.endsWith('.php') ? withoutApi : `${withoutApi}.php`
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
		alias: { '@': path.resolve(__dirname, './src') },
	},
})
