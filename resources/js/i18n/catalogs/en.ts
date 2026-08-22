export const en = {
    'locale.label': 'Interface language',
    'locale.ro': 'Romanian',
    'locale.en': 'English',
    'locale.updating': 'Updating language…',
} as const;

export type TranslationKey = keyof typeof en;
