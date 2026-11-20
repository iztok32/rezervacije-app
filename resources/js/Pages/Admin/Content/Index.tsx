import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ArrowDown, ArrowUp, ExternalLink, EyeOff, FileText, Link2, Pencil, Plus, Shapes, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Field from '@/Components/Field';
import { selectClass, SwitchRow } from '@/Components/form/inputs';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { formatDate } from '@/lib/dates';
import { useTranslation } from '@/lib/i18n';

type Location = 'header' | 'footer';
type Kind = 'page' | 'offer' | 'link';
type Visibility = 'all' | 'guests' | 'users';

interface PageRow {
    id: number;
    title: string;
    slug: string;
    url: string;
    is_published: boolean;
    updated_at: string | null;
}

interface MenuRow {
    id: number;
    kind: Kind;
    label: string | null;
    target: string | null;
    page_id: number | null;
    resource_type_id: number | null;
    url: string | null;
    visibility: Visibility;
    new_tab: boolean;
}

interface Props {
    pages: PageRow[];
    menu: Record<Location, MenuRow[]>;
    types: { id: number; name: string }[];
}

const KIND_ICON: Record<Kind, typeof FileText> = { page: FileText, offer: Shapes, link: Link2 };

/** Vsebinske strani in javni meni (glava, noga). */
export default function Index({ pages, menu, types }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout header={t('Content')}>
            <Head title={t('Content')} />
            <PageHeader title={t('Content')} description={t('Pages such as about us, price list or terms, and the menu on the public website.')} />

            <Tabs defaultValue="pages">
                <TabsList>
                    <TabsTrigger value="pages">{t('Pages')}</TabsTrigger>
                    <TabsTrigger value="menu">{t('Menu')}</TabsTrigger>
                </TabsList>
                <TabsContent value="pages" className="mt-4"><PagesTab pages={pages} /></TabsContent>
                <TabsContent value="menu" className="mt-4">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <MenuEditor location="header" title={t('Header')} items={menu.header} pages={pages} types={types} />
                        <MenuEditor location="footer" title={t('Footer')} items={menu.footer} pages={pages} types={types} />
                    </div>
                </TabsContent>
            </Tabs>
        </AppLayout>
    );
}

