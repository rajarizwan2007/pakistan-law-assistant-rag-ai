import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Forward /api calls to Laravel (via nginx) so the browser sees one origin: no CORS in dev.
    // Inside Docker the target is the nginx service; on the host it is the published port.
    proxy: {
      '/api': process.env.API_PROXY_TARGET ?? 'http://localhost:8090',
    },
  },
})
