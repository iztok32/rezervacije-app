import axios from 'axios';
import { useEffect, useState } from 'react';
import { Search, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/lib/i18n';

export interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
}

/** Iskanje obstoječe stranke (osebje). */
export default function CustomerPicker({ value, onChange }: { value: Customer | null; onChange: (c: Customer | null) => void }) {
    const { t } = useTranslation();
    const [q, setQ] = useState('');
    const [results, setResults] = useState<Customer[]>([]);

    useEffect(() => {
        if (q.trim().length < 2) {
            setResults([]);
            return;
        }
        const ctrl = new AbortController();
        const timer = setTimeout(() => {
            axios.get<Customer[]>(route('admin.customers.search'), { params: { q }, signal: ctrl.signal })
                .then((r) => setResults(r.data))
                .catch(() => {});
        }, 250);
        return () => { clearTimeout(timer); ctrl.abort(); };
    }, [q]);

    if (value) {
        return (
            <div className="flex items-center justify-between rounded-md border bg-muted/40 px-3 py-2 text-sm">
                <div>
                    <p className="font-medium">{value.name}</p>
                    <p className="text-xs text-muted-foreground">{value.email}{value.phone ? ` · ${value.phone}` : ''}</p>
                </div>
                <Button type="button" variant="ghost" size="icon" onClick={() => onChange(null)} aria-label={t('Remove')}>
                    <X className="h-4 w-4" />
                </Button>
            </div>
        );
    }

    return (
        <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Search registered customers…')} className="pl-9" />
            {results.length > 0 && (
                <div className="absolute z-20 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-lg">
                    {results.map((c) => (
                        <button key={c.id} type="button" onClick={() => { onChange(c); setQ(''); }}
                            className="block w-full px-3 py-2 text-left text-sm hover:bg-accent">
                            <span className="font-medium">{c.name}</span>
                            <span className="ml-2 text-xs text-muted-foreground">{c.email}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
