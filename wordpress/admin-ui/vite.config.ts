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
        chunkFileNames: 'chunks/[name]-[hash].js',
      },
    },
  },
  define: {
    'process.env.REOWN_PROJECT_ID': JSON.stringify(process.env.REOWN_PROJECT_ID || ''),
  },
})
