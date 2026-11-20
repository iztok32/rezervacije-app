import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import Field from '@/Components/Field';
import { MultiCheck, numberOrNull, selectClass, SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { TYPE_ICONS } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { EditProps } from './types';

/** Nastavitve tipa vira: osnovno, pravila rezervacij in nastavitve, specifične za način. */
export default function SettingsTab({ type, modes, selections, customerGroups }: EditProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: type.name,
        slug: type.slug,
        description: type.description ?? '',
        icon: type.icon,
        color: type.color ?? '#3b5bdb',
        booking_mode: type.booking_mode,
        resource_label: type.resource_label_raw ?? '',
        resource_selection: type.resource_selection,
        allow_guests: type.allow_guests,
        requires_approval: type.requires_approval,
        min_advance_minutes: type.min_advance_minutes,
        max_advance_days: type.max_advance_days,
        cancellation_hours: type.cancellation_hours as number | null,
        slot_interval_minutes: type.slot_interval_minutes,
        min_duration_minutes: type.min_duration_minutes as number | null,
        max_duration_minutes: type.max_duration_minutes as number | null,
        nightly_unit: type.nightly_unit,
        check_in_time: type.check_in_time_raw ?? '',
        check_out_time: type.check_out_time_raw ?? '',
        min_units: type.min_units,
        max_units: type.max_units as number | null,
        max_quantity_per_booking: type.max_quantity_per_booking,
        is_active: type.is_active,
        customer_group_ids: type.customer_group_ids,
    });
    const d = form.data;
    const e = form.errors;
    const mode = d.booking_mode;

    const submit = (ev: FormEvent) => {
        ev.preventDefault();
        form.transform((data) => ({ ...data, check_in_time: data.check_in_time || null, check_out_time: data.check_out_time || null }));
        form.put(route('admin.resource-types.update', type.slug), { preserveScroll: true });
    };

    const destroy = () => {
        if (confirm(t('Delete this offer? Past bookings are kept.'))) router.delete(route('admin.resource-types.destroy', type.slug));
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card>
                <CardHeader><CardTitle className="text-base">{t('Basic')}</CardTitle></CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <Field label={t('Name')} error={e.name} required><Input value={d.name} onChange={(ev) => form.setData('name', ev.target.value)} /></Field>
                    <Field label={t('Web address')} error={e.slug} hint={`/book/${d.slug}`}><Input value={d.slug} onChange={(ev) => form.setData('slug', ev.target.value)} /></Field>
                    <Field label={t('Description')} error={e.description} className="md:col-span-2">
                        <Textarea rows={3} value={d.description} onChange={(ev) => form.setData('description', ev.target.value)} />
                    </Field>
                    <Field label={t('Icon')} className="md:col-span-2">
                        <div className="flex flex-wrap gap-1.5">
                            {Object.entries(TYPE_ICONS).map(([name, Icon]) => (
                                <button key={name} type="button" title={name} onClick={() => form.setData('icon', name)}
                                    className={cn('flex h-9 w-9 items-center justify-center rounded-md border', d.icon === name ? 'border-primary bg-primary/10 text-primary' : 'hover:bg-accent')}>
                                    <Icon className="h-4 w-4" />
                                </button>
                            ))}
                        </div>
                    </Field>
                    <Field label={t('Colour')} error={e.color}>
                        <div className="flex items-center gap-2">
                            <input type="color" value={d.color} onChange={(ev) => form.setData('color', ev.target.value)} className="h-9 w-14 cursor-pointer rounded border" />
                            <Input value={d.color} onChange={(ev) => form.setData('color', ev.target.value)} className="w-32 font-mono" />
                        </div>
                    </Field>
                    <Field label={t('Resource name (singular)')} error={e.resource_label} hint={t('E.g. Horse, Hairdresser, Car, House, Room, Court.')}>
                        <Input value={d.resource_label} onChange={(ev) => form.setData('resource_label', ev.target.value)} />
                    </Field>
                    <div className="md:col-span-2">
                        <SwitchRow checked={d.is_active} onChange={(v) => form.setData('is_active', v)}
                            label={t('Active')} description={t('Inactive offers are hidden from customers; staff can still book.')} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle className="text-base">{t('Booking rules')}</CardTitle></CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <Field label={t('Booking mode')} error={e.booking_mode}>
                        <select className={selectClass} value={d.booking_mode} onChange={(ev) => form.setData('booking_mode', ev.target.value as typeof mode)}>
                            {modes.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                        </select>
                    </Field>
                    <Field label={t('Who chooses the resource')} error={e.resource_selection}>
                        <select className={selectClass} value={d.resource_selection} onChange={(ev) => form.setData('resource_selection', ev.target.value as typeof d.resource_selection)}>
                            {selections.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                    </Field>
                    {customerGroups.length > 0 && (
                        <Field label={t('Available to')} error={e.customer_group_ids}
                            hint={d.customer_group_ids.length ? t('Only members of the selected groups can see and book this offer.') : t('All customers. Select groups to limit access, e.g. members only.')}>
                            <MultiCheck options={customerGroups.map((g) => ({ value: g.id, label: g.name }))} value={d.customer_group_ids}
                                onChange={(v) => form.setData('customer_group_ids', v)} />
                        </Field>
                    )}
                    <SwitchRow checked={d.allow_guests && d.customer_group_ids.length === 0} onChange={(v) => form.setData('allow_guests', v)}
                        label={t('Guests can book')}
                        description={d.customer_group_ids.length ? t('Not possible when access is limited to customer groups.') : t('Without an account, with name, email and phone.')} />
                    <SwitchRow checked={d.requires_approval} onChange={(v) => form.setData('requires_approval', v)}
                        label={t('Requires approval')} description={t('Bookings wait for confirmation by staff.')} />
                    <Field label={t('Minimum notice (minutes)')} error={e.min_advance_minutes} hint={t('How long before the start a booking is still possible.')}>
                        <Input type="number" min={0} value={d.min_advance_minutes} onChange={(ev) => form.setData('min_advance_minutes', Number(ev.target.value))} />
                    </Field>
                    <Field label={t('Book at most (days ahead)')} error={e.max_advance_days}>
                        <Input type="number" min={0} value={d.max_advance_days} onChange={(ev) => form.setData('max_advance_days', Number(ev.target.value))} />
                    </Field>
                    <Field label={t('Free cancellation until (hours before)')} error={e.cancellation_hours} hint={t('Empty = customers can always cancel before the start.')}>
                        <Input type="number" min={0} value={d.cancellation_hours ?? ''} onChange={(ev) => form.setData('cancellation_hours', numberOrNull(ev.target.value))} />
                    </Field>
                    {(mode === 'timeslot' || mode === 'hourly') && (
                        <Field label={t('Max places per booking')} error={e.max_quantity_per_booking}>
                            <Input type="number" min={1} value={d.max_quantity_per_booking} onChange={(ev) => form.setData('max_quantity_per_booking', Number(ev.target.value))} />
                        </Field>
                    )}
                </CardContent>
            </Card>

            {(mode === 'service' || mode === 'hourly') && (
                <Card>
                    <CardHeader><CardTitle className="text-base">{mode === 'service' ? t('Services') : t('Hourly rental')}</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <Field label={t('Start time step (minutes)')} error={e.slot_interval_minutes} hint={t('E.g. 15 → 9:00, 9:15, 9:30 …')}>
                            <Input type="number" min={5} step={5} value={d.slot_interval_minutes} onChange={(ev) => form.setData('slot_interval_minutes', Number(ev.target.value))} />
                        </Field>
                        {mode === 'hourly' && (
                            <>
                                <Field label={t('Minimum duration (minutes)')} error={e.min_duration_minutes}>
                                    <Input type="number" min={5} value={d.min_duration_minutes ?? ''} onChange={(ev) => form.setData('min_duration_minutes', numberOrNull(ev.target.value))} />
                                </Field>
                                <Field label={t('Maximum duration (minutes)')} error={e.max_duration_minutes}>
                                    <Input type="number" min={5} value={d.max_duration_minutes ?? ''} onChange={(ev) => form.setData('max_duration_minutes', numberOrNull(ev.target.value))} />
                                </Field>
                            </>
                        )}
                    </CardContent>
                </Card>
            )}

            {mode === 'nightly' && (
                <Card>
                    <CardHeader><CardTitle className="text-base">{t('Days / nights')}</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <Field label={t('Charged per')} error={e.nightly_unit}>
                            <select className={selectClass} value={d.nightly_unit} onChange={(ev) => form.setData('nightly_unit', ev.target.value as 'night' | 'day')}>
                                <option value="night">{t('night (accommodation)')}</option>
                                <option value="day">{t('day (rental)')}</option>
                            </select>
                        </Field>
                        <Field label={d.nightly_unit === 'night' ? t('Check-in from') : t('Pick-up time')} error={e.check_in_time}>
                            <Input type="time" value={d.check_in_time} onChange={(ev) => form.setData('check_in_time', ev.target.value)} />
                        </Field>
                        <Field label={d.nightly_unit === 'night' ? t('Check-out by') : t('Return time')} error={e.check_out_time}>
                            <Input type="time" value={d.check_out_time} onChange={(ev) => form.setData('check_out_time', ev.target.value)} />
                        </Field>
                        <Field label={d.nightly_unit === 'night' ? t('Minimum nights') : t('Minimum days')} error={e.min_units}>
                            <Input type="number" min={1} value={d.min_units} onChange={(ev) => form.setData('min_units', Number(ev.target.value))} />
                        </Field>
                        <Field label={d.nightly_unit === 'night' ? t('Maximum nights') : t('Maximum days')} error={e.max_units}>
                            <Input type="number" min={1} value={d.max_units ?? ''} onChange={(ev) => form.setData('max_units', numberOrNull(ev.target.value))} />
                        </Field>
                    </CardContent>
                </Card>
            )}

            <div className="flex items-center justify-between">
                <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                <Button type="button" variant="ghost" className="text-destructive" onClick={destroy}>{t('Delete offer')}</Button>
            </div>
        </form>
    );
}
