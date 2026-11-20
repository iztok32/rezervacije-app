import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarCheck, CalendarDays, Clock, Plus, TrendingUp } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import BookingRow from '@/Components/booking/BookingRow';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { formatLongDate, toIsoDate } from '@/lib/dates';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { useCan } from '@/lib/permissions';
import { Booking, PageProps } from '@/types';

interface Props {
    today: Booking[];
    pending: Booking[];
    stats: { today: number; pending: number; next7: number; created30: number; revenue30: number };
}

/** Nadzorna plošča osebja: današnje rezervacije, čakajoče na potrditev, kratka statistika. */
export default function Dashboard({ today, pending, stats }: Props) {
    const { bookableTypes } = usePage<PageProps>().props;
    const { t, money, locale } = useTranslation();
    const can = useCan();

    const tiles = [
        { label: t('Today'), value: stats.today, icon: CalendarDays },
        { label: t('Awaiting confirmation'), value: stats.pending, icon: Clock, highlight: stats.pending > 0 },
        { label: t('Next 7 days'), value: stats.next7, icon: CalendarCheck },
        { label: t('Revenue (30 days)'), value: money(stats.revenue30), icon: TrendingUp },
    ];

    return (
        <AppLayout header={t('Overview')}>
            <Head title={t('Overview')} />
            <PageHeader
                title={t('Overview')}
                description={formatLongDate(toIsoDate(new Date()), locale)}
                actions={can('bookings.create') && bookableTypes.length > 0 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button><Plus className="mr-1.5 h-4 w-4" />{t('New booking')}</Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {bookableTypes.map((type) => (
                                <DropdownMenuItem key={type.id} asChild>
                                    <Link href={route('book.show', type.slug)}><TypeIcon name={type.icon} className="mr-2 h-4 w-4" />{type.name}</Link>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            />

            <div className="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {tiles.map((tile) => (
                    <div key={tile.label} className={`rounded-xl border bg-card p-4 ${tile.highlight ? 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' : ''}`}>
                        <div className="flex items-center justify-between text-sm text-muted-foreground">
                            {tile.label}<tile.icon className="h-4 w-4" />
                        </div>
                        <p className="mt-2 text-2xl font-semibold tabular-nums">{tile.value}</p>
                    </div>
                ))}
            </div>

            {pending.length > 0 && (
                <section className="mb-8">
                    <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                        {t('Awaiting confirmation')}
                        <span className="rounded-full bg-amber-500 px-2 text-xs text-white">{stats.pending}</span>
                    </h2>
                    <div className="space-y-2">{pending.map((b) => <BookingRow key={b.id} booking={b} staff />)}</div>
                </section>
            )}

            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-lg font-semibold">{t('Today')}</h2>
                    <Button asChild variant="link" size="sm"><Link href={route('admin.calendar')}>{t('Open calendar')}</Link></Button>
                </div>
                {today.length === 0
                    ? <EmptyState title={t('No bookings today.')} />
                    : <div className="space-y-2">{today.map((b) => <BookingRow key={b.id} booking={b} staff />)}</div>}
            </section>
        </AppLayout>
    );
}
