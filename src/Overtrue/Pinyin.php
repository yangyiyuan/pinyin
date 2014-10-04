<?php
namespace  Overtrue;

/**
 * Pinyin.php
 *
 * @author Carlos <anzhengchao@gmail.com>
 * @date   [2014-07-17 15:49]
 */
/**
 * @see \Overtrue\Pinyin::pinyin()
 *
 * @return string
 */
function pinyin()
{
    return forward_static_call_array(array('Overtrue\Pinyin', 'pinyin'), func_get_args());
}

/**
 * @see \Overtrue\Pinyin::letter()
 *
 * @return string
 */
function letter()
{
    return forward_static_call_array(array('Overtrue\Pinyin', 'letter'), func_get_args());
}

/**
 * Chinese to pinyin translator
 *
 * @example
 * <pre>
 *      echo \Overtrue\Pinyin::pinyin('带着希望去旅行，比到达终点更美好'), "\n";
 *      //output: "dài zhe xī wàng qù lǔ xíng bǐ dào dá zhōng diǎn gèng měi hǎo"
 * </pre>
 */
class Pinyin
{

    /**
     * dictionary path
     *
     * @var array
     */
    protected static $dictionary;

    /**
     * settings
     *
     * @var array
     */
    protected static $settings = array(
                                  'delimiter'    => ' ',
                                  'traditional'  => false,
                                  'accent'       => true,
                                  'letter'       => false,
                                  'only_chinese' => false,
                                 );

    /**
     * the instance
     *
     * @var \Overtrue\Pinyin
     */
    protected static $instance;

    /**
     * constructor
     *
     * set dictionary path.
     */
    public function __construct()
    {
        if (is_null(self::$dictionary)) {
            self::$dictionary = $this->loadDictionary();
        }
    }

    /**
     * get class instance
     *
     * @return Overtrue\Pinyin
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }

        return self::$instance;
    }

    /**
     * set.
     *
     * @param array $settings settings.
     */
    public static function set($key, $value)
    {
        self::$settings[$key] = $value;
    }

    /**
     * setting.
     *
     * @param array $settings settings.
     */
    public static function settings(array $settings = array())
    {
        self::$settings = array_merge(self::$settings, $settings);
    }

    /**
     * chinese to pinyin
     *
     * @param string $string  source string.
     * @param array  $settings settings.
     *
     * @return string
     */
    public static function pinyin($string, array $settings = array())
    {
        $instance = self::getInstance();

        $oldSettings = self::$settings;

        // merge setting
        empty($settings) || self::settings($settings);

        if (self::$settings['letter']) {
            self::settings($oldSettings);

            return self::letter($string);
        }

        // remove non-Chinese char.
        if (self::$settings['only_chinese']) {
            $string = $instance->onlyChinese($string);
        }

        $string = $instance->string2pinyin($string);

        // add delimiter
        $string = $instance->addDelimiter($string, self::$settings['delimiter']);

        self::settings($oldSettings);

        return $instance->escape($string);
    }

    /**
     * get first letters of chars
     *
     * @param string $string    source string.
     * @param string $delimiter delimiter for letters.
     *
     * @return string
     */
    public static function letter($string, $delimiter = null)
    {
        $instance = self::getInstance();

        $letters = [];

        for ($i = 0; $char = $instance->getChar($string, $i); $i++) {
            if ($letter = $instance->getCharFirstLetter($char)) {
                $letters[] = $letter;
            }
        }

        !is_null($delimiter) || $delimiter = self::$settings['delimiter'];

        return $instance->addDelimiter(join(' ', $letters), $delimiter);
    }

    /**
     * replace string to pinyin
     *
     * @param string $string
     *
     * @return string
     */
    protected function string2pinyin($string)
    {
        $stringLength = $this->getStringLength($string);
        $pingyin = [];

        // do replace
        for ($i = 0; $i < $stringLength; ) {
            $str = $this->getChar($string, $i);
            $next = $str . $this->getChar($string, ++$i);

            while ((
                    !$this->containsChinese($str)
                    && !$this->containsChinese($next)
                   ) || ($i < $stringLength && $this->hasPinyin($next))) {
                $str  = $next;
                $next = $str . $this->getChar($string, ++$i);
            };

            $pingyin[] = $this->getPinyin($str);
        }

        return join(' ', $pingyin);
    }

    /**
     * detect the pinyin of string.
     *
     * @param string $string source string.
     *
     * @return boolean
     */
    protected function hasPinyin($string)
    {
        return isset(self::$dictionary[$string]);
    }

