import { usePage } from '@inertiajs/react';

const INTL_LOCALES: Record<string, string> = { sl: 'sl-SI', en: 'en-GB', hr: 'hr-HR', de: 'de-DE', it: 'it-IT' };

export function intlLocale(locale: string): string {
    return INTL_LOCALES[locale] ?? locale;
}

/**
 * Indeks množinske oblike. Prevod vsebuje oblike, ločene z "|":
 * sl: ednina | dvojina | 3–4 | 5+ (1 noč | 2 noči | 3 noči | 5 noči), ostali jeziki: 1 | več.
 */
function pluralIndex(locale: string, n: number): number {
    const abs = Math.abs(n);
    const mod100 = abs % 100;

    if (locale === 'sl') {
        if (mod100 === 1) return 0;
        if (mod100 === 2) return 1;
        if (mod100 === 3 || mod100 === 4) return 2;
        return 3;
    }

    return abs === 1 ? 0 : 1;
}

/**
 * Prevodi iz lang/{locale}.json (deljeni prek Inertia). Ključ je angleško besedilo;
 * brez prevoda se prikaže ključ.
 */
export function useTranslation() {
    const { props } = usePage();
    const translations = (props.translations ?? {}) as Record<string, string>;
    const locale = (props.locale as string) ?? 'sl';
    const currency = ((props.business as { currency?: string } | undefined)?.currency) ?? 'EUR';

    const replace = (text: string, replacements: Record<string, string | number>) =>
        Object.keys(replacements).reduce(
            (result, key) => result.split(`:${key}`).join(String(replacements[key])),
            text,
        );

    const t = (key: string, replacements: Record<string, string | number> = {}) =>
        replace(translations[key] || key, replacements);

    /** Prevod z množino: tc(':count nights', 3) → "3 noči". */
    const tc = (key: string, count: number, replacements: Record<string, string | number> = {}) => {
        const forms = (translations[key] || key).split('|');
        const form = forms[Math.min(pluralIndex(locale, count), forms.length - 1)];

        return replace(form, { count, ...replacements });
    };

    const money = (amount: number | null | undefined) =>
        amount === null || amount === undefined
            ? ''
            : new Intl.NumberFormat(intlLocale(locale), { style: 'currency', currency }).format(amount);

    return { t, tc, money, locale, availableLocales: (props.availableLocales as string[]) ?? ['sl'] };
}
