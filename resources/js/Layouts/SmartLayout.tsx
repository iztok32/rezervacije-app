import { usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';

/** Prijavljeni vidijo stran v aplikaciji (s stransko vrstico), gostje v javni postavitvi. */
export default function SmartLayout({ children, header }: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage<PageProps>().props;

    return auth.user
        ? <AppLayout header={header}>{children}</AppLayout>
        : <PublicLayout><div className="mx-auto max-w-5xl px-4 py-8">{children}</div></PublicLayout>;
}
