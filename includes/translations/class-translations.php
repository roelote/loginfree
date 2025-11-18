<?php
/**
 * Sistema de traducciones para LoginFree
 */

if (!defined('ABSPATH')) {
    exit;
}

class LoginFree_Translations {
    
    private static $instance = null;
    private static $current_language = null;
    private static $strings = array();
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Detectar el idioma actual
     */
    public static function get_current_language() {
        if (self::$current_language !== null) {
            return self::$current_language;
        }
        
        // 1. Intentar detectar via WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            self::$current_language = ICL_LANGUAGE_CODE;
            return self::$current_language;
        }
        
        // 2. Detectar por URL
        $url = $_SERVER['REQUEST_URI'];
        if (preg_match('#/(en|es|pt-br|fr|it)/#', $url, $matches)) {
            self::$current_language = $matches[1];
            return self::$current_language;
        }
        
        // 3. Fallback a español
        self::$current_language = 'es';
        return self::$current_language;
    }
    
    /**
     * Cargar strings de traducción por categoría
     */
    private static function load_strings($category) {
        if (!isset(self::$strings[$category])) {
            $file = plugin_dir_path(__FILE__) . 'strings-' . $category . '.php';
            if (file_exists($file)) {
                self::$strings[$category] = require $file;
            }
        }
        return isset(self::$strings[$category]) ? self::$strings[$category] : array();
    }
    
    /**
     * Obtener traducción
     */
    public static function get($key, $lang = null) {
        if ($lang === null) {
            $lang = self::get_current_language();
        }
        
        // Buscar en todas las categorías
        $categories = array('auth', 'forms', 'messages');
        
        foreach ($categories as $category) {
            $strings = self::load_strings($category);
            if (isset($strings[$key])) {
                if (isset($strings[$key][$lang])) {
                    return $strings[$key][$lang];
                }
                // Fallback a español
                if (isset($strings[$key]['es'])) {
                    return $strings[$key]['es'];
                }
            }
        }
        
        return $key; // Devolver la clave si no se encuentra traducción
    }
    
    /**
     * Obtener todas las traducciones para JavaScript
     */
    public static function get_js_strings($lang = null) {
        if ($lang === null) {
            $lang = self::get_current_language();
        }
        
        $js_strings = self::load_strings('js');
        $result = array();
        
        foreach ($js_strings as $key => $translations) {
            $result[$key] = isset($translations[$lang]) ? $translations[$lang] : $translations['es'];
        }
        
        return $result;
    }
}

/**
 * Función helper para traducciones
 */
function lf_trans($key) {
    return LoginFree_Translations::get($key);
}

/**
 * Alias corto
 */
function lft($key) {
    return lf_trans($key);
}
