import { Head } from '@inertiajs/react';
import { Inbox as InboxIcon } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import { Badge } from '@/Components/ui/badge';
import { formatDate, formatTime } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { PaginationLink } from '@/types';

interface Item {
    id: number;
    subject: string;
    body: string;
    created_at: string;
    unread: boolean;
}

/** Sporočila, ki jih je podjetje poslalo uporabniku v nabiralnik portala. */
export default function Inbox({ items, from }: { items: { data: Item[]; links: PaginationLink[] }; from: string }) {
    const { t, locale } = useTranslation();

    return (
        <AppLayout header={t('Inbox')}>
            <Head title={t('Inbox')} />
            <PageHeader title={t('Inbox')} description={t('Messages from :name.', { name: from })} />

            {items.data.length === 0 ? (
                <EmptyState icon={<InboxIcon className="h-8 w-8" />} title={t('No messages yet.')} />
            ) : (
                <div className="space-y-3">
                    {items.data.map((item) => (
                        <article key={item.id} className={cn('rounded-lg border bg-card p-4', item.unread && 'border-primary/50 ring-1 ring-primary/20')}>
                            <header className="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <h2 className="flex items-center gap-2 font-medium">
                                    {item.subject}
                                    {item.unread && <Badge className="h-5 px-1.5 text-[11px]">{t('New')}</Badge>}
                                </h2>
                                <time className="text-xs text-muted-foreground" dateTime={item.created_at}>
                                    {formatDate(item.created_at, locale)} {formatTime(item.created_at)}
                                </time>
                            </header>
                            <p className="whitespace-pre-line text-sm leading-relaxed text-foreground/90">{item.body}</p>
                        </article>
                    ))}
                </div>
            )}
            <Pagination links={items.links} />
        </AppLayout>
    );
}
