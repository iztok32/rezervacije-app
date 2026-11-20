import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarClock, CheckCircle2, Clock, Hash, Mail, Phone, Tag, XCircle } from 'lucide-react';
import SmartLayout from '@/Layouts/SmartLayout';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import { Textarea } from '@/Components/ui/textarea';
import { formatLongDate, formatTime } from '@/lib/dates';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { Booking, PageProps } from '@/types';

interface Props {
    booking: Booking;
    token: string | null;
    cancelBlocker: string | null;
    cancellationHours: number | null;
    businessContact: { email: string | null; phone: string | null };
}

/** Potrditvena stran in upravljanje rezervacije za stranko (tudi gosta s povezavo). */
export default function Show({ booking, token, cancelBlocker, businessContact }: Props) {
    const { auth } = usePage<PageProps>().props;
    const { t, tc, money, locale } = useTranslation();
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const active = booking.status === 'pending' || booking.status === 'confirmed';
    const nightly = booking.type?.booking_mode === 'nightly';

    const cancel = () => router.post(route('bookings.cancel', booking.reference), { token, reason }, {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
    });

    return (
        <SmartLayout header={`${t('Booking')} ${booking.reference}`}>
            <Head title={`${t('Booking')} ${booking.reference}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <StatusHero booking={booking} />

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <div className="flex items-start gap-3">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                style={{ background: `${booking.type?.color ?? '#3b5bdb'}1a`, color: booking.type?.color ?? undefined }}>
                                <TypeIcon name={booking.type?.icon} className="h-5 w-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold">{booking.title}</p>
                                <div className="mt-1"><StatusBadge status={booking.status} /></div>
                            </div>
                        </div>

                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <Item icon={<Hash />} label={t('Reference')} value={<span className="font-mono font-semibold">{booking.reference}</span>} />
                            {nightly ? (
                                <>
                                    <Item icon={<CalendarClock />} label={booking.type?.nightly_unit === 'day' ? t('Pick-up') : t('Arrival')}
                                        value={`${formatLongDate(booking.starts_at, locale)}, ${formatTime(booking.starts_at)}`} />
                                    <Item icon={<CalendarClock />} label={booking.type?.nightly_unit === 'day' ? t('Return') : t('Departure')}
                                        value={`${formatLongDate(booking.ends_at, locale)}, ${formatTime(booking.ends_at)}`} />
                                </>
                            ) : (
                                <>
                                    <Item icon={<CalendarClock />} label={t('Date')} value={formatLongDate(booking.starts_at, locale)} />
                                    <Item icon={<Clock />} label={t('Time')} value={`${formatTime(booking.starts_at)}–${formatTime(booking.ends_at)}`} />
                                </>
                            )}
                            {booking.quantity > 1 && <Item icon={<Tag />} label={t('Quantity')} value={tc(':count persons', booking.quantity)} />}
                            {booking.total_price !== null && <Item icon={<Tag />} label={t('Price')} value={money(booking.total_price)} />}
                        </dl>

                        {booking.notes && <p className="rounded-md bg-muted/50 p-3 text-sm"><span className="text-muted-foreground">{t('Note')}:</span> {booking.notes}</p>}
                        {booking.cancellation_reason && <p className="text-sm text-muted-foreground">{t('Reason')}: {booking.cancellation_reason}</p>}
                    </CardContent>
                </Card>

                {active && (
                    <Card>
                        <CardContent className="flex flex-col gap-3 pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm">
                                <p className="font-medium">{t('Cannot make it?')}</p>
                                <p className="text-muted-foreground">{cancelBlocker ?? t('You can cancel the booking here.')}</p>
                            </div>
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="outline" disabled={!!cancelBlocker || processing}>{t('Cancel booking')}</Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>{t('Cancel booking?')}</AlertDialogTitle>
                                        <AlertDialogDescription>{t('The booking will be cancelled and the place released.')}</AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <Textarea rows={2} placeholder={t('Reason (optional)')} value={reason} onChange={(e) => setReason(e.target.value)} />
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>{t('Back')}</AlertDialogCancel>
                                        <AlertDialogAction onClick={cancel}>{t('Cancel booking')}</AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </CardContent>
                    </Card>
                )}

                {(businessContact.email || businessContact.phone) && (
                    <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span>{t('Questions? Contact us:')}</span>
                        {businessContact.phone && <a className="inline-flex items-center gap-1 hover:text-foreground" href={`tel:${businessContact.phone}`}><Phone className="h-3.5 w-3.5" />{businessContact.phone}</a>}
                        {businessContact.email && <a className="inline-flex items-center gap-1 hover:text-foreground" href={`mailto:${businessContact.email}`}><Mail className="h-3.5 w-3.5" />{businessContact.email}</a>}
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    {booking.type && <Button asChild variant="secondary"><Link href={route('book.show', booking.type.slug)}>{t('New booking')}</Link></Button>}
                    {auth.user && <Button asChild variant="ghost"><Link href={route('my-bookings')}>{t('My bookings')}</Link></Button>}
                </div>
            </div>
        </SmartLayout>
    );
}

function StatusHero({ booking }: { booking: Booking }) {
    const { t } = useTranslation();

    if (booking.status === 'confirmed') {
        return <Hero icon={<CheckCircle2 className="h-6 w-6 text-emerald-600" />} title={t('Your booking is confirmed.')} text={t('A confirmation has been sent to :email.', { email: booking.customer_email })} />;
    }
    if (booking.status === 'pending') {
        return <Hero icon={<Clock className="h-6 w-6 text-amber-600" />} title={t('Booking received')} text={t('We will confirm your booking by email (:email).', { email: booking.customer_email })} />;
    }
    if (booking.status === 'cancelled' || booking.status === 'rejected') {
        return <Hero icon={<XCircle className="h-6 w-6 text-muted-foreground" />} title={booking.status === 'cancelled' ? t('Booking cancelled') : t('Booking declined')} />;
    }
    return null;
}

function Hero({ icon, title, text }: { icon: React.ReactNode; title: string; text?: string }) {
    return (
        <div className="flex items-start gap-3">
            {icon}
            <div>
                <h1 className="text-xl font-semibold">{title}</h1>
                {text && <p className="text-sm text-muted-foreground">{text}</p>}
            </div>
        </div>
    );
}

function Item({ icon, label, value }: { icon: React.ReactNode; label: string; value: React.ReactNode }) {
    return (
        <div className="flex gap-2.5">
            <span className="mt-0.5 text-muted-foreground [&>svg]:h-4 [&>svg]:w-4">{icon}</span>
            <div>
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="font-medium">{value}</dd>
            </div>
        </div>
    );
}
