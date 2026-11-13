<?php

use GlpiPlugin\Quadros\Cartao;
use GlpiPlugin\Quadros\Lista;
use GlpiPlugin\Quadros\Quadro;

function plugin_quadros_install()
{
    /** @var DBmysql $DB */
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();
    $engine    = "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC";

    $quadros = Quadro::getTable();
    if (!$DB->tableExists($quadros)) {
        $DB->doQuery("CREATE TABLE `$quadros` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT 0,
            `is_recursive` tinyint NOT NULL DEFAULT 0,
            `name` varchar(255) DEFAULT NULL,
            `comment` text,
            `users_id` int {$sign} NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `date_mod` (`date_mod`),
            KEY `date_creation` (`date_creation`)
        ) {$engine};");
    }

    $listas = Lista::getTable();
    if (!$DB->tableExists($listas)) {
        $DB->doQuery("CREATE TABLE `$listas` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_quadros_quadros_id` int {$sign} NOT NULL DEFAULT 0,
            `name` varchar(255) DEFAULT NULL,
            `position` int NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_quadros_quadros_id` (`plugin_quadros_quadros_id`),
            KEY `position` (`position`)
        ) {$engine};");
    }

    $cartoes = Cartao::getTable();
    if (!$DB->tableExists($cartoes)) {
        $DB->doQuery("CREATE TABLE `$cartoes` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_quadros_quadros_id` int {$sign} NOT NULL DEFAULT 0,
            `plugin_quadros_listas_id` int {$sign} NOT NULL DEFAULT 0,
            `name` varchar(255) DEFAULT NULL,
            `description` text,
            `users_id` int {$sign} NOT NULL DEFAULT 0,
            `date_due` date DEFAULT NULL,
            `cor` varchar(20) DEFAULT NULL,
            `is_done` tinyint NOT NULL DEFAULT 0,
            `position` int NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_quadros_quadros_id` (`plugin_quadros_quadros_id`),
            KEY `plugin_quadros_listas_id` (`plugin_quadros_listas_id`),
            KEY `date_due` (`date_due`),
            KEY `position` (`position`)
        ) {$engine};");
    }

    $membros = Cartao::TABLE_MEMBROS;
    if (!$DB->tableExists($membros)) {
        $DB->doQuery("CREATE TABLE `$membros` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_quadros_cartoes_id` int {$sign} NOT NULL DEFAULT 0,
            `users_id` int {$sign} NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_quadros_cartoes_id`, `users_id`),
            KEY `users_id` (`users_id`)
        ) {$engine};");
    }

    // Direitos: cria uma unica vez e concede tudo ao perfil atual (ou Super-Admin via CLI)
    if (countElementsInTable('glpi_profilerights', ['name' => Quadro::$rightname]) === 0) {
        ProfileRight::addProfileRights([Quadro::$rightname]);

        $profile_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($profile_id === 0) {
            $row = $DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles', 'WHERE' => ['name' => 'Super-Admin']])->current();
            $profile_id = (int) ($row['id'] ?? 0);
        }
        if ($profile_id > 0) {
            ProfileRight::updateProfileRights($profile_id, [Quadro::$rightname => ALLSTANDARDRIGHT]);
            if (isset($_SESSION['glpiactiveprofile'])) {
                $_SESSION['glpiactiveprofile'][Quadro::$rightname] = ALLSTANDARDRIGHT;
            }
        }
    }

    // Colunas padrao da lista de quadros: nome, criador, ultima atualizacao
    $prefs = 'glpi_displaypreferences';
    if (countElementsInTable($prefs, ['itemtype' => Quadro::class, 'users_id' => 0]) === 0) {
        foreach ([1, 3, 19] as $rank => $num) {
            $DB->insert($prefs, [
                'itemtype' => Quadro::class,
                'num'      => $num,
                'rank'     => $rank + 1,
                'users_id' => 0,
            ]);
        }
    }

    return true;
}

function plugin_quadros_uninstall()
{
    /** @var DBmysql $DB */
    global $DB;

    foreach ([Cartao::TABLE_MEMBROS, Cartao::getTable(), Lista::getTable(), Quadro::getTable()] as $table) {
        $DB->doQuery("DROP TABLE IF EXISTS `$table`");
    }

    ProfileRight::deleteProfileRights([Quadro::$rightname]);
    $DB->delete('glpi_displaypreferences', ['itemtype' => Quadro::class]);
    $DB->delete('glpi_logs', ['itemtype' => Quadro::class]);

    return true;
}
