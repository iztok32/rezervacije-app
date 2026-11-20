import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import BookingRow from '@/Components/booking/BookingRow';
import { Button } from '@/Components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { useTranslation } from '@/lib/i18n';
import { Booking } from '@/types';

export default function Mine({ upcoming, past }: { upcoming: Booking[]; past: Booking[] }) {
    const { t } = useTranslation();

    return (
        <AppLayout header={t('My bookings')}>
            <Head title={t('My bookings')} />
            <PageHeader title={t('My bookings')} actions={<Button asChild><Link href={route('home')}>{t('New booking')}</Link></Button>} />

            <Tabs defaultValue="upcoming">
                <TabsList>
                    <TabsTrigger value="upcoming">{t('Upcoming')} ({upcoming.length})</TabsTrigger>
                    <TabsTrigger value="past">{t('Past')}</TabsTrigger>
                </TabsList>
                <TabsContent value="upcoming" className="space-y-2">
                    {upcoming.length === 0
                        ? <EmptyState title={t('You have no upcoming bookings.')} action={<Button asChild variant="outline"><Link href={route('home')}>{t('Book now')}</Link></Button>} />
                        : upcoming.map((b) => <BookingRow key={b.id} booking={b} />)}
                </TabsContent>
                <TabsContent value="past" className="space-y-2">
                    {past.length === 0
                        ? <EmptyState title={t('No past bookings.')} />
                        : past.map((b) => <BookingRow key={b.id} booking={b} />)}
                </TabsContent>
            </Tabs>
        </AppLayout>
    );
}
