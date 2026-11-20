export type Permission =
    | 'bookings.view' | 'bookings.manage' | 'bookings.create'
    | 'offer.manage' | 'closures.manage' | 'messages.send' | 'content.manage' | 'users.manage' | 'roles.manage' | 'settings.manage';
export type BookingMode = 'timeslot' | 'service' | 'nightly' | 'hourly';
export type ResourceSelection = 'customer' | 'auto' | 'staff';
export type BookingStatus = 'pending' | 'confirmed' | 'cancelled' | 'rejected' | 'completed' | 'no_show';

export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    /** Ime vloge osebja; stranka je nima. */
    role: string | null;
    is_staff: boolean;
    is_admin: boolean;
    permissions: Permission[];
    email_verified_at?: string | null;
}

export interface MenuLink {
    id: number;
    label: string;
    href: string;
    external: boolean;
    new_tab: boolean;
}

export interface Business {
    name: string;
    tagline: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    website: string | null;
    currency: string;
    allow_registration: boolean;
}

export interface TypeLink {
    id: number;
    name: string;
    slug: string;
    icon: string;
    color: string | null;
}

export interface Option<T = string> {
    value: T;
    label: string;
}

export interface Property {
    label: string;
    value: string;
}

export interface ResourceTypeData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string;
    color: string | null;
    booking_mode: BookingMode;
    resource_label: string;
    resource_selection: ResourceSelection;
    allow_guests: boolean;
    requires_approval: boolean;
    max_quantity_per_booking: number;
    cancellation_hours: number | null;
    reminder_hours: number | null;
    min_advance_minutes: number;
    max_advance_days: number;
    slot_interval_minutes: number;
    min_duration_minutes: number | null;
    max_duration_minutes: number | null;
    nightly_unit: 'night' | 'day';
    check_in_time: string;
    check_out_time: string;
    min_units: number;
    max_units: number | null;
    is_active: boolean;
}

export interface ResourceData {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    color: string | null;
    price: number | null;
    capacity: number;
    max_guests: number | null;
    properties: Property[];
}

export interface ServiceData {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: number | null;
    resource_ids: number[];
}

export interface Booking {
    id: number;
    reference: string;
    title: string;
    status: BookingStatus;
    status_label: string;
    starts_at: string;
    ends_at: string;
    quantity: number;
    total_price: number | null;
    notes: string | null;
    customer_name: string;
    customer_email: string;
    customer_phone: string | null;
    created_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    type: {
        id: number;
        name: string;
        slug: string;
        icon: string;
        color: string | null;
        booking_mode: BookingMode;
        resource_label: string;
        nightly_unit: 'night' | 'day';
    } | null;
    resource: { id: number; name: string; color: string | null } | null;
    service: { id: number; name: string } | null;
    timeslot: { id: number; name: string } | null;
    // Samo za osebje
    internal_notes?: string | null;
    user?: { id: number; name: string; email: string } | null;
    created_by?: string | null;
    confirmed_at?: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: { user: User | null };
    business: Business;
    bookableTypes: TypeLink[];
    pendingBookings: number;
    unreadMessages: number;
    menu: { header: MenuLink[]; footer: MenuLink[] };
    flash: { success?: string | null; error?: string | null };
    locale: string;
    availableLocales: string[];
    translations: Record<string, string>;
};