function PagesTab({ pages }: { pages: PageRow[] }) {
    const { t, locale } = useTranslation();

    return (
        <>
            <div className="mb-4 flex justify-end">
                <Button asChild><Link href={route('admin.pages.create')}><Plus className="mr-1.5 h-4 w-4" />{t('Add page')}</Link></Button>
            </div>
            {pages.length === 0 ? (
                <EmptyState icon={<FileText className="h-8 w-8" />} title={t('No pages yet.')} description={t('Add e.g. "About us", a price list or the terms and conditions, then link them in the menu.')} />
            ) : (
                <div className="divide-y rounded-lg border bg-card">
                    {pages.map((p) => (
                        <div key={p.id} className="flex items-center gap-3 p-3">
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    {p.title}
                                    {!p.is_published && <Badge variant="secondary" className="gap-1 font-normal"><EyeOff className="h-3 w-3" />{t('Draft')}</Badge>}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">/p/{p.slug}{p.updated_at && ` · ${formatDate(p.updated_at, locale)}`}</p>
                            </div>
                            <Button size="icon" variant="ghost" asChild><a href={p.url} target="_blank" rel="noreferrer" aria-label={t('Open')}><ExternalLink className="h-4 w-4" /></a></Button>
                            <Button size="icon" variant="ghost" asChild><Link href={route('admin.pages.edit', p.id)} aria-label={t('Edit')}><Pencil className="h-4 w-4" /></Link></Button>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}

function MenuEditor({ location, title, items, pages, types }: { location: Location; title: string; items: MenuRow[]; pages: PageRow[]; types: { id: number; name: string }[] }) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<MenuRow | 'new' | null>(null);
    const visibilityLabel: Record<Visibility, string> = { all: '', guests: t('Guests only'), users: t('Logged-in only') };

    const move = (index: number, delta: number) => {
        const ids = items.map((i) => i.id);
        [ids[index], ids[index + delta]] = [ids[index + delta], ids[index]];
        router.post(route('admin.menu-items.reorder'), { ids }, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
                <CardTitle className="text-base">{title}</CardTitle>
                <Button size="sm" variant="outline" onClick={() => setEditing('new')}><Plus className="mr-1 h-4 w-4" />{t('Add item')}</Button>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('No items.')}</p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {items.map((item, i) => {
                            const Icon = KIND_ICON[item.kind];
                            return (
                                <li key={item.id} className="flex items-center gap-2 p-2 pl-3">
                                    <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">{item.label || item.target || '—'}</p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {item.label && item.target !== item.label ? item.target : ''}
                                            {visibilityLabel[item.visibility] && ` ${visibilityLabel[item.visibility]}`}
                                        </p>
                                    </div>
                                    <Button size="icon" variant="ghost" className="h-8 w-8" disabled={i === 0} onClick={() => move(i, -1)} aria-label={t('Move up')}><ArrowUp className="h-4 w-4" /></Button>
                                    <Button size="icon" variant="ghost" className="h-8 w-8" disabled={i === items.length - 1} onClick={() => move(i, 1)} aria-label={t('Move down')}><ArrowDown className="h-4 w-4" /></Button>
                                    <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => setEditing(item)} aria-label={t('Edit')}><Pencil className="h-4 w-4" /></Button>
                                    <Button size="icon" variant="ghost" className="h-8 w-8" aria-label={t('Delete')}
                                        onClick={() => router.delete(route('admin.menu-items.destroy', item.id), { preserveScroll: true })}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
            {editing && (
                <MenuItemDialog location={location} item={editing === 'new' ? null : editing} pages={pages} types={types} onClose={() => setEditing(null)} />
            )}
        </Card>
    );
}

function MenuItemDialog({ location, item, pages, types, onClose }: {
    location: Location;
    item: MenuRow | null;
    pages: PageRow[];
    types: { id: number; name: string }[];
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({
        location,
        kind: item?.kind ?? ('page' as Kind),
        label: item?.label ?? '',
        page_id: item?.page_id ?? pages[0]?.id ?? null,
        resource_type_id: item?.resource_type_id ?? types[0]?.id ?? null,
        url: item?.url ?? '',
        visibility: item?.visibility ?? ('all' as Visibility),
        new_tab: item?.new_tab ?? false,
    });
    const d = form.data;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (item) form.put(route('admin.menu-items.update', item.id), options);
        else form.post(route('admin.menu-items.store'), options);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader><DialogTitle>{item ? t('Edit menu item') : t('New menu item')}</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Links to')}>
                        <select className={selectClass} value={d.kind} onChange={(e) => form.setData('kind', e.target.value as Kind)}>
                            <option value="page">{t('Page')}</option>
                            <option value="offer">{t('Offer (booking)')}</option>
                            <option value="link">{t('Link')}</option>
                        </select>
                    </Field>
                    {d.kind === 'page' && (
                        <Field label={t('Page')} error={form.errors.page_id}>
                            <select className={selectClass} value={d.page_id ?? ''} onChange={(e) => form.setData('page_id', Number(e.target.value))}>
                                {pages.map((p) => <option key={p.id} value={p.id}>{p.title}</option>)}
                            </select>
                        </Field>
                    )}
                    {d.kind === 'offer' && (
                        <Field label={t('Offer')} error={form.errors.resource_type_id}>
                            <select className={selectClass} value={d.resource_type_id ?? ''} onChange={(e) => form.setData('resource_type_id', Number(e.target.value))}>
                                {types.map((ty) => <option key={ty.id} value={ty.id}>{ty.name}</option>)}
                            </select>
                        </Field>
                    )}
                    {d.kind === 'link' && (
                        <Field label={t('Address')} error={form.errors.url} hint={t('E.g. https://… or /my-bookings')} required>
                            <Input value={d.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="https://" />
                        </Field>
                    )}
                    <Field label={t('Label')} error={form.errors.label} required={d.kind === 'link'}
                        hint={d.kind !== 'link' ? t('Empty = page title or offer name.') : undefined}>
                        <Input value={d.label} onChange={(e) => form.setData('label', e.target.value)} />
                    </Field>
                    <Field label={t('Visible to')}>
                        <select className={selectClass} value={d.visibility} onChange={(e) => form.setData('visibility', e.target.value as Visibility)}>
                            <option value="all">{t('Everyone')}</option>
                            <option value="guests">{t('Guests only')}</option>
                            <option value="users">{t('Logged-in only')}</option>
                        </select>
                    </Field>
                    <SwitchRow checked={d.new_tab} onChange={(v) => form.setData('new_tab', v)} label={t('Open in a new tab')} />
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>{t('Cancel')}</Button>
                        <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
