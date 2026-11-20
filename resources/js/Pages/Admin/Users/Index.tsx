import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import Field from '@/Components/Field';
import { MultiCheck, selectClass, SwitchRow } from '@/Components/form/inputs';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { PageProps, PaginationLink } from '@/types';

interface GroupRef {
    id: number;
    name: string;
    color: string | null;
}

interface RoleRef {
    id: number;
    name: string;
    is_admin: boolean;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role_id: number | null;
    role: string | null;
    is_admin: boolean;
    is_active: boolean;
    groups: GroupRef[];
    bookings_count: number;
    created_at: string | null;
}

interface Props {
    users: { data: UserRow[]; links: PaginationLink[] };
    filters: { q?: string; role?: string; group?: number };
    roles: RoleRef[];
    groups: GroupRef[];
    canManageRoles: boolean;
    canAssignAdmin: boolean;
}

/** Uporabniki: stranke (brez vloge) in osebje (z vlogo) ter članstvo v skupinah strank. */
export default function Index({ users, filters, roles, groups, canManageRoles, canAssignAdmin }: Props) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const [editing, setEditing] = useState<UserRow | 'new' | null>(null);
    const [q, setQ] = useState(filters.q ?? '');

    const filter = (changes: Partial<Props['filters']>) =>
        router.get(route('admin.users.index'), { ...filters, ...changes }, { preserveState: true });

    const search = (e: FormEvent) => {
        e.preventDefault();
        filter({ q: q || undefined });
    };

    return (
        <AppLayout header={t('Users')}>
            <Head title={t('Users')} />
            <PageHeader
                title={t('Users')}
                description={t('Customers have no role. Staff get a role that defines their permissions.')}
                actions={<Button onClick={() => setEditing('new')}><Plus className="mr-1.5 h-4 w-4" />{t('Add user')}</Button>}
            />

            <form onSubmit={search} className="mb-4 flex flex-wrap gap-2">
                <div className="relative min-w-56 flex-1">
                    <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input className="pl-9" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Name or email')} />
                </div>
                <select className={`${selectClass} w-auto`} value={filters.role ?? ''} onChange={(e) => filter({ role: e.target.value || undefined })}>
                    <option value="">{t('All users')}</option>
                    <option value="customer">{t('Customers')}</option>
                    <option value="staff">{t('Staff')}</option>
                    {roles.map((r) => <option key={r.id} value={String(r.id)}>— {r.name}</option>)}
                </select>
                {groups.length > 0 && (
                    <select className={`${selectClass} w-auto`} value={filters.group ?? ''} onChange={(e) => filter({ group: e.target.value ? Number(e.target.value) : undefined })}>
                        <option value="">{t('All groups')}</option>
                        {groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
                    </select>
                )}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="p-3 font-medium">{t('Name')}</th>
                            <th className="p-3 font-medium">{t('Contact')}</th>
                            <th className="p-3 font-medium">{t('Role')}</th>
                            <th className="p-3 font-medium">{t('Groups')}</th>
                            <th className="p-3 text-right font-medium">{t('Bookings')}</th>
                            <th className="p-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {users.data.map((u) => {
                            const locked = u.is_admin && !canAssignAdmin;
                            return (
                                <tr key={u.id} className={u.is_active ? '' : 'opacity-50'}>
                                    <td className="p-3 font-medium">{u.name}{!u.is_active && <span className="ml-2 text-xs font-normal">({t('inactive')})</span>}</td>
                                    <td className="p-3 text-muted-foreground">{u.email}{u.phone && <span className="block text-xs">{u.phone}</span>}</td>
                                    <td className="p-3">{u.role ?? <span className="text-muted-foreground">{t('Customer')}</span>}</td>
                                    <td className="p-3">
                                        <div className="flex flex-wrap gap-1">
                                            {u.groups.map((g) => (
                                                <Badge key={g.id} variant="outline" className="gap-1.5 font-normal">
                                                    <span className="h-2 w-2 rounded-full" style={{ background: g.color ?? 'var(--muted-foreground)' }} />{g.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="p-3 text-right tabular-nums">{u.bookings_count}</td>
                                    <td className="whitespace-nowrap p-3 text-right">
                                        {!locked && (
                                            <Button size="icon" variant="ghost" onClick={() => setEditing(u)} aria-label={t('Edit')}><Pencil className="h-4 w-4" /></Button>
                                        )}
                                        {!locked && u.id !== auth.user!.id && (!u.role_id || canManageRoles) && (
                                            <Button size="icon" variant="ghost" aria-label={t('Delete')}
                                                onClick={() => confirm(t('Delete :name?', { name: u.name })) && router.delete(route('admin.users.destroy', u.id), { preserveScroll: true })}>
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <Pagination links={users.links} />

            {editing && (
                <UserDialog
                    user={editing === 'new' ? null : editing}
                    isSelf={editing !== 'new' && editing.id === auth.user!.id}
                    roles={roles.filter((r) => !r.is_admin || canAssignAdmin)}
                    groups={groups}
                    canManageRoles={canManageRoles}
                    onClose={() => setEditing(null)}
                />
            )}
        </AppLayout>
    );
}

function UserDialog({ user, isSelf, roles, groups, canManageRoles, onClose }: {
    user: UserRow | null;
    isSelf: boolean;
    roles: RoleRef[];
    groups: GroupRef[];
    canManageRoles: boolean;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        role_id: user?.role_id ?? null as number | null,
        is_active: user?.is_active ?? true,
        customer_group_ids: user?.groups.map((g) => g.id) ?? [],
    });
    const roleLocked = !canManageRoles || isSelf;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (user) form.put(route('admin.users.update', user.id), options);
        else form.post(route('admin.users.store'), options);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader><DialogTitle>{user ? user.name : t('New user')}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name} required><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus /></Field>
                    <Field label={t('Email')} error={form.errors.email} required hint={!user ? t('The user will receive a link to set a password.') : undefined}>
                        <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    </Field>
                    <Field label={t('Phone')} error={form.errors.phone}><Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} /></Field>
                    <Field
                        label={t('Role')}
                        error={form.errors.role_id}
                        hint={isSelf ? t('You cannot change your own role.') : !canManageRoles ? t('Only users who manage roles can change this.') : undefined}
                    >
                        <select className={selectClass} value={form.data.role_id ?? ''} disabled={roleLocked}
                            onChange={(e) => form.setData('role_id', e.target.value ? Number(e.target.value) : null)}>
                            <option value="">{t('Customer (no role)')}</option>
                            {/* Trenutna vloga ostane izbrana, tudi če je uporabnik ne sme dodeliti */}
                            {user?.role_id && !roles.some((r) => r.id === user.role_id) && <option value={user.role_id}>{user.role}</option>}
                            {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                        </select>
                    </Field>
                    {groups.length > 0 && (
                        <Field label={t('Customer groups')} error={form.errors.customer_group_ids}>
                            <MultiCheck options={groups.map((g) => ({ value: g.id, label: g.name }))} value={form.data.customer_group_ids}
                                onChange={(v) => form.setData('customer_group_ids', v)} />
                        </Field>
                    )}
                    {!isSelf && (
                        <SwitchRow checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label={t('Active')} description={t('Inactive users cannot log in.')} />
                    )}
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>{t('Cancel')}</Button>
                        <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
