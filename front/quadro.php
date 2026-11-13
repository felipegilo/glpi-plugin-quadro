<?php

use GlpiPlugin\Quadros\Quadro;

include('../../../inc/includes.php');

Session::checkRight(Quadro::$rightname, READ);

Html::header(Quadro::getTypeName(Session::getPluralNumber()), '', 'tools', Quadro::class);
Search::show(Quadro::class);
Html::footer();
