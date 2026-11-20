import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import ClosureForm from '@/Components/ClosureForm';
import ClosureList from '@/Components/ClosureList';
import { useTranslation } from '@/lib/i18n';

interface Props {
    closures: { id: number; type: string | null; resource: string | null; starts_at: string; ends_at: string; reason: string | null }[];
    types: { id: number; name: string }[];
    resources: { id: number; name: string; resource_type_id: number }[];
}

/** Zaprtja in odsotnosti za celo podjetje, posamezen tip ali vir. */
export default function Index({ closures, types, resources }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout header={t('Closures')}>
            <Head title={t('Closures')} />
            <PageHeader title={t('Closures')} description={t('Public holidays, vacations, maintenance — nothing can be booked during a closure.')} />
            <div className="space-y-6">
                <ClosureForm types={types} resources={resources} />
                <ClosureList closures={closures} />
            </div>
        </AppLayout>
    );
}
