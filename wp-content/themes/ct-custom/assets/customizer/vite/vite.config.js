import { defineConfig } from 'vite';
import { resolve } from 'path';

const root = import.meta.dirname;

export default defineConfig({
    build: {
        outDir: resolve(root, '..'),
        emptyOutDir: false,
        rollupOptions: {
            input: {
                controls: resolve(root, 'src/controls.js'),
                preview: resolve(root, 'src/preview.js'),
                'controls-styles': resolve(root, 'src/styles/controls.scss'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'css/customizer.css';
                    }
                    return 'assets/[name][extname]';
                },
            },
        },
        minify: true,
        sourcemap: false,
    },
    css: {
        preprocessorOptions: {
            scss: {},
        },
    },
});
