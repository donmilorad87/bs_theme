import { defineConfig } from 'vite';
import { resolve } from 'path';

const root = import.meta.dirname;

export default defineConfig({
    build: {
        outDir: resolve(root, '..'),
        emptyOutDir: false,
        rollupOptions: {
            input: {
                'profile-app': resolve(root, 'src/js/profile-app.js'),
                'profile-app-styles': resolve(root, 'src/scss/profile-app.scss'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'css/profile-app.css';
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
