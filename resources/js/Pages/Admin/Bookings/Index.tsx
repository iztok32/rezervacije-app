import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import BookingRow from '@/Components/booking/BookingRow';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { Booking, Option, PaginationLink } from '@/types';

interface Props {
    bookings: { data: Booking[]; links: PaginationLink[]; total: number };
    filters: { type?: number; status?: string; from?: string; to?: string; q?: string; upcoming_only: boolean };
    types: { id: number; name: string }[];
    statuses: Option[];
}

const selectClass = 'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring';

/** Seznam rezervacij z iskanjem in filtri (osebje). */
export default function Index({ bookings, filters, types, statuses }: Props) {
    const { t } = useTranslation();
    const [values, setValues] = useState({
        q: filters.q ?? '',
        type: filters.type ? String(filters.type) : '',
        status: filters.status ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const apply = (next = values) => {
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== ''));
        router.get(route('admin.bookings.index'), query, { preserveState: true, preserveScroll: true });
    };
    const change = (key: keyof typeof values, value: string) => {
        const next = { ...values, [key]: value };
        setValues(next);
        if (key !== 'q') apply(next);
    };
    const submit = (e: FormEvent) => { e.preventDefault(); apply(); };

    return (
        <AppLayout header={t('Bookings')}>
            <Head title={t('Bookings')} />
            <PageHeader
                title={t('Bookings')}
                description={filters.upcoming_only ? t('Upcoming bookings. Set a date range to see past ones.') : t(':count bookings', { count: bookings.total })}
            />

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2">
                <div className="relative min-w-56 flex-1">
                    <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input className="pl-9" placeholder={t('Name, email, phone or reference')} value={values.q} onChange={(e) => change('q', e.target.value)} />
                </div>
                <select className={selectClass} value={values.type} onChange={(e) => change('type', e.target.value)}>
                    <option value="">{t('All types')}</option>
                    {types.map((ty) => <option key={ty.id} value={ty.id}>{ty.name}</option>)}
                </select>
                <select className={selectClass} value={values.status} onChange={(e) => change('status', e.target.value)}>
                    <option value="">{t('All statuses')}</option>
                    <option value="active">{t('Active (pending + confirmed)')}</option>
                    {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
                <Input type="date" className="w-auto" value={values.from} onChange={(e) => change('from', e.target.value)} aria-label={t('From')} />
                <Input type="date" className="w-auto" value={values.to} onChange={(e) => change('to', e.target.value)} aria-label={t('To')} />
                <Button type="submit" variant="secondary">{t('Search')}</Button>
            </form>

            {bookings.data.length === 0
                ? <EmptyState title={t('No bookings found.')} />
                : <div className="space-y-2">{bookings.data.map((b) => <BookingRow key={b.id} booking={b} staff />)}</div>}

            <Pagination links={bookings.links} />
        </AppLayout>
    );
}
