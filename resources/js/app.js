/*
 * Library berat (Toast UI Editor & Cropper.js) dimuat secara lazy:
 * baru diunduh saat modal edit deskripsi / crop foto dibuka,
 * sehingga halaman awal tetap ringan (skor Performance Lighthouse).
 */

let toastEditorPromise = null;
let cropperPromise = null;

window.loadToastEditor = () => {
    toastEditorPromise ??= Promise.all([
        import('@toast-ui/editor'),
        import('@toast-ui/editor/dist/toastui-editor.css'),
    ]).then(([module]) => module.default);

    return toastEditorPromise;
};

window.loadCropper = () => {
    cropperPromise ??= Promise.all([
        import('cropperjs'),
        import('cropperjs/dist/cropper.css'),
    ]).then(([module]) => module.default);

    return cropperPromise;
};
