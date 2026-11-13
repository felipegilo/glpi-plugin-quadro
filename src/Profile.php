<?php

namespace GlpiPlugin\Quadros;

use CommonDBTM;
use CommonGLPI;
use Html;
use Session;

class Profile extends CommonDBTM
{
    public static $rightname = 'profile';

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Profile && $item->getField('id')) {
            return 'Quadros';
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof \Profile) {
            self::showForProfile((int) $item->getID());
        }
        return true;
    }

    public static function showForProfile(int $profiles_id): void
    {
        $profile = new \Profile();
        $profile->getFromDB($profiles_id);

        $canedit = Session::haveRightsOr('profile', [CREATE, UPDATE, PURGE]);
        echo "<div class='firstbloc'>";
        if ($canedit) {
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }
        $rights = [[
            'itemtype' => Quadro::class,
            'label'    => 'Quadros, listas e cartões',
            'field'    => Quadro::$rightname,
        ]];
        $profile->displayRightsChoiceMatrix($rights, [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => 'Quadros',
        ]);
        if ($canedit) {
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary mt-2']);
            Html::closeForm();
        }
        echo '</div>';
    }
}
