import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { ArrowLeft, Inbox, Loader2, Mail, Search, Send, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Field from '@/Components/Field';
import { MultiCheck, numberOrNull, selectClass } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { addDays, parseDate, toIsoDate } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Channel } from './shared';

type Mode = 'customers' | 'bookings' | 'users';

interface UserRef {
    id: number;
    name: string;
    email: string;
}

interface Preview {
    count: number;
    with_account: number;
    sample: { name: string; email: string; guest: boolean }[];
}

interface Props {
    groups: { id: number; name: string }[];
    types: { id: number; name: string }[];
    resources: { id: number; name: string; resource_type_id: number }[];
    canUseBookings: boolean;
    prefill: Partial<Record<'mode' | 'from' | 'to' | 'resource_type_id' | 'resource_id' | 'subject', string>>;
    today: string;
}

/** Novo sporočilo: izbira prejemnikov s sprotnim predogledom, kanali in vsebina. */
export default function Create({ groups, types, resources, canUseBookings, prefill, today }: Props) {
    const { t, tc } = useTranslation();
    const initialMode = (prefill.mode as Mode) ?? (canUseBookings ? 'bookings' : 'customers');
    const form = useForm({
        mode: initialMode,
        group_ids: [] as number[],
        from: prefill.from ?? today,
        to: prefill.to ?? toIsoDate(addDays(parseDate(today), 7)),
        resource_type_id: numberOrNull(prefill.resource_type_id ?? ''),
        resource_id: numberOrNull(prefill.resource_id ?? ''),
        user_ids: [] as number[],
        channels: ['mail', 'portal'] as Channel[],
        subject: prefill.subject ?? '',
        body: '',
    });
    const d = form.data;
    const [selectedUsers, setSelectedUsers] = useState<UserRef[]>([]);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [loading, setLoading] = useState(false);

    // Samo filtri prejemnikov sprožijo nov predogled (ne zadeva in besedilo)
    const audience = useMemo(() => ({
        mode: d.mode,
        group_ids: d.mode === 'customers' ? d.group_ids : undefined,
        from: d.mode === 'bookings' ? d.from : undefined,
        to: d.mode === 'bookings' ? d.to : undefined,
        resource_type_id: d.mode === 'bookings' ? d.resource_type_id : undefined,
        resource_id: d.mode === 'bookings' ? d.resource_id : undefined,
        user_ids: d.mode === 'users' ? d.user_ids : undefined,
    }), [d.mode, d.group_ids, d.from, d.to, d.resource_type_id, d.resource_id, d.user_ids]);

    useEffect(() => {
        if (audience.mode === 'users' && !audience.user_ids?.length) {
            setPreview({ count: 0, with_account: 0, sample: [] });
            return;
        }
        if (audience.mode === 'bookings' && (!audience.from || !audience.to || audience.to < audience.from)) {
            setPreview(null);
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            axios.post<Preview>(route('admin.messages.preview'), audience, { signal: controller.signal })
                .then((res) => setPreview(res.data))
                .catch((err) => !axios.isCancel(err) && setPreview(null))
                .finally(() => setLoading(false));
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [audience]);

    const portalOnly = d.channels.length === 1 && d.channels[0] === 'portal';
    const effectiveCount = preview ? (portalOnly ? preview.with_account : preview.count) : 0;

    const toggleChannel = (channel: Channel, on: boolean) =>
        form.setData('channels', on ? [...d.channels, channel] : d.channels.filter((c) => c !== channel));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!confirm(tc('Send the message to :count recipients?', effectiveCount))) return;
        form.post(route('admin.messages.store'));
    };

    const modes: { value: Mode; label: string; description: string; hidden?: boolean }[] = [
        { value: 'bookings', label: t('Customers with bookings'), description: t('E.g. everyone booked tomorrow on court 2. Includes guests.'), hidden: !canUseBookings },
        { value: 'customers', label: t('Customers'), description: t('All customers with an account, or selected groups.') },
        { value: 'users', label: t('Selected users'), description: t('Pick individual people.') },
    ];

    return (
        <AppLayout header={t('New message')}>
            <Head title={t('New message')} />
            <Button variant="ghost" size="sm" asChild className="-ml-2 mb-2">
                <Link href={route('admin.messages.index')}><ArrowLeft className="mr-1.5 h-4 w-4" />{t('Messages')}</Link>
            </Button>
            <PageHeader title={t('New message')} />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader><CardTitle className="text-base">{t('Recipients')}</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2 sm:grid-cols-3">
                                {modes.filter((m) => !m.hidden).map((m) => (
                                    <button key={m.value} type="button" onClick={() => form.setData('mode', m.value)}
                                        className={cn('rounded-lg border p-3 text-left transition', d.mode === m.value ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'hover:bg-accent/40')}>
                                        <span className="block text-sm font-medium">{m.label}</span>
                                        <span className="mt-0.5 block text-xs text-muted-foreground">{m.description}</span>
                                    </button>
                                ))}
                            </div>

                            {d.mode === 'customers' && (
                                <Field label={t('Customer groups')} hint={t('Without a selection, all customers with an account.')} error={form.errors.group_ids}>
                                    <MultiCheck options={groups.map((g) => ({ value: g.id, label: g.name }))} value={d.group_ids}
                                        onChange={(v) => form.setData('group_ids', v)} emptyHint={t('No customer groups yet — the message goes to all customers.')} />
                                </Field>
                            )}

                            {d.mode === 'bookings' && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label={t('From')} error={form.errors.from} required>
                                        <Input type="date" value={d.from} onChange={(e) => form.setData('from', e.target.value)} />
                                    </Field>
                                    <Field label={t('To')} error={form.errors.to} required>
                                        <Input type="date" value={d.to} min={d.from} onChange={(e) => form.setData('to', e.target.value)} />
                                    </Field>
                                    <Field label={t('Offer')}>
                                        <select className={selectClass} value={d.resource_type_id ?? ''}
                                            onChange={(e) => form.setData({ ...d, resource_type_id: numberOrNull(e.target.value), resource_id: null })}>
                                            <option value="">{t('All')}</option>
                                            {types.map((ty) => <option key={ty.id} value={ty.id}>{ty.name}</option>)}
                                        </select>
                                    </Field>
                                    <Field label={t('Resource')}>
                                        <select className={selectClass} value={d.resource_id ?? ''} onChange={(e) => form.setData('resource_id', numberOrNull(e.target.value))}>
                                            <option value="">{t('All')}</option>
                                            {resources.filter((r) => !d.resource_type_id || r.resource_type_id === d.resource_type_id)
                                                .map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                                        </select>
                                    </Field>
                                </div>
                            )}

                            {d.mode === 'users' && (
                                <UserPicker
                                    selected={selectedUsers}
                                    onChange={(users) => { setSelectedUsers(users); form.setData('user_ids', users.map((u) => u.id)); }}
                                    error={form.errors.user_ids}
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="text-base">{t('Message')}</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <Field label={t('Send via')} error={form.errors.channels}>
                                <div className="flex flex-wrap gap-4">
                                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                                        <Checkbox checked={d.channels.includes('mail')} onCheckedChange={(v) => toggleChannel('mail', v === true)} />
                                        <Mail className="h-4 w-4 text-muted-foreground" />{t('Email')}
                                    </label>
                                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                                        <Checkbox checked={d.channels.includes('portal')} onCheckedChange={(v) => toggleChannel('portal', v === true)} />
                                        <Inbox className="h-4 w-4 text-muted-foreground" />{t('Portal inbox')}
                                    </label>
                                </div>
                            </Field>
                            <Field label={t('Subject')} error={form.errors.subject} required>
                                <Input value={d.subject} onChange={(e) => form.setData('subject', e.target.value)} maxLength={200} />
                            </Field>
                            <Field label={t('Text')} error={form.errors.body} required hint={t('The greeting with the recipient\'s name and your business name are added automatically.')}>
                                <Textarea rows={8} value={d.body} onChange={(e) => form.setData('body', e.target.value)} />
                            </Field>
                        </CardContent>
                    </Card>
                </div>

                <div className="lg:sticky lg:top-20 lg:self-start">
                    <Card>
                        <CardHeader><CardTitle className="flex items-center gap-2 text-base">{t('Preview')}{loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            {preview ? (
                                <>
                                    <div>
                                        <p className="text-xl font-semibold tabular-nums">{tc(':count recipients', effectiveCount)}</p>
                                        {preview.count > preview.with_account && (
                                            <p className="text-sm text-muted-foreground">
                                                {portalOnly
                                                    ? t('Guests without an account do not get portal messages.')
                                                    : tc(':count without an account (email only)', preview.count - preview.with_account)}
                                            </p>
                                        )}
                                    </div>
                                    {preview.sample.length > 0 && (
                                        <ul className="space-y-1 text-sm">
                                            {preview.sample.filter((s) => !portalOnly || !s.guest).map((s) => (
                                                <li key={s.email} className="flex justify-between gap-2">
                                                    <span className="truncate">{s.name}</span>
                                                    <span className="truncate text-xs text-muted-foreground">{s.email}</span>
                                                </li>
                                            ))}
                                            {preview.count > preview.sample.length && <li className="text-xs text-muted-foreground">…</li>}
                                        </ul>
                                    )}
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">{t('Choose the recipients.')}</p>
                            )}
                            <Button type="submit" className="w-full" disabled={form.processing || effectiveCount === 0 || d.channels.length === 0 || !d.subject || !d.body}>
                                <Send className="mr-1.5 h-4 w-4" />{t('Send')}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}

function UserPicker({ selected, onChange, error }: { selected: UserRef[]; onChange: (users: UserRef[]) => void; error?: string }) {
    const { t } = useTranslation();
    const [q, setQ] = useState('');
    const [results, setResults] = useState<UserRef[]>([]);

    useEffect(() => {
        if (q.trim().length < 2) {
            setResults([]);
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            axios.get<UserRef[]>(route('admin.messages.users'), { params: { q }, signal: controller.signal })
                .then((res) => setResults(res.data))
                .catch(() => undefined);
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [q]);

    return (
        <Field label={t('Users')} error={error}>
            {selected.length > 0 && (
                <div className="mb-2 flex flex-wrap gap-1.5">
                    {selected.map((u) => (
                        <span key={u.id} className="inline-flex items-center gap-1 rounded-md border bg-muted/50 py-0.5 pl-2 pr-1 text-sm">
                            {u.name}
                            <button type="button" onClick={() => onChange(selected.filter((x) => x.id !== u.id))} className="rounded p-0.5 hover:bg-accent" aria-label={t('Remove')}>
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </span>
                    ))}
                </div>
            )}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input className="pl-9" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Name or email')} />
            </div>
            {results.length > 0 && (
                <ul className="mt-1 max-h-56 overflow-y-auto rounded-md border bg-popover text-sm shadow-sm">
                    {results.filter((r) => !selected.some((s) => s.id === r.id)).map((r) => (
                        <li key={r.id}>
                            <button type="button" className="flex w-full justify-between gap-2 px-3 py-2 text-left hover:bg-accent"
                                onClick={() => { onChange([...selected, r]); setQ(''); }}>
                                <span>{r.name}</span><span className="truncate text-xs text-muted-foreground">{r.email}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </Field>
    );
}
