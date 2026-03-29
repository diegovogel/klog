import './bootstrap'
import './components/audio-player'
import './components/rich-editor'
import './components/media-upload'
import './components/media-capture'
import './components/memory-date-warning'
import './components/web-clippings'
import './components/child-selector'
import './components/tag-input'
import './components/confirm-delete'
import './components/submit-loading'

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {})
}
