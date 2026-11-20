import { AlertCircle, CheckCheck, Clock, Eye, Inbox, Mail } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';

export type Channel = 'mail' | 'portal';

export interface MessageSummary {
    id: number;
    subject: string;
    channels: Channel[];
    audience: string | null;
    sender: string | null;
    recipients_count: number;
    sent_count: number;
    failed_count: number;
    pending_count: number;
    read_count: number;
    created_at: string;
    completed: boolean;
}

export function ChannelIcons({ channels }: { channels: Channel[] }) {
    const { t } = useTranslation();

    return (
        <span className="inline-flex shrink-0 gap-1 text-muted-foreground">
            {channels.includes('mail') && <Mail className="h-3.5 w-3.5" aria-label={t('Email')} />}
            {channels.includes('portal') && <Inbox className="h-3.5 w-3.5" aria-label={t('Portal inbox')} />}
        </span>
    );
}

/** Prejemniki, poslano / v čakalni vrsti / napake, prebrano v portalu. */
export function DeliveryStats({ message: m }: { message: MessageSummary }) {
    const { t, tc } = useTranslation();

    return (
        <div className="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span className="font-medium text-foreground">{tc(':count recipients', m.recipients_count)}</span>
            {m.channels.includes('mail') && (
                <>
                    <span className="inline-flex items-center gap-1" title={t('Emails sent')}><CheckCheck className="h-3.5 w-3.5 text-emerald-600" />{m.sent_count}</span>
                    {m.pending_count > 0 && <span className="inline-flex items-center gap-1" title={t('Waiting to be sent')}><Clock className="h-3.5 w-3.5" />{m.pending_count}</span>}
                    {m.failed_count > 0 && <span className="inline-flex items-center gap-1 text-destructive" title={t('Failed')}><AlertCircle className="h-3.5 w-3.5" />{m.failed_count}</span>}
                </>
            )}
            {m.channels.includes('portal') && (
                <span className="inline-flex items-center gap-1" title={t('Read in the portal')}><Eye className="h-3.5 w-3.5" />{m.read_count}</span>
            )}
        </div>
    );
}
