import { Head, Link } from '@inertiajs/react';
import { EyeOff, Pencil } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/lib/i18n';

interface Props {
    page: {
        title: string;
        /** Varen HTML iz Markdowna (pretvorjen na strežniku, brez surovega HTML-ja). */
        html: string;
        is_published: boolean;
        edit_url: string | null;
    };
}

/** Javna vsebinska stran (O nas, cenik, splošni pogoji …). */
export default function Page({ page }: Props) {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title={page.title} />
            <article className="mx-auto max-w-3xl px-4 py-10 md:py-14">
                {(!page.is_published || page.edit_url) && (
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-dashed px-3 py-2 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5">
                            {!page.is_published && <><EyeOff className="h-4 w-4" />{t('Draft — only visible to editors.')}</>}
                        </span>
                        {page.edit_url && (
                            <Button asChild variant="ghost" size="sm"><Link href={page.edit_url}><Pencil className="mr-1.5 h-3.5 w-3.5" />{t('Edit')}</Link></Button>
                        )}
                    </div>
                )}
                <h1 className="mb-6 text-3xl font-semibold tracking-tight">{page.title}</h1>
                <div className="prose prose-neutral max-w-none dark:prose-invert prose-a:text-primary" dangerouslySetInnerHTML={{ __html: page.html }} />
            </article>
        </PublicLayout>
    );
}
