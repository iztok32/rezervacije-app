import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { CalendarCheck, Mail, MapPin, Phone } from 'lucide-react';
import FlashToaster from '@/Components/FlashToaster';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';

/** Javna postavitev (ponudba, rezervacija kot gost, prijava). */
export default function PublicLayout({ children, wide = false }: PropsWithChildren<{ wide?: boolean }>) {
    const { auth, business } = usePage<PageProps>().props;
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <header className="sticky top-0 z-30 border-b bg-background/85 backdrop-blur">
                <div className={`mx-auto flex h-16 items-center justify-between gap-4 px-4 ${wide ? 'max-w-7xl' : 'max-w-5xl'}`}>
                    <Link href={route('home')} className="flex min-w-0 items-center gap-2 font-semibold">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <CalendarCheck className="h-4 w-4" />
                        </span>
                        <span className="truncate">{business.name}</span>
                    </Link>
                    <nav className="flex items-center gap-1">
                        <LanguageSwitcher />
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={route('dashboard')}>{t('My account')}</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={route('login')}>{t('Log in')}</Link>
                                </Button>
                                {business.allow_registration && (
                                    <Button asChild size="sm" className="hidden sm:inline-flex">
                                        <Link href={route('register')}>{t('Register')}</Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t bg-muted/40">
                <div className={`mx-auto flex flex-col gap-3 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between ${wide ? 'max-w-7xl' : 'max-w-5xl'}`}>
                    <span className="font-medium text-foreground">{business.name}</span>
                    <div className="flex flex-wrap gap-x-5 gap-y-1">
                        {business.address && <span className="inline-flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5" />{business.address}</span>}
                        {business.phone && <a href={`tel:${business.phone}`} className="inline-flex items-center gap-1.5 hover:text-foreground"><Phone className="h-3.5 w-3.5" />{business.phone}</a>}
                        {business.email && <a href={`mailto:${business.email}`} className="inline-flex items-center gap-1.5 hover:text-foreground"><Mail className="h-3.5 w-3.5" />{business.email}</a>}
                    </div>
                </div>
            </footer>

            <FlashToaster />
        </div>
    );
}
