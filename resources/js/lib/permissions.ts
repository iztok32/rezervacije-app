import { usePage } from '@inertiajs/react';
import { PageProps, Permission } from '@/types';

/**
 * Preverjanje pravic v vmesniku (samo za prikaz — strežnik pravice vedno preveri sam).
 * Seznam pravic je v App\Enums\Permission.
 */
export function useCan(): (permission: Permission) => boolean {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];

    return (permission) => permissions.includes(permission);
}
