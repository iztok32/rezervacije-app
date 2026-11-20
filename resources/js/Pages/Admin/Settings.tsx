import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Field from '@/Components/Field';
import { SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/lib/i18n';

/** Nastavitve podjetja: ime in kontakt, obvestila, pogoji, registracija. */
export default function Settings({ settings }: { settings: Record<string, string | null> }) {
    const { t } = useTranslation();
    const form = useForm({
        business_name: settings['business.name'] ?? '',
        business_tagline: settings['business.tagline'] ?? '',
        business_email: settings['business.email'] ?? '',
        business_phone: settings['business.phone'] ?? '',
        business_address: settings['business.address'] ?? '',
        business_website: settings['business.website'] ?? '',
        business_currency: settings['business.currency'] ?? 'EUR',
        booking_notify_email: settings['booking.notify_email'] ?? '',
        booking_terms: settings['booking.terms'] ?? '',
        booking_allow_registration: settings['booking.allow_registration'] !== '0',
    });
    const d = form.data;
    const e = form.errors;

    const submit = (ev: FormEvent) => {
        ev.preventDefault();
        form.put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <AppLayout header={t('Settings')}>
            <Head title={t('Settings')} />
            <PageHeader title={t('Settings')} />

            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader><CardTitle className="text-base">{t('Business')}</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <Field label={t('Business name')} error={e.business_name}><Input value={d.business_name} onChange={(ev) => form.setData('business_name', ev.target.value)} /></Field>
                        <Field label={t('Short description')} error={e.business_tagline}><Input value={d.business_tagline} onChange={(ev) => form.setData('business_tagline', ev.target.value)} /></Field>
                        <Field label={t('Email')} error={e.business_email}><Input type="email" value={d.business_email} onChange={(ev) => form.setData('business_email', ev.target.value)} /></Field>
                        <Field label={t('Phone')} error={e.business_phone}><Input value={d.business_phone} onChange={(ev) => form.setData('business_phone', ev.target.value)} /></Field>
                        <Field label={t('Address')} error={e.business_address}><Input value={d.business_address} onChange={(ev) => form.setData('business_address', ev.target.value)} /></Field>
                        <Field label={t('Website')} error={e.business_website}><Input type="url" value={d.business_website} onChange={(ev) => form.setData('business_website', ev.target.value)} placeholder="https://" /></Field>
                        <Field label={t('Currency')} error={e.business_currency} hint="EUR, USD, CHF …"><Input maxLength={3} value={d.business_currency} onChange={(ev) => form.setData('business_currency', ev.target.value.toUpperCase())} /></Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">{t('Bookings')}</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <Field label={t('Email for new booking notifications')} error={e.booking_notify_email} hint={t('Staff linked to a resource are also notified about their bookings.')}>
                            <Input type="email" value={d.booking_notify_email} onChange={(ev) => form.setData('booking_notify_email', ev.target.value)} />
                        </Field>
                        <Field label={t('Booking terms')} error={e.booking_terms} hint={t('If set, customers must accept them when booking.')}>
                            <Textarea rows={5} value={d.booking_terms} onChange={(ev) => form.setData('booking_terms', ev.target.value)} />
                        </Field>
                        <SwitchRow checked={d.booking_allow_registration} onChange={(v) => form.setData('booking_allow_registration', v)}
                            label={t('Customers can register')} description={t('Otherwise only users created by an administrator can log in.')} />
                    </CardContent>
                </Card>

                <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
            </form>
        </AppLayout>
    );
}
