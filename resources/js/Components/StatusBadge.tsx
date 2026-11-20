import { cn } from '@/lib/utils';
import { BookingStatus } from '@/types';
import { useTranslation } from '@/lib/i18n';

const STYLES: Record<BookingStatus, string> = {
    pending: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30',
    confirmed: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/30',
    completed: 'bg-sky-100 text-sky-800 border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/30',
    cancelled: 'bg-muted text-muted-foreground border-border line-through decoration-1',
    rejected: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-500/15 dark:text-red-300 dark:border-red-500/30',
    no_show: 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/30',
};

export const STATUS_LABELS: Record<BookingStatus, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    cancelled: 'Cancelled',
    rejected: 'Rejected',
    completed: 'Completed',
    no_show: 'No-show',
};

export default function StatusBadge({ status, className }: { status: BookingStatus; className?: string }) {
    const { t } = useTranslation();

    return (
        <span className={cn('inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium', STYLES[status], className)}>
            {t(STATUS_LABELS[status])}
        </span>
    );
}
