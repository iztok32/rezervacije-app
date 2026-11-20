import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import Field from '@/Components/Field';
import { selectClass, SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { Option, PageProps, PaginationLink, Role } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: Role;
    is_active: boolean;
    bookings_count: number;
    created_at: string | null;
}

interface Props {
    users: { data: UserRow[]; links: PaginationLink[] };
    filters: { q?: string; role?: string };
    roles: Option<Role>[];
}

/** Uporabniki in vloge: administrator, osebje, stranka. */
export default function Index({ users, filters, roles }: Props) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const [editing, setEditing] = useState<UserRow | 'new' | null>(null);
    const [q, setQ] = useState(filters.q ?? '');
    const roleLabel = (r: Role) => roles.find((o) => o.value === r)?.label ?? r;

    const search = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { ...filters, q: q || undefined }, { preserveState: true });
    };

    return (
        <AppLayout header={t('Users')}>
            <Head title={t('Users')} />
            <PageHeader
                title={t('Users')}
                description={t('Staff manage bookings; administrators also manage the offer and settings.')}
                actions={<Button onClick={() => setEditing('new')}><Plus className="mr-1.5 h-4 w-4" />{t('Add user')}</Button>}
            />

            <form onSubmit={search} className="mb-4 flex flex-wrap gap-2">
                <div className="relative min-w-56 flex-1">
                    <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input className="pl-9" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Name or email')} />
                </div>
                <select className={`${selectClass} w-auto`} value={filters.role ?? ''}
                    onChange={(e) => router.get(route('admin.users.index'), { ...filters, role: e.target.value || undefined }, { preserveState: true })}>
                    <option value="">{t('All roles')}</option>
                    {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                </select>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="p-3 font-medium">{t('Name')}</th>
                            <th className="p-3 font-medium">{t('Contact')}</th>
                            <th className="p-3 font-medium">{t('Role')}</th>
                            <th className="p-3 text-right font-medium">{t('Bookings')}</th>
                            <th className="p-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {users.data.map((u) => (
                            <tr key={u.id} className={u.is_active ? '' : 'opacity-50'}>
                                <td className="p-3 font-medium">{u.name}{!u.is_active && <span className="ml-2 text-xs font-normal">({t('inactive')})</span>}</td>
                                <td className="p-3 text-muted-foreground">{u.email}{u.phone && <span className="block text-xs">{u.phone}</span>}</td>
                                <td className="p-3">{roleLabel(u.role)}</td>
                                <td className="p-3 text-right tabular-nums">{u.bookings_count}</td>
                                <td className="whitespace-nowrap p-3 text-right">
                                    <Button size="icon" variant="ghost" onClick={() => setEditing(u)}><Pencil className="h-4 w-4" /></Button>
                                    {u.id !== auth.user!.id && (
                                        <Button size="icon" variant="ghost" onClick={() => confirm(t('Delete :name?', { name: u.name })) && router.delete(route('admin.users.destroy', u.id), { preserveScroll: true })}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination links={users.links} />

            {editing && <UserDialog user={editing === 'new' ? null : editing} roles={roles} onClose={() => setEditing(null)} />}
        </AppLayout>
    );
}

function UserDialog({ user, roles, onClose }: { user: UserRow | null; roles: Option<Role>[]; onClose: () => void }) {
    const { t } = useTranslation();
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        role: user?.role ?? 'customer' as Role,
        is_active: user?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (user) form.put(route('admin.users.update', user.id), options);
        else form.post(route('admin.users.store'), options);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader><DialogTitle>{user ? user.name : t('New user')}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name} required><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus /></Field>
                    <Field label={t('Email')} error={form.errors.email} required hint={!user ? t('The user will receive a link to set a password.') : undefined}>
                        <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    </Field>
                    <Field label={t('Phone')} error={form.errors.phone}><Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} /></Field>
                    <Field label={t('Role')} error={form.errors.role}>
                        <select className={selectClass} value={form.data.role} onChange={(e) => form.setData('role', e.target.value as Role)}>
                            {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                        </select>
                    </Field>
                    <SwitchRow checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label={t('Active')} description={t('Inactive users cannot log in.')} />
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>{t('Cancel')}</Button>
                        <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
