import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';

export default function Register() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', email: '', phone: '', password: '', password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout title={t('Register')} description={t('With an account you can see and manage all your bookings.')}>
            <Head title={t('Register')} />
            <form onSubmit={submit} className="space-y-4">
                <Field label={t('Name and surname')} htmlFor="name" error={errors.name} required>
                    <Input id="name" value={data.name} autoComplete="name" autoFocus onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label={t('Email')} htmlFor="email" error={errors.email} required>
                    <Input id="email" type="email" value={data.email} autoComplete="username" onChange={(e) => setData('email', e.target.value)} />
                </Field>
                <Field label={t('Phone')} htmlFor="phone" error={errors.phone}>
                    <Input id="phone" type="tel" value={data.phone} autoComplete="tel" onChange={(e) => setData('phone', e.target.value)} />
                </Field>
                <Field label={t('Password')} htmlFor="password" error={errors.password} required>
                    <Input id="password" type="password" value={data.password} autoComplete="new-password" onChange={(e) => setData('password', e.target.value)} />
                </Field>
                <Field label={t('Confirm password')} htmlFor="password_confirmation" error={errors.password_confirmation} required>
                    <Input id="password_confirmation" type="password" value={data.password_confirmation} autoComplete="new-password" onChange={(e) => setData('password_confirmation', e.target.value)} />
                </Field>
                <Button type="submit" className="w-full" disabled={processing}>{t('Register')}</Button>
                <p className="text-center text-sm text-muted-foreground">
                    {t('Already registered?')} <Link href={route('login')} className="font-medium text-primary underline">{t('Log in')}</Link>
                </p>
            </form>
        </GuestLayout>
    );
}
