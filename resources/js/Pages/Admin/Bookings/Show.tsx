import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ArrowLeft, Copy, ExternalLink, Mail, Phone, UserRound } from 'lucide-react';
import { toast } from 'sonner';
import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/StatusBadge';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { formatDate, formatLongDate, formatTime } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { Booking, Option } from '@/types';

interface Props {
    booking: Booking;
    manageUrl: string;
    transitions: Option[];
    resources: { id: number; name: string }[];
    canManage: boolean;
}

const ACTION_STYLE: Record<string, 'default' | 'destructive' | 'outline' | 'secondary'> = {
    confirmed: 'default',
    completed: 'secondary',
    rejected: 'destructive',
    cancelled: 'outline',
    no_show: 'outline',
};

const ACTION_LABEL: Record<string, string> = {
    confirmed: 'Confirm',
    completed: 'Mark as completed',
    rejected: 'Decline',
    cancelled: 'Cancel',
    no_show: 'Mark as no-show',
};

/** Podrobnosti rezervacije za osebje: statusi, dodelitev vira, interne opombe. */
export default function Show({ booking, manageUrl, transitions, resources, canManage }: Props) {
    const { t, tc, money, locale } = useTranslation();
    const [reason, setReason] = useState('');
    const notes = useForm({ internal_notes: booking.internal_notes ?? '' });

    const changeStatus = (status: string) => {
        if (['rejected', 'cancelled'].includes(status) && !confirm(t('Are you sure? The customer will be notified by email.'))) return;
        router.post(route('admin.bookings.status', booking.reference), { status, reason }, { preserveScroll: true });
    };

    const assign = (resourceId: string) => router.patch(
        route('admin.bookings.update', booking.reference),
        { resource_id: resourceId ? Number(resourceId) : null },
        { preserveScroll: true },
    );

    const saveNotes = (e: FormEvent) => {
        e.preventDefault();
        notes.patch(route('admin.bookings.update', booking.reference), { preserveScroll: true });
    };

    const nightly = booking.type?.booking_mode === 'nightly';

    return (
        <AppLayout header={`${t('Booking')} ${booking.reference}`}>
            <Head title={`${booking.reference} · ${booking.customer_name}`} />

            <Button asChild variant="ghost" size="sm" className="mb-3 -ml-2">
                <Link href={route('admin.bookings.index')}><ArrowLeft className="mr-1 h-4 w-4" />{t('Bookings')}</Link>
            </Button>

            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="flex items-center gap-3 text-2xl font-semibold">
                        {booking.customer_name}
                        <StatusBadge status={booking.status} className="text-sm" />
                    </h1>
                    <p className="mt-1 text-muted-foreground">{booking.title}</p>
                </div>
                <span className="rounded-md border px-2 py-1 font-mono text-sm">{booking.reference}</span>
            </div>

            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader><CardTitle className="text-base">{t('Details')}</CardTitle></CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <Detail label={nightly ? t('From') : t('Date')} value={nightly ? `${formatLongDate(booking.starts_at, locale)}, ${formatTime(booking.starts_at)}` : formatLongDate(booking.starts_at, locale)} />
                                <Detail label={nightly ? t('To') : t('Time')} value={nightly ? `${formatLongDate(booking.ends_at, locale)}, ${formatTime(booking.ends_at)}` : `${formatTime(booking.starts_at)}–${formatTime(booking.ends_at)}`} />
                                {booking.service && <Detail label={t('Service')} value={booking.service.name} />}
                                {booking.timeslot && <Detail label={t('Session')} value={booking.timeslot.name} />}
                                {booking.quantity > 1 && <Detail label={t('Quantity')} value={tc(':count persons', booking.quantity)} />}
                                {booking.total_price !== null && <Detail label={t('Price')} value={money(booking.total_price)} />}
                                <Detail label={t('Created')} value={`${booking.created_at ? formatDate(booking.created_at, locale) + ' ' + formatTime(booking.created_at) : ''}${booking.created_by && booking.created_by !== booking.user?.name ? ` · ${booking.created_by}` : ''}`} />
                                {booking.confirmed_at && <Detail label={t('Confirmed')} value={`${formatDate(booking.confirmed_at, locale)} ${formatTime(booking.confirmed_at)}`} />}
                                {booking.cancelled_at && <Detail label={t('Cancelled')} value={`${formatDate(booking.cancelled_at, locale)} ${formatTime(booking.cancelled_at)}${booking.cancellation_reason ? ` — ${booking.cancellation_reason}` : ''}`} />}
                            </dl>
                            {booking.notes && <p className="mt-4 rounded-md bg-muted/50 p-3 text-sm"><span className="text-muted-foreground">{t('Customer note')}:</span> {booking.notes}</p>}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="text-base">{booking.type?.resource_label ?? t('Resource')}</CardTitle></CardHeader>
                        <CardContent>
                            <select
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                value={booking.resource?.id ?? ''}
                                onChange={(e) => assign(e.target.value)}
                                disabled={!canManage || !['pending', 'confirmed'].includes(booking.status)}
                            >
                                <option value="">— {t('Not assigned')} —</option>
                                {resources.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                            </select>
                            <p className="mt-2 text-xs text-muted-foreground">{t('Only free resources can be assigned.')}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="text-base">{t('Internal notes')}</CardTitle></CardHeader>
                        <CardContent>
                            <form onSubmit={saveNotes} className="space-y-2">
                                <Textarea rows={3} value={notes.data.internal_notes} onChange={(e) => notes.setData('internal_notes', e.target.value)} readOnly={!canManage} />
                                {canManage && <Button type="submit" size="sm" variant="secondary" disabled={notes.processing || !notes.isDirty}>{t('Save')}</Button>}
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    {canManage && <Card>
                        <CardHeader><CardTitle className="text-base">{t('Actions')}</CardTitle></CardHeader>
                        <CardContent className="space-y-3">
                            {transitions.length === 0 && <p className="text-sm text-muted-foreground">{t('No further actions.')}</p>}
                            {transitions.some((s) => ['rejected', 'cancelled'].includes(s.value)) && (
                                <Field label={t('Reason (sent to the customer)')}>
                                    <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('Optional')} />
                                </Field>
                            )}
                            <div className="flex flex-col gap-2">
                                {transitions.map((s) => (
                                    <Button key={s.value} variant={ACTION_STYLE[s.value] ?? 'outline'} onClick={() => changeStatus(s.value)}>
                                        {t(ACTION_LABEL[s.value] ?? s.label)}
                                    </Button>
                                ))}
                            </div>
                        </CardContent>
                    </Card>}

                    <Card>
                        <CardHeader><CardTitle className="text-base">{t('Customer')}</CardTitle></CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p className="flex items-center gap-2"><UserRound className="h-4 w-4 text-muted-foreground" />{booking.customer_name}{!booking.user && <span className="text-xs text-muted-foreground">({t('guest')})</span>}</p>
                            <a className="flex items-center gap-2 hover:underline" href={`mailto:${booking.customer_email}`}><Mail className="h-4 w-4 text-muted-foreground" />{booking.customer_email}</a>
                            {booking.customer_phone && <a className="flex items-center gap-2 hover:underline" href={`tel:${booking.customer_phone}`}><Phone className="h-4 w-4 text-muted-foreground" />{booking.customer_phone}</a>}
                            <div className="flex gap-2 pt-2">
                                <Button size="sm" variant="outline" onClick={() => { navigator.clipboard.writeText(manageUrl); toast.success(t('Link copied.')); }}>
                                    <Copy className="mr-1.5 h-3.5 w-3.5" />{t('Customer link')}
                                </Button>
                                <Button size="sm" variant="ghost" asChild><a href={manageUrl} target="_blank" rel="noreferrer"><ExternalLink className="h-3.5 w-3.5" /></a></Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}
