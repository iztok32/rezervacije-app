import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ token, email, password: '', password_confirmation: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout title={t('Set a new password')}>
            <Head title={t('Set a new password')} />
            <form onSubmit={submit} className="space-y-4">
                <Field label={t('Email')} htmlFor="email" error={errors.email}>
                    <Input id="email" type="email" value={data.email} autoComplete="username" onChange={(e) => setData('email', e.target.value)} />
                </Field>
                <Field label={t('Password')} htmlFor="password" error={errors.password}>
                    <Input id="password" type="password" value={data.password} autoComplete="new-password" autoFocus onChange={(e) => setData('password', e.target.value)} />
                </Field>
                <Field label={t('Confirm password')} htmlFor="password_confirmation" error={errors.password_confirmation}>
                    <Input id="password_confirmation" type="password" value={data.password_confirmation} autoComplete="new-password" onChange={(e) => setData('password_confirmation', e.target.value)} />
                </Field>
                <Button type="submit" className="w-full" disabled={processing}>{t('Save password')}</Button>
            </form>
        </GuestLayout>
    );
}
