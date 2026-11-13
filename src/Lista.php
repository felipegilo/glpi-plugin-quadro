<?php

namespace GlpiPlugin\Quadros;

use GlpiPlugin\Quadros\Compat\BaseItem;

class Lista extends BaseItem
{

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
