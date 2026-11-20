import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import BookingRow from '@/Components/booking/BookingRow';
import { Button } from '@/Components/ui/button';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { Booking, PageProps } from '@/types';

/** Nadzorna plošča stranke: prihajajoče rezervacije in hitre povezave za rezervacijo. */
export default function Dashboard({ upcoming }: { upcoming: Booking[] }) {
    const { auth, bookableTypes } = usePage<PageProps>().props;
    const { t } = useTranslation();

    return (
        <AppLayout header={t('Overview')}>
            <Head title={t('Overview')} />
            <PageHeader title={t('Hello, :name', { name: auth.user!.name.split(' ')[0] })} description={t('Here are your upcoming bookings.')} />

            <section className="mb-8 space-y-2">
                {upcoming.length === 0
                    ? <EmptyState title={t('You have no upcoming bookings.')} />
                    : upcoming.map((b) => <BookingRow key={b.id} booking={b} />)}
                {upcoming.length > 0 && (
                    <Button asChild variant="link" className="px-0"><Link href={route('my-bookings')}>{t('All my bookings')} <ArrowRight className="ml-1 h-4 w-4" /></Link></Button>
                )}
            </section>

            {bookableTypes.length > 0 && (
                <section>
                    <h2 className="mb-3 text-lg font-semibold">{t('Book')}</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {bookableTypes.map((type) => (
                            <Link key={type.id} href={route('book.show', type.slug)}
                                className="flex items-center gap-3 rounded-lg border bg-card p-4 transition hover:border-primary/40">
                                <span className="flex h-10 w-10 items-center justify-center rounded-lg"
                                    style={{ background: `${type.color ?? '#3b5bdb'}1a`, color: type.color ?? undefined }}>
                                    <TypeIcon name={type.icon} className="h-5 w-5" />
                                </span>
                                <span className="font-medium">{type.name}</span>
                                <ArrowRight className="ml-auto h-4 w-4 text-muted-foreground" />
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
