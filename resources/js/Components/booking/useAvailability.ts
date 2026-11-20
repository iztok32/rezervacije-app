import axios from 'axios';
import { useEffect, useRef, useState } from 'react';

/**
 * Nalaganje razpoložljivosti za tip vira. Ob spremembi parametrov se prejšnja zahteva
 * prekliče; $reloadKey omogoča ponovno nalaganje (npr. po napaki "termin ni več prost").
 */
export function useAvailability<T>(slug: string, params: Record<string, string | number | null | undefined> | null, reloadKey = 0) {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const key = params ? JSON.stringify(params) : null;
    const controller = useRef<AbortController | null>(null);

    useEffect(() => {
        if (!key) {
            setData(null);
            return;
        }

        controller.current?.abort();
        const ctrl = new AbortController();
        controller.current = ctrl;
        setLoading(true);
        setError(null);

        const clean = Object.fromEntries(Object.entries(JSON.parse(key)).filter(([, v]) => v !== null && v !== undefined && v !== ''));

        axios.get<T>(route('book.availability', slug), { params: clean, signal: ctrl.signal })
            .then((res) => setData(res.data))
            .catch((e) => {
                if (!axios.isCancel(e)) setError(e?.response?.data?.message ?? 'Error');
            })
            .finally(() => {
                if (!ctrl.signal.aborted) setLoading(false);
            });

        return () => ctrl.abort();
    }, [slug, key, reloadKey]);

    return { data, loading, error };
}