    /**
     * get string pinyin
     *
     * @param string $string source string.
     *
     * @return string
     */
    protected function getPinyin($string)
    {
        $pinyin = $this->hasPinyin($string) ? self::$dictionary[$string] : $string;

        // add accents
        if (self::$settings['accent']) {
            $pinyin = $this->addaccents(strtolower($pinyin));
        } else {
            $pinyin = $this->removeTone(strtolower($pinyin));
        }

        return $pinyin;
    }

    /**
     * get char
     *
     * @param string  $string source string.
     * @param integer $offset offset.
     *
     * @return string
     */
    protected function getChar($string, $offset)
    {
        return mb_substr($string, $offset, 1, 'UTF-8');
    }

    /**
     * get length of string
     *
     * @param string $string source string.
     *
     * @return integer
     */
    protected function getStringLength($string)
    {
        return mb_strlen($string, 'UTF-8');
    }

    /**
     * load dictionary content
     *
     * @return array
     */
    protected function loadDictionary()
    {
        $cacheFilename = $this->getCacheFilename(__DIR__ . '/cedict/cedict_ts.u8');

        // load from cache
        if (file_exists($cacheFilename)) {
            return $this->loadFromCache($cacheFilename);
        }

        // parse and cache
        $parsedDictionary = $this->parseDictionary(self::$dictionary);
        $this->cache($cacheFilename, $parsedDictionary);

        return $parsedDictionary;
    }

    /**
     * get the filename of cache file.
     *
     * @param string $dictionary dictionary path.
     *
     * @return string
     */
    protected function getCacheFilename($dictionary)
    {
        is_dir(__DIR__ .'/cache/') || mkdir(__DIR__ .'/cache/', 0755, true);

        return __DIR__ .'/cache/' . md5($dictionary);
    }

    /**
     * parse the dict to php array
     *
     * @param string $dictionary path of dictionary file.
     *
     * @return array
     */
    protected function parseDictionary($dictionary)
    {
        $handle = fopen($dictionary, 'r');
        $regex = "#(.*?) (.*?) \[(.*?)\]\/#";

        $content = array();

        while ($line = fgets($handle, 4096)) {
            if (0 === stripos($line, '#')) {
                continue;
            }
            preg_match($regex, $line, $matches);

            if (empty($matches[1]) || empty($matches[2]) || empty($matches[3])) {
                continue;
            }

            $key = self::$settings['traditional'] ? $matches[1] : $matches[2];
            $content[$key] = $matches[3];
        }

        return $content;
    }

    /**
     * get first letter of char.
     *
     * @param string $string source string.
     *
     * @return string
     */
    protected function getCharFirstLetter($char)
    {
        if (empty($char) || !$this->containsChinese($char)) {
            return '';
        }

        $fchar = ord($char{0});

        if ($fchar >= ord('A') && $fchar <= ord('z')) {
            return strtoupper($char{0});
        }

        $s1 = iconv('UTF-8', 'gb2312', $char);
        $s2 = iconv('gb2312', 'UTF-8', $s1);

        $s = $s2 == $char ? $s1 : $str;

        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;

        if ($asc >= - 20319 && $asc <= - 20284) return 'A';
        if ($asc >= - 20283 && $asc <= - 19776) return 'B';
        if ($asc >= - 19775 && $asc <= - 19219) return 'C';
        if ($asc >= - 19218 && $asc <= - 18711) return 'D';
        if ($asc >= - 18710 && $asc <= - 18527) return 'E';
        if ($asc >= - 18526 && $asc <= - 18240) return 'F';
        if ($asc >= - 18239 && $asc <= - 17923) return 'G';
        if ($asc >= - 17922 && $asc <= - 17418) return 'H';
        if ($asc >= - 17417 && $asc <= - 16475) return 'J';
        if ($asc >= - 16474 && $asc <= - 16213) return 'K';
        if ($asc >= - 16212 && $asc <= - 15641) return 'L';
        if ($asc >= - 15640 && $asc <= - 15166) return 'M';
        if ($asc >= - 15165 && $asc <= - 14923) return 'N';
        if ($asc >= - 14922 && $asc <= - 14915) return 'O';
        if ($asc >= - 14914 && $asc <= - 14631) return 'P';
        if ($asc >= - 14630 && $asc <= - 14150) return 'Q';
        if ($asc >= - 14149 && $asc <= - 14091) return 'R';
        if ($asc >= - 14090 && $asc <= - 13319) return 'S';
        if ($asc >= - 13318 && $asc <= - 12839) return 'T';
        if ($asc >= - 12838 && $asc <= - 12557) return 'W';
        if ($asc >= - 12556 && $asc <= - 11848) return 'X';
        if ($asc >= - 11847 && $asc <= - 11056) return 'Y';
        if ($asc >= - 11055 && $asc <= - 10247) return 'Z';

        return null;
    }

