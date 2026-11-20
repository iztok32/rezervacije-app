import { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** Gumb za izbiro (ura, vir, storitev) z izbranim stanjem. */
export default function ChoiceChip({ selected, className, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { selected?: boolean }) {
    return (
        <button
            type="button"
            {...props}
            className={cn(
                'rounded-md border px-3 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40',
                selected ? 'border-primary bg-primary text-primary-foreground shadow-sm' : 'bg-card hover:border-primary/50 hover:bg-accent',
                className,
            )}
        />
    );
}
