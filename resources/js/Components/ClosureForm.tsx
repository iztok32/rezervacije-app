import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useEffect, useState } from 'react';
import { AlertTriangle } from 'lucide-react';
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

interface Affected {
    count: number;
    can_cancel: boolean;
    bookings: { reference: string; title: string; when: string; customer: string }[];
}

/**
 * Dodajanje zaprtja: celodnevno ali v urah, za vir, tip ali celo podjetje.
 * Pokaže rezervacije v tem obdobju in jih po želji odpove z obvestilom strankam.
 */
export default function ClosureForm({ typeId, types, resources }: Props) {
    const { t, tc } = useTranslation();
    const form = useForm({
        resource_type_id: typeId ?? null as number | null,
        resource_id: null as number | null,
        all_day: true,
        starts_at: '',
        ends_at: '',
        reason: '',
        cancel_bookings: false,
    });
    const d = form.data;
    const visibleResources = d.resource_type_id ? resources.filter((r) => !r.resource_type_id || r.resource_type_id === d.resource_type_id) : resources;
    const [affected, setAffected] = useState<Affected | null>(null);

    useEffect(() => {
        if (!d.starts_at || !d.ends_at || d.ends_at < d.starts_at) {
            setAffected(null);
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            axios.get<Affected>(route('admin.closures.affected'), {
                params: {
                    resource_type_id: d.resource_type_id ?? undefined,
                    resource_id: d.resource_id ?? undefined,
                    all_day: d.all_day ? 1 : 0,
                    starts_at: d.starts_at,
                    ends_at: d.ends_at,
                },
                signal: controller.signal,
            }).then((res) => setAffected(res.data)).catch(() => undefined);
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [d.resource_type_id, d.resource_id, d.all_day, d.starts_at, d.ends_at]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (d.cancel_bookings && affected?.count && !confirm(tc('Cancel :count bookings and notify the customers?', affected.count))) return;
        form.post(route('admin.closures.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('starts_at', 'ends_at', 'reason', 'resource_id', 'cancel_bookings');
                setAffected(null);
            },
        });
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
            <Field label={t('Reason')} error={form.errors.reason} className="md:col-span-2" hint={d.cancel_bookings ? t('The reason is included in the cancellation email.') : undefined}>
                <Input value={d.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder={t('e.g. Holiday, maintenance, public holiday')} />
            </Field>

            {affected && affected.count > 0 && (
                <div className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm md:col-span-2 dark:border-amber-500/40 dark:bg-amber-500/10">
                    <p className="flex items-center gap-2 font-medium text-amber-900 dark:text-amber-200">
                        <AlertTriangle className="h-4 w-4" />
                        {tc('This period affects :count active booking.', affected.count)}
                    </p>
                    <ul className="space-y-0.5 text-xs text-amber-900/80 dark:text-amber-100/80">
                        {affected.bookings.map((b) => <li key={b.reference}>{b.when} · {b.title} · {b.customer}</li>)}
                        {affected.count > affected.bookings.length && <li>…</li>}
                    </ul>
                    {affected.can_cancel ? (
                        <SwitchRow checked={d.cancel_bookings} onChange={(v) => form.setData('cancel_bookings', v)}
                            label={t('Cancel these bookings and notify the customers')}
                            description={t('Otherwise the bookings stay as they are and you contact the customers yourself.')} />
                    ) : (
                        <p className="text-xs text-amber-900/80 dark:text-amber-100/80">{t('The bookings stay as they are. Contact the customers or ask someone who manages bookings.')}</p>
                    )}
                </div>
            )}

            <div className="md:col-span-2">
                <Button type="submit" variant={d.cancel_bookings ? 'destructive' : 'default'} disabled={form.processing}>
                    {d.cancel_bookings && affected?.count ? tc('Add closure and cancel :count bookings', affected.count) : t('Add closure')}
                </Button>
            </div>
        </form>
    );
}
