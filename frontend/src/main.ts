import { mount } from 'svelte'
import './app.css'
import App from './App.svelte'

console.log(
  '%c🚜 TraktOr%c\nDer Traktor rollt an. Guten Tag, Entwickler :)',
  'font-weight: 700; font-size: 14px;',
  'font-weight: 400;'
)

const app = mount(App, {
  target: document.getElementById('app')!,
})

export default app