    /**
     * load dictionary from cached file
     *
     * @param string $dictionary cached file name
     *
     * @return array
     */
    protected function loadFromCache($dictionary)
    {
        return include $dictionary;
    }

    /**
     * write array to file
     *
     * @param string $filename  filename.
     * @param array  $array     parsed dictionary.
     *
     * @return void
     */
    protected function cache($filename, $array)
    {
        file_put_contents($filename, "<?php\nreturn ".var_export($array, true).";") ;
    }

    /**
     * check if the string has Chinese chars
     *
     * @param string $string string to check.
     *
     * @return int
     */
    protected function containsChinese($string)
    {
        return preg_match('/\p{Han}+/u', $string);
    }

    /**
     * Credits for this function go to velcrow, who shared this
     * at http://stackoverflow.com/questions/1162491/alternative-to-mysql-real-escape-string-without-connecting-to-db
     *
     * @param string $string the string to  be escaped
     *
     * @return string the escaped string
     */
    protected function escape($value)
    {
        $search  = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
        $replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");

        return str_replace($search, $replace, $value);
    }

    /**
     * Remove the non-Chinese characters
     *
     * @param string $string source string.
     *
     * @return string
     */
    protected function onlyChinese($string)
    {
        return preg_replace('/[^\p{Han}]/u', '', $string);
    }

    /**
     * add delimiter
     *
     * @param string $string
     */
    protected function addDelimiter($string, $delimiter = '')
    {
        return preg_replace('/\s+/', strval($delimiter), trim($string));
    }

    /**
     * remove tone
     *
     * @param string $string string with tone.
     *
     * @return string
     */
    protected function removeTone($string)
    {
        $replacement = array(
                        '/u:/' => 'u',
                        '/\d/' => '',
                       );

        return preg_replace(array_keys($replacement), $replacement, $string);
    }

    /**
     * Credits for these 2 functions go to Bouke Versteegh, who shared these
     * at http://stackoverflow.com/questions/1598856/convert-numbered-to-accentuated-pinyin
     *
     * @param string $string The pinyin string with tone numbers, i.e. "ni3 hao3"
     *
     * @return string The formatted string with tone marks, i.e.
     */
    protected function addaccents($string)
    {
        # Find words with a number behind them, and replace with callback fn.
        return str_replace('u:', 'ü', preg_replace_callback(
            '~([a-zA-ZüÜ]+\:?)(\d)~',
            array($this, 'addaccentsCallback'),
            $string));
    }

    # Helper callback
    protected function addaccentsCallback($match)
    {
        static $accentmap = null;

        if ($accentmap === null) {
            # Where to place the accent marks
            $stars =
                    'a* e* i* o* u* ü* ü* ' .
                    'A* E* I* O* U* Ü* ' .
                    'a*i a*o e*i ia* ia*o ie* io* iu* ' .
                    'A*I A*O E*I IA* IA*O IE* IO* IU* ' .
                    'o*u ua* ua*i ue* ui* uo* üe* ' .
                    'O*U UA* UA*I UE* UI* UO* ÜE*';
            $nostars =
                    'a e i o u u: ü ' .
                    'A E I O U Ü ' .
                    'ai ao ei ia iao ie io iu ' .
                    'AI AO EI IA IAO IE IO IU ' .
                    'ou ua uai ue ui uo üe ' .
                    'OU UA UAI UE UI UO ÜE';

            # Build an array like array('a' => 'a*') and store statically
            $accentmap = array_combine(explode(' ', $nostars), explode(' ', $stars));
        }

        $vowels = array('a*', 'e*', 'i*', 'o*', 'u*', 'ü*', 'A*', 'E*', 'I*', 'O*', 'U*', 'Ü*');

        $pinyin = array(
            1 => array('ā', 'ē', 'ī', 'ō', 'ū', 'ǖ', 'Ā', 'Ē', 'Ī', 'Ō', 'Ū', 'Ǖ'),
            2 => array('á', 'é', 'í', 'ó', 'ú', 'ǘ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ǘ'),
            3 => array('ǎ', 'ě', 'ǐ', 'ǒ', 'ǔ', 'ǚ', 'Ǎ', 'Ě', 'Ǐ', 'Ǒ', 'Ǔ', 'Ǚ'),
            4 => array('à', 'è', 'ì', 'ò', 'ù', 'ǜ', 'À', 'È', 'Ì', 'Ò', 'Ù', 'Ǜ'),
            5 => array('a', 'e', 'i', 'o', 'u', 'ü', 'A', 'E', 'I', 'O', 'U', 'Ü')
        );

        list(, $word, $tone) = $match;

        # Add star to vowelcluster
        $word = strtr($word, $accentmap);

        # Replace starred letter with accented
        $word = str_replace($vowels, $pinyin[$tone], $word);

        return $word;
    }

}// end of class
