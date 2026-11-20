import { ReactNode } from 'react';
import { Switch } from '@/Components/ui/switch';
import { cn } from '@/lib/utils';

export const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring';

/** Stikalo z opisom. */
export function SwitchRow({ checked, onChange, label, description }: { checked: boolean; onChange: (v: boolean) => void; label: ReactNode; description?: ReactNode }) {
    return (
        <label className="flex cursor-pointer items-start justify-between gap-4 rounded-lg border p-3">
            <span>
                <span className="block text-sm font-medium">{label}</span>
                {description && <span className="mt-0.5 block text-xs text-muted-foreground">{description}</span>}
            </span>
            <Switch checked={checked} onCheckedChange={onChange} />
        </label>
    );
}

/** Seznam potrditvenih polj za izbiro več elementov (npr. viri storitve). */
export function MultiCheck<T extends string | number>({ options, value, onChange, emptyHint }: {
    options: { value: T; label: string }[];
    value: T[];
    onChange: (v: T[]) => void;
    emptyHint?: string;
}) {
    if (options.length === 0) return emptyHint ? <p className="text-xs text-muted-foreground">{emptyHint}</p> : null;

    return (
        <div className="flex flex-wrap gap-2">
            {options.map((o) => {
                const on = value.includes(o.value);
                return (
                    <button
                        key={String(o.value)}
                        type="button"
                        onClick={() => onChange(on ? value.filter((v) => v !== o.value) : [...value, o.value])}
                        className={cn('rounded-md border px-2.5 py-1 text-sm transition', on ? 'border-primary bg-primary/10 font-medium text-primary' : 'hover:bg-accent')}
                    >
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}

/** Vnos števila, ki prazen niz pretvori v null. */
export function numberOrNull(v: string): number | null {
    return v === '' ? null : Number(v);
}
