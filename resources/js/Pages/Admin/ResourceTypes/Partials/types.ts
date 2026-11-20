import { Option, Property, ResourceTypeData } from '@/types';

export interface EditType extends ResourceTypeData {
    check_in_time_raw: string | null;
    check_out_time_raw: string | null;
    resource_label_raw: string | null;
}

export interface AdminResource {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    color: string | null;
    price: number | null;
    capacity: number;
    max_guests: number | null;
    properties: Property[];
    is_active: boolean;
    user_id: number | null;
}

export interface AdminService {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    buffer_minutes: number;
    price: number | null;
    is_active: boolean;
    resource_ids: number[];
}

export interface AdminTimeslot {
    id: number;
    name: string;
    description: string | null;
    weekdays: number[];
    start_time: string;
    end_time: string;
    valid_from: string | null;
    valid_to: string | null;
    capacity: number | null;
    price: number | null;
    is_active: boolean;
    resource_ids: number[];
    staff_ids: number[];
}

export interface WorkingHourRow {
    resource_id: number | null;
    weekday: number;
    start_time: string;
    end_time: string;
}

export interface ClosureRow {
    id: number;
    resource: string | null;
    starts_at: string;
    ends_at: string;
    reason: string | null;
}

export interface EditProps {
    type: EditType;
    resources: AdminResource[];
    services: AdminService[];
    timeslots: AdminTimeslot[];
    workingHours: WorkingHourRow[];
    closures: ClosureRow[];
    staff: { id: number; name: string }[];
    modes: Option[];
    selections: Option[];
}
