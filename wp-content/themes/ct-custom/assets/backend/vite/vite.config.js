import { defineConfig } from 'vite';
import { resolve } from 'path';

const root = import.meta.dirname;

export default defineConfig({
    build: {
        outDir: resolve(root, '..'),
        emptyOutDir: false,
        rollupOptions: {
            input: {
                admin: resolve(root, 'src/js/Admin_App.js'),
                'app-styles': resolve(root, 'src/scss/app.scss'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'css/app.css';
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
