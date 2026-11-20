import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ImageIcon, Pencil, Plus, Trash2, X } from 'lucide-react';
import Field from '@/Components/Field';
import EmptyState from '@/Components/EmptyState';
import { numberOrNull, selectClass, SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import { Property } from '@/types';
import { AdminResource, EditProps } from './types';

/** Viri tipa (konji, frizerji, avtomobili, sobe …). */
export default function ResourcesTab({ type, resources, staff }: EditProps) {
    const { t, money } = useTranslation();
    const [editing, setEditing] = useState<AdminResource | 'new' | null>(null);
    const mode = type.booking_mode;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{t('Each resource can be booked separately.')}</p>
                <Button size="sm" onClick={() => setEditing('new')}><Plus className="mr-1.5 h-4 w-4" />{t('Add')}: {type.resource_label.toLowerCase()}</Button>
            </div>

            {resources.length === 0 ? (
                <EmptyState title={t('No resources yet.')} />
            ) : (
                <div className="divide-y rounded-lg border bg-card">
                    {resources.map((r) => (
                        <div key={r.id} className="flex items-center gap-3 p-3">
                            {r.image_url
                                ? <img src={r.image_url} alt="" className="h-12 w-12 rounded-md object-cover" />
                                : <span className="flex h-12 w-12 items-center justify-center rounded-md bg-muted" style={{ background: r.color ? `${r.color}33` : undefined }}><ImageIcon className="h-4 w-4 text-muted-foreground" /></span>}
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    {r.name}
                                    {!r.is_active && <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-normal text-muted-foreground">{t('Inactive')}</span>}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {[
                                        r.price !== null && (mode === 'nightly' || mode === 'hourly') ? `${money(r.price)} / ${mode === 'hourly' ? 'h' : type.nightly_unit === 'night' ? t('night') : t('day')}` : null,
                                        r.max_guests ? `${t('up to :count', { count: r.max_guests })} ${t('persons')}` : null,
                                        r.capacity > 1 ? `${t('Capacity')}: ${r.capacity}` : null,
                                        staff.find((s) => s.id === r.user_id)?.name,
                                        ...r.properties.map((p) => `${p.label}: ${p.value}`),
                                    ].filter(Boolean).join(' · ')}
                                </p>
                            </div>
                            <Button size="icon" variant="ghost" onClick={() => setEditing(r)}><Pencil className="h-4 w-4" /></Button>
                            <Button size="icon" variant="ghost" onClick={() => confirm(t('Delete :name?', { name: r.name })) && router.delete(route('admin.resources.destroy', r.id), { preserveScroll: true })}>
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}

            {editing && <ResourceDialog {...{ type, staff }} resource={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </div>
    );
}

function ResourceDialog({ type, staff, resource, onClose }: Pick<EditProps, 'type' | 'staff'> & { resource: AdminResource | null; onClose: () => void }) {
    const { t } = useTranslation();
    const mode = type.booking_mode;
    const form = useForm({
        name: resource?.name ?? '',
        description: resource?.description ?? '',
        color: resource?.color ?? '',
        capacity: resource?.capacity ?? 1,
        max_guests: resource?.max_guests ?? null as number | null,
        price: resource?.price ?? null as number | null,
        user_id: resource?.user_id ?? null as number | null,
        properties: resource?.properties ?? [] as Property[],
        is_active: resource?.is_active ?? true,
        image: null as File | null,
        remove_image: false,
    });
    const d = form.data;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, color: data.color || null, is_active: data.is_active ? 1 : 0, remove_image: data.remove_image ? 1 : 0 }));
        const options = { preserveScroll: true, forceFormData: true, onSuccess: onClose };
        if (resource) form.post(route('admin.resources.update', resource.id), options);
        else form.post(route('admin.resources.store', type.slug), options);
    };

    const setProperty = (i: number, key: keyof Property, value: string) =>
        form.setData('properties', d.properties.map((p, j) => (j === i ? { ...p, [key]: value } : p)));

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader><DialogTitle>{resource ? resource.name : `${t('New')}: ${type.resource_label.toLowerCase()}`}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name} required><Input value={d.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus /></Field>
                    <Field label={t('Description')} error={form.errors.description}><Textarea rows={2} value={d.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {(mode === 'nightly' || mode === 'hourly') && (
                            <Field label={mode === 'hourly' ? t('Price per hour') : type.nightly_unit === 'night' ? t('Price per night') : t('Price per day')} error={form.errors.price}>
                                <Input type="number" min={0} step="0.01" value={d.price ?? ''} onChange={(e) => form.setData('price', numberOrNull(e.target.value))} />
                            </Field>
                        )}
                        {mode === 'nightly' && (
                            <Field label={t('Max persons')} error={form.errors.max_guests}>
                                <Input type="number" min={1} value={d.max_guests ?? ''} onChange={(e) => form.setData('max_guests', numberOrNull(e.target.value))} />
                            </Field>
                        )}
                        <Field label={t('Simultaneous bookings')} error={form.errors.capacity} hint={t('Usually 1.')}>
                            <Input type="number" min={1} value={d.capacity} onChange={(e) => form.setData('capacity', Number(e.target.value))} />
                        </Field>
                        <Field label={t('Linked staff member')} error={form.errors.user_id} hint={t('Receives emails about their bookings.')}>
                            <select className={selectClass} value={d.user_id ?? ''} onChange={(e) => form.setData('user_id', numberOrNull(e.target.value))}>
                                <option value="">—</option>
                                {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </Field>
                        <Field label={t('Colour')}>
                            <div className="flex gap-2">
                                <input type="color" value={d.color || '#3b5bdb'} onChange={(e) => form.setData('color', e.target.value)} className="h-9 w-12 cursor-pointer rounded border" />
                                {d.color && <Button type="button" variant="ghost" size="icon" onClick={() => form.setData('color', '')}><X className="h-4 w-4" /></Button>}
                            </div>
                        </Field>
                    </div>

                    <Field label={t('Properties shown to customers')} error={form.errors.properties}>
                        <div className="space-y-2">
                            {d.properties.map((p, i) => (
                                <div key={i} className="flex gap-2">
                                    <Input placeholder={t('e.g. Seats')} value={p.label} onChange={(e) => setProperty(i, 'label', e.target.value)} />
                                    <Input placeholder={t('e.g. 5')} value={p.value} onChange={(e) => setProperty(i, 'value', e.target.value)} />
                                    <Button type="button" variant="ghost" size="icon" onClick={() => form.setData('properties', d.properties.filter((_, j) => j !== i))}><X className="h-4 w-4" /></Button>
                                </div>
                            ))}
                            <Button type="button" variant="outline" size="sm" onClick={() => form.setData('properties', [...d.properties, { label: '', value: '' }])}>
                                <Plus className="mr-1 h-3.5 w-3.5" />{t('Add property')}
                            </Button>
                        </div>
                    </Field>

                    <Field label={t('Image')} error={form.errors.image}>
                        {resource?.image_url && !d.remove_image && !d.image && (
                            <div className="mb-2 flex items-center gap-2">
                                <img src={resource.image_url} alt="" className="h-16 w-16 rounded object-cover" />
                                <Button type="button" variant="ghost" size="sm" onClick={() => form.setData('remove_image', true)}>{t('Remove')}</Button>
                            </div>
                        )}
                        <Input type="file" accept="image/*" onChange={(e) => form.setData('image', e.target.files?.[0] ?? null)} />
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
