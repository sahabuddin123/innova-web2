import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

/**
 * Vite Configuration for INNOVA Web React Foundation (Phase 3.0)
 *
 * Compiles modern React 18 + TypeScript entry points into public/react/
 * without conflicting with the existing Laravel Mix asset pipeline.
 */
export default defineConfig({
  plugins: [react()],
  publicDir: false,
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './resources/js'),
    },
  },
  build: {
    outDir: 'public/react',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'resources/js/react/main.tsx'),
      output: {
        entryFileNames: 'main.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'main.css';
          }
          return 'assets/[name]-[hash].[ext]';
        },
      },
    },
  },
  server: {
    port: 3000,
    strictPort: true,
    hmr: {
      host: 'localhost',
    },
  },
});
