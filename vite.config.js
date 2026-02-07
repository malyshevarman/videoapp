import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import vue from '@vitejs/plugin-vue';
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/sass/style.scss',
                'resources/sass/appstyle.scss',
                'resources/js/app.js',
            ],
            refresh: [
                'resources/views/**',
                'resources/images/**', // ← добавляем отслеживание изменений изображений
            ],
        }),
        vue(),
        viteStaticCopy({
            targets: [
                {
                    src: 'resources/images/*',
                    dest: 'images'
                }
            ]
        })
    ],
});
