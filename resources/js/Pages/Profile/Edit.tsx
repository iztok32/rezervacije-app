import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Field from '@/Components/Field';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';

/** Profil: osebni podatki, geslo, izbris računa. */
export default function Edit({ status }: { mustVerifyEmail: boolean; status?: string }) {
    const user = usePage<PageProps>().props.auth.user!;
    const { t } = useTranslation();

    const info = useForm({ name: user.name, email: user.email, phone: user.phone ?? '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const remove = useForm({ password: '' });
    const [confirmDelete, setConfirmDelete] = useState(false);

    const saveInfo = (e: FormEvent) => { e.preventDefault(); info.patch(route('profile.update'), { preserveScroll: true }); };
    const savePassword = (e: FormEvent) => {
        e.preventDefault();
        password.put(route('password.update'), { preserveScroll: true, onSuccess: () => password.reset() });
    };
    const deleteAccount = (e: FormEvent) => {
        e.preventDefault();
        remove.delete(route('profile.destroy'), { preserveScroll: true, onFinish: () => remove.reset() });
    };

    return (
        <AppLayout header={t('Profile')}>
            <Head title={t('Profile')} />
            <PageHeader title={t('Profile')} />

            <div className="max-w-2xl space-y-6">
                <Card>
                    <CardHeader><CardTitle className="text-base">{t('Personal information')}</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={saveInfo} className="space-y-4">
                            <Field label={t('Name and surname')} error={info.errors.name}><Input value={info.data.name} onChange={(e) => info.setData('name', e.target.value)} /></Field>
                            <Field label={t('Email')} error={info.errors.email}><Input type="email" value={info.data.email} onChange={(e) => info.setData('email', e.target.value)} /></Field>
                            <Field label={t('Phone')} error={info.errors.phone}><Input type="tel" value={info.data.phone} onChange={(e) => info.setData('phone', e.target.value)} /></Field>
                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={info.processing}>{t('Save')}</Button>
                                {info.recentlySuccessful && <span className="text-sm text-muted-foreground">{t('Saved.')}</span>}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">{t('Password')}</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={savePassword} className="space-y-4">
                            <Field label={t('Current password')} error={password.errors.current_password}>
                                <Input type="password" autoComplete="current-password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} />
                            </Field>
                            <Field label={t('New password')} error={password.errors.password}>
                                <Input type="password" autoComplete="new-password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} />
                            </Field>
                            <Field label={t('Confirm password')} error={password.errors.password_confirmation}>
                                <Input type="password" autoComplete="new-password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} />
                            </Field>
                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={password.processing}>{t('Change password')}</Button>
                                {password.recentlySuccessful && <span className="text-sm text-muted-foreground">{t('Saved.')}</span>}
                                {status && <span className="text-sm text-muted-foreground">{status}</span>}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Delete account')}</CardTitle>
                        <CardDescription>{t('Your account will be deleted. Past bookings remain on record for the business.')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="destructive" onClick={() => setConfirmDelete(true)}>{t('Delete account')}</Button>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <form onSubmit={deleteAccount} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>{t('Delete account?')}</DialogTitle>
                            <DialogDescription>{t('Enter your password to confirm.')}</DialogDescription>
                        </DialogHeader>
                        <Field error={remove.errors.password}>
                            <Input type="password" value={remove.data.password} onChange={(e) => remove.setData('password', e.target.value)} autoFocus />
                        </Field>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setConfirmDelete(false)}>{t('Cancel')}</Button>
                            <Button type="submit" variant="destructive" disabled={remove.processing}>{t('Delete account')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
