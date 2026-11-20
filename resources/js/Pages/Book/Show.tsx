import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AlertCircle, CalendarCheck, Info, Loader2, LogIn } from 'lucide-react';
import SmartLayout from '@/Layouts/SmartLayout';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import CustomerPicker, { Customer } from '@/Components/booking/CustomerPicker';
import HourlyPicker from '@/Components/booking/HourlyPicker';
import NightlyPicker from '@/Components/booking/NightlyPicker';
import ServicePicker from '@/Components/booking/ServicePicker';
import TimeslotPicker from '@/Components/booking/TimeslotPicker';
import { PickerProps, Selection } from '@/Components/booking/types';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { PageProps, ResourceData, ResourceTypeData, ServiceData } from '@/types';

interface Props {
    type: ResourceTypeData;
    resources: ResourceData[];
    services: ServiceData[];
    today: string;
    lastBookableDate: string;
    requiresLogin: boolean;
    terms: string | null;
    isStaff: boolean;
}

const PICKERS: Record<ResourceTypeData['booking_mode'], (p: PickerProps) => JSX.Element> = {
    timeslot: TimeslotPicker,
    service: ServicePicker,
    nightly: NightlyPicker,
    hourly: HourlyPicker,
};

// Napake, ki se nanašajo na izbiro in ne na polje obrazca
const SELECTION_FIELDS = ['booking', 'auth', 'resource_type', 'timeslot_id', 'service_id', 'resource_id', 'date', 'time', 'start_time', 'end_time', 'start_date', 'end_date', 'quantity'];

