<?php
namespace SingleQuote\LocaleFinder\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\Finder\Finder;

class FindAndAddLanguageKeysCommand extends Command
{

    const STORAGE_DISK = 'lang';
    const LOCALES = ['nl', 'en', 'de'];

    /**
     * @var  string
     */
    protected $signature = 'language:find-and-add';

    /**
     * @var  string
     */
    protected $description = 'Automatically find, translate and save missing translation keys.';
    private GoogleTranslate $googleTranslate;
    
    /**
     * @param GoogleTranslate $googleTranslate
     */
    public function __construct(GoogleTranslate $googleTranslate)
    {
        $this->googleTranslate = $googleTranslate;
        parent::__construct();
    }
    
    /**
     * 
     */
    public function handle()
    {
        $alreadyTranslated = $this->loadAllSavedTranslations();
        $translationsKeys = $this->findKeysInFiles();
        $this->translateAndSaveNewKeys($translationsKeys, $alreadyTranslated);
        $this->info('success');
    }
    
    /**
     * @return array
     */
    private function loadAllSavedTranslations(): array
    {
        $path = Storage::disk('localeFinder')->path('');
        $finder = new Finder();
        $finder->in($path)->name(['*.json'])->files();
        $translations = [];
        foreach ($finder as $file) {
            $locale = $file->getFilenameWithoutExtension();
            if (!in_array($locale, self::LOCALES)) {
                continue;
            }
            $this->info('loading: ' . $locale);
            $jsonString = $file->getContents();
            $translations[$locale] = json_decode($jsonString, true);
        }

        return $translations;
    }
    
    /**
     * @return array
     */
    private function findKeysInFiles(): array
    {
        $path = [resource_path('views')];
        $functions = ['\$t', 'i18n.t', '@lang', '__'];
        $pattern = "[^\w|>]" . // Must not have an alphanum or _ or > before real method
            "(" . implode('|', $functions) . ")" . // Must start with one of the functions
            "\(" . // Match opening parenthese
            "[\'\"]" . // Match " or '
            "(" . // Start a new group to match:
            "([^\1)]+)+" . // this is the key/value
            ")" . // Close group
            "[\'\"]" . // Closing quote
            "[\),]";                            // Close parentheses or new parameter
        $finder = new Finder();
        $finder->in($path)->exclude('storage')->name(['*.php'])->files();
        $this->info('> ' . $finder->count() . ' php files found');
        $keys = [];
        foreach ($finder as $file) {
            if (preg_match_all("/$pattern/siU", $file->getContents(), $matches)) {
                if (count($matches) < 2) {
                    continue;
                }
                $this->info('>> ' . count($matches[2]) . ' keys found for ' . $file->getFilename());
                foreach ($matches[2] as $key) {
                    if (strlen($key) < 2) {
                        continue;
                    }
                    $keys[$key] = '';
                }
            }
        }
        uksort($keys, 'strnatcasecmp');

        return $keys;
    }
    
    /**
     * @param array $translationsKeys
     * @param array $alreadyTranslated
     */
    private function translateAndSaveNewKeys(array $translationsKeys, array $alreadyTranslated)
    {
        foreach (self::LOCALES as $locale) {
            $newKeysFound = array_diff_key($translationsKeys, $alreadyTranslated[$locale]);
            if (count($newKeysFound) < 1) {
                continue;
            }
            $this->info(count($newKeysFound) . ' new keys found for "' . $locale . '"');
            $newKeysWithValues = $this->translateKeys($locale, $newKeysFound);
                        
            $this->saveToFile($locale, $newKeysWithValues, $alreadyTranslated[$locale]);
        }
    }

    private function translateKeys(string $locale, array $keys): array
    {
        foreach ($keys as $keyIndex => $keyValue) {
            $keys[$keyIndex] = $this->translateKey($locale, $keyIndex);
        }

        return $keys;
    }
    
    /**
     * @param string $locale
     * @param string $key
     * @return string
     */
    private function translateKey(string $locale, string $key): string
    {
        if ($locale === 'en') {
            return $key;
        }
        try {
            $this->googleTranslate->setTarget($locale);
            $translated = $this->googleTranslate->translate($key);
        } catch (Exception $exception) {
            Log::warning('Google translate issue with ' . $key . ': ' . $exception->getMessage());
            $translated = $key;
        }

        return $translated;
    }
    
    /**
     * @param string $locale
     * @param array $newKeysWithValues
     * @param array $alreadyTranslated
     */
    private function saveToFile(string $locale, array $newKeysWithValues, array $alreadyTranslated)
    {
        $localeTranslations = array_merge($newKeysWithValues, $alreadyTranslated);
        uksort($localeTranslations, 'strnatcasecmp');
        Storage::disk('localeFinder')->put("$locale.json", json_encode($localeTranslations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
