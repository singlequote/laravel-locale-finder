<?php
namespace SingleQuote\LocaleFinder\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Str;

class FindAndAddLanguageKeysCommand extends Command
{

    /**
     * @var  string
     */
    protected $signature = 'language:find-and-add {--locales=} {--notranslate}';

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
     * @return void
     */
    public function handle() : int
    {                
        if(!$this->option('locales')){
            $this->info('The --locales option is required! Use as --locales=nl,en,de');
            return 0;
        }
        
        $this->getTranslationFiles();
                
        $alreadyTranslated = $this->loadAllSavedTranslations();
        $translationsKeys = $this->findKeysInFiles();
        $this->translateAndSaveNewKeys($translationsKeys, $alreadyTranslated);
        $this->info("Finished");
        
        return 1;
    }
    
    /**
     * @return void
     */
    private function getTranslationFiles() : void
    {
        if($this->option('locales') !== 'all'){
            $this->locales = explode(',', $this->option('locales'));
            return;
        }
        
        $files = Storage::disk('localeFinder')->files();
        
        foreach($files as $file){
            if(Str::endsWith($file, '.json')){
                $this->locales[] = Str::before($file, '.json');
            }
        }
        
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
            if (!in_array($locale, $this->locales)) {
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
        $path = config('locale-finder.search.folders');
        
        $functions = config('locale-finder.translation_methods');
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
        
        $finder->in($path)->exclude(config('locale-finder.search.exclude'))
            ->name(config('locale-finder.search.file_extension'))
            ->files();

        $this->info('> ' . $finder->count() . ' files found');
        
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
    private function translateAndSaveNewKeys(array $translationsKeys, array $alreadyTranslated) : void
    {        
                        
        foreach ($this->locales as $locale) {     
            try{
                $newKeysFound = array_diff_key($translationsKeys, $alreadyTranslated[$locale]);
                
                $old = array_diff_key($alreadyTranslated[$locale], $translationsKeys);
                
                foreach($old as $key => $value){
                    $this->info("Removed $key keys from '$locale'");
                    unset($alreadyTranslated[$locale][$key]);
                }
                
            } catch (\Exception $ex) {
                $this->error("Could not find the '$locale' translation file");
                
                return;
            }
            
            if (count($newKeysFound) < 1) {
                $this->saveToFile($locale, $alreadyTranslated[$locale], $alreadyTranslated[$locale]);
                continue;
            }
            
            $this->info(count($newKeysFound) . ' new keys found for "' . $locale . '"');
            $newKeysWithValues = $this->translateKeys($locale, $newKeysFound);
                        
            $this->saveToFile($locale, $newKeysWithValues, $alreadyTranslated[$locale]);
        }
    }
    
    /**
     * @param string $locale
     * @param array $keys
     * @return array
     */
    private function translateKeys(string $locale, array $keys): array
    {
        foreach ($keys as $keyIndex => $keyValue) {
            if($keyValue === '...'){
                continue;
            }
            
            if(Str::contains($keyIndex, ":")){
                $shouldTranslate = $this->removeVariables($keyIndex);
            }else{
                $shouldTranslate = $keyIndex;
            }
            if($this->option('notranslate', true)){
                $keys[$keyIndex] = $keyIndex;
            }else{
                $keys[$keyIndex] = $this->parseVariables($this->translateKey($locale, $shouldTranslate));
            }
        }
        
        return $keys;
    }
    
    /**
     * @param string $string
     * @return string
     */
    private function removeVariables(string $string) : string
    {
        if(Str::contains($string, ":")){
            $variable = Str::betweenFirst($string, ":", " ");
            $replaced = $this->replace(":$variable", "{{".base64_encode($variable)."}}", $string);

            return $this->removeVariables($replaced);
        }
        
        return $string;
    }
    
    /**
     * @param string $string
     * @return string
     */
    private function parseVariables(string $string) : string
    {
        if(Str::contains($string, "{{")){
            $variable = Str::betweenFirst($string, "{{", "}}");
            
            $replaced = $this->replace("{{".$variable."}}", ":".base64_decode($variable), $string);
                        
            return $this->parseVariables($replaced);
        }
                
        return $string;
    }
    
    /**
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    private function replace(string $search, string $replace, string $subject) : string 
    {
        return implode($replace, explode($search, $subject, 2));
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
