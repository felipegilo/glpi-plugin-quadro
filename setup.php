<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Quadros\Profile as QuadrosProfile;
use GlpiPlugin\Quadros\Quadro;

define('PLUGIN_QUADROS_VERSION', '0.2.0');
define('PLUGIN_QUADROS_MIN_GLPI', '11.0.0');
define('PLUGIN_QUADROS_MAX_GLPI', '11.0.99');

function plugin_init_quadros()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['quadros'] = true;

    if (!Plugin::isPluginActive('quadros')) {
        return;
    }

    Plugin::registerClass(Quadro::class);
    Plugin::registerClass(QuadrosProfile::class, ['addtabon' => Profile::class]);

    if (Session::haveRight(Quadro::$rightname, READ)) {
        $PLUGIN_HOOKS[Hooks::MENU_TOADD]['quadros'] = ['tools' => Quadro::class];
    }
}

function plugin_version_quadros()
{
    return [
        'name'         => 'Quadros',
        'version'      => PLUGIN_QUADROS_VERSION,
        'author'       => 'TI',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_QUADROS_MIN_GLPI,
                'max' => PLUGIN_QUADROS_MAX_GLPI,
            ],
            'php'  => ['min' => '8.2'],
        ],
    ];
}
