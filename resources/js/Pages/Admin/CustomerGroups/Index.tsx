import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Lock, Pencil, Plus, Tags, Trash2, Users } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/lib/i18n';

interface GroupRow {
    id: number;
    name: string;
    description: string | null;
    color: string | null;
    users_count: number;
    /** Ponudba in termini, omejeni na to skupino. */
    restricts: string[];
}

/** Skupine strank: člane določite pri uporabniku, dostop pri ponudbi ali terminu. */
export default function Index({ groups }: { groups: GroupRow[] }) {
    const { t, tc } = useTranslation();
    const [editing, setEditing] = useState<GroupRow | 'new' | null>(null);

    return (
        <AppLayout header={t('Customer groups')}>
            <Head title={t('Customer groups')} />
            <PageHeader
                title={t('Customer groups')}
                description={t('Groups limit who can book an offer or session, e.g. members only. Add members when editing a user.')}
                actions={<Button onClick={() => setEditing('new')}><Plus className="mr-1.5 h-4 w-4" />{t('Add group')}</Button>}
            />

            {groups.length === 0 ? (
                <EmptyState
                    icon={<Tags className="h-8 w-8" />}
                    title={t('No customer groups yet.')}
                    description={t('Without groups, the whole offer is available to all customers.')}
                />
            ) : (
                <div className="divide-y rounded-lg border bg-card">
                    {groups.map((g) => (
                        <div key={g.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ background: g.color ?? 'var(--muted-foreground)' }} />
                                    {g.name}
                                </p>
                                {g.description && <p className="mt-0.5 text-sm text-muted-foreground">{g.description}</p>}
                                {g.restricts.length > 0 && (
                                    <p className="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                        <Lock className="h-3 w-3" />{t('Only for this group')}: {g.restricts.join(', ')}
                                    </p>
                                )}
                            </div>
                            <div className="flex items-center gap-1">
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={route('admin.users.index', { group: g.id })}><Users className="mr-1.5 h-4 w-4" />{tc(':count members', g.users_count)}</Link>
                                </Button>
                                <Button size="icon" variant="ghost" onClick={() => setEditing(g)} aria-label={t('Edit')}><Pencil className="h-4 w-4" /></Button>
                                <Button size="icon" variant="ghost" aria-label={t('Delete')}
                                    onClick={() => confirm(t('Delete :name?', { name: g.name })) && router.delete(route('admin.customer-groups.destroy', g.id), { preserveScroll: true })}>
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {editing && <GroupDialog group={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </AppLayout>
    );
}

function GroupDialog({ group, onClose }: { group: GroupRow | null; onClose: () => void }) {
    const { t } = useTranslation();
    const form = useForm({
        name: group?.name ?? '',
        description: group?.description ?? '',
        color: group?.color ?? '#2563eb',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (group) form.put(route('admin.customer-groups.update', group.id), options);
        else form.post(route('admin.customer-groups.store'), options);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader><DialogTitle>{group ? group.name : t('New group')}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name} required>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                    </Field>
                    <Field label={t('Description')} error={form.errors.description}>
                        <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>
                    <Field label={t('Color')} error={form.errors.color}>
                        <Input type="color" className="h-9 w-20 p-1" value={form.data.color} onChange={(e) => form.setData('color', e.target.value)} />
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
