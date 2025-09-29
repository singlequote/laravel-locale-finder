<?php
namespace SingleQuote\LocaleFinder\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\Finder\Finder;
use const PHP_EOL;
use function collect;
use function config;
use function count;
use function str;

class FindAndAddLanguageKeysCommand extends Command
{

    /**
     * @var  string
     */
    protected $signature = 'locale:find {--locales=all} {--source=en} {--notranslate} {--modules} {--create} {--only=}';

    /**
     * @var  string
     */
    protected $description = 'Automatically find, translate and save missing translation keys.';

    /**
     * @var GoogleTranslate
     */
    private GoogleTranslate $googleTranslate;
    private array $reservedKeys = [];

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
    public function handle(): int
    {
        if (!$this->option('locales')) {
            $this->error('The --locales option is required! Use as --locales=nl,en,de');
            return 0;
        }

        $this->getTranslationFiles();

        $translationsKeys = $this->findKeysInFiles();

        $this->translateAndSaveNewKeys($translationsKeys);

        $this->info("Finished");

        return 1;
    }

    /**
     * @return void
     */
    private function getTranslationFiles(): void
    {
        if ($this->option('locales') !== 'all') {
            $this->locales = explode(',', $this->option('locales'));
            return;
        }

        $files = Storage::disk('localeFinder')->files();

        foreach ($files as $file) {
            if (Str::endsWith($file, '.json')) {
                $this->locales[] = Str::before($file, '.json');
            }
        }
    }

    /**
     * @param string $locale
     * @return array
     */
    private function loadJsonTranslationFile(string $locale): array
    {
        $this->info("Loading: $locale");

        $file = Storage::disk('localeFinder')->get("$locale.json");

        return json_decode($file, true) ?? [];
    }

    /**
     * @param string $locale
     * @param string $file
     * @return array
     */
    private function loadPhpTranslationFile(string $locale, string $file): array
    {
        $this->info("Loading: $locale/$file");

        if ($this->option('modules') && str($file)->contains('::')) {

            $hintPaths = Lang::getLoader()->namespaces();

            $namespace = str($file)->before('::')->toString();
            $file = str($file)->after('::')->toString();

            if (isset($hintPaths[$namespace])) {
                return require "$hintPaths[$namespace]/$locale/$file.php";
            }
        }

        return require Storage::disk('localeFinder')->path("$locale/$file.php");
    }

    /**
     * @return array
     */
    private function findKeysInFiles(): array
    {
        $path = config('locale-finder.search.folders', []);

        $functions = config('locale-finder.translation_methods', []);

        if (count($path) > 0) {
            $finder = new Finder();

            $finder->in($path)->exclude(config('locale-finder.search.exclude', []))
                ->name(config('locale-finder.search.file_extension', []))
                ->files();

            $this->info('> ' . $finder->count() . ' files found');
        }

        return $this->patternKeys($finder ?? [], $functions);
    }

    /**
     * @param array|Finder $finder
     * @param array $functions
     * @return array
     */
    private function patternKeys(array|Finder $finder, array $functions): array
    {
        $keys = [];

        foreach ($finder as $file) {
            $this->matchPatternKeysByContent($functions, $keys, $file->getContents());
        }

        foreach (config('locale-finder.search.files', []) as $file) {
            $this->matchPatternKeysByContent($functions, $keys, file_get_contents($file));
        }

        uksort($keys, 'strnatcasecmp');

        return $this->onlyExcept($keys);
    }

    /**
     * @param array $keys
     * @return array
     */
    private function onlyExcept(array $keys): array
    {
        if (!$this->option('only')) {
            return $keys;
        }

        $only = explode(',', $this->option('only'));

        return collect($keys)->filter(function ($value, $key) use ($only) {
                return $this->filterOnlyOnPrefix($key, $only);
            })->toArray();
    }

