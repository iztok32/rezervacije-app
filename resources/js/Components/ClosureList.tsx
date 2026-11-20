import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { formatDate, formatTime } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';

interface Row {
    id: number;
    type?: string | null;
    resource: string | null;
    starts_at: string;
    ends_at: string;
    reason: string | null;
}

/** Seznam zaprtij; celodnevna zaprtja (00:00–00:00) so prikazana kot dnevi. */
export default function ClosureList({ closures }: { closures: Row[] }) {
    const { t, locale } = useTranslation();

    if (closures.length === 0) return <p className="text-sm text-muted-foreground">{t('No closures.')}</p>;

    const describe = (c: Row) => {
        const allDay = formatTime(c.starts_at) === '00:00' && formatTime(c.ends_at) === '00:00';
        if (allDay) {
            const last = new Date(c.ends_at);
            last.setDate(last.getDate() - 1);
            const lastIso = `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`;
            return lastIso === c.starts_at.slice(0, 10)
                ? formatDate(c.starts_at, locale, { weekday: 'short' })
                : `${formatDate(c.starts_at, locale)} – ${formatDate(lastIso, locale)}`;
        }
        return `${formatDate(c.starts_at, locale)} ${formatTime(c.starts_at)} – ${formatDate(c.ends_at, locale)} ${formatTime(c.ends_at)}`;
    };

    return (
        <div className="divide-y rounded-lg border bg-card">
            {closures.map((c) => (
                <div key={c.id} className="flex items-center gap-3 p-3 text-sm">
                    <div className="min-w-0 flex-1">
                        <p className="font-medium">{describe(c)}</p>
                        <p className="text-xs text-muted-foreground">
                            {[c.resource ?? c.type ?? (c.type === undefined ? t('All resources') : t('Whole business')), c.reason].filter(Boolean).join(' · ')}
                        </p>
                    </div>
                    <Button size="icon" variant="ghost" onClick={() => router.delete(route('admin.closures.destroy', c.id), { preserveScroll: true })}>
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ))}
        </div>
    );
}
