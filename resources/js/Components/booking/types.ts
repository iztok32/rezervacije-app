import { ResourceData, ResourceTypeData, ServiceData } from '@/types';

/** Izbira v izbirniku: podatki za oddajo in povzetek za prikaz. */
export interface Selection {
    payload: Record<string, string | number | null>;
    summary: {
        lines: string[];
        price: number | null;
    };
}

export interface PickerProps {
    type: ResourceTypeData;
    resources: ResourceData[];
    services: ServiceData[];
    today: string;
    lastBookableDate: string;
    onChange: (selection: Selection | null) => void;
    /** Poveča se, ko je treba razpoložljivost ponovno naložiti (npr. po napaki). */
    reloadKey: number;
}
