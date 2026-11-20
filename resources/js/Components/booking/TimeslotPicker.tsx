import { useEffect, useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, Loader2, UserRound, Users } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import ChoiceChip from '@/Components/booking/ChoiceChip';
import { useAvailability } from '@/Components/booking/useAvailability';
import { PickerProps } from '@/Components/booking/types';
import { addDays, formatDate, formatLongDate, parseDate, startOfWeek, toIsoDate } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Occurrence {
    key: string;
    timeslot_id: number;
    name: string;
    description: string | null;
    date: string;
    start: string;
    end: string;
    capacity: number | null;
    booked: number;
    remaining: number | null;
    price: number | null;
    status: 'open' | 'full' | 'closed' | 'past' | 'unavailable';
    staff: string[];
    mine: boolean;
    resources?: { id: number; name: string; available: boolean }[];
}

/** Termini s kapaciteto: tedenski pregled terminov, izbira termina in po potrebi vira. */
export default function TimeslotPicker({ type, today, lastBookableDate, onChange, reloadKey }: PickerProps) {
    const { t, tc, money, locale } = useTranslation();
    const [weekStart, setWeekStart] = useState(() => startOfWeek(parseDate(today)));
    const [selectedKey, setSelectedKey] = useState<string | null>(null);
    const [resourceId, setResourceId] = useState<number | null>(null);
    const [quantity, setQuantity] = useState(1);

    const from = toIsoDate(weekStart);
    const to = toIsoDate(addDays(weekStart, 6));
    const { data, loading } = useAvailability<{ occurrences: Occurrence[] }>(type.slug, { from, to }, reloadKey);

    const occurrences = data?.occurrences ?? [];
    const selected = occurrences.find((o) => o.key === selectedKey) ?? null;
    const pickResource = type.resource_selection === 'customer' && (selected?.resources?.length ?? 0) > 0;

    // Po ponovnem nalaganju preveri, ali je izbira še veljavna
    useEffect(() => {
        if (selected && selected.status !== 'open') setSelectedKey(null);
        if (selected && resourceId && !selected.resources?.find((r) => r.id === resourceId && r.available)) setResourceId(null);
    }, [data]);

    useEffect(() => {
        if (!selected || (pickResource && !resourceId)) {
            onChange(null);
            return;
        }
        const resource = selected.resources?.find((r) => r.id === resourceId);
        onChange({
            payload: { timeslot_id: selected.timeslot_id, date: selected.date, resource_id: resourceId, quantity },
            summary: {
                lines: [
                    selected.name,
                    `${formatLongDate(selected.date, locale)}, ${selected.start}–${selected.end}`,
                    ...(resource ? [`${type.resource_label}: ${resource.name}`] : []),
                    ...(quantity > 1 ? [tc(':count places', quantity)] : []),
                ],
                price: selected.price !== null ? selected.price * quantity : null,
            },
        });
    }, [selectedKey, resourceId, quantity, data]);

    const byDay = useMemo(() => {
        const days: { date: string; items: Occurrence[] }[] = [];
        for (let i = 0; i < 7; i++) {
            const date = toIsoDate(addDays(weekStart, i));
            days.push({ date, items: occurrences.filter((o) => o.date === date) });
        }
        return days;
    }, [occurrences, weekStart]);

    const canPrev = weekStart > startOfWeek(parseDate(today));
    const canNext = addDays(weekStart, 7) <= parseDate(lastBookableDate);
    const maxQty = Math.min(type.max_quantity_per_booking, selected?.remaining ?? type.max_quantity_per_booking);

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between gap-2">
                <Button type="button" variant="outline" size="icon" disabled={!canPrev} onClick={() => setWeekStart(addDays(weekStart, -7))}>
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                <div className="flex items-center gap-2 text-sm font-medium">
                    {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
                    {formatDate(from, locale, { day: 'numeric', month: 'short', year: undefined })} – {formatDate(to, locale, { day: 'numeric', month: 'short', year: 'numeric' })}
                </div>
                <Button type="button" variant="outline" size="icon" disabled={!canNext} onClick={() => setWeekStart(addDays(weekStart, 7))}>
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>

            <div className={cn('space-y-3', loading && !data && 'opacity-50')}>
                {byDay.every((d) => d.items.length === 0) && !loading && (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">{t('No sessions this week.')}</p>
                )}
                {byDay.filter((d) => d.items.length > 0).map((day) => (
                    <div key={day.date}>
                        <p className="mb-1.5 text-sm font-semibold">{formatLongDate(day.date, locale)}</p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {day.items.map((o) => {
                                const open = o.status === 'open' && !o.mine;
                                const isSelected = o.key === selectedKey;
                                return (
                                    <button
                                        key={o.key}
                                        type="button"
                                        disabled={!open}
                                        onClick={() => { setSelectedKey(o.key); setResourceId(null); setQuantity(1); }}
                                        className={cn(
                                            'flex flex-col rounded-lg border bg-card p-3 text-left transition',
                                            isSelected ? 'border-primary ring-2 ring-primary/30' : open && 'hover:border-primary/50',
                                            !open && 'cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="font-semibold tabular-nums">{o.start}–{o.end}</span>
                                            <SlotStatus o={o} />
                                        </span>
                                        <span className="mt-0.5 text-sm">{o.name}</span>
                                        <span className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                            {o.capacity !== null && (
                                                <span className="inline-flex items-center gap-1"><Users className="h-3 w-3" />{o.booked}/{o.capacity}</span>
                                            )}
                                            {o.staff.length > 0 && <span className="inline-flex items-center gap-1"><UserRound className="h-3 w-3" />{o.staff.join(', ')}</span>}
                                            {o.price !== null && <span>{money(o.price)}</span>}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            {selected && pickResource && (
                <div>
                    <p className="mb-2 text-sm font-semibold">{t('Choose')}: {type.resource_label.toLowerCase()}</p>
                    <div className="flex flex-wrap gap-2">
                        {selected.resources!.map((r) => (
                            <ChoiceChip key={r.id} selected={r.id === resourceId} disabled={!r.available} onClick={() => setResourceId(r.id)}
                                title={r.available ? undefined : t('Not available')}>
                                {r.name}
                            </ChoiceChip>
                        ))}
                    </div>
                </div>
            )}

            {selected && type.max_quantity_per_booking > 1 && (
                <div>
                    <p className="mb-2 text-sm font-semibold">{t('Number of places')}</p>
                    <div className="flex flex-wrap gap-2">
                        {Array.from({ length: Math.max(1, maxQty) }, (_, i) => i + 1).map((n) => (
                            <ChoiceChip key={n} selected={n === quantity} onClick={() => setQuantity(n)} className="min-w-10">{n}</ChoiceChip>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function SlotStatus({ o }: { o: Occurrence }) {
    const { t, tc } = useTranslation();
    const base = 'rounded px-1.5 py-0.5 text-[11px] font-medium';

    if (o.mine) return <span className={cn(base, 'bg-primary/15 text-primary')}>{t('Booked')}</span>;
    if (o.status === 'full') return <span className={cn(base, 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300')}>{t('Full')}</span>;
    if (o.status === 'closed') return <span className={cn(base, 'bg-muted text-muted-foreground')}>{t('Closed')}</span>;
    if (o.status !== 'open') return <span className={cn(base, 'bg-muted text-muted-foreground')}>{t('Not available')}</span>;
    if (o.remaining !== null && o.remaining <= 2) {
        return <span className={cn(base, 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300')}>{tc(':count places left', o.remaining)}</span>;
    }
    return <span className={cn(base, 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300')}>{t('Available')}</span>;
}
