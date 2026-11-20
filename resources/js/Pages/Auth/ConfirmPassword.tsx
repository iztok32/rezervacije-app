import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';

export default function ConfirmPassword() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title={t('Confirm password')} description={t('Please confirm your password before continuing.')}>
            <Head title={t('Confirm password')} />
            <form onSubmit={submit} className="space-y-4">
                <Field label={t('Password')} htmlFor="password" error={errors.password}>
                    <Input id="password" type="password" value={data.password} autoFocus onChange={(e) => setData('password', e.target.value)} />
                </Field>
                <Button type="submit" className="w-full" disabled={processing}>{t('Confirm')}</Button>
            </form>
        </GuestLayout>
    );
}
