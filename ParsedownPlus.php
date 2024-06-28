<?php

class ParsedownPlus extends ParsedownFilter
{
    protected $embedingMode = true;
    protected $cssAdded = false;
    protected $predefinedColors = [];
    protected $monospaceFont = 'monospace';

    const CODE_BLOCK_PATTERN = '/(```.*?```|<pre>.*?<\/pre>)/s';
    const VIDEO_TAG_PATTERN = '/\[video.*src="([^"]*)".*\]/';
    const COLOR_TAG_PATTERN = '/\[color=([^\]]+)\](.*?)\[\/color\]/s';
    const RTL_TAG_PATTERN = '/\[rtl\](.*?)\[\/rtl\]/s';
    const LTR_TAG_PATTERN = '/\[ltr\](.*?)\[\/ltr\]/s';
    const MONO_TAG_PATTERN = '/\[mono\](.*?)\[\/mono\]/s';

    function __construct(array $params = null)
    {
        parent::__construct($params);

        // Ensure the parent class version is compatible
        if (version_compare(parent::version, '0.8.0-beta-1') < 0) {
            throw new Exception('ParsedownPlus requires a later version of Parsedown');
        }

        // Load predefined colors and fonts from config.php if it exists
        $this->loadConfig();
    }

    protected function loadConfig()
    {
        $configFile = __DIR__ . '/config.php';
        if (file_exists($configFile)) {
            $config = include($configFile);
            if (is_array($config)) {
                if (isset($config['colors']) && is_array($config['colors'])) {
                    $this->predefinedColors = $config['colors'];
                }
                if (isset($config['fonts']) && isset($config['fonts']['monospace'])) {
                    $this->monospaceFont = $config['fonts']['monospace'];
                }
            }
        }
    }

    public function text($text)
    {
        if (!$this->cssAdded) {
            $text = $this->addCss($text);
            $this->cssAdded = true;
        }

        // Process custom tags outside code blocks
        $text = $this->processCustomTagsOutsideCode($text);

        // Pass the processed text to the parent class
        return parent::text($text);
    }

    protected function addCss($text)
    {
        $css = "<style>
            .video-responsive {
                position: relative;
                padding-bottom: 56.25%;
                height: 0;
                overflow: hidden;
                max-width: 100%;
                background: #000;
            }
            .video-responsive iframe {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }
            .rtl, .rtl * {
                direction: rtl;
                unicode-bidi: isolate;
                text-align: right;
            }
            .ltr, .ltr * {
                direction: ltr;
                unicode-bidi: isolate;
                text-align: left;
            }
            .mono {
                font-family: {$this->monospaceFont};
            }
        </style>\n";
        return $css . $text;
    }

    protected function processCustomTagsOutsideCode($text)
    {
        $parts = preg_split(self::CODE_BLOCK_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as &$part) {
            if (!preg_match(self::CODE_BLOCK_PATTERN, $part)) {
                $part = $this->processCustomTags($part);
            }
        }

        return implode('', $parts);
    }

    protected function processCustomTags($text)
    {
        $text = $this->processColorTags($text);
        $text = $this->processVideoTags($text);
        $text = $this->processRtlTags($text);
        $text = $this->processLtrTags($text);
        $text = $this->processMonoTags($text);
        return $text;
    }

    protected function processVideoTags($text)
    {
        return preg_replace_callback(
            self::VIDEO_TAG_PATTERN,
            function ($matches) {
                $url = $matches[1];
                $type = '';

                $needles = array('youtube', 'vimeo');
                foreach ($needles as $needle) {
                    if (strpos($url, $needle) !== false) {
                        $type = $needle;
                    }
                }

                switch ($type) {
                    case 'youtube':
                        $src = preg_replace('/.*\?v=([^\&\]]*).*/', 'https://www.youtube.com/embed/$1', $url);
                        return '<div class="video-responsive"><iframe src="' . $src . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
                    case 'vimeo':
                        $src = preg_replace('/(?:https?:\/\/(?:[\w]{3}\.|player\.)*vimeo\.com(?:[\/\w:]*(?:\/videos)?)?\/([0-9]+)[^\s]*)/', 'https://player.vimeo.com/video/$1', $url);
                        return '<div class="video-responsive"><iframe src="' . $src . '" title="Vimeo video player" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen sandbox="allow-same-origin allow-scripts allow-forms"></iframe></div>';
                    default:
                        return $matches[0]; // Return the original if no match
                }
            },
            $text
        );
    }

    protected function processColorTags($text)
    {
        return preg_replace_callback(
            self::COLOR_TAG_PATTERN,
            function ($matches) {
                $color = $matches[1];
                if (isset($this->predefinedColors[$color])) {
                    $color = $this->predefinedColors[$color];
                } else {
                    $color = htmlspecialchars($color);
                }
                $content = $matches[2];
                return "<span style=\"color:$color;\">$content</span>";
            },
            $text
        );
    }

    protected function processRtlTags($text)
    {
        return preg_replace_callback(
            self::RTL_TAG_PATTERN,
            function ($matches) {
                $content = $this->text($matches[1]);
                return "<div class=\"rtl\">$content</div>";
            },
            $text
        );
    }

    protected function processLtrTags($text)
    {
        return preg_replace_callback(
            self::LTR_TAG_PATTERN,
            function ($matches) {
                $content = $this->text($matches[1]);
                return "<div class=\"ltr\">$content</div>";
            },
            $text
        );
    }

    protected function processMonoTags($text)
    {
        return preg_replace_callback(
            self::MONO_TAG_PATTERN,
            function ($matches) {
                $content = $this->text($matches[1]);
                return "<div class=\"mono\">$content</div>";
            },
            $text
        );
    }
}
