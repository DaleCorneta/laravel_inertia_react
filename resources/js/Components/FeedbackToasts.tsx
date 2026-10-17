import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Toaster, toast } from 'sonner';

export default function FeedbackToasts() {
    const { props, url } = usePage<{ flash: { status?: string | null } }>();
    const status = props.flash?.status;
    const [theme, setTheme] = useState<'light' | 'dark'>(() =>
        typeof document !== 'undefined' && document.documentElement.classList.contains('dark') ? 'dark' : 'light',
    );

    useEffect(() => {
        const root = document.documentElement;
        const observer = new MutationObserver(() => setTheme(root.classList.contains('dark') ? 'dark' : 'light'));

        observer.observe(root, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (status) toast.success(status);
    }, [status, url]);

    return <Toaster closeButton position="top-right" richColors theme={theme} />;
}