    /**
     * @param string $functions
     * @param array $keys
     * @param string $content
     * @return array
     */
    private function matchPatternKeysByContent(array $functions, array &$keys, string $content): array
    {
        $minified = str($content)->replace(["\r", "\n", "  "], '-')->toString();

        foreach ($functions as $function) {
            $minified1 = $this->replaceMatchingBeforeFinding($function, $minified, '"');
            $minified2 = $this->replaceMatchingBeforeFinding($function, $minified1, "'");
            $minified = $this->replaceMatchingBeforeFinding($function, $minified2, "]");
        }

        foreach ($functions as $function) {
            $this->searchForKeysInPattern($keys, $minified);
        }

        return $keys;
    }

    /**
     * @param string $function
     * @param string $content
     * @param string $pattern
     * @return string
     */
    private function replaceMatchingBeforeFinding(string $function, string &$content, string $pattern): string
    {
        return str($content)
                ->replace("$function($pattern", "translationkeyFindedStart($pattern")
                ->replace("$pattern)", "{$pattern}translationkeyFindedEnd")
            ->value;
    }

    /**
     * Zoekt naar vertaalsleutels in een string met een reguliere expressie.
     *
     * @param string $content De content om in te zoeken.
     * @return array Een array met de gevonden sleutels.
     */
    private function searchForKeysInPattern(array &$keys, string $content): array
    {
        // Regex die zoekt naar translationkeyFindedStart('key') of translationkeyFindedStart("key").
        // Houdt rekening met escaped quotes (\' of \") en negeert eventuele extra parameters.
        $pattern = "/translationkeyFindedStart\(\s*(?:'((?:\\\\'|[^'])*)'|\"((?:\\\\\"|[^\"])*)\")/";

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            // De gevonden sleutel staat in capture group 1 (voor ') of 2 (voor ").
            // We gebruiken stripslashes om de escaped quotes weer normaal te maken.
            $key = stripslashes($match[1] ?: $match[2]);
            $keys[$key] = ""; // Voeg de sleutel toe aan de array
        }

