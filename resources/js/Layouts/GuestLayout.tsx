import { PropsWithChildren, ReactNode } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

/** Prijava, registracija, ponastavitev gesla: kartica na sredini javne postavitve. */
export default function GuestLayout({ title, description, children }: PropsWithChildren<{ title: string; description?: ReactNode }>) {
    return (
        <PublicLayout>
            <div className="flex justify-center px-4 py-12">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <CardTitle className="text-xl">{title}</CardTitle>
                        {description && <CardDescription>{description}</CardDescription>}
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>
            </div>
        </PublicLayout>
    );
}
