import type { ButtonHTMLAttributes, PropsWithChildren } from 'react';

type ButtonProps = PropsWithChildren<ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'secondary' | 'danger';
}>;

export default function Button({ children, className = '', variant = 'primary', ...props }: ButtonProps) {
    const styles = {
        primary: 'bg-brand text-white shadow-sm hover:bg-brand/90 focus:ring-brand/25',
        secondary: 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:ring-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-slate-800',
        danger: 'bg-red-600 text-white shadow-sm hover:bg-red-700 focus:ring-red-200',
    }[variant];

    return (
        <button
            className={`inline-flex items-center justify-center rounded-lg px-5 py-3 text-sm font-semibold transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60 ${styles} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
