import { defineConfig } from 'vite';
import { resolve } from 'path';

const root = import.meta.dirname;

export default defineConfig({
    build: {
        outDir: resolve(root, '..'),
        emptyOutDir: false,
        rollupOptions: {
            input: {
                'auth-app': resolve(root, 'src/js/auth-app.js'),
                'auth-app-styles': resolve(root, 'src/scss/auth-app.scss'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'css/auth-app.css';
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
