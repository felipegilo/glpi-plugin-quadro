<?php

namespace GlpiPlugin\Quadros;

use CommonDBTM;

class Cartao extends CommonDBTM
{
    public static $rightname = 'plugin_quadros';

    public const TABLE_MEMBROS = 'glpi_plugin_quadros_cartoes_users';

    public static function getTable($classname = null)
    {
        if ($classname !== null && $classname !== static::class) {
            return parent::getTable($classname);
        }
        return 'glpi_plugin_quadros_cartoes';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Cartão', 'Cartões', $nb);
    }

    public function cleanDBonPurge()
    {
        /** @var \DBmysql $DB */
        global $DB;
        $DB->delete(self::TABLE_MEMBROS, ['plugin_quadros_cartoes_id' => $this->fields['id']]);
    }
}
