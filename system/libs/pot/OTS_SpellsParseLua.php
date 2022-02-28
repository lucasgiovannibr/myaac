<?php

/**
 * Class OTS_SpellsParseLua
 *
 * @package ${NAMESPACE}
 * @author  William Alvares <william@uilia.com.br>
 * @date    28/02/2022
 */
class OTS_SpellsParseLua
{
    /**
     * @var \RecursiveDirectoryIterator
     */
    private $iterator;
    
    /**
     * @var
     */
    private $array;
    
    /**
     * @param $dir
     */
    public function __construct($dir)
    {
        $this->iterator = new RecursiveDirectoryIterator($dir);
        
        $this->getContent();
    }
    
    /**
     * @return mixed
     */
    public function get()
    {
        return $this->array;
    }
    
    /**
     *
     */
    private function getContent()
    {
        global $config;
        
        foreach (new RecursiveIteratorIterator($this->iterator) as $file) {
            if ($file->getExtension() != 'lua') {
                continue;
            }
            
            $path = str_replace($config['data_path'], "", $file->getPath());
            
            if ($file->getFilename() === "#example.lua") {
                continue;
            }
            
            if ($this->str_contains('summon', $path) || $this->str_contains('monster', $path) || $this->str_contains('house', $path) || $this->str_contains('party', $path)) {
                continue;
            }
            
            $content = file_get_contents($file->getPathname());
            
            $type = $this->between($content, 'local spell = Spell(', ")\n");
            if (empty($type)) {
                $type = $this->between($content, 'local rune = Spell(', ")\n");
            }
            
            $type = str_replace('"', '', $type);
            
            if ($this->str_contains('conjuring', $path) && $type === "SPELL_INSTANT" || $type === 'instant') {
                $this->array['conjuring'][] = $this->extract($content, 'spell');
            }
            
            if ($type === "SPELL_RUNE" || $type === 'rune') {
                $this->array['runes'][] = $this->extract($content, 'rune');
            }
            
            if ($type === "SPELL_INSTANT" || $type === 'instant') {
                $this->array['instant'][] = $this->extract($content, 'spell');
            }
        }
    }
    
    /**
     * @param $content
     * @param $type
     *
     * @return array
     */
    private function extract($content, $type)
    {
        $array = [];
        
        if (empty($content)) {
            return $array;
        }
        
        if ($type === "spell") {
            preg_match_all('/spell:(.*?)[)]$/m', $content, $result);
        }
        
        if ($type === "rune") {
            preg_match_all('/rune:(.*?)[)]$/m', $content, $result);
        }
        
        if (empty($result[0])) {
            return $array;
        }
        
        foreach ($result[0] as $attr) {
            $attr = str_replace("$type:", '', $attr);
            
            preg_match_all('/(.*?)[(](.*?)[)]/s', $attr, $matches);
            
            unset($matches[0]);
            
            if ($matches[1][0] === "words") {
                if ($this->str_contains('##', $matches[2][0])) {
                    $array['ignore'] = true;
                    continue;
                }
            }
    
            $array['hidden'] = 0;
            if ($attr === "$type:register()") {
                $array['hidden'] = 1;
                continue;
            }
            
            if ($matches[1][0] === "groupCooldown" || $matches[1][0] === "cooldown") {
                $array[$matches[1][0]] = $this->math($matches[2][0]);
                continue;
            }
            
            if ($matches[1][0] === "vocation") {
                $vocations = explode(",", str_replace(";true", "", $matches[2][0]));
                $array[$matches[1][0]] = array_map(function($value) {
                    return ucwords(str_replace('"', "", trim($value)));
                }, $vocations);
                
                continue;
            }
            
            $array[$matches[1][0]] = str_replace('"', "", $matches[2][0]);
        }
        
        return $array;
    }
    
    /**
     * @param       $content
     * @param       $start
     * @param null  $end
     * @param false $include
     * @param false $exclusive
     *
     * @return string
     */
    private function between($content, $start, $end = null, $include = false, $exclusive = false)
    {
        $r = $exclusive ? explode($start, $content, 2) : explode($start, $content);
        
        if (isset($r[1])) {
            if ($end) {
                $r = explode($end, $r[1]);
                return ($include ? $start : '') . $r[0];
            }
            
            return ($include ? $start : '') . $r[1];
        }
        
        return '';
    }
    
    /**
     * @param $needle
     * @param $haystack
     *
     * @return bool
     */
    private function str_contains($needle, $haystack)
    {
        return (strpos($haystack, $needle) !== false);
    }
    
    /**
     * @param $value
     *
     * @return float|int|mixed
     */
    private function math($value)
    {
        $p = 0;
        
        if (preg_match('/(\d+)(?:\s*)([\+\-\*\/])(?:\s*)(\d+)/', $value, $matches) !== false) {
            $operator = $matches[2];
            
            switch ($operator) {
                case '+':
                    $p = $matches[1] + $matches[3];
                    break;
                case '-':
                    $p = $matches[1] - $matches[3];
                    break;
                case '*':
                    $p = $matches[1] * $matches[3];
                    break;
                case '/':
                    $p = $matches[1] / $matches[3];
                    break;
            }
            
            return $p;
        }
        
        return $p;
    }
}