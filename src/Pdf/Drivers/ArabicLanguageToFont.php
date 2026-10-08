<?php

namespace BiztechEG\EasyPdfWord\Pdf\Drivers;

use Mpdf\Language\LanguageToFont;

/**
 * mPDF picks a font per language. This keeps mPDF's choices for every
 * script (Chinese, Hindi, ...) but sends Arabic-script text to the
 * document's own Arabic font instead of mPDF's default XB Riyaz.
 */
class ArabicLanguageToFont extends LanguageToFont
{
    private const ARABIC_SCRIPT_LANGUAGES = ['ar', 'ara', 'fa', 'fas', 'ps', 'pus', 'ku', 'kur', 'ur', 'urd', 'ckb', 'sd', 'ug'];

    public function __construct(private string $arabicFont) {}

    public function getLanguageOptions($llcc, $adobeCJK)
    {
        $tags = array_map('strtolower', explode('-', (string) $llcc));
        $isArabic = in_array($tags[0], self::ARABIC_SCRIPT_LANGUAGES, true) || in_array('arab', $tags, true);

        if ($isArabic) {
            return [false, $this->arabicFont];
        }

        return parent::getLanguageOptions($llcc, $adobeCJK);
    }
}
