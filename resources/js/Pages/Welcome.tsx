import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarClock, Clock, Moon, Timer } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import EmptyState from '@/Components/EmptyState';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { BookingMode, PageProps } from '@/types';

interface TypeCard {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string;
    color: string | null;
    booking_mode: BookingMode;
    allow_guests: boolean;
    resource_label: string;
    resources_count: number;
}

const MODE_HINTS: Record<BookingMode, { icon: typeof Clock; label: string }> = {
    timeslot: { icon: CalendarClock, label: 'Choose a session' },
    service: { icon: Timer, label: 'Choose a service and time' },
    nightly: { icon: Moon, label: 'Choose dates' },
    hourly: { icon: Clock, label: 'Choose hours' },
};

export default function Welcome({ types }: { types: TypeCard[] }) {
    const { business, auth } = usePage<PageProps>().props;
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title={t('Book online')} />

            <section className="border-b bg-gradient-to-b from-primary/10 via-background to-background">
                <div className="mx-auto max-w-5xl px-4 py-14 sm:py-20">
                    <h1 className="text-3xl font-bold tracking-tight sm:text-5xl">{business.name}</h1>
                    <p className="mt-4 max-w-2xl text-lg text-muted-foreground">
                        {business.tagline || t('Check availability and book online in a few steps.')}
                    </p>
                </div>
            </section>

            <section className="mx-auto max-w-5xl px-4 py-10">
                <h2 className="mb-5 text-lg font-semibold">{t('What would you like to book?')}</h2>

                {types.length === 0 ? (
                    <EmptyState
                        title={t('There is nothing to book yet.')}
                        description={auth.user?.is_admin ? t('Add your first offer in the administration.') : undefined}
                        action={auth.user?.is_admin ? <Link className="text-sm font-medium text-primary underline" href={route('admin.resource-types.create')}>{t('Add offer')}</Link> : undefined}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {types.map((type) => {
                            const hint = MODE_HINTS[type.booking_mode];
                            return (
                                <Link
                                    key={type.id}
                                    href={route('book.show', type.slug)}
                                    className="group relative flex flex-col overflow-hidden rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                >
                                    <span className="absolute inset-x-0 top-0 h-1" style={{ background: type.color ?? 'var(--primary)' }} />
                                    <span
                                        className="mb-4 flex h-11 w-11 items-center justify-center rounded-lg"
                                        style={{ background: `${type.color ?? '#3b5bdb'}1a`, color: type.color ?? undefined }}
                                    >
                                        <TypeIcon name={type.icon} className="h-5 w-5" />
                                    </span>
                                    <span className="text-lg font-semibold">{type.name}</span>
                                    {type.description && <span className="mt-1 line-clamp-3 text-sm text-muted-foreground">{type.description}</span>}
                                    <span className="mt-auto flex items-center justify-between pt-5 text-sm">
                                        <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                            <hint.icon className="h-4 w-4" />
                                            {t(hint.label)}
                                        </span>
                                        <ArrowRight className="h-4 w-4 text-primary transition group-hover:translate-x-1" />
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </section>
        </PublicLayout>
    );
}
