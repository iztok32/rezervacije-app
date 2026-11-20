import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { CalendarClock, Clock, Moon, Timer, Wand2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Field from '@/Components/Field';
import { SwitchRow } from '@/Components/form/inputs';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Option } from '@/types';

const MODE_ICONS: Record<string, typeof Clock> = { timeslot: CalendarClock, service: Timer, nightly: Moon, hourly: Clock };

const MODE_EXAMPLES: Record<string, string> = {
    timeslot: 'Riding lessons, group classes, workshops',
    service: 'Hair salon, massage, consultations',
    nightly: 'Rooms, holiday houses, car or equipment rental',
    hourly: 'Courts, halls, meeting rooms',
};

/** Nov tip vira: iz predloge (z vzorčnimi podatki) ali prazen z izbranim načinom. */
export default function Create({ presets, modes }: { presets: (Option & { mode: string })[]; modes: Option[] }) {
    const { t } = useTranslation();
    const form = useForm({ preset: '' as string, with_samples: true, name: '', booking_mode: '' as string });

    const choosePreset = (p: Option & { mode: string }) => {
        form.setData({ ...form.data, preset: p.value, booking_mode: '', name: form.data.name || p.label.replace(/\s*\(.*\)$/, '') });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('admin.resource-types.store'));
    };

    return (
        <AppLayout header={t('New offer')}>
            <Head title={t('New offer')} />
            <PageHeader title={t('New offer')} description={t('Start from a template or choose a booking mode. Everything can be changed later.')} />

            <form onSubmit={submit} className="space-y-6">
                <section>
                    <h2 className="mb-3 flex items-center gap-2 font-semibold"><Wand2 className="h-4 w-4" />{t('Templates')}</h2>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {presets.map((p) => {
                            const Icon = MODE_ICONS[p.mode];
                            return (
                                <button key={p.value} type="button" onClick={() => choosePreset(p)}
                                    className={cn('flex items-center gap-3 rounded-lg border bg-card p-3 text-left text-sm transition', form.data.preset === p.value ? 'border-primary ring-2 ring-primary/30' : 'hover:border-primary/50')}>
                                    <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />{p.label}
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section>
                    <h2 className="mb-3 font-semibold">{t('…or an empty type with a booking mode')}</h2>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {modes.map((m) => {
                            const Icon = MODE_ICONS[m.value];
                            return (
                                <button key={m.value} type="button" onClick={() => form.setData({ ...form.data, booking_mode: m.value, preset: '' })}
                                    className={cn('flex items-start gap-3 rounded-lg border bg-card p-3 text-left transition', form.data.booking_mode === m.value ? 'border-primary ring-2 ring-primary/30' : 'hover:border-primary/50')}>
                                    <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span>
                                        <span className="block text-sm font-medium">{m.label}</span>
                                        <span className="block text-xs text-muted-foreground">{t(MODE_EXAMPLES[m.value])}</span>
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {form.errors.booking_mode && <p className="mt-2 text-xs text-destructive">{form.errors.booking_mode}</p>}
                </section>

                <Card className="max-w-xl">
                    <CardContent className="space-y-4 pt-6">
                        <Field label={t('Name')} htmlFor="name" error={form.errors.name} required hint={t('Shown to customers, e.g. "Hair salon" or "Rooms".')}>
                            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        {form.data.preset && (
                            <SwitchRow checked={form.data.with_samples} onChange={(v) => form.setData('with_samples', v)}
                                label={t('Add sample data')} description={t('Sample resources, services, sessions and working hours that you can edit.')} />
                        )}
                        <Button type="submit" disabled={form.processing || (!form.data.preset && !form.data.booking_mode)}>{t('Create')}</Button>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
