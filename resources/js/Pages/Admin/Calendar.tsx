import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { addDays, formatDate, formatTime, parseDate, toIsoDate } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Booking } from '@/types';

interface Closure {
    id: number;
    starts_at: string;
    ends_at: string;
    reason: string | null;
    resource_id: number | null;
    scope: string | null;
}

interface Props {
    view: 'week' | 'timeline';
    from: string;
    to: string;
    date: string;
    typeId: number | null;
    types: { id: number; name: string; color: string | null; booking_mode: string }[];
    bookings: Booking[];
    resources: { id: number; name: string; color: string | null; type: string | null }[];
    closures: Closure[];
}

/** Koledar: tedenski pregled po dnevih ali časovnica po virih (14 dni). */
export default function Calendar(props: Props) {
    const { view, from, date, typeId, types } = props;
    const { t } = useTranslation();
    const step = view === 'week' ? 7 : 14;

    const go = (params: Partial<{ view: string; date: string; type: number | null }>) => {
        const query = { view, date, type: typeId, ...params };
        router.get(route('admin.calendar'), Object.fromEntries(Object.entries(query).filter(([, v]) => v !== null && v !== '')), { preserveScroll: true });
    };

    return (
        <AppLayout header={t('Calendar')}>
            <Head title={t('Calendar')} />
            <PageHeader
                title={t('Calendar')}
                actions={
                    <div className="flex rounded-md border p-0.5">
                        {(['week', 'timeline'] as const).map((v) => (
                            <button key={v} type="button" onClick={() => go({ view: v })}
                                className={cn('rounded px-3 py-1 text-sm', v === view ? 'bg-primary text-primary-foreground' : 'hover:bg-accent')}>
                                {v === 'week' ? t('Week') : t('By resource')}
                            </button>
                        ))}
                    </div>
                }
            />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <Button variant="outline" size="icon" onClick={() => go({ date: toIsoDate(addDays(parseDate(from), -step)) })}><ChevronLeft className="h-4 w-4" /></Button>
                <Button variant="outline" onClick={() => go({ date: toIsoDate(new Date()) })}>{t('Today')}</Button>
                <Button variant="outline" size="icon" onClick={() => go({ date: toIsoDate(addDays(parseDate(from), step)) })}><ChevronRight className="h-4 w-4" /></Button>
                <select className="h-9 rounded-md border border-input bg-background px-3 text-sm" value={typeId ?? ''} onChange={(e) => go({ type: e.target.value ? Number(e.target.value) : null })}>
                    <option value="">{t('All types')}</option>
                    {types.map((ty) => <option key={ty.id} value={ty.id}>{ty.name}</option>)}
                </select>
            </div>

            {view === 'week' ? <WeekView {...props} /> : <TimelineView {...props} />}
        </AppLayout>
    );
}

function color(b: Booking) {
    return b.resource?.color ?? b.type?.color ?? '#3b5bdb';
}

