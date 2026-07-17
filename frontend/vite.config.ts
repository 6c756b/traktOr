import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'

/** Ensures the base path starts and ends with exactly one "/" -- Vite needs
 *  the exact format, regardless of how the path was written in deploy/.env. */
function normalizeBasePath(path: string): string {
  const trimmed = path.replace(/^\/+|\/+$/g, '')
  return trimmed ? `/${trimmed}/` : '/'
}

// Single source of truth for the app version -- see backend/src/Support/Version.php,
// which reads the same file at the repo root.
const appVersion = readFileSync(fileURLToPath(new URL('../VERSION', import.meta.url)), 'utf-8').trim()

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  plugins: [svelte()],
  // Allows hosting in a subfolder (e.g. /traktor/) instead of the domain root, without
  // any code change -- the path comes from deploy/.env (BASE_PATH), set as VITE_BASE_PATH
  // by deploy.sh. Always root in the dev server, regardless of the deploy target.
  base: command === 'build' ? normalizeBasePath(process.env.VITE_BASE_PATH || '/') : '/',
  define: {
    __APP_VERSION__: JSON.stringify(appVersion),
  },
  server: {
    proxy: {
      "/api": "http://localhost:8000",
    },
  },
}))
