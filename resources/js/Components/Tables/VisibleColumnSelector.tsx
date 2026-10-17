type ColumnOption<Value extends string> = { value: Value; label: string };

type Props<Value extends string> = {
    columns: readonly ColumnOption<Value>[];
    selected: readonly Value[];
    onChange: (selected: Value[]) => void;
    legend: string;
    className?: string;
    legendClassName?: string;
};

export default function VisibleColumnSelector<Value extends string>({
    columns,
    selected,
    onChange,
    legend,
    className = 'mt-4 grid grid-cols-2 gap-x-4 gap-y-2',
    legendClassName = 'sr-only',
}: Props<Value>) {
    return (
        <fieldset className={className}>
            <legend className={legendClassName}>{legend}</legend>
            {columns.map(({ value, label }) => (
                <label className="inline-flex min-w-0 items-center gap-2 text-sm text-slate-700 dark:text-slate-300" key={value}>
                    <input
                        checked={selected.includes(value)}
                        className="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand dark:border-slate-600"
                        disabled={selected.length === 1 && selected.includes(value)}
                        name="columns[]"
                        onChange={(event) => onChange(event.target.checked ? [...selected, value] : selected.filter((column) => column !== value))}
                        type="checkbox"
                        value={value}
                    />
                    <span>{label}</span>
                </label>
            ))}
        </fieldset>
    );
}
