import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useState } from 'react';
import { CalendarCheck, Mail, MapPin, Menu, Phone } from 'lucide-react';
import FlashToaster from '@/Components/FlashToaster';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { Button } from '@/Components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { MenuLink, PageProps } from '@/types';

/** Povezava iz menija: notranja prek Inertie, zunanja kot običajna povezava. */
function MenuAnchor({ item, className, onClick }: { item: MenuLink; className?: string; onClick?: () => void }) {
    const active = !item.external && typeof window !== 'undefined' && window.location.pathname === new URL(item.href, window.location.origin).pathname;
    const target = item.new_tab ? { target: '_blank', rel: 'noopener noreferrer' } : {};
    const classes = cn(className, active && 'text-foreground font-medium');

    return item.external || item.new_tab
        ? <a href={item.href} className={classes} onClick={onClick} {...target}>{item.label}</a>
        : <Link href={item.href} className={classes} onClick={onClick}>{item.label}</Link>;
}

/** Javna postavitev (ponudba, rezervacija kot gost, prijava, vsebinske strani). */
export default function PublicLayout({ children, wide = false }: PropsWithChildren<{ wide?: boolean }>) {
    const { auth, business, menu } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const width = wide ? 'max-w-7xl' : 'max-w-5xl';
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <header className="sticky top-0 z-30 border-b bg-background/85 backdrop-blur">
                <div className={`mx-auto flex h-16 items-center justify-between gap-4 px-4 ${width}`}>
                    <Link href={route('home')} className="flex min-w-0 items-center gap-2 font-semibold">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <CalendarCheck className="h-4 w-4" />
                        </span>
                        <span className="truncate">{business.name}</span>
                    </Link>

                    {menu.header.length > 0 && (
                        <nav className="hidden flex-1 items-center gap-5 text-sm text-muted-foreground md:flex">
                            {menu.header.map((item) => <MenuAnchor key={item.id} item={item} className="transition hover:text-foreground" />)}
                        </nav>
                    )}

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
                        {menu.header.length > 0 && (
                            <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                                <SheetTrigger asChild>
                                    <Button variant="ghost" size="icon" className="md:hidden" aria-label={t('Menu')}><Menu className="h-5 w-5" /></Button>
                                </SheetTrigger>
                                <SheetContent side="right" className="w-72">
                                    <SheetHeader><SheetTitle>{business.name}</SheetTitle></SheetHeader>
                                    <nav className="mt-6 flex flex-col gap-1">
                                        {menu.header.map((item) => (
                                            <MenuAnchor key={item.id} item={item} onClick={() => setMenuOpen(false)} className="rounded-md px-3 py-2 text-sm hover:bg-accent" />
                                        ))}
                                    </nav>
                                </SheetContent>
                            </Sheet>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t bg-muted/40">
                <div className={`mx-auto flex flex-col gap-3 px-4 py-6 text-sm text-muted-foreground ${width}`}>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <span className="font-medium text-foreground">{business.name}</span>
                        <div className="flex flex-wrap gap-x-5 gap-y-1">
                            {business.address && <span className="inline-flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5" />{business.address}</span>}
                            {business.phone && <a href={`tel:${business.phone}`} className="inline-flex items-center gap-1.5 hover:text-foreground"><Phone className="h-3.5 w-3.5" />{business.phone}</a>}
                            {business.email && <a href={`mailto:${business.email}`} className="inline-flex items-center gap-1.5 hover:text-foreground"><Mail className="h-3.5 w-3.5" />{business.email}</a>}
                        </div>
                    </div>
                    {menu.footer.length > 0 && (
                        <nav className="flex flex-wrap gap-x-5 gap-y-1 border-t pt-3 text-xs">
                            {menu.footer.map((item) => <MenuAnchor key={item.id} item={item} className="hover:text-foreground" />)}
                        </nav>
                    )}
                </div>
            </footer>

            <FlashToaster />
        </div>
    );
}