        return array_keys($keys); // Retourneer een lijst van unieke sleutels
    }
    
    
    /**
     * @param array $keys
     * @param string $content
     * @return array
     */
    private function searchForKeysInPattern2(array &$keys, string $content): array
    {
        $key = null;

        if (!str($content)->contains("translationkeyFindedStart(")) {
            return $keys;
        }

        $found = str($content)->betweenFirst("translationkeyFindedStart(", "translationkeyFindedEnd");

        $newContent = str($content)->replaceFirst("translationkeyFindedStart(", "replacedPattern(");

        if ($found->startsWith('"')) {
            $key = $found->betweenFirst('"', '"');
        }

        if ($found->startsWith("'")) {
            $key = $found->betweenFirst("'", "'");
        }

        if (!$key || strlen($key) === 0) {
            return $this->searchForKeysInPattern($keys, $newContent);
        }

        $keyString = $key->ltrim("'\"")->rtrim("'\"")->toString();

        $keys[$keyString] = "";

        return $this->searchForKeysInPattern($keys, $newContent);
    }

    /**
     * @param string $key
     * @param array $only
     * @param bool $keep
     * @return bool
     */
    private function filterOnlyOnPrefix(string $key, array $only, bool $keep = false): bool
    {
        foreach ($only as $prefix) {
            if (str($prefix)->endsWith('*') && str($key)->startsWith(str($prefix)->before('*')->toString())) {
                $keep = true;
            } elseif ($prefix === $key) {
                $keep = true;
            }
        }

        return $keep;
    }

    /**
     * @param array $translationsKeys
     * @return void
     */
    private function translateAndSaveNewKeys(array $translationsKeys): void
    {
        foreach ($this->locales as $locale) {

            $types = $this->parseKeys($locale, $translationsKeys);

            foreach ($types as $type => $keys) {

                if ($type === 'json') {
                    $this->parseJsonKeys($locale, $keys);
                    continue;
                }

                $this->parsePhpArrayKeys($locale, $type, $keys);
            }
        }
    }

    /**
     * @param string $locale
     * @param array $keys
     * @return void
     */
    private function parseJsonKeys(string $locale, array $keys): void
    {
        $alreadyTranslated = $this->loadJsonTranslationFile($locale);

        $old = $this->checkDiffMulti($alreadyTranslated, $keys);

        $this->info("Removed " . count($old) . " old keys from locale $locale");

        $existingKeys = $this->removeOldKeys($alreadyTranslated, $old);

        $translationsKeys = $this->checkDiffMulti($keys, $existingKeys);

        $newKeysWithValues = $this->translateKeys($locale, $translationsKeys);

        $this->info("Found " . count($newKeysWithValues) . " new keys for locale $locale");

        $newKeys = array_merge_recursive($existingKeys, $newKeysWithValues);

        uksort($newKeys, 'strnatcasecmp');

        foreach ($newKeys as $key => $value) {
            $newKeys[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        Storage::disk('localeFinder')->put("$locale.json", json_encode($newKeys, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * @param string $locale
     * @param string $file
     * @param array $keys
     * @return void
     */
    private function parsePhpArrayKeys(string $locale, string $file, array $keys): void
    {
        $alreadyTranslated = $this->loadPhpTranslationFile($locale, $file);

        $old = $this->checkDiffMulti($alreadyTranslated, $keys);

        $this->info("Removed " . count($old) . " old keys from locale $locale");

        $existingKeys = $this->removeOldKeys($alreadyTranslated, $old);

        $translationsKeys = $this->checkDiffMulti($keys, $existingKeys);

        $newKeysWithValues = $this->translateKeys($locale, $translationsKeys);

        $this->info("Found " . count($newKeysWithValues) . " new keys for locale $locale");

        $newKeys = array_merge_recursive($existingKeys, $newKeysWithValues);

        uksort($newKeys, 'strnatcasecmp');

        $export = $this->varexport($newKeys);

        $code = "<?php " . PHP_EOL . PHP_EOL . "return $export;";

        if ($this->option('modules') && str($file)->contains('::')) {
            $this->parsePhpModuleKeys($locale, $file, $code);
        } else {
            file_put_contents(Storage::disk('localeFinder')->path("$locale/$file.php"), $code);
        }
    }

    /**
     * @param string $locale
     * @param string $parent
     * @param string $code
     * @return void
     */
    private function parsePhpModuleKeys(string $locale, string $parent, string $code): void
    {
        $hintPaths = Lang::getLoader()->namespaces();

        $namespace = str($parent)->before('::')->toString();
        $file = str($parent)->after('::')->toString();

        try {
            file_put_contents("$hintPaths[$namespace]/$locale/$file.php", $code);
        } catch (\Throwable $ex) {
            $this->error("Key $namespace not found");
        }
    }

    /**
     * @param string $locale
     * @param array $newKeysFound
     * @return array
     */
    private function parseKeys(string $locale, array $newKeysFound): array
    {
        $items = [];

        foreach ($newKeysFound as $key => $value) {

            $parent = Str::before($key, '.');
            $child = Str::after($key, '.');

            if (!Str::contains(rtrim($key, '.'), ['.', "::"]) || Str::startsWith($child, ' ')) {
                $items['json'][$key] = $value;
                continue;
            }

            if ($this->parentExists($locale, $parent)) {
                $parsed = $this->createParentKeys($child, $value, []);

                $items[$parent] = array_merge_recursive($parsed, $items[$parent] ?? []);
            } else {
                $items['json'][$key] = $value;
                $this->error("Translation file $parent does not exists. Adding to json file!");
            }
        }

        return $items;
    }

    /**
     * @param string $key
     * @param string $value
     * @return array
     */
    private function createParentKeys(string $key, string $value): array
    {
        $parent = Str::before($key, '.');
        $child = Str::after($key, '.');

        if (!Str::contains(rtrim($key, '.'), '.') || Str::startsWith($child, ' ')) {
            return [$key => $value];
        }

        if (Str::contains(rtrim($child, '.'), '.')) {

            $child = $this->createParentKeys($child, $value);
        } else {
            $child = [$child => $value];
        }

        return [$parent => $child];
    }

    /**
     * @param string $locale
     * @param string $parent
     * @return bool
     */
    private function parentExists(string $locale, string $parent): bool
    {
        if ($this->option('modules') && str($parent)->contains('::')) {

            $hintPaths = Lang::getLoader()->namespaces();

            $namespace = str($parent)->before('::')->toString();
            $parent = str($parent)->after('::')->toString();

            if (isset($hintPaths[$namespace])) {
                return $this->createOrStay("$hintPaths[$namespace]/$locale", "$hintPaths[$namespace]/$locale/$parent.php");
            }
        }

        return $this->createOrStay(Storage::disk('localeFinder')->path("$locale"), Storage::disk('localeFinder')->path("$locale/$parent.php"));
    }

    /**
     * @param string $path
     * @return bool
     */
    private function createOrStay(string $root, string $path): bool
    {
        $exists = File::exists($path);

        if (!$this->option('create') || $exists) {
            return $exists;
        }

        $export = $this->varexport([]);

        if (!File::isDirectory($root)) {
            File::makeDirectory($root, 0755, true);
        }

        return File::put($path, "<?php " . PHP_EOL . PHP_EOL . "return $export;");
    }

    /**
     * @param string $locale
     * @param array $keys
     * @return array
     */
    private function translateKeys(string $locale, array $keys): array
    {        
        foreach ($keys as $keyIndex => $keyValue) {
            if ($keyValue === '...') {
                continue;
            }

            if (is_array($keyValue)) {
                $keys[$keyIndex] = $this->translateKeys($locale, $keyValue);
                continue;
            }

            if (Str::contains($keyIndex, ":")) {
                $shouldTranslate = $this->removeVariables($keyIndex);
            } else {
                $shouldTranslate = $keyIndex;
            }
            if ($this->option('notranslate', true)) {
                $keys[$keyIndex] = $keyIndex;
            } else {
                $keys[$keyIndex] = $this->parseVariables($this->translateKey($locale, $shouldTranslate));
            }
        }

        return $keys;
    }

    /**
     * @param string $string
     * @return string
     */
    private function removeVariables(string $string): string
    {
        if (Str::contains($string, ":")) {
            $variable = Str::betweenFirst($string, ":", " ");

            $unique = uniqid();
            $this->reservedKeys[$unique] = $variable;

            $replaced = $this->replace(":$variable", "{{" . $unique . "}}", $string);

            return $this->removeVariables($replaced);
        }

        return $string;
    }

    /**
     * @param string $string
     * @return string
     */
    private function parseVariables(string $string): string
    {
        if (Str::contains($string, "{{")) {

            $variable = Str::betweenFirst($string, "{{", "}}");

            $replaced = $this->replace("{{" . $variable . "}}", ":" . $this->reservedKeys[$variable], $string);

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
    private function replace(string $search, string $replace, string $subject): string
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
        try {
            $this->googleTranslate->setSource($this->option('source'));
            $this->googleTranslate->setTarget($locale);
            $translated = $this->googleTranslate->translate($key);
        } catch (Exception $exception) {
            $this->error('Google translate issue with ' . $key . ': ' . $exception->getMessage());
            $translated = $key;
        }

        if (!$translated) {
            $translated = $key;
        }

        return $translated;
    }

    /**
     * @param array $current
     * @param array $old
     * @return array
     */
    private function removeOldKeys(array $current, array $old): array
    {
        foreach ($old as $key => $value) {

            if (isset($current[$key]) && is_array($current[$key])) {
                $current[$key] = $this->removeOldKeys($current[$key], $old[$key]);

                if (!count($current[$key])) {
                    unset($current[$key]);
                }

                continue;
            }

            if (isset($current[$key])) {
                unset($current[$key]);
            }
        }

        return $current;
    }

    /**
     * @param array $expression
     * @return string
     */
    private function varexport(array $expression): string
    {
        $export = var_export($expression, true);

        $patterns = [
            "/array \(/" => '[',
            "/^([ ]*)\)(,?)$/m" => '$1]$2',
            "/=>[ ]?\n[ ]+\[/" => '=> [',
            "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
        ];

        $replace = preg_replace(array_keys($patterns), array_values($patterns), $export);

        return $replace;
    }

    /**
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    private function checkDiffMulti(array $array1, array $array2): array
    {
        $result = [];

        foreach ($array1 as $key => $value) {

            if (isset($array2[$key]) && is_array($value) && is_array($array2[$key])) {

                $result[$key] = $this->checkDiffMulti($array1[$key], $array2[$key]);
                continue;
            }

            if (!isset($array2[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
