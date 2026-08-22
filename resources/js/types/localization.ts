export type UiLocale = 'ro' | 'en';

export type LocalizationProps = {
    locale: UiLocale;
    fallbackLocale: 'en';
    supportedLocales: UiLocale[];
};
