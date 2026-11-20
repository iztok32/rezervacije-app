import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ArrowLeft, ExternalLink, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Field from '@/Components/Field';
import { SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/lib/i18n';

interface Props {
    page: { id: number; title: string; slug: string; body: string | null; is_published: boolean; url: string } | null;
}

/** Urejanje vsebinske strani (Markdown). */
export default function PageForm({ page }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        body: page?.body ?? '',
        is_published: page?.is_published ?? true,
    });
    const d = form.data;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (page) form.put(route('admin.pages.update', page.id), { preserveScroll: true });
        else form.post(route('admin.pages.store'));
    };

    return (
        <AppLayout header={t('Content')}>
            <Head title={page ? page.title : t('New page')} />
            <Button variant="ghost" size="sm" asChild className="-ml-2 mb-2">
                <Link href={route('admin.content.index')}><ArrowLeft className="mr-1.5 h-4 w-4" />{t('Content')}</Link>
            </Button>
            <PageHeader
                title={page ? page.title : t('New page')}
                actions={page && (
                    <>
                        <Button variant="outline" asChild><a href={page.url} target="_blank" rel="noreferrer"><ExternalLink className="mr-1.5 h-4 w-4" />{t('Open page')}</a></Button>
                        <Button variant="ghost" size="icon" aria-label={t('Delete')}
                            onClick={() => confirm(t('Delete :name?', { name: page.title })) && router.delete(route('admin.pages.destroy', page.id))}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </>
                )}
            />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1fr_18rem]">
                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <Field label={t('Title')} error={form.errors.title} required>
                            <Input value={d.title} onChange={(e) => form.setData('title', e.target.value)} autoFocus={!page} />
                        </Field>
                        <Field label={t('Text')} error={form.errors.body}>
                            <Textarea rows={20} className="font-mono text-sm" value={d.body} onChange={(e) => form.setData('body', e.target.value)} />
                        </Field>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <Field label={t('Web address')} error={form.errors.slug} hint={t('Empty = generated from the title.')}>
                                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                    /p/<Input value={d.slug} onChange={(e) => form.setData('slug', e.target.value)} placeholder="o-nas" />
                                </div>
                            </Field>
                            <SwitchRow checked={d.is_published} onChange={(v) => form.setData('is_published', v)}
                                label={t('Published')} description={t('Drafts are only visible to editors.')} />
                            <Button type="submit" className="w-full" disabled={form.processing}>{t('Save')}</Button>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="space-y-1.5 pt-6 text-xs text-muted-foreground">
                            <p className="font-medium text-foreground">{t('Formatting')}</p>
                            <p><code>## {t('Heading')}</code></p>
                            <p><code>**{t('bold')}**</code> · <code>*{t('italic')}*</code></p>
                            <p><code>- {t('list item')}</code></p>
                            <p><code>[{t('link text')}](https://…)</code></p>
                            <p>{t('An empty line starts a new paragraph.')}</p>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}
