import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { addDays, startOfMonth, startOfWeek, toIsoDate, weekdayNames } from '@/lib/dates';
import { intlLocale, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface DayState {
    disabled?: boolean;
    /** Dan je izbran (začetek/konec obdobja ali izbrani dan). */
    selected?: boolean;
    /** Dan je znotraj izbranega obdobja. */
    inRange?: boolean;
    /** Dan ima prosta mesta (zelena pika). */
    available?: boolean;
    /** Dan je zaseden (prečrtan). */
    blocked?: boolean;
    title?: string;
}

interface Props {
    month: Date;
    onMonthChange?: (month: Date) => void;
    dayState: (iso: string) => DayState;
    onSelect: (iso: string) => void;
    minMonth?: Date;
    maxMonth?: Date;
    /** Koliko mesecev prikazati drug ob drugem (na širokem zaslonu). */
    months?: 1 | 2;
    loading?: boolean;
}

/** Mesečni koledar z označevanjem razpoložljivosti in izbire (dan ali obdobje). */
export default function MonthGrid({ month, onMonthChange, dayState, onSelect, minMonth, maxMonth, months = 1, loading }: Props) {
    const { locale } = useTranslation();
    const first = startOfMonth(month);
    const canPrev = !minMonth || first > startOfMonth(minMonth);
    const lastShown = new Date(first.getFullYear(), first.getMonth() + months - 1, 1);
    const canNext = !maxMonth || lastShown < startOfMonth(maxMonth);

    return (
        <div className="relative">
            <div className="mb-2 flex items-center justify-between">
                <Button type="button" variant="ghost" size="icon" disabled={!canPrev} onClick={() => onMonthChange?.(new Date(first.getFullYear(), first.getMonth() - 1, 1))}>
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                <div className={cn('grid flex-1 gap-6 text-center text-sm font-semibold capitalize', months === 2 && 'md:grid-cols-2')}>
                    {Array.from({ length: months }, (_, i) => (
                        <span key={i} className={cn(i > 0 && 'hidden md:block')}>
                            {new Intl.DateTimeFormat(intlLocale(locale), { month: 'long', year: 'numeric' }).format(new Date(first.getFullYear(), first.getMonth() + i, 1))}
                        </span>
                    ))}
                </div>
                <Button type="button" variant="ghost" size="icon" disabled={!canNext} onClick={() => onMonthChange?.(new Date(first.getFullYear(), first.getMonth() + 1, 1))}>
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>

            <div className={cn('grid gap-6', months === 2 && 'md:grid-cols-2', loading && 'opacity-60')}>
                {Array.from({ length: months }, (_, i) => (
                    <Month key={i} month={new Date(first.getFullYear(), first.getMonth() + i, 1)} dayState={dayState} onSelect={onSelect}
                        className={cn(i > 0 && 'hidden md:block')} />
                ))}
            </div>
        </div>
    );
}

function Month({ month, dayState, onSelect, className }: { month: Date; dayState: Props['dayState']; onSelect: Props['onSelect']; className?: string }) {
    const { locale } = useTranslation();
    const gridStart = startOfWeek(month);
    const days = Array.from({ length: 42 }, (_, i) => addDays(gridStart, i));
    const todayIso = toIsoDate(new Date());

    return (
        <div className={className}>
            <div className="mb-1 grid grid-cols-7 text-center text-xs text-muted-foreground">
                {weekdayNames(locale).map((d) => <span key={d} className="py-1">{d}</span>)}
            </div>
            <div className="grid grid-cols-7 gap-y-1">
                {days.map((day) => {
                    const iso = toIsoDate(day);
                    const outside = day.getMonth() !== month.getMonth();
                    if (outside) return <span key={iso} />;

                    const s = dayState(iso);
                    return (
                        <button
                            key={iso}
                            type="button"
                            title={s.title}
                            disabled={s.disabled}
                            onClick={() => onSelect(iso)}
                            className={cn(
                                'relative mx-auto flex h-10 w-full items-center justify-center text-sm transition',
                                s.inRange && 'bg-primary/15',
                                !s.selected && !s.disabled && 'hover:bg-accent rounded-md',
                                s.selected && 'rounded-md bg-primary font-semibold text-primary-foreground',
                                s.disabled && 'cursor-not-allowed opacity-35',
                                s.blocked && 'line-through decoration-muted-foreground/60',
                                iso === todayIso && !s.selected && 'font-bold text-primary',
                            )}
                        >
                            {day.getDate()}
                            {s.available && !s.selected && <span className="absolute bottom-1 h-1 w-1 rounded-full bg-emerald-500" />}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
