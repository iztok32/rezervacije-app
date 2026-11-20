import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useTranslation } from '@/lib/i18n';

const NAMES: Record<string, string> = { sl: 'Slovenščina', en: 'English', de: 'Deutsch', it: 'Italiano', hr: 'Hrvatski' };

export default function LanguageSwitcher() {
    const { locale, availableLocales, t } = useTranslation();

    if (availableLocales.length < 2) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="gap-1.5" aria-label={t('Language')}>
                    <Languages className="h-4 w-4" />
                    <span className="uppercase">{locale}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {availableLocales.map((code) => (
                    <DropdownMenuItem
                        key={code}
                        onSelect={() => router.post(route('locale.update'), { locale: code }, { preserveScroll: true })}
                        className={code === locale ? 'font-semibold' : ''}
                    >
                        {NAMES[code] ?? code}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
