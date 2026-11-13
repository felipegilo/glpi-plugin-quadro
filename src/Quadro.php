<?php

namespace GlpiPlugin\Quadros;

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Quadros\Compat\BaseItem;
use Session;

class Quadro extends BaseItem
{
    public function __construct()
    {
        parent::__construct();
        // Historico de alteracoes (propriedade tipada no GLPI 12: por isso e atribuida aqui)
        $this->dohistory = true;
    }

    public static function getTable($classname = null)
    {
        if ($classname !== null && $classname !== static::class) {
            return parent::getTable($classname);
        }
        return 'glpi_plugin_quadros_quadros';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Quadro', 'Quadros', $nb);
    }

    public static function getIcon()
    {
        return 'ti ti-layout-kanban';
    }

    public static function getMenuName()
    {
        return 'Quadros';
    }

    /** Ao abrir um quadro existente, vai direto para a tela Kanban. */
    public static function getFormURLWithID($id = 0, $full = true)
    {
        $id = (int) $id;
        if ($id > 0) {
            return str_replace('.form.php', '.view.php', self::getFormURL($full)) . '?id=' . $id;
        }
        return self::getFormURL($full);
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getMenuName(),
            'page'  => self::getSearchURL(false),
            'icon'  => self::getIcon(),
            'links' => [
                'search' => self::getSearchURL(false),
                'add'    => self::getFormURL(false),
            ],
        ];
    }

    public function rawSearchOptions()
    {
        $t = self::getTable();
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => self::getTypeName(Session::getPluralNumber())];
        $tab[] = ['id' => '1', 'table' => $t, 'field' => 'name', 'name' => __('Name'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => '2', 'table' => $t, 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false];
        $tab[] = ['id' => '3', 'table' => 'glpi_users', 'field' => 'name', 'name' => 'Criado por', 'datatype' => 'dropdown', 'massiveaction' => false];
        $tab[] = ['id' => '4', 'table' => $t, 'field' => 'comment', 'name' => __('Comments'), 'datatype' => 'text'];
        $tab[] = [
            'id'            => '5',
            'table'         => Lista::getTable(),
            'field'         => 'id',
            'name'          => 'Listas',
            'datatype'      => 'count',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'massiveaction' => false,
            'joinparams'    => ['jointype' => 'child'],
        ];
        $tab[] = [
            'id'            => '6',
            'table'         => Cartao::getTable(),
            'field'         => 'id',
            'name'          => 'Cartões',
            'datatype'      => 'count',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'massiveaction' => false,
            'joinparams'    => ['jointype' => 'child'],
        ];
        $tab[] = ['id' => '19', 'table' => $t, 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false];
        $tab[] = ['id' => '80', 'table' => 'glpi_entities', 'field' => 'completename', 'name' => __('Entity'), 'datatype' => 'dropdown'];
        return $tab;
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(\Log::class, $tabs, $options);
        return $tabs;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('@quadros/quadro.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);
        return true;
    }

    public function prepareInputForAdd($input)
    {
        $input['name'] = trim((string) ($input['name'] ?? ''));
        if ($input['name'] === '') {
            Session::addMessageAfterRedirect('Informe o nome do quadro.', false, ERROR);
            return false;
        }
        if (empty($input['users_id'])) {
            $input['users_id'] = (int) Session::getLoginUserID();
        }
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['name']) && trim($input['name']) === '') {
            Session::addMessageAfterRedirect('Informe o nome do quadro.', false, ERROR);
            return false;
        }
        return $input;
    }

    /** Cria as listas iniciais de um quadro novo. */
    public function post_addItem()
    {
        foreach (['A fazer', 'Em andamento', 'Concluído'] as $pos => $nome) {
            (new Lista())->add([
                'plugin_quadros_quadros_id' => $this->fields['id'],
                'name'                      => $nome,
                'position'                  => $pos + 1,
            ]);
        }
    }

    public function cleanDBonPurge()
    {
        /** @var \DBmysql $DB */
        global $DB;

        $cartoes = array_column(iterator_to_array($DB->request([
            'SELECT' => ['id'],
            'FROM'   => Cartao::getTable(),
            'WHERE'  => ['plugin_quadros_quadros_id' => $this->fields['id']],
        ])), 'id');
        if ($cartoes) {
            $DB->delete(Cartao::TABLE_MEMBROS, ['plugin_quadros_cartoes_id' => $cartoes]);
            $DB->delete(Cartao::TABLE_CHECKLIST, ['plugin_quadros_cartoes_id' => $cartoes]);
            $DB->delete(Cartao::TABLE_COMENTARIOS, ['plugin_quadros_cartoes_id' => $cartoes]);
        }
        $DB->delete(Cartao::getTable(), ['plugin_quadros_quadros_id' => $this->fields['id']]);
        $DB->delete(Lista::getTable(), ['plugin_quadros_quadros_id' => $this->fields['id']]);
    }
}
