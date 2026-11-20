import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import ClosureForm from '@/Components/ClosureForm';
import ClosureList from '@/Components/ClosureList';
import { Button } from '@/Components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import HoursTab from './Partials/HoursTab';
import ResourcesTab from './Partials/ResourcesTab';
import ServicesTab from './Partials/ServicesTab';
import SettingsTab from './Partials/SettingsTab';
import TimeslotsTab from './Partials/TimeslotsTab';
import { EditProps } from './Partials/types';

// Zadnji odprti zavihek po tipu — stran se po shranjevanju ponovno izriše, zavihek pa ostane
const lastTab: Record<string, string> = {};

/** Urejanje tipa vira: nastavitve in vsebina (zavihki glede na način rezervacije). */
export default function Edit(props: EditProps) {
    const { type, resources, closures } = props;
    const { t } = useTranslation();
    const mode = type.booking_mode;
    const [tab, setTab] = useState(() => lastTab[type.slug] ?? (resources.length === 0 ? 'resources' : 'settings'));
    const changeTab = (value: string) => {
        lastTab[type.slug] = value;
        setTab(value);
    };

    return (
        <AppLayout header={type.name}>
            <Head title={type.name} />

            <Button asChild variant="ghost" size="sm" className="mb-3 -ml-2">
                <Link href={route('admin.resource-types.index')}><ArrowLeft className="mr-1 h-4 w-4" />{t('Offer')}</Link>
            </Button>

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <span className="flex h-11 w-11 items-center justify-center rounded-lg" style={{ background: `${type.color ?? '#3b5bdb'}1a`, color: type.color ?? undefined }}>
                    <TypeIcon name={type.icon} className="h-5 w-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h1 className="text-2xl font-semibold">{type.name}</h1>
                    {!type.is_active && <p className="text-xs font-medium text-amber-600">{t('Inactive — not visible to customers.')}</p>}
                </div>
                <Button asChild variant="outline" size="sm"><Link href={route('book.show', type.slug)}><ExternalLink className="mr-1.5 h-3.5 w-3.5" />{t('Booking page')}</Link></Button>
            </div>

            <Tabs value={tab} onValueChange={changeTab}>
                <TabsList className="mb-4 flex h-auto flex-wrap justify-start">
                    <TabsTrigger value="settings">{t('Settings')}</TabsTrigger>
                    <TabsTrigger value="resources">{t('Resources')} ({resources.length})</TabsTrigger>
                    {mode === 'service' && <TabsTrigger value="services">{t('Services')} ({props.services.length})</TabsTrigger>}
                    {mode === 'timeslot' && <TabsTrigger value="timeslots">{t('Sessions')} ({props.timeslots.length})</TabsTrigger>}
                    {(mode === 'service' || mode === 'hourly') && <TabsTrigger value="hours">{t('Working hours')}</TabsTrigger>}
                    <TabsTrigger value="closures">{t('Closures')}</TabsTrigger>
                </TabsList>

                <TabsContent value="settings"><SettingsTab {...props} /></TabsContent>
                <TabsContent value="resources"><ResourcesTab {...props} /></TabsContent>
                {mode === 'service' && <TabsContent value="services"><ServicesTab {...props} /></TabsContent>}
                {mode === 'timeslot' && <TabsContent value="timeslots"><TimeslotsTab {...props} /></TabsContent>}
                {(mode === 'service' || mode === 'hourly') && <TabsContent value="hours"><HoursTab {...props} /></TabsContent>}
                <TabsContent value="closures" className="space-y-4">
                    <ClosureForm typeId={type.id} resources={resources} />
                    <ClosureList closures={closures} />
                </TabsContent>
            </Tabs>
        </AppLayout>
    );
}
