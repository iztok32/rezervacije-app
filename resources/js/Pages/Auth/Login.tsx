import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword: boolean }) {
    const { business } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '', remember: false });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title={t('Log in')}>
            <Head title={t('Log in')} />
            {status && <div className="mb-4 text-sm font-medium text-emerald-600">{status}</div>}

            <form onSubmit={submit} className="space-y-4">
                <Field label={t('Email')} htmlFor="email" error={errors.email}>
                    <Input id="email" type="email" value={data.email} autoComplete="username" autoFocus onChange={(e) => setData('email', e.target.value)} />
                </Field>
                <Field label={t('Password')} htmlFor="password" error={errors.password}>
                    <Input id="password" type="password" value={data.password} autoComplete="current-password" onChange={(e) => setData('password', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={data.remember} onCheckedChange={(v) => setData('remember', v === true)} />
                    {t('Remember me')}
                </label>
                <Button type="submit" className="w-full" disabled={processing}>{t('Log in')}</Button>
                <div className="flex justify-between text-sm">
                    {canResetPassword && <Link href={route('password.request')} className="text-muted-foreground underline hover:text-foreground">{t('Forgot your password?')}</Link>}
                    {business.allow_registration && <Link href={route('register')} className="font-medium text-primary underline">{t('Register')}</Link>}
                </div>
            </form>
        </GuestLayout>
    );
}
