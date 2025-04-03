<?php

class MM_WPFS_Languages {
    /**
	 * Creates an array of locales/languages supported by Stripe Checkout.
	 *
	 * @return array list of locales/languages
	 */
    public static function getCheckoutLanguages() {
        return array(
            array(
                'value' => 'bg',
                'name' => __('Bulgarian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'cs',
                'name' => __('Czech', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'da',
                'name' => __('Danish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'de',
                'name' => __('German', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'el',
                'name' => __('Greek', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'en',
                'name' => __('English', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'en-GB',
                'name' => __('English (United Kingdom)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'es',
                'name' => __('Spanish (Spain)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'es-419',
                'name' => __('Spanish (Latin America)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'et',
                'name' => __('Estonian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fi',
                'name' => __('Finnish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fil',
                'name' => __('Filipino', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fr',
                'name' => __('French (France)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fr-CA',
                'name' => __('French (Canada)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'hr',
                'name' => __('Croatian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'hu',
                'name' => __('Hungarian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'id',
                'name' => __('Indonesian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'it',
                'name' => __('Italian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ja',
                'name' => __('Japanese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ko',
                'name' => __('Korean', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'lv',
                'name' => __('Lithuanian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'lt',
                'name' => __('Latvian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ms',
                'name' => __('Malay', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'mt',
                'name' => __('Maltese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'nb',
                'name' => __('Norwegian Bokmål', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'nl',
                'name' => __('Dutch', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'pl',
                'name' => __('Polish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'pt',
                'name' => __('Portuguese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'pt-BR',
                'name' => __('Portuguese (Brazil)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ro',
                'name' => __('Romanian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ru',
                'name' => __('Russian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'sk',
                'name' => __('Slovak', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'sl',
                'name' => __('Slovenian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'sv',
                'name' => __('Swedish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'th',
                'name' => __('Thai', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'tr',
                'name' => __('Turkish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'vi',
                'name' => __('Vietnamese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'zh',
                'name' => __('Simplified Chinese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'zh-HK',
                'name' => __('Chinese Traditional (Hong Kong)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'zh-TW',
                'name' => __('Chinese Traditional (Taiwan)', 'wp-full-stripe-free')
            )
        );
    }

    public static function getCheckoutLanguageCodes() {
        $languages = MM_WPFS_Languages::getCheckoutLanguages();
        $languageCodes = array();

        foreach ($languages as $language) {
            array_push($languageCodes, $language['value']);
        }

        return $languageCodes;
    }

    /**
     * Creates an array of locales/languages supported by Stripe Elements.
     *
     * @return array list of locales/languages
     */
    public static function getStripeElementsLanguages() {
        return array(
            array(
                'value' => 'ar',
                'name' => __('Arabic', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'bg',
                'name' => __('Bulgarian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'cs',
                'name' => __('Czech', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'da',
                'name' => __('Danish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'de',
                'name' => __('German', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'el',
                'name' => __('Greek', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'en',
                'name' => __('English', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'en-GB',
                'name' => __('English (United Kingdom)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'es',
                'name' => __('Spanish (Spain)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'es-419',
                'name' => __('Spanish (Latin America)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'et',
                'name' => __('Estonian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fi',
                'name' => __('Finnish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fil',
                'name' => __('Filipino', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fr',
                'name' => __('French (France)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'fr-CA',
                'name' => __('French (Canada)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'he',
                'name' => __('Hebrew', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'hr',
                'name' => __('Croatian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'hu',
                'name' => __('Hungarian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'id',
                'name' => __('Indonesian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'it',
                'name' => __('Italian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ja',
                'name' => __('Japanese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ko',
                'name' => __('Korean', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'lv',
                'name' => __('Lithuanian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'lt',
                'name' => __('Latvian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ms',
                'name' => __('Malay', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'mt',
                'name' => __('Maltese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'nb',
                'name' => __('Norwegian Bokmål', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'nl',
                'name' => __('Dutch', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'pl',
                'name' => __('Polish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'pt',
                'name' => __('Portuguese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'pt-BR',
                'name' => __('Portuguese (Brazil)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ro',
                'name' => __('Romanian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'ru',
                'name' => __('Russian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'sk',
                'name' => __('Slovak', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'sl',
                'name' => __('Slovenian', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'sv',
                'name' => __('Swedish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'th',
                'name' => __('Thai', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'tr',
                'name' => __('Turkish', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'vi',
                'name' => __('Vietnamese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'zh',
                'name' => __('Simplified Chinese', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'zh-HK',
                'name' => __('Chinese Traditional (Hong Kong)', 'wp-full-stripe-free')
            ),
            array(
                'value' => 'zh-TW',
                'name' => __('Chinese Traditional (Taiwan)', 'wp-full-stripe-free')
            )
        );
    }

    public static function getStripeElementsLanguageCodes() {
        $languages = self::getStripeElementsLanguages();
        $languageCodes = array();

        foreach ($languages as $language) {
            array_push($languageCodes, $language['value']);
        }

        return $languageCodes;
    }
}
