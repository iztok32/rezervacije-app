import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Copy, Plus, X } from 'lucide-react';
import { selectClass } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { weekdayNames } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { EditProps, WorkingHourRow } from './types';

type Interval = { start_time: string; end_time: string };

/**
 * Delovni čas: privzeti za cel tip ali lasten za posamezen vir (npr. frizerka, ki dela
 * samo popoldne). Vir brez lastnega urnika uporablja privzetega.
 */
export default function HoursTab({ type, resources, workingHours }: EditProps) {
    const { t, locale } = useTranslation();
    const days = weekdayNames(locale, 'long');
    const [scope, setScope] = useState<number | null>(null);
    const [week, setWeek] = useState<Interval[][]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const rowsFor = (rid: number | null) => workingHours.filter((h) => h.resource_id === rid);
    const hasOwn = (rid: number) => rowsFor(rid).length > 0;

    useEffect(() => {
        const rows = rowsFor(scope);
        setWeek(Array.from({ length: 7 }, (_, i) => rows.filter((h) => h.weekday === i + 1).map(({ start_time, end_time }) => ({ start_time, end_time }))));
        setErrors({});
    }, [scope, workingHours]);

    const update = (day: number, idx: number, key: keyof Interval, value: string) =>
        setWeek(week.map((list, d) => (d === day ? list.map((iv, i) => (i === idx ? { ...iv, [key]: value } : iv)) : list)));
    const add = (day: number) => {
        const last = week[day][week[day].length - 1];
        setWeek(week.map((list, d) => (d === day ? [...list, last ? { start_time: last.end_time, end_time: '20:00' } : { start_time: '08:00', end_time: '16:00' }] : list)));
    };
    const remove = (day: number, idx: number) => setWeek(week.map((list, d) => (d === day ? list.filter((_, i) => i !== idx) : list)));
    const copyToWeekdays = (day: number) => setWeek(week.map((list, d) => (d < 5 ? week[day].map((iv) => ({ ...iv })) : list)));

    const save = (hours: WorkingHourRow[] | null = null) => {
        const payload = hours ?? week.flatMap((list, d) => list.map((iv) => ({ weekday: d + 1, ...iv })));
        router.put(route('admin.working-hours.update', type.slug), { resource_id: scope, hours: payload } as never, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onError: setErrors,
            onSuccess: () => setErrors({}),
        });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <select className={`${selectClass} w-auto`} value={scope ?? ''} onChange={(e) => setScope(e.target.value ? Number(e.target.value) : null)}>
                    <option value="">{t('Default for all')}</option>
                    {resources.map((r) => <option key={r.id} value={r.id}>{r.name}{hasOwn(r.id) ? ` (${t('own hours')})` : ''}</option>)}
                </select>
                <p className="text-xs text-muted-foreground">
                    {scope === null
                        ? t('Used by every :resource without its own hours.', { resource: type.resource_label.toLowerCase() })
                        : hasOwn(scope) ? t('Own working hours of this resource.') : t('Uses default hours. Save to set its own hours.')}
                </p>
            </div>

            <div className="divide-y rounded-lg border bg-card">
                {days.map((name, d) => (
                    <div key={d} className="flex flex-col gap-2 p-3 sm:flex-row sm:items-start">
                        <div className="w-32 shrink-0 pt-2 text-sm font-medium capitalize">{name}</div>
                        <div className="flex-1 space-y-2">
                            {(week[d] ?? []).length === 0 && <p className="pt-2 text-sm text-muted-foreground">{t('Closed')}</p>}
                            {(week[d] ?? []).map((iv, i) => (
                                <div key={i} className="flex items-center gap-2">
                                    <Input type="time" className="w-32" value={iv.start_time} onChange={(e) => update(d, i, 'start_time', e.target.value)} />
                                    <span>–</span>
                                    <Input type="time" className="w-32" value={iv.end_time} onChange={(e) => update(d, i, 'end_time', e.target.value)} />
                                    <Button type="button" variant="ghost" size="icon" onClick={() => remove(d, i)}><X className="h-4 w-4" /></Button>
                                </div>
                            ))}
                        </div>
                        <div className="flex gap-1">
                            <Button type="button" variant="outline" size="sm" onClick={() => add(d)}><Plus className="mr-1 h-3.5 w-3.5" />{t('Interval')}</Button>
                            {d === 0 && (week[0] ?? []).length > 0 && (
                                <Button type="button" variant="ghost" size="sm" onClick={() => copyToWeekdays(0)} title={t('Copy to Tuesday–Friday')}><Copy className="h-3.5 w-3.5" /></Button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {Object.keys(errors).length > 0 && (
                <div className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                    {Object.values(errors).map((m, i) => <p key={i}>{m}</p>)}
                </div>
            )}

            <div className="flex gap-2">
                <Button onClick={() => save()} disabled={saving}>{t('Save working hours')}</Button>
                {scope !== null && hasOwn(scope) && (
                    <Button variant="ghost" onClick={() => save([])} disabled={saving}>{t('Use default hours')}</Button>
                )}
            </div>
        </div>
    );
}
