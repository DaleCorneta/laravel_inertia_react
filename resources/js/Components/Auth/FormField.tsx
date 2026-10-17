import type { InputHTMLAttributes, ReactNode } from 'react';

type FormFieldProps = InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    error?: string;
    trailing?: ReactNode;
};

export default function FormField({ id, label, error, trailing, className = '', ...props }: FormFieldProps) {
    const errorId = `${id}-error`;

    return (
        <div>
            <div className="mb-2 flex items-center justify-between gap-4">
                <label className="block text-sm font-medium text-slate-900 dark:text-slate-100" htmlFor={id}>{label}</label>
                {trailing}
            </div>
            <input
                aria-describedby={error ? errorId : undefined}
                aria-invalid={Boolean(error)}
                className={`block w-full rounded-lg border bg-slate-50 p-3 text-sm text-slate-900 transition placeholder:text-slate-400 focus:bg-white focus:ring-brand dark:bg-slate-800 dark:text-slate-100 dark:focus:bg-slate-800 ${error ? 'border-red-500 focus:border-red-500' : 'border-slate-300 focus:border-brand dark:border-slate-700'} ${className}`}
                id={id}
                {...props}
            />
            {error && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id={errorId} role="alert">{error}</p>}
        </div>
    );
}
