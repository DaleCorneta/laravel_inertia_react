import './bootstrap';
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
    pages: {
        path: './Pages',
        extension: '.tsx',
    },
    strictMode: true,
});
