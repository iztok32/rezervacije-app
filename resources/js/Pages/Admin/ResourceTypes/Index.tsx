import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import { Button } from '@/Components/ui/button';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';

interface TypeRow {
    id: number;
    name: string;
    slug: string;
    icon: string;
    color: string | null;
    booking_mode: string;
    mode_label: string;
    is_active: boolean;
    allow_guests: boolean;
    requires_approval: boolean;
    resources_count: number;
    upcoming_count: number;
}

/** Ponudba: tipi virov (vsak ima svoj način rezervacije in pravila). */
export default function Index({ types }: { types: TypeRow[] }) {
    const { t } = useTranslation();

    return (
        <AppLayout header={t('Offer')}>
            <Head title={t('Offer')} />
            <PageHeader
                title={t('Offer')}
                description={t('What customers can book. Each type has its own booking mode and rules.')}
                actions={<Button asChild><Link href={route('admin.resource-types.create')}><Plus className="mr-1.5 h-4 w-4" />{t('Add')}</Link></Button>}
            />

            {types.length === 0 ? (
                <EmptyState title={t('No offer yet.')} description={t('Start from a template: horse riding, hair salon, car rental, glamping, hotel, courts …')}
                    action={<Button asChild><Link href={route('admin.resource-types.create')}>{t('Add offer')}</Link></Button>} />
            ) : (
                <div className="grid gap-3 md:grid-cols-2">
                    {types.map((type) => (
                        <div key={type.id} className="flex items-start gap-4 rounded-xl border bg-card p-4">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
                                style={{ background: `${type.color ?? '#3b5bdb'}1a`, color: type.color ?? undefined }}>
                                <TypeIcon name={type.icon} className="h-5 w-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={route('admin.resource-types.edit', type.slug)} className="font-semibold hover:underline">{type.name}</Link>
                                    {!type.is_active && <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground">{t('Inactive')}</span>}
                                </div>
                                <p className="text-sm text-muted-foreground">{type.mode_label}</p>
                                <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span>{t('Resources')}: {type.resources_count}</span>
                                    <span>{t('Upcoming bookings')}: {type.upcoming_count}</span>
                                    {type.allow_guests && <span>{t('Guests allowed')}</span>}
                                    {type.requires_approval && <span>{t('Requires approval')}</span>}
                                </p>
                            </div>
                            <div className="flex flex-col gap-1">
                                <Button asChild size="sm" variant="secondary"><Link href={route('admin.resource-types.edit', type.slug)}>{t('Edit')}</Link></Button>
                                <Button asChild size="sm" variant="ghost"><Link href={route('book.show', type.slug)}><ExternalLink className="h-3.5 w-3.5" /></Link></Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
