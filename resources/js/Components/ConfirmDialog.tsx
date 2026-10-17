import { useEffect, useId, useRef } from 'react';
import type { ReactNode } from 'react';
import Button from './Auth/Button';

type ConfirmDialogProps = {
    open: boolean;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: 'primary' | 'danger';
    onConfirm: () => void;
    onCancel: () => void;
};

export default function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel = 'Continue',
    cancelLabel = 'Cancel',
    tone = 'danger',
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const id = useId();

    useEffect(() => {
        const dialog = dialogRef.current;

        if (!dialog) return;

        if (open && !dialog.open) {
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    function handleCancel(event: React.SyntheticEvent<HTMLDialogElement>) {
        event.preventDefault();
        onCancel();
    }

    return (
        <dialog
            aria-describedby={`${id}-description`}
            aria-labelledby={`${id}-title`}
            aria-modal="true"
            className="fixed inset-0 z-50 m-0 h-full max-h-none w-full max-w-none overflow-y-auto bg-transparent p-4 backdrop:bg-gray-900/50 open:flex open:items-center open:justify-center"
            onCancel={handleCancel}
            ref={dialogRef}
        >
            <section className="relative w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div className="flex items-start justify-between rounded-t border-b border-slate-200 p-4 sm:p-5 dark:border-slate-800">
                    <h2 className="font-display text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100" id={`${id}-title`}>
                    {title}
                    </h2>
                </div>
                <div className="space-y-4 p-4 text-sm leading-6 text-slate-600 dark:text-slate-300 sm:p-5" id={`${id}-description`}>
                    {description}
                </div>
                <div className="flex flex-col-reverse gap-3 rounded-b border-t border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:justify-end sm:p-5">
                    <Button autoFocus onClick={onCancel} type="button" variant="secondary">{cancelLabel}</Button>
                    <Button onClick={onConfirm} type="button" variant={tone === 'danger' ? 'danger' : 'primary'}>{confirmLabel}</Button>
                </div>
            </section>
        </dialog>
    );
}
