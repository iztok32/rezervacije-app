import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout title={t('Forgot your password?')} description={t('Enter your email and we will send you a link to set a new password.')}>
            <Head title={t('Forgot your password?')} />
            {status && <div className="mb-4 text-sm font-medium text-emerald-600">{status}</div>}
            <form onSubmit={submit} className="space-y-4">
                <Field label={t('Email')} htmlFor="email" error={errors.email}>
                    <Input id="email" type="email" value={data.email} autoFocus onChange={(e) => setData('email', e.target.value)} />
                </Field>
                <Button type="submit" className="w-full" disabled={processing}>{t('Email password reset link')}</Button>
            </form>
        </GuestLayout>
    );
}
