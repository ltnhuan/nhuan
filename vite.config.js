import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  base: '/build/',
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
  build: {
    outDir: 'public/build',
    manifest: 'manifest.json',
    rollupOptions: {
      input: ['resources/css/app.css', 'resources/js/app.js'],
    },
  },
})
