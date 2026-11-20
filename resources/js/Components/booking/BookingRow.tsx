import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import StatusBadge from '@/Components/StatusBadge';
import { formatRange } from '@/lib/dates';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { Booking } from '@/types';

/** Vrstica rezervacije v seznamih. $staff: prikaže stranko in vodi v administracijo. */
export default function BookingRow({ booking, staff = false }: { booking: Booking; staff?: boolean }) {
    const { locale, money } = useTranslation();
    const href = staff ? route('admin.bookings.show', booking.reference) : route('bookings.show', booking.reference);

    return (
        <Link href={href} className="flex items-center gap-3 rounded-lg border bg-card p-3 transition hover:border-primary/40 hover:bg-accent/40">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                style={{ background: `${booking.type?.color ?? '#3b5bdb'}1a`, color: booking.type?.color ?? undefined }}>
                <TypeIcon name={booking.type?.icon} className="h-5 w-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="truncate font-medium">{staff ? booking.customer_name : booking.title}</span>
                    <StatusBadge status={booking.status} />
                </span>
                <span className="mt-0.5 block truncate text-sm text-muted-foreground">
                    {formatRange(booking.starts_at, booking.ends_at, locale)}
                    {staff && ` · ${booking.title}`}
                </span>
            </span>
            {booking.total_price !== null && <span className="hidden text-sm font-medium sm:block">{money(booking.total_price)}</span>}
            <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
        </Link>
    );
}
