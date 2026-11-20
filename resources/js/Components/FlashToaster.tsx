import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { Toaster, toast } from 'sonner';
import { PageProps } from '@/types';

/** Prikaže sporočila iz seje (flash) kot obvestila. */
export default function FlashToaster() {
    const { flash } = usePage<PageProps>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    return <Toaster position="top-right" richColors closeButton />;
}
