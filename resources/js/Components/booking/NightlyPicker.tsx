import { useEffect, useMemo, useState } from 'react';
import { Loader2, Users } from 'lucide-react';
import ChoiceChip from '@/Components/booking/ChoiceChip';
import MonthGrid from '@/Components/booking/MonthGrid';
import { useAvailability } from '@/Components/booking/useAvailability';
import { PickerProps } from '@/Components/booking/types';
import { addDays, daysBetween, endOfMonth, formatLongDate, parseDate, startOfMonth, toIsoDate } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { ResourceData } from '@/types';

interface Availability {
    unit: 'night' | 'day';
    check_in: string;
    check_out: string;
    min_units: number;
    max_units: number | null;
    resources: (ResourceData & { blocked: string[] })[];
}

/**
 * Dnevi / nočitve: izbira vira (sobe, hiške, avta) in obdobja v dvomesečnem koledarju.
 * Pri nočitvah je dan odhoda lahko dan prihoda drugega gosta.
 */
export default function NightlyPicker({ type, resources, today, lastBookableDate, onChange, reloadKey }: PickerProps) {
    const { t, tc, money, locale } = useTranslation();
    const nights = type.nightly_unit === 'night';
    const [resourceId, setResourceId] = useState<number | null>(type.resource_selection === 'customer' && resources.length === 1 ? resources[0].id : null);
    const [month, setMonth] = useState(() => startOfMonth(parseDate(today)));
    const [start, setStart] = useState<string | null>(null);
    const [end, setEnd] = useState<string | null>(null);
    const [guests, setGuests] = useState(1);

    const mustPick = type.resource_selection === 'customer';
    const from = toIsoDate(month < parseDate(today) ? parseDate(today) : month);
    const to = toIsoDate(endOfMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1)));
    const { data, loading } = useAvailability<Availability>(type.slug, { from, to }, reloadKey);

    const blockedSets = useMemo(
        () => Object.fromEntries((data?.resources ?? []).map((r) => [r.id, new Set(r.blocked)])),
        [data],
    );
    const candidates = (data?.resources ?? []).filter((r) => (mustPick ? r.id === resourceId : !resourceId || r.id === resourceId));

    const units = (a: string, b: string) => (nights ? daysBetween(a, b) : daysBetween(a, b) + 1);
    const unitDates = (a: string, b: string) => {
        const n = units(a, b);
        return Array.from({ length: Math.max(0, n) }, (_, i) => toIsoDate(addDays(parseDate(a), i)));
    };
    const freeFor = (rid: number, dates: string[]) => dates.every((d) => !blockedSets[rid]?.has(d));
    const canStart = (iso: string) => iso >= today && iso <= lastBookableDate && candidates.some((r) => freeFor(r.id, [iso]));
    const validRange = (a: string, b: string) => {
        const n = units(a, b);
        if (n < 1 || n < type.min_units || (type.max_units && n > type.max_units)) return false;
        return candidates.some((r) => freeFor(r.id, unitDates(a, b)));
    };

    // Ob spremembi vira ali po ponovnem nalaganju preveri, ali je obdobje še prosto
    useEffect(() => {
        if (start && end && data && !validRange(start, end)) setEnd(null);
    }, [data, resourceId]);

    const freeResource = start && end ? candidates.find((r) => freeFor(r.id, unitDates(start, end))) : undefined;
    const chosen = resourceId ? resources.find((r) => r.id === resourceId) : freeResource;
    const maxGuests = chosen?.max_guests ?? Math.max(1, ...resources.map((r) => r.max_guests ?? 1));

    useEffect(() => {
        if (!start || !end || (mustPick && !resourceId) || !freeResource) {
            onChange(null);
            return;
        }
        const n = units(start, end);
        onChange({
            payload: { resource_id: resourceId, start_date: start, end_date: end, quantity: guests },
            summary: {
                lines: [
                    ...(chosen && resourceId ? [chosen.name] : []),
                    `${formatLongDate(start, locale)} ${type.check_in_time}`,
                    `→ ${formatLongDate(end, locale)} ${type.check_out_time}`,
                    nights ? tc(':count nights', n) : tc(':count days', n),
                    ...(type.max_quantity_per_booking > 1 || (chosen?.max_guests ?? 0) > 1 ? [tc(':count persons', guests)] : []),
                ],
                price: freeResource.price !== null ? freeResource.price * n : null,
            },
        });
    }, [start, end, resourceId, guests, data]);

    const select = (iso: string) => {
        if (start && !end && iso >= start && validRange(start, iso)) {
            setEnd(iso);
        } else if (canStart(iso)) {
            setStart(iso);
            setEnd(null);
        }
    };

    return (
        <div className="space-y-6">
            {resources.length > 1 && (
                <section>
                    <h3 className="mb-2 text-sm font-semibold">{type.resource_label}</h3>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {!mustPick && (
                            <button type="button" onClick={() => setResourceId(null)}
                                className={cn('rounded-lg border bg-card p-3 text-left text-sm font-medium', resourceId === null ? 'border-primary ring-2 ring-primary/30' : 'hover:border-primary/50')}>
                                {t('Any available')}
                            </button>
                        )}
                        {resources.map((r) => (
                            <button key={r.id} type="button" onClick={() => setResourceId(r.id)}
                                className={cn('flex gap-3 overflow-hidden rounded-lg border bg-card text-left transition', r.id === resourceId ? 'border-primary ring-2 ring-primary/30' : 'hover:border-primary/50')}>
                                {r.image_url && <img src={r.image_url} alt="" className="h-auto w-24 shrink-0 object-cover" />}
                                <span className="flex min-w-0 flex-1 flex-col p-3">
                                    <span className="flex items-start justify-between gap-2">
                                        <span className="font-medium">{r.name}</span>
                                        {r.price !== null && (
                                            <span className="whitespace-nowrap text-sm font-semibold">
                                                {money(r.price)}<span className="text-xs font-normal text-muted-foreground">/{nights ? t('night') : t('day')}</span>
                                            </span>
                                        )}
                                    </span>
                                    {r.max_guests && <span className="mt-0.5 inline-flex items-center gap-1 text-xs text-muted-foreground"><Users className="h-3 w-3" />{t('up to :count', { count: r.max_guests })}</span>}
                                    {r.properties.length > 0 && (
                                        <span className="mt-1 text-xs text-muted-foreground">{r.properties.map((p) => `${p.label}: ${p.value}`).join(' · ')}</span>
                                    )}
                                </span>
                            </button>
                        ))}
                    </div>
                </section>
            )}

            {(!mustPick || resourceId) && (
                <section>
                    <h3 className="mb-1 flex items-center gap-2 text-sm font-semibold">
                        {nights ? t('Arrival and departure') : t('Pick-up and return')}
                        {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
                    </h3>
                    <p className="mb-3 text-xs text-muted-foreground">
                        {!start || end
                            ? (nights ? t('Choose the arrival date.') : t('Choose the pick-up date.'))
                            : (nights ? t('Now choose the departure date.') : t('Now choose the return date.'))}
                        {' '}
                        {nights
                            ? t('Check-in from :in, check-out by :out.', { in: type.check_in_time, out: type.check_out_time })
                            : t('Pick-up at :in, return by :out.', { in: type.check_in_time, out: type.check_out_time })}
                        {type.min_units > 1 && ' ' + (nights ? tc('Minimum :count nights.', type.min_units) : tc('Minimum :count days.', type.min_units))}
                    </p>
                    <MonthGrid
                        months={2}
                        month={month}
                        onMonthChange={setMonth}
                        minMonth={parseDate(today)}
                        maxMonth={parseDate(lastBookableDate)}
                        loading={loading}
                        onSelect={select}
                        dayState={(iso) => {
                            const isEndCandidate = !!start && !end && iso >= start && validRange(start, iso);
                            const startable = canStart(iso);
                            const inRange = !!start && !!end && iso > start && iso < end;
                            return {
                                selected: iso === start || iso === end,
                                inRange,
                                disabled: !isEndCandidate && !startable,
                                blocked: !!data && iso >= today && !startable && !isEndCandidate,
                            };
                        }}
                    />
                </section>
            )}

            {start && end && maxGuests > 1 && (
                <section>
                    <h3 className="mb-2 text-sm font-semibold">{t('Number of persons')}</h3>
                    <div className="flex flex-wrap gap-2">
                        {Array.from({ length: maxGuests }, (_, i) => i + 1).map((n) => (
                            <ChoiceChip key={n} selected={n === guests} onClick={() => setGuests(n)} className="min-w-10">{n}</ChoiceChip>
                        ))}
                    </div>
                </section>
            )}

            {start && end && !freeResource && data && (
                <p className="text-sm text-destructive">{t('Not available for the selected dates.')}</p>
            )}
        </div>
    );
}
