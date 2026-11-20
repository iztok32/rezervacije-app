import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Clock, Pencil, Plus, Trash2 } from 'lucide-react';
import Field from '@/Components/Field';
import EmptyState from '@/Components/EmptyState';
import { MultiCheck, numberOrNull, SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import { AdminService, EditProps } from './types';

/** Storitve s trajanjem (način "service"). */
export default function ServicesTab(props: EditProps) {
    const { type, services, resources } = props;
    const { t, money } = useTranslation();
    const [editing, setEditing] = useState<AdminService | 'new' | null>(null);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{t('Services customers can choose, with duration and price.')}</p>
                <Button size="sm" onClick={() => setEditing('new')}><Plus className="mr-1.5 h-4 w-4" />{t('Add service')}</Button>
            </div>

            {services.length === 0 ? <EmptyState title={t('No services yet.')} /> : (
                <div className="divide-y rounded-lg border bg-card">
                    {services.map((s) => (
                        <div key={s.id} className="flex items-center gap-3 p-3">
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    {s.name}
                                    {!s.is_active && <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-normal text-muted-foreground">{t('Inactive')}</span>}
                                </p>
                                <p className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                    <span className="inline-flex items-center gap-1"><Clock className="h-3 w-3" />{s.duration_minutes} min{s.buffer_minutes > 0 && ` + ${s.buffer_minutes} min`}</span>
                                    {s.price !== null && <span>{money(s.price)}</span>}
                                    <span>{s.resource_ids.length === 0 ? t('All') : resources.filter((r) => s.resource_ids.includes(r.id)).map((r) => r.name).join(', ')}</span>
                                </p>
                            </div>
                            <Button size="icon" variant="ghost" onClick={() => setEditing(s)}><Pencil className="h-4 w-4" /></Button>
                            <Button size="icon" variant="ghost" onClick={() => confirm(t('Delete :name?', { name: s.name })) && router.delete(route('admin.services.destroy', s.id), { preserveScroll: true })}>
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}

            {editing && <ServiceDialog type={type} resources={resources} service={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </div>
    );
}

function ServiceDialog({ type, resources, service, onClose }: Pick<EditProps, 'type' | 'resources'> & { service: AdminService | null; onClose: () => void }) {
    const { t } = useTranslation();
    const form = useForm({
        name: service?.name ?? '',
        description: service?.description ?? '',
        duration_minutes: service?.duration_minutes ?? 30,
        buffer_minutes: service?.buffer_minutes ?? 0,
        price: service?.price ?? null as number | null,
        is_active: service?.is_active ?? true,
        resource_ids: service?.resource_ids ?? [] as number[],
    });
    const d = form.data;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (service) form.put(route('admin.services.update', service.id), options);
        else form.post(route('admin.services.store', type.slug), options);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader><DialogTitle>{service ? service.name : t('New service')}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name} required><Input value={d.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus /></Field>
                    <Field label={t('Description')} error={form.errors.description}><Textarea rows={2} value={d.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label={t('Duration (min)')} error={form.errors.duration_minutes} required>
                            <Input type="number" min={5} step={5} value={d.duration_minutes} onChange={(e) => form.setData('duration_minutes', Number(e.target.value))} />
                        </Field>
                        <Field label={t('Break after (min)')} error={form.errors.buffer_minutes} hint={t('Cleaning, preparation.')}>
                            <Input type="number" min={0} step={5} value={d.buffer_minutes} onChange={(e) => form.setData('buffer_minutes', Number(e.target.value))} />
                        </Field>
                        <Field label={t('Price')} error={form.errors.price}>
                            <Input type="number" min={0} step="0.01" value={d.price ?? ''} onChange={(e) => form.setData('price', numberOrNull(e.target.value))} />
                        </Field>
                    </div>
                    <Field label={t('Performed by')} hint={t('None selected = everyone.')}>
                        <MultiCheck options={resources.map((r) => ({ value: r.id, label: r.name }))} value={d.resource_ids} onChange={(v) => form.setData('resource_ids', v)} />
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
