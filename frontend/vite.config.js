// vite.config.js
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import svgr from 'vite-plugin-svgr'
import path from 'path'
import fs from 'fs'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), ''); // loads VITE_* from .env.*
  const BACKEND = env.VITE_BACKEND_ORIGIN || 'https://drawnext-expo.ddev.site';

  return {
    plugins: [react(), tailwindcss(), svgr({ svgo: false })],
    build: { outDir: 'dist', assetsDir: 'assets', emptyOutDir: true, copyPublicDir: true },
    server: {
      port: 5173,
      open: true,
      host: true,
      https: {
        key: fs.readFileSync('./.cert/dev-key.pem'),
        cert: fs.readFileSync('./.cert/dev-cert.pem'),
      },
      hmr: { host: process.env.LAN_IP || '192.168.68.104', port: 5173, protocol: 'wss' },
      proxy: {
        '/api': {
          target: BACKEND,
          changeOrigin: true,
          secure: false,
          rewrite: (p) => {
            const [pathOnly, query = ''] = p.split('?')
            const withoutApi = pathOnly.replace(/^\/api/, '/backend/api')
            const rewritten = withoutApi.endsWith('.php') ? withoutApi : `${withoutApi}.php`
            return query ? `${rewritten}?${query}` : rewritten
          },
        },
        '/uploads': {
          target: BACKEND,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    resolve: { alias: { '@': path.resolve(__dirname, './src') } },
  }
})
