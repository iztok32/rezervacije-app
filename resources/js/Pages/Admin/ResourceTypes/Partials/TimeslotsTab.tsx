import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import Field from '@/Components/Field';
import EmptyState from '@/Components/EmptyState';
import { MultiCheck, numberOrNull, SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { formatDate, weekdayNames } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { AdminTimeslot, EditProps } from './types';

/** Ponavljajoči se termini s kapaciteto (način "timeslot"). */
export default function TimeslotsTab(props: EditProps) {
    const { type, timeslots, resources, staff } = props;
    const { t, money, locale } = useTranslation();
    const [editing, setEditing] = useState<AdminTimeslot | 'new' | null>(null);
    const days = weekdayNames(locale);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{t('Recurring sessions with a limited number of places.')}</p>
                <Button size="sm" onClick={() => setEditing('new')}><Plus className="mr-1.5 h-4 w-4" />{t('Add session')}</Button>
            </div>

            {timeslots.length === 0 ? <EmptyState title={t('No sessions yet.')} /> : (
                <div className="divide-y rounded-lg border bg-card">
                    {timeslots.map((s) => (
                        <div key={s.id} className="flex items-center gap-3 p-3">
                            <div className="w-24 shrink-0 font-semibold tabular-nums">{s.start_time}–{s.end_time}</div>
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    {s.name}
                                    {!s.is_active && <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-normal text-muted-foreground">{t('Inactive')}</span>}
                                </p>
                                <p className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                    <span className="capitalize">{s.weekdays.map((d) => days[d - 1]).join(', ')}</span>
                                    {s.capacity && <span className="inline-flex items-center gap-1"><Users className="h-3 w-3" />{s.capacity}</span>}
                                    {s.price !== null && <span>{money(s.price)}</span>}
                                    {(s.valid_from || s.valid_to) && <span>{s.valid_from ? formatDate(s.valid_from, locale) : '…'} – {s.valid_to ? formatDate(s.valid_to, locale) : '…'}</span>}
                                    {s.staff_ids.length > 0 && <span>{staff.filter((u) => s.staff_ids.includes(u.id)).map((u) => u.name).join(', ')}</span>}
                                </p>
                            </div>
                            <Button size="icon" variant="ghost" onClick={() => setEditing(s)}><Pencil className="h-4 w-4" /></Button>
                            <Button size="icon" variant="ghost" onClick={() => confirm(t('Delete :name?', { name: s.name })) && router.delete(route('admin.timeslots.destroy', s.id), { preserveScroll: true })}>
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}

            {editing && <TimeslotDialog {...props} timeslot={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </div>
    );
}

function TimeslotDialog({ type, resources, staff, timeslot, onClose }: EditProps & { timeslot: AdminTimeslot | null; onClose: () => void }) {
    const { t, locale } = useTranslation();
    const days = weekdayNames(locale, 'long');
    const form = useForm({
        name: timeslot?.name ?? '',
        description: timeslot?.description ?? '',
        weekdays: timeslot?.weekdays ?? [] as number[],
        start_time: timeslot?.start_time ?? '17:00',
        end_time: timeslot?.end_time ?? '18:00',
        valid_from: timeslot?.valid_from ?? '',
        valid_to: timeslot?.valid_to ?? '',
        capacity: timeslot?.capacity ?? null as number | null,
        price: timeslot?.price ?? null as number | null,
        is_active: timeslot?.is_active ?? true,
        resource_ids: timeslot?.resource_ids ?? [] as number[],
        staff_ids: timeslot?.staff_ids ?? [] as number[],
    });
    const d = form.data;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, valid_from: data.valid_from || null, valid_to: data.valid_to || null }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (timeslot) form.put(route('admin.timeslots.update', timeslot.id), options);
        else form.post(route('admin.timeslots.store', type.slug), options);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader><DialogTitle>{timeslot ? timeslot.name : t('New session')}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name} required><Input value={d.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus /></Field>
                    <Field label={t('Days of the week')} error={form.errors.weekdays} required>
                        <MultiCheck options={days.map((label, i) => ({ value: i + 1, label }))} value={d.weekdays} onChange={(v) => form.setData('weekdays', v)} />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('Start')} error={form.errors.start_time} required><Input type="time" value={d.start_time} onChange={(e) => form.setData('start_time', e.target.value)} /></Field>
                        <Field label={t('End')} error={form.errors.end_time} required><Input type="time" value={d.end_time} onChange={(e) => form.setData('end_time', e.target.value)} /></Field>
                        <Field label={t('Places')} error={form.errors.capacity} hint={t('Empty = unlimited.')}>
                            <Input type="number" min={1} value={d.capacity ?? ''} onChange={(e) => form.setData('capacity', numberOrNull(e.target.value))} />
                        </Field>
                        <Field label={t('Price per place')} error={form.errors.price}>
                            <Input type="number" min={0} step="0.01" value={d.price ?? ''} onChange={(e) => form.setData('price', numberOrNull(e.target.value))} />
                        </Field>
                        <Field label={t('Valid from')} error={form.errors.valid_from}><Input type="date" value={d.valid_from} onChange={(e) => form.setData('valid_from', e.target.value)} /></Field>
                        <Field label={t('Valid until')} error={form.errors.valid_to}><Input type="date" value={d.valid_to} onChange={(e) => form.setData('valid_to', e.target.value)} /></Field>
                    </div>
                    <Field label={t('Description')} error={form.errors.description}><Textarea rows={2} value={d.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>
                    {resources.length > 0 && (
                        <Field label={`${type.resource_label} — ${t('available in this session')}`} hint={t('None selected = all.')}>
                            <MultiCheck options={resources.map((r) => ({ value: r.id, label: r.name }))} value={d.resource_ids} onChange={(v) => form.setData('resource_ids', v)} />
                        </Field>
                    )}
                    <Field label={t('Led by')}>
                        <MultiCheck options={staff.map((s) => ({ value: s.id, label: s.name }))} value={d.staff_ids} onChange={(v) => form.setData('staff_ids', v)}
                            emptyHint={t('Add staff members under Users.')} />
                    </Field>
                    <SwitchRow checked={d.is_active} onChange={(v) => form.setData('is_active', v)} label={t('Active')} />
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>{t('Cancel')}</Button>
                        <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
