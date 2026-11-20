import { useEffect, useMemo, useState } from 'react';
import { Clock, Loader2 } from 'lucide-react';
import ChoiceChip from '@/Components/booking/ChoiceChip';
import MonthGrid from '@/Components/booking/MonthGrid';
import { useAvailability } from '@/Components/booking/useAvailability';
import { PickerProps } from '@/Components/booking/types';
import { endOfMonth, formatLongDate, parseDate, startOfMonth, toIsoDate } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface DayData {
    date: string;
    slots: { time: string; resource_ids: number[] }[];
}

/** Storitve: izbira storitve, izvajalca (ali kdorkoli), dneva in prostega začetka. */
export default function ServicePicker({ type, resources, services, today, lastBookableDate, onChange, reloadKey }: PickerProps) {
    const { t, money, locale } = useTranslation();
    const [serviceId, setServiceId] = useState<number | null>(services.length === 1 ? services[0].id : null);
    const [resourceId, setResourceId] = useState<number | null>(null);
    const [month, setMonth] = useState(() => startOfMonth(parseDate(today)));
    const [date, setDate] = useState<string | null>(null);
    const [time, setTime] = useState<string | null>(null);

    const service = services.find((s) => s.id === serviceId) ?? null;
    const mustPickResource = type.resource_selection === 'customer';
    const performers = useMemo(
        () => (service ? resources.filter((r) => service.resource_ids.length === 0 || service.resource_ids.includes(r.id)) : []),
        [service, resources],
    );

    const from = toIsoDate(month < parseDate(today) ? parseDate(today) : month);
    const monthEnd = endOfMonth(month);
    const to = toIsoDate(monthEnd > parseDate(lastBookableDate) ? parseDate(lastBookableDate) : monthEnd);
    const ready = service && (!mustPickResource || resourceId);

    const { data, loading } = useAvailability<{ days: DayData[] }>(
        type.slug,
        ready && from <= to ? { service_id: service!.id, resource_id: resourceId, from, to } : null,
        reloadKey,
    );

    const days = useMemo(() => Object.fromEntries((data?.days ?? []).map((d) => [d.date, d.slots])), [data]);
    const slots = date ? days[date] ?? [] : [];

    // Izbrana ura ni več prosta (po ponovnem nalaganju)
    useEffect(() => {
        if (time && date && data && !slots.find((s) => s.time === time)) setTime(null);
    }, [data]);

    useEffect(() => {
        if (!service || !date || !time) {
            onChange(null);
            return;
        }
        const performer = resources.find((r) => r.id === resourceId);
        onChange({
            payload: { service_id: service.id, resource_id: resourceId, date, time },
            summary: {
                lines: [
                    `${service.name} (${service.duration_minutes} min)`,
                    `${formatLongDate(date, locale)}, ${time}`,
                    performer ? `${type.resource_label}: ${performer.name}` : t('Any available'),
                ],
                price: service.price,
            },
        });
    }, [serviceId, resourceId, date, time]);

    const reset = () => { setDate(null); setTime(null); };

    return (
        <div className="space-y-6">
            <section>
                <h3 className="mb-2 text-sm font-semibold">1. {t('Service')}</h3>
                <div className="grid gap-2 sm:grid-cols-2">
                    {services.map((s) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => { setServiceId(s.id); setResourceId(null); reset(); }}
                            className={cn('rounded-lg border bg-card p-3 text-left transition', s.id === serviceId ? 'border-primary ring-2 ring-primary/30' : 'hover:border-primary/50')}
                        >
                            <span className="flex items-start justify-between gap-2">
                                <span className="font-medium">{s.name}</span>
                                {s.price !== null && <span className="text-sm font-semibold">{money(s.price)}</span>}
                            </span>
                            <span className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground"><Clock className="h-3 w-3" />{s.duration_minutes} min</span>
                            {s.description && <span className="mt-1 block text-xs text-muted-foreground">{s.description}</span>}
                        </button>
                    ))}
                </div>
                {services.length === 0 && <p className="text-sm text-muted-foreground">{t('No services are available yet.')}</p>}
            </section>

            {service && performers.length > 0 && (
                <section>
                    <h3 className="mb-2 text-sm font-semibold">2. {type.resource_label}</h3>
                    <div className="flex flex-wrap gap-2">
                        {!mustPickResource && (
                            <ChoiceChip selected={resourceId === null} onClick={() => { setResourceId(null); reset(); }}>{t('Any available')}</ChoiceChip>
                        )}
                        {performers.map((r) => (
                            <ChoiceChip key={r.id} selected={r.id === resourceId} onClick={() => { setResourceId(r.id); reset(); }}>{r.name}</ChoiceChip>
                        ))}
                    </div>
                </section>
            )}

            {ready && (
                <section>
                    <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold">
                        3. {t('Date and time')}
                        {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
                    </h3>
                    <div className="grid gap-6 md:grid-cols-[minmax(0,320px)_1fr]">
                        <MonthGrid
                            month={month}
                            onMonthChange={(m) => { setMonth(m); }}
                            minMonth={parseDate(today)}
                            maxMonth={parseDate(lastBookableDate)}
                            loading={loading}
                            onSelect={(iso) => { setDate(iso); setTime(null); }}
                            dayState={(iso) => {
                                const has = (days[iso]?.length ?? 0) > 0;
                                return { disabled: !has, available: has, selected: iso === date };
                            }}
                        />
                        <div>
                            {!date && <p className="text-sm text-muted-foreground">{t('Choose a day with free times (marked with a dot).')}</p>}
                            {date && (
                                <>
                                    <p className="mb-2 text-sm font-medium">{formatLongDate(date, locale)}</p>
                                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                        {slots.map((s) => (
                                            <ChoiceChip key={s.time} selected={s.time === time} onClick={() => setTime(s.time)} className="tabular-nums">{s.time}</ChoiceChip>
                                        ))}
                                    </div>
                                    {slots.length === 0 && <p className="text-sm text-muted-foreground">{t('No free times on this day.')}</p>}
                                </>
                            )}
                        </div>
                    </div>
                </section>
            )}
        </div>
    );
}
