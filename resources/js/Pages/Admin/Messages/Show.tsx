import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { formatDate, formatTime } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { PaginationLink } from '@/types';
import { ChannelIcons, DeliveryStats, MessageSummary } from './shared';

interface RecipientRow {
    id: number;
    name: string;
    email: string | null;
    guest: boolean;
    mail_status: 'pending' | 'sent' | 'failed' | null;
    error: string | null;
    in_portal: boolean;
    read_at: string | null;
}

interface Props {
    message: MessageSummary & { body: string };
    recipients: { data: RecipientRow[]; links: PaginationLink[] };
}

const MAIL_STATUS: Record<string, { label: string; className: string }> = {
    pending: { label: 'Waiting to be sent', className: 'text-muted-foreground' },
    sent: { label: 'Sent', className: 'text-emerald-700 dark:text-emerald-400' },
    failed: { label: 'Failed', className: 'text-destructive' },
};

/** Vsebina sporočila in stanje pošiljanja po prejemnikih. */
export default function Show({ message, recipients }: Props) {
    const { t, locale } = useTranslation();

    return (
        <AppLayout header={t('Messages')}>
            <Head title={message.subject} />
            <Button variant="ghost" size="sm" asChild className="-ml-2 mb-2">
                <Link href={route('admin.messages.index')}><ArrowLeft className="mr-1.5 h-4 w-4" />{t('Messages')}</Link>
            </Button>
            <PageHeader
                title={<span className="flex items-center gap-2">{message.subject}<ChannelIcons channels={message.channels} /></span>}
                description={[
                    `${formatDate(message.created_at, locale)} ${formatTime(message.created_at)}`,
                    message.sender,
                    message.audience,
                ].filter(Boolean).join(' · ')}
            />

            <div className="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
                <Card className="h-fit">
                    <CardContent className="pt-6">
                        <p className="whitespace-pre-line text-sm leading-relaxed">{message.body}</p>
                    </CardContent>
                </Card>

                <div className="min-w-0">
                    <div className="mb-3"><DeliveryStats message={message} /></div>
                    <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">{t('Recipient')}</th>
                                    {message.channels.includes('mail') && <th className="p-3 font-medium">{t('Email')}</th>}
                                    {message.channels.includes('portal') && <th className="p-3 font-medium">{t('Portal inbox')}</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recipients.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="p-3">
                                            <span className="font-medium">{r.name}</span>
                                            {r.guest && <span className="ml-1.5 text-xs text-muted-foreground">({t('guest')})</span>}
                                            {r.email && <span className="block text-xs text-muted-foreground">{r.email}</span>}
                                        </td>
                                        {message.channels.includes('mail') && (
                                            <td className="p-3">
                                                {r.mail_status && (
                                                    <span className={cn('text-xs font-medium', MAIL_STATUS[r.mail_status].className)} title={r.error ?? undefined}>
                                                        {t(MAIL_STATUS[r.mail_status].label)}
                                                    </span>
                                                )}
                                                {r.error && <span className="block max-w-xs truncate text-xs text-muted-foreground">{r.error}</span>}
                                            </td>
                                        )}
                                        {message.channels.includes('portal') && (
                                            <td className="p-3 text-xs text-muted-foreground">
                                                {!r.in_portal ? '—' : r.read_at ? `${t('Read')} ${formatDate(r.read_at, locale)}` : t('Unread')}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={recipients.links} />
                </div>
            </div>
        </AppLayout>
    );
}
