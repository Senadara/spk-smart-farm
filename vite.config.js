import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: false,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        allowedHosts: ['vite-dev', 'localhost'],
        watch: {
            usePolling: true,
            interval: 3000,
            binaryInterval: 5000,
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/storage/**',
                '**/bootstrap/**',
                '**/tests/**',
                '**/database/**',
                '**/config/**',
                '**/lang/**',
                '**/public/build/**',
                '**/*.map',
                '**/*.log',
            ],
        },
    },
});
