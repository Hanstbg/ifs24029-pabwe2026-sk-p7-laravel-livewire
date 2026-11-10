// Livewire 4 + Alpine dibundel lewat Vite (module = deferred, tidak memblokir render).
// Layout memakai @livewireScriptConfig sehingga Livewire tidak menyuntik livewire.min.js lagi.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;
Livewire.start();

// Muat pustaka pihak ketiga hanya saat dibutuhkan (tidak memblokir render halaman).
const cache = {};
const once = (key, make) => {
    if (!cache[key]) {
        cache[key] = make().catch((e) => { delete cache[key]; throw e; });
    }
    return cache[key];
};
const loadScript = (src) => new Promise((resolve, reject) => {
    const el = document.createElement('script');
    el.src = src;
    el.async = true;
    el.onload = resolve;
    el.onerror = () => reject(new Error('Gagal memuat ' + src));
    document.head.appendChild(el);
});
const loadStyle = (href) => new Promise((resolve, reject) => {
    const el = document.createElement('link');
    el.rel = 'stylesheet';
    el.href = href;
    el.onload = resolve;
    el.onerror = () => reject(new Error('Gagal memuat ' + href));
    document.head.appendChild(el);
});

window.loadToastUI = () => once('toastui', () => Promise.all([
    loadStyle('https://uicdn.toast.com/editor/latest/toastui-editor.min.css'),
    loadScript('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js'),
]));

window.loadCropper = () => once('cropper', () => Promise.all([
    loadStyle('https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css'),
    loadScript('https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js'),
]));
