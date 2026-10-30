import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
    title: (title) => (title ? `${title} — ZimComply AI` : 'ZimComply AI — Zimbabwe Business Compliance Platform'),
    resolve: (name) => {
        const pages = import.meta.glob<any>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Inertia Page not found: ${name}. Ensure it exists in resources/js/Pages/`);
        }
        return page.default ? page.default : page;
    },
    setup({ el, App, props }: any) {
        if (!el) return;
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#10b981', // Emerald green
        showSpinner: true,
    },
});
