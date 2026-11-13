<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Quadros\Quadro;

include('../../../inc/includes.php');

Session::checkRight(Quadro::$rightname, READ);

$id    = (int) ($_GET['id'] ?? 0);
$board = new Quadro();
if ($id <= 0 || !$board->getFromDB($id) || !$board->can($id, READ)) {
    Html::displayNotFoundError();
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Html::requireJs('sortable');
Html::header($board->fields['name'], '', 'tools', Quadro::class);

TemplateRenderer::getInstance()->display('@quadros/board.html.twig', [
    'board_id'   => $id,
    'board_name' => $board->fields['name'],
    'can_edit'   => $board->can($id, UPDATE),
    'ajax_url'   => $CFG_GLPI['root_doc'] . '/plugins/quadros/ajax/quadro.php',
    'list_url'   => Quadro::getSearchURL(),
    'config_url' => Quadro::getFormURL() . '?id=' . $id,
]);

Html::footer();
