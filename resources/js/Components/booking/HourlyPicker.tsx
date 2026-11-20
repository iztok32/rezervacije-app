import { useEffect, useMemo, useState } from 'react';
import { Loader2 } from 'lucide-react';
import ChoiceChip from '@/Components/booking/ChoiceChip';
import MonthGrid from '@/Components/booking/MonthGrid';
import { useAvailability } from '@/Components/booking/useAvailability';
import { PickerProps } from '@/Components/booking/types';
import { formatLongDate, minutesOf, parseDate, startOfMonth, timeOf } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';

interface Start {
    time: string;
    max_end: string;
}

interface DayAvailability {
    date: string;
    step: number;
    min_duration: number;
    max_duration: number | null;
    resources: { id: number; name: string; price: number | null; starts: Start[] }[];
    any: Start[];
}

/** Urni najem: izbira vira (ali kateregakoli), dneva, začetka in konca. */
export default function HourlyPicker({ type, resources, today, lastBookableDate, onChange, reloadKey }: PickerProps) {
    const { t, money, locale } = useTranslation();
    const mustPick = type.resource_selection === 'customer';
    const [resourceId, setResourceId] = useState<number | null>(mustPick && resources.length === 1 ? resources[0].id : null);
    const [month, setMonth] = useState(() => startOfMonth(parseDate(today)));
    const [date, setDate] = useState<string>(today);
    const [start, setStart] = useState<string | null>(null);
    const [end, setEnd] = useState<string | null>(null);

    const ready = !mustPick || resourceId;
    const { data, loading } = useAvailability<DayAvailability>(type.slug, ready ? { date, resource_id: resourceId } : null, reloadKey);

    const starts: Start[] = useMemo(() => {
        if (!data) return [];
        return resourceId ? data.resources.find((r) => r.id === resourceId)?.starts ?? [] : data.any;
    }, [data, resourceId]);

    const current = starts.find((s) => s.time === start);
    const ends = useMemo(() => {
        if (!current || !data) return [];
        const list: string[] = [];
        const from = minutesOf(current.time);
        const max = current.max_end === '24:00' ? 24 * 60 : minutesOf(current.max_end);
        for (let m = from + data.min_duration; m <= max; m += data.step) {
            list.push(m === 24 * 60 ? '00:00' : timeOf(m));
        }
        return list;
    }, [current, data]);

    useEffect(() => {
        if (start && data && !current) { setStart(null); setEnd(null); }
        if (end && !ends.includes(end)) setEnd(null);
    }, [data, ends]);

    useEffect(() => {
        if (!start || !end || !data) {
            onChange(null);
            return;
        }
        const minutes = (end === '00:00' ? 24 * 60 : minutesOf(end)) - minutesOf(start);
        const resource = resourceId ? data.resources.find((r) => r.id === resourceId) : null;
        const price = resource?.price ?? null;
        onChange({
            payload: { resource_id: resourceId, date, start_time: start, end_time: end },
            summary: {
                lines: [
                    ...(resource ? [resource.name] : [t('Any available')]),
                    `${formatLongDate(date, locale)}, ${start}–${end === '00:00' ? '24:00' : end}`,
                    `${minutes / 60} h`,
                ],
                price: price !== null ? (price * minutes) / 60 : null,
            },
        });
    }, [start, end, date, resourceId, data]);

    return (
        <div className="space-y-6">
            {resources.length > 1 && (
                <section>
                    <h3 className="mb-2 text-sm font-semibold">{type.resource_label}</h3>
                    <div className="flex flex-wrap gap-2">
                        {!mustPick && <ChoiceChip selected={resourceId === null} onClick={() => { setResourceId(null); setStart(null); }}>{t('Any available')}</ChoiceChip>}
                        {resources.map((r) => (
                            <ChoiceChip key={r.id} selected={r.id === resourceId} onClick={() => { setResourceId(r.id); setStart(null); setEnd(null); }}>
                                {r.name}{r.price !== null && <span className="ml-1.5 font-normal opacity-75">{money(r.price)}/h</span>}
                            </ChoiceChip>
                        ))}
                    </div>
                </section>
            )}

            {ready && (
                <section className="grid gap-6 md:grid-cols-[minmax(0,320px)_1fr]">
                    <MonthGrid
                        month={month}
                        onMonthChange={setMonth}
                        minMonth={parseDate(today)}
                        maxMonth={parseDate(lastBookableDate)}
                        onSelect={(iso) => { setDate(iso); setStart(null); setEnd(null); }}
                        dayState={(iso) => ({ selected: iso === date, disabled: iso < today || iso > lastBookableDate })}
                    />
                    <div className="space-y-4">
                        <p className="flex items-center gap-2 text-sm font-medium">
                            {formatLongDate(date, locale)}
                            {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
                        </p>
                        <div>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('From')}</p>
                            <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                                {starts.map((s) => (
                                    <ChoiceChip key={s.time} selected={s.time === start} onClick={() => { setStart(s.time); setEnd(null); }} className="tabular-nums">{s.time}</ChoiceChip>
                                ))}
                            </div>
                            {!loading && data && starts.length === 0 && <p className="text-sm text-muted-foreground">{t('No free times on this day.')}</p>}
                        </div>
                        {start && (
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('To')}</p>
                                <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                                    {ends.map((e) => (
                                        <ChoiceChip key={e} selected={e === end} onClick={() => setEnd(e)} className="tabular-nums">{e === '00:00' ? '24:00' : e}</ChoiceChip>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            )}
        </div>
    );
}
