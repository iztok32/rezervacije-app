import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/lib/i18n';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout title={t('Verify your email')} description={t('Please confirm your email address by clicking the link we sent you.')}>
            <Head title={t('Verify your email')} />
            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-emerald-600">{t('A new verification link has been sent to your email address.')}</div>
            )}
            <form onSubmit={submit} className="flex items-center justify-between">
                <Button disabled={processing}>{t('Resend verification email')}</Button>
                <Link href={route('logout')} method="post" as="button" className="text-sm text-muted-foreground underline">{t('Log out')}</Link>
            </form>
        </GuestLayout>
    );
}
