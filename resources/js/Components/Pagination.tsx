import { Link } from '@inertiajs/react';
import { PaginationLink } from '@/types';
import { cn } from '@/lib/utils';

export default function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;

    return (
        <nav className="mt-4 flex flex-wrap justify-center gap-1">
            {links.map((link, i) => {
                const label = link.label.replace('&laquo;', '«').replace('&raquo;', '»');
                return link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        className={cn(
                            'min-w-9 rounded-md border px-3 py-1.5 text-center text-sm',
                            link.active ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-accent',
                        )}
                        dangerouslySetInnerHTML={{ __html: label }}
                    />
                ) : (
                    <span key={i} className="min-w-9 rounded-md border px-3 py-1.5 text-center text-sm text-muted-foreground opacity-50" dangerouslySetInnerHTML={{ __html: label }} />
                );
            })}
        </nav>
    );
}
