import { intlLocale } from '@/lib/i18n';

/**
 * Datumi so na strežniku in v vmesniku v lokalnem času podjetja; ISO nizi brez časovnega
 * pasu ("2026-11-24", "2026-11-24T17:00") se zato obravnavajo kot lokalni.
 */

export function parseDate(value: string): Date {
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
}

export function parseDateTime(value: string): Date {
    const [date, time = '00:00'] = value.split('T');
    const d = parseDate(date);
    const [h, min] = time.split(':').map(Number);
    d.setHours(h, min, 0, 0);
    return d;
}

export function toIsoDate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function addDays(date: Date, days: number): Date {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
}

export function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

export function endOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

/** Ponedeljek v tednu datuma. */
export function startOfWeek(date: Date): Date {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const day = (d.getDay() + 6) % 7;
    d.setDate(d.getDate() - day);
    return d;
}

export function daysBetween(from: string, to: string): number {
    return Math.round((parseDate(to).getTime() - parseDate(from).getTime()) / 86400000);
}

export function minutesOf(time: string): number {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
}

export function timeOf(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

export function formatDate(value: string | Date, locale: string, options: Intl.DateTimeFormatOptions = {}): string {
    const date = typeof value === 'string' ? (value.includes('T') ? parseDateTime(value) : parseDate(value)) : value;
    return new Intl.DateTimeFormat(intlLocale(locale), { day: 'numeric', month: 'numeric', year: 'numeric', ...options }).format(date);
}

export function formatLongDate(value: string | Date, locale: string): string {
    const text = formatDate(value, locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    // samo prva črka; CSS capitalize bi povečal tudi ime meseca ("Petek, 20. November")
    return text.charAt(0).toLocaleUpperCase() + text.slice(1);
}

export function formatTime(value: string): string {
    return value.includes('T') ? value.split('T')[1].slice(0, 5) : value.slice(0, 5);
}

export function weekdayNames(locale: string, format: 'short' | 'long' = 'short'): string[] {
    // 2024-01-01 je ponedeljek
    return Array.from({ length: 7 }, (_, i) =>
        new Intl.DateTimeFormat(intlLocale(locale), { weekday: format }).format(new Date(2024, 0, 1 + i)),
    );
}

/** Opis obdobja rezervacije, npr. "tor, 24. 11. 2026, 17:00–18:00". */
export function formatRange(starts: string, ends: string, locale: string): string {
    const sameDay = starts.slice(0, 10) === ends.slice(0, 10);
    if (sameDay) {
        return `${formatDate(starts, locale, { weekday: 'short' })}, ${formatTime(starts)}–${formatTime(ends)}`;
    }
    return `${formatDate(starts, locale, { weekday: 'short' })} ${formatTime(starts)} – ${formatDate(ends, locale, { weekday: 'short' })} ${formatTime(ends)}`;
}
