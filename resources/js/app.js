import { createApp } from 'vue'
import App from './App.vue'

const el = document.getElementById('app')
const payload = JSON.parse(el?.dataset.page || '{}')

createApp(App, payload).mount(el)

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {})
  })
}
