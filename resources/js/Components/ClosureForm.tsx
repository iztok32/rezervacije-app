import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import Field from '@/Components/Field';
import { numberOrNull, selectClass, SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';

interface Props {
    /** Fiksen tip (zavihek tipa) ali izbira tipa (splošni seznam zaprtij). */
    typeId?: number | null;
    types?: { id: number; name: string }[];
    resources: { id: number; name: string; resource_type_id?: number }[];
}

/** Dodajanje zaprtja: celodnevno ali v urah, za vir, tip ali celo podjetje. */
export default function ClosureForm({ typeId, types, resources }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        resource_type_id: typeId ?? null as number | null,
        resource_id: null as number | null,
        all_day: true,
        starts_at: '',
        ends_at: '',
        reason: '',
    });
    const d = form.data;
    const visibleResources = d.resource_type_id ? resources.filter((r) => !r.resource_type_id || r.resource_type_id === d.resource_type_id) : resources;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('admin.closures.store'), { preserveScroll: true, onSuccess: () => form.reset('starts_at', 'ends_at', 'reason', 'resource_id') });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-4 md:grid-cols-2">
            {types && (
                <Field label={t('Applies to')} error={form.errors.resource_type_id}>
                    <select className={selectClass} value={d.resource_type_id ?? ''} onChange={(e) => form.setData({ ...d, resource_type_id: numberOrNull(e.target.value), resource_id: null })}>
                        <option value="">{t('Whole business')}</option>
                        {types.map((ty) => <option key={ty.id} value={ty.id}>{ty.name}</option>)}
                    </select>
                </Field>
            )}
            <Field label={t('Resource')} error={form.errors.resource_id}>
                <select className={selectClass} value={d.resource_id ?? ''} onChange={(e) => form.setData('resource_id', numberOrNull(e.target.value))}>
                    <option value="">{typeId || d.resource_type_id ? t('All resources of this type') : '—'}</option>
                    {visibleResources.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                </select>
            </Field>
            <div className="md:col-span-2">
                <SwitchRow checked={d.all_day} onChange={(v) => form.setData('all_day', v)} label={t('All day')} />
            </div>
            <Field label={d.all_day ? t('First day') : t('From')} error={form.errors.starts_at} required>
                <Input type={d.all_day ? 'date' : 'datetime-local'} value={d.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} />
            </Field>
            <Field label={d.all_day ? t('Last day') : t('To')} error={form.errors.ends_at} required>
                <Input type={d.all_day ? 'date' : 'datetime-local'} value={d.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} />
            </Field>
            <Field label={t('Reason')} error={form.errors.reason} className="md:col-span-2">
                <Input value={d.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder={t('e.g. Holiday, maintenance, public holiday')} />
            </Field>
            <div className="md:col-span-2"><Button type="submit" disabled={form.processing}>{t('Add closure')}</Button></div>
        </form>
    );
}
