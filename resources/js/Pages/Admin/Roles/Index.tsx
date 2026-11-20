import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Check, Lock, Pencil, Plus, Trash2, Users } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Field from '@/Components/Field';
import { MultiCheck } from '@/Components/form/inputs';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Permission } from '@/types';

type Scope = 'all' | 'own';

interface RoleRow {
    id: number;
    name: string;
    description: string | null;
    is_admin: boolean;
    permissions: Permission[];
    booking_scope: Scope;
    resource_type_ids: number[];
    users_count: number;
}

interface PermissionOption {
    value: Permission;
    label: string;
    description: string;
}

interface Props {
    roles: RoleRow[];
    permissions: PermissionOption[];
    types: { id: number; name: string }[];
}

/** Vloge osebja: katere pravice ima vloga, katere rezervacije vidi in za katero ponudbo. */
export default function Index({ roles, permissions, types }: Props) {
    const { t, tc } = useTranslation();
    const [editing, setEditing] = useState<RoleRow | 'new' | null>(null);
    const typeName = (id: number) => types.find((x) => x.id === id)?.name;

    return (
        <AppLayout header={t('Roles')}>
            <Head title={t('Roles')} />
            <PageHeader
                title={t('Roles')}
                description={t('A role defines what staff members can do. Customers have no role.')}
                actions={<Button onClick={() => setEditing('new')}><Plus className="mr-1.5 h-4 w-4" />{t('Add role')}</Button>}
            />

            <div className="grid gap-4 lg:grid-cols-2">
                {roles.map((role) => (
                    <Card key={role.id} className="flex flex-col">
                        <CardHeader className="flex-row items-start justify-between gap-3 space-y-0 pb-3">
                            <div className="min-w-0">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    {role.name}
                                    {role.is_admin && <Lock className="h-3.5 w-3.5 text-muted-foreground" />}
                                </CardTitle>
                                {role.description && <p className="mt-1 text-sm text-muted-foreground">{role.description}</p>}
                            </div>
                            {!role.is_admin && (
                                <div className="flex shrink-0">
                                    <Button size="icon" variant="ghost" onClick={() => setEditing(role)} aria-label={t('Edit')}><Pencil className="h-4 w-4" /></Button>
                                    <Button size="icon" variant="ghost" aria-label={t('Delete')}
                                        onClick={() => confirm(t('Delete :name?', { name: role.name })) && router.delete(route('admin.roles.destroy', role.id), { preserveScroll: true })}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col gap-3">
                            <div className="flex flex-wrap gap-1.5">
                                <Badge variant="secondary">
                                    {role.is_admin ? t('All permissions') : role.booking_scope === 'own' ? t('Own resources only') : t('All bookings')}
                                </Badge>
                                {role.resource_type_ids.map((id) => <Badge key={id} variant="outline">{typeName(id)}</Badge>)}
                            </div>
                            {!role.is_admin && (
                                <ul className="space-y-1 text-sm">
                                    {permissions.filter((p) => role.permissions.includes(p.value)).map((p) => (
                                        <li key={p.value} className="flex items-center gap-2"><Check className="h-3.5 w-3.5 text-primary" />{p.label}</li>
                                    ))}
                                    {role.permissions.length === 0 && <li className="text-muted-foreground">{t('No permissions.')}</li>}
                                </ul>
                            )}
                            <Link href={route('admin.users.index', { role: role.id })} className="mt-auto flex items-center gap-1.5 pt-1 text-sm text-muted-foreground hover:text-foreground">
                                <Users className="h-4 w-4" />
                                {tc(':count users', role.users_count)}
                            </Link>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {editing && (
                <RoleDialog role={editing === 'new' ? null : editing} permissions={permissions} types={types} onClose={() => setEditing(null)} />
            )}
        </AppLayout>
    );
}

function RoleDialog({ role, permissions, types, onClose }: {
    role: RoleRow | null;
    permissions: PermissionOption[];
    types: { id: number; name: string }[];
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({
        name: role?.name ?? '',
        description: role?.description ?? '',
        permissions: role?.permissions ?? (['bookings.view'] as Permission[]),
        booking_scope: role?.booking_scope ?? ('all' as Scope),
        resource_type_ids: role?.resource_type_ids ?? [],
    });

    const toggle = (p: Permission, on: boolean) =>
        form.setData('permissions', on ? [...form.data.permissions, p] : form.data.permissions.filter((x) => x !== p));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (role) form.put(route('admin.roles.update', role.id), options);
        else form.post(route('admin.roles.store'), options);
    };

    const scopes: { value: Scope; label: string; description: string }[] = [
        { value: 'all', label: t('All bookings'), description: t('Sees all bookings (within the selected offer).') },
        { value: 'own', label: t('Own resources only'), description: t('Sees bookings for resources and sessions assigned to the user, e.g. a hairdresser or instructor.') },
    ];

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader><DialogTitle>{role ? role.name : t('New role')}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <Field label={t('Name')} error={form.errors.name} required>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                    </Field>
                    <Field label={t('Description')} error={form.errors.description}>
                        <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>

                    <Field label={t('Permissions')} error={form.errors.permissions}>
                        <div className="divide-y rounded-lg border">
                            {permissions.map((p) => (
                                <label key={p.value} className="flex cursor-pointer items-start gap-3 p-3 hover:bg-accent/40">
                                    <Checkbox className="mt-0.5" checked={form.data.permissions.includes(p.value)} onCheckedChange={(v) => toggle(p.value, v === true)} />
                                    <span>
                                        <span className="block text-sm font-medium">{p.label}</span>
                                        <span className="block text-xs text-muted-foreground">{p.description}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {form.data.permissions.includes('roles.manage') && (
                            <p className="text-xs text-amber-600 dark:text-amber-400">
                                {t('Users with this permission can change permissions of roles, including their own. Assign it only to trusted people.')}
                            </p>
                        )}
                    </Field>

                    <Field label={t('Which bookings')} error={form.errors.booking_scope}>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {scopes.map((s) => (
                                <button key={s.value} type="button" onClick={() => form.setData('booking_scope', s.value)}
                                    className={cn('rounded-lg border p-3 text-left transition', form.data.booking_scope === s.value ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'hover:bg-accent/40')}>
                                    <span className="block text-sm font-medium">{s.label}</span>
                                    <span className="mt-0.5 block text-xs text-muted-foreground">{s.description}</span>
                                </button>
                            ))}
                        </div>
                    </Field>

                    <Field label={t('Limit to offer')} hint={t('Without a selection, the role applies to the whole offer.')} error={form.errors.resource_type_ids}>
                        <MultiCheck options={types.map((x) => ({ value: x.id, label: x.name }))} value={form.data.resource_type_ids}
                            onChange={(v) => form.setData('resource_type_ids', v)} />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>{t('Cancel')}</Button>
                        <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
