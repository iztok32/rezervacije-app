import { ReactNode } from 'react';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

/** Oznaka, vnosno polje, namig in napaka validacije. */
export default function Field({
    label, htmlFor, error, hint, children, className, required,
}: {
    label?: ReactNode;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
    required?: boolean;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <Label htmlFor={htmlFor}>
                    {label}
                    {required && <span className="ml-0.5 text-destructive">*</span>}
                </Label>
            )}
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs font-medium text-destructive">{error}</p>}
        </div>
    );
}
