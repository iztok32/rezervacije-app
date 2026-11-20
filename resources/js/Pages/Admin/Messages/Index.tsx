import { Head, Link } from '@inertiajs/react';
import { Plus, Send } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { formatDate, formatTime } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { PaginationLink } from '@/types';
import { ChannelIcons, DeliveryStats, MessageSummary } from './shared';

/** Poslana sporočila strankam z učinkom pošiljanja. */
export default function Index({ messages }: { messages: { data: MessageSummary[]; links: PaginationLink[] } }) {
    const { t, locale } = useTranslation();

    return (
        <AppLayout header={t('Messages')}>
            <Head title={t('Messages')} />
            <PageHeader
                title={t('Messages')}
                description={t('Send an email or portal message to customers, a customer group or customers with bookings.')}
                actions={<Button asChild><Link href={route('admin.messages.create')}><Plus className="mr-1.5 h-4 w-4" />{t('New message')}</Link></Button>}
            />

            {messages.data.length === 0 ? (
                <EmptyState icon={<Send className="h-8 w-8" />} title={t('No messages sent yet.')} />
            ) : (
                <div className="divide-y rounded-lg border bg-card">
                    {messages.data.map((m) => (
                        <Link key={m.id} href={route('admin.messages.show', m.id)} className="flex flex-col gap-2 p-4 transition hover:bg-accent/40 sm:flex-row sm:items-center">
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    <span className="truncate">{m.subject}</span>
                                    <ChannelIcons channels={m.channels} />
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {formatDate(m.created_at, locale)} {formatTime(m.created_at)}
                                    {m.sender && ` · ${m.sender}`}
                                    {m.audience && ` · ${m.audience}`}
                                </p>
                            </div>
                            <DeliveryStats message={m} />
                        </Link>
                    ))}
                </div>
            )}
            <Pagination links={messages.links} />
        </AppLayout>
    );
}