export default function Show(props: Props) {
    const { type, requiresLogin, terms, isStaff } = props;
    const { auth, business } = usePage<PageProps>().props;
    const { t, tc, money } = useTranslation();
    const user = auth.user;

    const [selection, setSelection] = useState<Selection | null>(null);
    const [reloadKey, setReloadKey] = useState(0);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [form, setForm] = useState({
        customer_name: '',
        customer_email: '',
        customer_phone: user?.phone ?? '',
        notes: '',
        internal_notes: '',
        accept_terms: false,
    });
    const set = (key: keyof typeof form, value: string | boolean) => setForm((f) => ({ ...f, [key]: value }));

    const Picker = PICKERS[type.booking_mode];
    const selectionErrors = Object.entries(errors).filter(([k]) => SELECTION_FIELDS.includes(k)).map(([, v]) => v);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!selection) return;

        const data: Record<string, unknown> = { ...selection.payload, notes: form.notes, customer_phone: form.customer_phone };
        if (isStaff) {
            Object.assign(data, customer
                ? { user_id: customer.id }
                : { customer_name: form.customer_name, customer_email: form.customer_email });
            data.internal_notes = form.internal_notes;
        } else if (!user) {
            Object.assign(data, { customer_name: form.customer_name, customer_email: form.customer_email });
        }
        if (terms && !isStaff) data.accept_terms = form.accept_terms;

        router.post(route('book.store', type.slug), data as never, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                // Izbira morda ni več prosta — osveži razpoložljivost
                if (Object.keys(errs).some((k) => SELECTION_FIELDS.includes(k))) setReloadKey((k) => k + 1);
            },
            onSuccess: () => setErrors({}),
        });
    };

    return (
        <SmartLayout header={type.name}>
            <Head title={type.name} />

            <div className="mb-6 flex items-start gap-4">
                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                    style={{ background: `${type.color ?? '#3b5bdb'}1a`, color: type.color ?? undefined }}>
                    <TypeIcon name={type.icon} className="h-6 w-6" />
                </span>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{type.name}</h1>
                    {type.description && <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{type.description}</p>}
                    {!type.is_active && <p className="mt-1 text-xs font-medium text-amber-600">{t('This offer is inactive and visible only to staff.')}</p>}
                </div>
            </div>

            {requiresLogin ? (
                <Card className="max-w-lg">
                    <CardContent className="space-y-4 pt-6">
                        <p className="flex items-start gap-2 text-sm"><LogIn className="mt-0.5 h-4 w-4 shrink-0" />{t('Please log in to make a booking.')}</p>
                        <div className="flex gap-2">
                            <Button asChild><Link href={route('login')}>{t('Log in')}</Link></Button>
                            {business.allow_registration && <Button asChild variant="outline"><Link href={route('register')}>{t('Register')}</Link></Button>}
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <Card>
                        <CardContent className="pt-6">
                            <Picker {...props} onChange={(s) => { setSelection(s); }} reloadKey={reloadKey} />
                        </CardContent>
                    </Card>

                    <div className="space-y-4 lg:sticky lg:top-20 lg:self-start">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base"><CalendarCheck className="h-4 w-4" />{t('Your booking')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {selection ? (
                                    <div className="space-y-1 text-sm">
                                        {selection.summary.lines.map((line, i) => (
                                            <p key={i} className={i === 0 ? 'font-medium' : 'text-muted-foreground'}>{line}</p>
                                        ))}
                                        {selection.summary.price !== null && (
                                            <p className="flex items-center justify-between border-t pt-2 font-semibold">
                                                <span>{t('Total')}</span><span>{money(selection.summary.price)}</span>
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">{t('Select what you would like to book.')}</p>
                                )}

                                {selectionErrors.length > 0 && (
                                    <div className="flex gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                        <div>{selectionErrors.map((m, i) => <p key={i}>{m}</p>)}</div>
                                    </div>
                                )}

                                {isStaff && (
                                    <div className="space-y-3 rounded-md border border-dashed p-3">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Customer')}</p>
                                        <CustomerPicker value={customer} onChange={setCustomer} />
                                        {!customer && (
                                            <>
                                                <p className="text-xs text-muted-foreground">{t('…or enter a new customer:')}</p>
                                                <Field error={errors.customer_name}><Input placeholder={t('Name and surname')} value={form.customer_name} onChange={(e) => set('customer_name', e.target.value)} /></Field>
                                                <Field error={errors.customer_email}><Input type="email" placeholder={t('Email')} value={form.customer_email} onChange={(e) => set('customer_email', e.target.value)} /></Field>
                                            </>
                                        )}
                                        <Field error={errors.customer_phone}><Input placeholder={t('Phone')} value={form.customer_phone} onChange={(e) => set('customer_phone', e.target.value)} /></Field>
                                        <Field error={errors.internal_notes}><Textarea rows={2} placeholder={t('Internal note (not visible to the customer)')} value={form.internal_notes} onChange={(e) => set('internal_notes', e.target.value)} /></Field>
                                    </div>
                                )}

                                {!isStaff && !user && (
                                    <div className="space-y-3">
                                        <Field label={t('Name and surname')} htmlFor="customer_name" error={errors.customer_name} required>
                                            <Input id="customer_name" autoComplete="name" value={form.customer_name} onChange={(e) => set('customer_name', e.target.value)} />
                                        </Field>
                                        <Field label={t('Email')} htmlFor="customer_email" error={errors.customer_email} required>
                                            <Input id="customer_email" type="email" autoComplete="email" value={form.customer_email} onChange={(e) => set('customer_email', e.target.value)} />
                                        </Field>
                                        <Field label={t('Phone')} htmlFor="customer_phone" error={errors.customer_phone} required>
                                            <Input id="customer_phone" type="tel" autoComplete="tel" value={form.customer_phone} onChange={(e) => set('customer_phone', e.target.value)} />
                                        </Field>
                                    </div>
                                )}

                                {!isStaff && user && (
                                    <div className="space-y-3">
                                        <p className="text-sm"><span className="text-muted-foreground">{t('Booking for')}:</span> {user.name}</p>
                                        <Field label={t('Phone')} htmlFor="customer_phone" error={errors.customer_phone}>
                                            <Input id="customer_phone" type="tel" value={form.customer_phone} onChange={(e) => set('customer_phone', e.target.value)} />
                                        </Field>
                                    </div>
                                )}

                                <Field label={t('Note')} htmlFor="notes" error={errors.notes}>
                                    <Textarea id="notes" rows={2} value={form.notes} onChange={(e) => set('notes', e.target.value)} placeholder={t('Optional')} />
                                </Field>

                                {terms && !isStaff && (
                                    <Field error={errors.accept_terms}>
                                        <label className="flex items-start gap-2 text-sm">
                                            <Checkbox checked={form.accept_terms} onCheckedChange={(v) => set('accept_terms', v === true)} className="mt-0.5" />
                                            <span>
                                                {t('I accept the booking terms.')}
                                                <details className="mt-1 text-xs text-muted-foreground">
                                                    <summary className="cursor-pointer">{t('Show terms')}</summary>
                                                    <p className="mt-1 whitespace-pre-line">{terms}</p>
                                                </details>
                                            </span>
                                        </label>
                                    </Field>
                                )}

                                <Button type="submit" className="w-full" disabled={!selection || processing}>
                                    {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    {type.requires_approval && !isStaff ? t('Send booking request') : t('Confirm booking')}
                                </Button>

                                <div className="space-y-1 text-xs text-muted-foreground">
                                    {type.requires_approval && !isStaff && (
                                        <p className="flex gap-1.5"><Info className="mt-0.5 h-3 w-3 shrink-0" />{t('The booking will be confirmed by email.')}</p>
                                    )}
                                    {type.cancellation_hours !== null && type.cancellation_hours > 0 && (
                                        <p className="flex gap-1.5"><Info className="mt-0.5 h-3 w-3 shrink-0" />{tc('Free cancellation up to :count hours before the start.', type.cancellation_hours)}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            )}
        </SmartLayout>
    );
}
