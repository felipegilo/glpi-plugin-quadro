<?php

namespace GlpiPlugin\Quadros;

use CommonDBTM;

class Lista extends CommonDBTM
{
    public static $rightname = 'plugin_quadros';

    public static function getTable($classname = null)
    {
        if ($classname !== null && $classname !== static::class) {
            return parent::getTable($classname);
        }
        return 'glpi_plugin_quadros_listas';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Lista', 'Listas', $nb);
    }
}