function WeekView({ from, bookings, closures }: Props) {
    const { t, locale } = useTranslation();
    const days = Array.from({ length: 7 }, (_, i) => toIsoDate(addDays(parseDate(from), i)));
    const todayIso = toIsoDate(new Date());

    return (
        <div className="grid gap-2 md:grid-cols-7">
            {days.map((day) => {
                const items = bookings.filter((b) => b.starts_at.slice(0, 10) <= day && b.ends_at.slice(0, 10) >= day && !(b.ends_at.slice(0, 10) === day && b.ends_at.slice(11) === '00:00' && b.starts_at.slice(0, 10) !== day));
                const closed = closures.filter((c) => c.starts_at.slice(0, 10) <= day && c.ends_at > day + 'T00:00');
                return (
                    <div key={day} className={cn('min-h-32 rounded-lg border bg-card p-2', day === todayIso && 'border-primary')}>
                        <p className={cn('mb-2 text-xs font-semibold capitalize', day === todayIso ? 'text-primary' : 'text-muted-foreground')}>
                            {formatDate(day, locale, { weekday: 'short', day: 'numeric', month: 'numeric', year: undefined })}
                        </p>
                        <div className="space-y-1.5">
                            {closed.map((c) => (
                                <p key={c.id} className="rounded bg-muted px-1.5 py-1 text-[11px] text-muted-foreground">⛔ {c.scope ?? t('Closed')}{c.reason ? `: ${c.reason}` : ''}</p>
                            ))}
                            {items.map((b) => {
                                const multiDay = b.starts_at.slice(0, 10) !== b.ends_at.slice(0, 10);
                                return (
                                    <Link key={b.id} href={route('admin.bookings.show', b.reference)}
                                        className={cn('block rounded border-l-4 bg-muted/50 px-1.5 py-1 text-xs hover:bg-accent', b.status === 'pending' && 'border-dashed')}
                                        style={{ borderLeftColor: color(b) }}>
                                        <span className="block font-medium tabular-nums">
                                            {multiDay
                                                ? (b.starts_at.slice(0, 10) === day ? `→ ${formatTime(b.starts_at)}` : b.ends_at.slice(0, 10) === day ? `${formatTime(b.ends_at)} →` : '↔')
                                                : `${formatTime(b.starts_at)}–${formatTime(b.ends_at)}`}
                                        </span>
                                        <span className="block truncate">{b.customer_name}</span>
                                        <span className="block truncate text-muted-foreground">{b.resource?.name ?? b.service?.name ?? b.timeslot?.name ?? b.type?.name}</span>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function TimelineView({ from, bookings, resources, closures }: Props) {
    const { t, locale } = useTranslation();
    const days = Array.from({ length: 14 }, (_, i) => toIsoDate(addDays(parseDate(from), i)));
    const start = parseDate(from).getTime();
    const span = 14 * 86400000;
    const pos = (iso: string) => {
        const [d, time = '00:00'] = iso.split('T');
        const [h, m] = time.split(':').map(Number);
        return ((parseDate(d).getTime() + (h * 60 + m) * 60000 - start) / span) * 100;
    };
    const unassigned = bookings.filter((b) => !b.resource);
    const rows = [
        ...resources.map((r) => ({ key: `r${r.id}`, label: r.name, sub: r.type, items: bookings.filter((b) => b.resource?.id === r.id), closures: closures.filter((c) => c.resource_id === r.id || c.resource_id === null) })),
        ...(unassigned.length ? [{ key: 'none', label: t('Not assigned'), sub: null, items: unassigned, closures: [] as Closure[] }] : []),
    ];

    return (
        <div className="overflow-x-auto rounded-lg border bg-card">
            <div className="min-w-[900px]">
                <div className="grid grid-cols-[180px_1fr] border-b text-xs text-muted-foreground">
                    <div className="p-2" />
                    <div className="grid grid-cols-14" style={{ gridTemplateColumns: 'repeat(14, minmax(0, 1fr))' }}>
                        {days.map((d) => (
                            <div key={d} className={cn('border-l p-1 text-center capitalize', d === toIsoDate(new Date()) && 'bg-primary/10 font-semibold text-primary')}>
                                {formatDate(d, locale, { weekday: 'short', day: 'numeric', month: undefined, year: undefined })}
                            </div>
                        ))}
                    </div>
                </div>
                {rows.map((row) => (
                    <div key={row.key} className="grid grid-cols-[180px_1fr] border-b last:border-b-0">
                        <div className="truncate p-2 text-sm">
                            <span className="font-medium">{row.label}</span>
                            {row.sub && <span className="block truncate text-xs text-muted-foreground">{row.sub}</span>}
                        </div>
                        <div className="relative h-12">
                            <div className="absolute inset-0 grid" style={{ gridTemplateColumns: 'repeat(14, minmax(0, 1fr))' }}>
                                {days.map((d) => <div key={d} className="border-l" />)}
                            </div>
                            {row.closures.map((c) => {
                                const left = Math.max(0, pos(c.starts_at));
                                const right = Math.min(100, pos(c.ends_at));
                                return right > left && (
                                    <div key={`c${c.id}`} title={c.reason ?? ''} className="absolute inset-y-1 rounded bg-[repeating-linear-gradient(45deg,transparent,transparent_4px,hsl(0_0%_50%/0.2)_4px,hsl(0_0%_50%/0.2)_8px)]"
                                        style={{ left: `${left}%`, width: `${right - left}%` }} />
                                );
                            })}
                            {row.items.map((b) => {
                                const left = Math.max(0, pos(b.starts_at));
                                const right = Math.min(100, pos(b.ends_at));
                                if (right <= left) return null;
                                return (
                                    <Link key={b.id} href={route('admin.bookings.show', b.reference)} title={`${b.customer_name} · ${b.title}`}
                                        className={cn('absolute inset-y-2 overflow-hidden truncate rounded px-1.5 text-[11px] leading-8 text-white shadow-sm hover:brightness-110', b.status === 'pending' && 'opacity-70 ring-2 ring-amber-400')}
                                        style={{ left: `${left}%`, width: `max(${right - left}%, 6px)`, background: color(b) }}>
                                        {b.customer_name}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                ))}
                {rows.length === 0 && <p className="p-6 text-center text-sm text-muted-foreground">{t('No resources.')}</p>}
            </div>
        </div>
    );
}
