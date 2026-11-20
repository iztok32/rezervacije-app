import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';
import {
    Ban, CalendarCheck, CalendarDays, ChevronsUpDown, ClipboardList, Home, LayoutDashboard, LogOut,
    Settings, Shapes, UserRound, Users,
} from 'lucide-react';
import FlashToaster from '@/Components/FlashToaster';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarHeader,
    SidebarInset, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem, SidebarProvider, SidebarTrigger,
} from '@/Components/ui/sidebar';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { TypeIcon } from '@/lib/icons';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: ReactNode;
    active: boolean;
    badge?: number;
}

/** Postavitev za prijavljene uporabnike: stranska vrstica glede na vlogo. */
export default function AppLayout({ children, header }: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, business, bookableTypes, pendingBookings } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const user = auth.user!;
    const current = (name: string) => route().current(name);

    const main: NavItem[] = [
        { label: t('Overview'), href: route('dashboard'), icon: <LayoutDashboard />, active: current('dashboard') },
        { label: t('My bookings'), href: route('my-bookings'), icon: <ClipboardList />, active: current('my-bookings') },
    ];

    const staff: NavItem[] = user.is_staff ? [
        { label: t('Bookings'), href: route('admin.bookings.index'), icon: <CalendarCheck />, active: current('admin.bookings.*'), badge: pendingBookings },
        { label: t('Calendar'), href: route('admin.calendar'), icon: <CalendarDays />, active: current('admin.calendar') },
    ] : [];

    const admin: NavItem[] = user.is_admin ? [
        { label: t('Offer'), href: route('admin.resource-types.index'), icon: <Shapes />, active: current('admin.resource-types.*') },
        { label: t('Closures'), href: route('admin.closures.index'), icon: <Ban />, active: current('admin.closures.*') },
        { label: t('Users'), href: route('admin.users.index'), icon: <Users />, active: current('admin.users.*') },
        { label: t('Settings'), href: route('admin.settings.index'), icon: <Settings />, active: current('admin.settings.*') },
    ] : [];

    const renderItems = (items: NavItem[]) => items.map((item) => (
        <SidebarMenuItem key={item.href}>
            <SidebarMenuButton asChild isActive={item.active} tooltip={item.label}>
                <Link href={item.href}>
                    {item.icon}
                    <span>{item.label}</span>
                </Link>
            </SidebarMenuButton>
            {!!item.badge && <SidebarMenuBadge className="bg-amber-500 text-white">{item.badge}</SidebarMenuBadge>}
        </SidebarMenuItem>
    ));

    return (
        <SidebarProvider>
            <Sidebar collapsible="icon">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={route('home')}>
                                    <span className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                        <CalendarCheck className="size-4" />
                                    </span>
                                    <span className="truncate font-semibold">{business.name}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu>{renderItems(main)}</SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>

                    {bookableTypes.length > 0 && (
                        <SidebarGroup>
                            <SidebarGroupLabel>{t('Book')}</SidebarGroupLabel>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {bookableTypes.map((type) => (
                                        <SidebarMenuItem key={type.id}>
                                            <SidebarMenuButton asChild tooltip={type.name}
                                                isActive={current('book.show') && route().params.resourceType === type.slug}>
                                                <Link href={route('book.show', type.slug)}>
                                                    <TypeIcon name={type.icon} />
                                                    <span>{type.name}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    )}

                    {staff.length > 0 && (
                        <SidebarGroup>
                            <SidebarGroupLabel>{t('Management')}</SidebarGroupLabel>
                            <SidebarGroupContent>
                                <SidebarMenu>{renderItems(staff)}</SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    )}

                    {admin.length > 0 && (
                        <SidebarGroup>
                            <SidebarGroupLabel>{t('Administration')}</SidebarGroupLabel>
                            <SidebarGroupContent>
                                <SidebarMenu>{renderItems(admin)}</SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    )}
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton size="lg">
                                        <span className="flex size-8 items-center justify-center rounded-full bg-sidebar-accent text-sm font-semibold">
                                            {user.name.charAt(0).toUpperCase()}
                                        </span>
                                        <span className="grid flex-1 text-left text-sm leading-tight">
                                            <span className="truncate font-medium">{user.name}</span>
                                            <span className="truncate text-xs text-muted-foreground">{user.email}</span>
                                        </span>
                                        <ChevronsUpDown className="ml-auto size-4" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent side="top" align="start" className="w-56">
                                    <DropdownMenuLabel className="font-normal text-muted-foreground">{user.email}</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link href={route('profile.edit')}><UserRound className="mr-2 h-4 w-4" />{t('Profile')}</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route('home')}><Home className="mr-2 h-4 w-4" />{t('Public page')}</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onSelect={() => router.post(route('logout'))}>
                                        <LogOut className="mr-2 h-4 w-4" />{t('Log out')}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            </Sidebar>

            <SidebarInset className="min-w-0">
                <header className="sticky top-0 z-20 flex h-14 items-center gap-2 border-b bg-background/85 px-4 backdrop-blur">
                    <SidebarTrigger className="-ml-1" />
                    <div className="min-w-0 flex-1 truncate text-sm font-medium">{header}</div>
                    <LanguageSwitcher />
                </header>
                <div className="mx-auto w-full min-w-0 max-w-6xl flex-1 p-4 md:p-6">{children}</div>
            </SidebarInset>

            <FlashToaster />
        </SidebarProvider>
    );
}
