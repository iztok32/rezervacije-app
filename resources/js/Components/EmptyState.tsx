import { ReactNode } from 'react';
import { CalendarX2 } from 'lucide-react';

export default function EmptyState({ title, description, action, icon }: { title: string; description?: string; action?: ReactNode; icon?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed px-6 py-12 text-center">
            <div className="mb-3 text-muted-foreground">{icon ?? <CalendarX2 className="h-8 w-8" />}</div>
            <p className="font-medium">{title}</p>
            {description && <p className="mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
