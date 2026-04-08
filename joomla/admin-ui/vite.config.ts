import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: 'dist',
    cssCodeSplit: false,
    rollupOptions: {
      output: {
        entryFileNames: 'xenarch-admin.js',
        assetFileNames: 'xenarch-admin.[ext]',
        manualChunks: () => 'xenarch-admin',
      },
    },
    chunkSizeWarningLimit: 2000,
  },
})
